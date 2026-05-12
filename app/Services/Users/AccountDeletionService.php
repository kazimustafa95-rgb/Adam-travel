<?php

namespace App\Services\Users;

use App\Enums\SubscriptionStatus;
use App\Enums\TripInviteStatus;
use App\Models\TripInvite;
use App\Models\TripPlace;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AccountDeletionService
{
    public function delete(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $this->cancelPendingInvitesForEmail($user);
            $this->removeTripPlacesBackedByUserSavedPlaces($user);
            $this->cancelSubscriptions($user);

            DB::table('personal_access_tokens')
                ->where('tokenable_type', User::class)
                ->where('tokenable_id', $user->id)
                ->delete();

            $user->forceDelete();
        });
    }

    protected function cancelPendingInvitesForEmail(User $user): void
    {
        TripInvite::query()
            ->where('status', TripInviteStatus::Pending->value)
            ->whereNotNull('email')
            ->whereRaw('LOWER(email) = ?', [strtolower($user->email)])
            ->update([
                'status' => TripInviteStatus::Revoked->value,
            ]);
    }

    protected function removeTripPlacesBackedByUserSavedPlaces(User $user): void
    {
        $tripPlaceIds = TripPlace::query()
            ->whereIn('saved_place_id', function ($query) use ($user): void {
                $query->select('id')
                    ->from('saved_places')
                    ->where('user_id', $user->id);
            })
            ->pluck('id');

        if ($tripPlaceIds->isEmpty()) {
            return;
        }

        DB::table('itinerary_items')->whereIn('trip_place_id', $tripPlaceIds)->delete();
        DB::table('trip_place_hearts')->whereIn('trip_place_id', $tripPlaceIds)->delete();
        DB::table('trip_places')->whereIn('id', $tripPlaceIds)->delete();
    }

    protected function cancelSubscriptions(User $user): void
    {
        DB::table('subscriptions')
            ->where('user_id', $user->id)
            ->update([
                'status' => SubscriptionStatus::Expired->value,
                'auto_renews' => false,
                'canceled_at' => now(),
                'expires_at' => now(),
                'last_synced_at' => now(),
            ]);
    }
}
