<?php

namespace App\Http\Controllers;

use App\Services\AccountDeletion;
use App\Support\Bilingual;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Deleting your own account, from Settings. The app does the same through
 * DELETE /api/v1/me (Api\V1\AuthController::destroy).
 */
class AccountController extends Controller
{
    public function destroy(Request $request, AccountDeletion $deletion)
    {
        $user = $request->user();

        self::confirm($request, $user);

        Auth::logout();
        $deletion->anonymise($user);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('account_deleted', true);
    }

    /**
     * Proof that the person at the keyboard means it: their password, or for a
     * Google-only account (no password), the word DELETE.
     */
    public static function confirm(Request $request, $user): void
    {
        $confirmation = (string) $request->input('delete_confirm', '');

        $ok = $user->password && $user->google_id === null
            ? Hash::check($confirmation, $user->password)
            : ($user->password && Hash::check($confirmation, $user->password)) || strtoupper(trim($confirmation)) === 'DELETE';

        if (! $ok) {
            throw ValidationException::withMessages([
                'delete_confirm' => $user->password && $user->google_id === null
                    ? Bilingual::t('That password is not right.', "Ce mot de passe n'est pas le bon.")
                    : Bilingual::t('Type DELETE to confirm.', 'Tapez DELETE pour confirmer.'),
            ]);
        }
    }
}
