<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\GoogleIdToken;
use App\Services\Registration;
use App\Support\RemoteAvatar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

/**
 * Signing a device in and out.
 *
 * The web controller next door cannot be reused: `Auth::attempt()` opens a
 * session and hands back a cookie, which is exactly what a phone has no use
 * for. Here the password is checked directly and the answer is a token the
 * client stores itself — the arrangement the E-ride app uses against its own
 * backend.
 *
 * Validation messages and the `auth.failed` translation are shared with
 * LoginController on purpose, so a wrong password reads the same on both.
 */
class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            // Named so a member can sign one phone out and leave the others
            // alone, and so the tokens list is legible to a human.
            'device_name' => 'required|string|max:120',
        ]);

        // Tenant-scoped and soft-delete aware through the model's own scopes.
        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        // Checked here as well as in middleware: a suspended account should
        // never be handed a token in the first place.
        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => __('Your account is not active.'),
            ]);
        }

        // Re-signing in on the same device replaces that device's token rather
        // than adding another, or the table grows one row per app launch.
        $user->tokens()->where('name', $credentials['device_name'])->delete();

        $token = $user->createToken($credentials['device_name'])->plainTextToken;

        $user->forceFill(['last_active_at' => now()])->saveQuietly();

        return $this->signedIn($request, $user, $token);
    }

    /**
     * "Continue with Google": the web's GoogleAuthController::callback, for an
     * ID token from the phone's own Google sign-in.
     *
     * The same three branches. A member already linked to this Google account
     * is signed in. A member with the same, Google-verified, email is linked
     * and signed in. Anyone else is new: nothing is created, and the answer
     * is `status: register` with what Google said about them, so the app runs
     * the register wizard (its location step included, as the web insists)
     * and sends the token again with it.
     */
    public function google(Request $request, GoogleIdToken $tokens): JsonResponse
    {
        $request->validate([
            'id_token' => 'required|string|max:4096',
            'device_name' => 'required|string|max:120',
        ]);

        $google = $tokens->verify((string) $request->input('id_token'));

        $user = User::withoutGlobalScopes()->where('google_id', $google['google_id'])->first();

        // Linking by email needs Google to vouch for the address; otherwise
        // anyone could add someone else's email to a Google account and take
        // their account here.
        if (! $user && $google['email_verified']) {
            $user = User::withoutGlobalScopes()->where('email', $google['email'])->first();
            $user?->forceFill([
                'google_id' => $google['google_id'],
                'email_verified_at' => $user->email_verified_at ?: now(),
            ])->save();
        }

        if (! $user) {
            if (! $google['email_verified']) {
                throw ValidationException::withMessages([
                    'id_token' => __('Google did not return an email address. Please use a Google account with a verified email.'),
                ]);
            }

            return response()->json([
                'status' => 'register',
                'google' => [
                    'email' => $google['email'],
                    'name' => $google['name'],
                    'avatar_url' => $google['picture'] ?: null,
                    'locale' => $google['locale'],
                ],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'id_token' => __('Your account is not active.'),
            ]);
        }

        // A Google picture fills an empty avatar, as on the web.
        if (empty($user->avatar) && $google['picture']) {
            if ($path = RemoteAvatar::fetch($google['picture'])) {
                $user->forceFill(['avatar' => $path])->save();
            }
        }

        $deviceName = (string) $request->input('device_name');
        $user->tokens()->where('name', $deviceName)->delete();
        $token = $user->createToken($deviceName)->plainTextToken;
        $user->forceFill(['last_active_at' => now()])->saveQuietly();

        return $this->signedIn($request, $user, $token, ['status' => 'signed_in']);
    }

    /**
     * Create an account and sign this device in: the web's register wizard,
     * in one request. Everything register() does on the web happens here too,
     * because both call App\Services\Registration.
     */
    public function register(Request $request, Registration $registration, GoogleIdToken $tokens): JsonResponse
    {
        // A newcomer from "Continue with Google" (the web's session
        // `google_signup`): the token stands in for the password, and the
        // email is Google's, whatever the form says, as the web locks it.
        $google = null;
        if ($request->filled('google_id_token')) {
            $google = $tokens->verify((string) $request->input('google_id_token'));
            if (! $google['email_verified']) {
                throw ValidationException::withMessages([
                    'email' => __('Google did not return an email address. Please use a Google account with a verified email.'),
                ]);
            }
            $request->merge(['email' => $google['email']]);
        }

        $data = $request->validate(
            Registration::rules(isGoogle: $google !== null) + ['device_name' => 'required|string|max:120'],
            Registration::messages(),
        );

        if ($google !== null) {
            $data['google_id'] = $google['google_id'];
            $data['avatar'] = RemoteAvatar::fetch($google['picture']);
            unset($data['password']);
        }

        $user = $registration->register($data);
        $token = $user->createToken($data['device_name'])->plainTextToken;

        return $this->signedIn($request, $user, $token, [
            // Kamer's first message for onboarding step 1, when the AI could
            // write one. Null: the app asks GET /onboarding for the standard one.
            'kamer_welcome' => $registration->welcomeMessage($user),
        ], 201);
    }

    /**
     * Whether an email can be used for a new account, for register step 2's
     * "Email is available" as the member types.
     */
    public function emailAvailable(Request $request): JsonResponse
    {
        $validator = validator($request->only('email'), ['email' => Registration::emailRules()]);

        return response()->json([
            'available' => $validator->passes(),
            'message' => $validator->errors()->first('email') ?: null,
        ]);
    }

    /** Send the reset link, exactly as the web's forgot-password form does. */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        $status = Password::sendResetLink($request->only('email'));

        if ($status !== Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        return response()->json(['message' => __($status)]);
    }

    /** Send the verification email again. */
    public function resendVerification(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => __('Your email is already verified.'), 'verified' => true]);
        }

        $user->sendEmailVerificationNotification();

        return response()->json(['message' => __('Verification link sent!'), 'verified' => false]);
    }

    /**
     * The answer to a sign-in or sign-up: the token, and the member with their
     * private fields. UserResource shows those only to "the member asking",
     * and nobody is authenticated yet on this request, so say who that is.
     * Without it the app never learns `onboarded` or `email_verified` at sign-in.
     */
    private function signedIn(Request $request, User $user, string $token, array $extra = [], int $status = 200): JsonResponse
    {
        $request->setUserResolver(fn () => $user);

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user),
            ...$extra,
        ], $status);
    }

    /**
     * The signed-in member, re-read.
     *
     * The app calls this at launch to find out whether its stored token is
     * still good and whether onboarding is still outstanding.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => new UserResource($request->user()),
        ]);
    }

    /**
     * Delete the signed-in member's account. Anonymised, exactly as on the
     * web (AccountController); every token, this one included, is revoked.
     * A wrong confirmation is a 422 on delete_confirm.
     */
    public function destroy(Request $request, \App\Services\AccountDeletion $deletion): JsonResponse
    {
        $user = $request->user();
        \App\Http\Controllers\AccountController::confirm($request, $user);
        $deletion->anonymise($user);

        return response()->json(['status' => 'deleted']);
    }

    /**
     * Sign out this device only.
     *
     * Deleting every token would sign the member out of their other phone and
     * their tablet, which is not what tapping "log out" here means.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => __('Signed out.')]);
    }
}
