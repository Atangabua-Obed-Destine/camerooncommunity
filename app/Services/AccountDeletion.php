<?php

namespace App\Services;

use App\Enums\OfferStatus;
use App\Enums\RoomType;
use App\Models\MarketplaceListing;
use App\Models\MarketplaceOffer;
use App\Models\User;
use App\Models\YardRoomMember;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A member deletes their account. Decided by the owner on 2026-11-11: anonymise.
 *
 * Deleted: the profile and photos, location history, connections, follows,
 * saved names, favourites, saved searches, notifications, stories, badges and
 * points, sign-in tokens and sessions, and their listings (with photos).
 *
 * Kept, with the name gone: their chat messages (others' conversations would
 * otherwise lose half of what was said), orders and reviews (the other side's
 * records), and one-to-one chats, so the other person keeps the history.
 *
 * The users row itself stays, scrubbed to "Deleted member". Deleting it would
 * cascade through yard_messages, marketplace_orders and marketplace_reviews,
 * and a soft delete would break every view that shows $message->user. It can
 * no longer sign in: the email, password and Google id are gone.
 *
 * This is also what Apple's guideline 5.1.1(v) needs from the app.
 */
class AccountDeletion
{
    public const NAME = 'Deleted member';

    public function anonymise(User $user): void
    {
        $files = array_filter([$user->avatar, $user->cover_photo]);

        DB::transaction(function () use ($user, &$files) {
            $id = $user->id;

            // Their listings go, photos and all. Orders keep pointing at the
            // (soft-deleted) row, so the other side still sees what was bought.
            $listings = MarketplaceListing::withTrashed()->where('user_id', $id)->get();
            foreach ($listings as $listing) {
                foreach ($listing->media()->get() as $media) {
                    $files[] = $media->path;
                    $media->delete();
                }
                $listing->forceFill(['status' => 'removed'])->save();
                if (! $listing->trashed()) {
                    $listing->delete();
                }
            }

            // Open offers either way are closed.
            MarketplaceOffer::where(fn ($q) => $q->where('buyer_id', $id)->orWhere('seller_id', $id))
                ->whereIn('status', [OfferStatus::Pending, OfferStatus::Countered])
                ->update(['status' => OfferStatus::Withdrawn, 'responded_at' => now()]);

            // Rooms: they leave everything but one-to-one chats.
            YardRoomMember::where('user_id', $id)
                ->whereHas('room', fn ($r) => $r->where('room_type', '!=', RoomType::DirectMessage->value))
                ->delete();

            foreach (DB::table('stories')->where('user_id', $id)->get(['media_path', 'thumbnail_path']) as $story) {
                $files[] = $story->media_path;
                $files[] = $story->thumbnail_path;
            }

            $byUser = [
                'user_location_history' => ['user_id'],
                'user_connections' => ['user_a_id', 'user_b_id'],
                'user_follows' => ['follower_id', 'following_id'],
                'user_contact_names' => ['owner_user_id', 'contact_user_id'],
                'marketplace_favorites' => ['user_id'],
                'marketplace_saved_searches' => ['user_id'],
                'notifications' => ['user_id'],
                'stories' => ['user_id'],
                'user_badges' => ['user_id'],
                'community_points_log' => ['user_id'],
                'yard_message_stars' => ['user_id'],
                'yard_room_join_prompts' => ['user_id'],
                'yard_join_requests' => ['user_id'],
                'sessions' => ['user_id'],
            ];
            foreach ($byUser as $table => $columns) {
                DB::table($table)->where(function ($q) use ($columns, $id) {
                    foreach ($columns as $column) {
                        $q->orWhere($column, $id);
                    }
                })->delete();
            }

            $user->tokens()->delete();
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            DB::table('coming_soon_signups')->where('email', $user->email)->delete();
            DB::table('activity_log')->where('subject_type', User::class)->where('subject_id', $id)->delete();

            $user->syncRoles([]);

            $user->forceFill([
                'name' => self::NAME,
                'username' => 'deleted-' . Str::lower(Str::random(10)),
                'email' => "deleted-{$id}-" . Str::lower(Str::random(6)) . '@deleted.invalid',
                'google_id' => null,
                'phone' => null,
                'password' => Hash::make(Str::random(64)),
                'remember_token' => null,
                'avatar' => null,
                'cover_photo' => null,
                'bio' => null,
                'home_region' => null,
                'home_city' => null,
                'current_country' => null,
                'current_region' => null,
                'current_city' => null,
                'active_country' => null,
                'active_region' => null,
                'current_lat' => null,
                'current_lng' => null,
                'location_updated_at' => null,
                'momo_number' => null,
                'momo_provider' => null,
                'is_active' => false,
                'is_verified' => false,
                'is_identity_verified' => false,
                'is_community_leader' => false,
                'community_points' => 0,
                'last_active_at' => null,
                'anonymised_at' => now(),
            ])->save();
        });

        // Files last, once nothing points at them.
        $disk = Storage::disk('public');
        foreach (array_unique(array_filter($files)) as $path) {
            if (! str_starts_with($path, 'http')) {
                $disk->delete($path);
            }
        }
    }
}
