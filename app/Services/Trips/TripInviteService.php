<?php

namespace App\Services\Trips;

use App\Enums\TripInviteStatus;
use App\Models\Trip;
use App\Models\TripInvite;
use App\Models\TripMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TripInviteService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(Trip $trip, User $actor, array $payload): TripInvite
    {
        return TripInvite::query()->create([
            'trip_id' => $trip->id,
            'invited_by_user_id' => $actor->id,
            'email' => $payload['email'] ?? null,
            'role' => $payload['role'],
            'status' => TripInviteStatus::Pending,
            'expires_at' => now()->addDays(7),
        ]);
    }

    public function revoke(Trip $trip, TripInvite $invite): TripInvite
    {
        if ($invite->trip_id !== $trip->id) {
            throw ValidationException::withMessages([
                'invite' => ['The invite does not belong to this trip.'],
            ]);
        }

        $invite->update([
            'status' => TripInviteStatus::Revoked,
        ]);

        return $invite->fresh();
    }

    public function accept(string $token, User $user): Trip
    {
        $invite = TripInvite::query()->where('token', $token)->first();

        if (! $invite) {
            throw ValidationException::withMessages([
                'token' => ['The invite token is invalid.'],
            ]);
        }

        return $this->acceptInvite($invite, $user);
    }

    /**
     * @return Collection<int, TripInvite>
     */
    public function pendingInboxForUser(User $user): Collection
    {
        TripInvite::query()
            ->where('status', TripInviteStatus::Pending->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update(['status' => TripInviteStatus::Expired->value]);

        return TripInvite::query()
            ->where('status', TripInviteStatus::Pending->value)
            ->whereNotNull('email')
            ->whereRaw('LOWER(email) = ?', [strtolower($user->email)])
            ->whereDoesntHave('trip.members', fn ($query) => $query->where('user_id', $user->id))
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->with([
                'invitedBy.socialAccounts',
                'trip.owner',
                'trip.members',
                'trip.pool.savedPlace.location',
            ])
            ->latest('id')
            ->get()
            ->map(function (TripInvite $invite): TripInvite {
                $trip = $invite->trip;

                if ($trip) {
                    $countryCode = $trip->pool
                        ->map(fn ($tripPlace) => $tripPlace->savedPlace?->location?->country_code)
                        ->filter()
                        ->first();

                    $trip->setAttribute('date_range_label', $trip->start_date && $trip->end_date
                        ? $trip->start_date->format('M j').' – '.$trip->end_date->format('M j')
                        : null);
                    $trip->setAttribute('primary_country_code', $countryCode);
                    $trip->setAttribute('primary_country_flag', $this->flagEmoji($countryCode));
                }

                return $invite;
            });
    }

    public function pendingCountForUser(User $user): int
    {
        return TripInvite::query()
            ->where('status', TripInviteStatus::Pending->value)
            ->whereNotNull('email')
            ->whereRaw('LOWER(email) = ?', [strtolower($user->email)])
            ->whereDoesntHave('trip.members', fn ($query) => $query->where('user_id', $user->id))
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->count();
    }

    public function acceptInvite(TripInvite $invite, User $user): Trip
    {
        $this->assertInviteBelongsToUser($invite, $user);

        return DB::transaction(function () use ($invite, $user): Trip {
            $member = TripMember::query()->firstOrNew([
                'trip_id' => $invite->trip_id,
                'user_id' => $user->id,
            ]);

            if (! $member->exists || $member->role->value !== \App\Enums\TripMemberRole::Owner->value) {
                $member->role = $invite->role;
                $member->joined_at = $member->joined_at ?? now();
                $member->save();
            }

            $invite->update([
                'status' => TripInviteStatus::Accepted,
                'accepted_at' => now(),
            ]);

            return $invite->trip->fresh();
        });
    }

    public function decline(TripInvite $invite, User $user): TripInvite
    {
        $this->assertInviteBelongsToUser($invite, $user);

        $invite->update([
            'status' => TripInviteStatus::Revoked,
        ]);

        return $invite->fresh(['invitedBy.socialAccounts', 'trip.owner', 'trip.members']);
    }

    protected function assertInviteBelongsToUser(TripInvite $invite, User $user): void
    {
        if ($invite->status !== TripInviteStatus::Pending) {
            throw ValidationException::withMessages([
                'invite' => ['This invite is no longer available.'],
            ]);
        }

        if ($invite->expires_at && $invite->expires_at->isPast()) {
            $invite->update(['status' => TripInviteStatus::Expired]);

            throw ValidationException::withMessages([
                'invite' => ['This invite has expired.'],
            ]);
        }

        if ($invite->email && strcasecmp($invite->email, $user->email) !== 0) {
            throw ValidationException::withMessages([
                'invite' => ['This invite was issued for a different email address.'],
            ]);
        }
    }

    protected function flagEmoji(string|null $countryCode): string|null
    {
        if (! is_string($countryCode) || strlen($countryCode) !== 2) {
            return null;
        }

        $countryCode = strtoupper($countryCode);
        $offset = 127397;

        return mb_chr(ord($countryCode[0]) + $offset).mb_chr(ord($countryCode[1]) + $offset);
    }
}
