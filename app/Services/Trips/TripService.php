<?php

namespace App\Services\Trips;

use App\Enums\TripMemberRole;
use App\Enums\TripStatus;
use App\Models\Trip;
use App\Models\TripMember;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class TripService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForUser(User $user, array $filters): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 15);

        return $this->queryForUser($user)
            ->when(! empty($filters['q']), function (Builder $query) use ($filters): void {
                $search = (string) $filters['q'];
                $query->where(function (Builder $builder) use ($search): void {
                    $builder->where('title', 'like', '%'.$search.'%')
                        ->orWhere('description', 'like', '%'.$search.'%')
                        ->orWhere('start_location_name', 'like', '%'.$search.'%')
                        ->orWhere('end_location_name', 'like', '%'.$search.'%');
                });
            })
            ->when(! empty($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->withCount(['members', 'pool'])
            ->orderByDesc('start_date')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(User $user, array $payload): Trip
    {
        return DB::transaction(function () use ($user, $payload): Trip {
            $trip = Trip::query()->create([
                'owner_user_id' => $user->id,
                'title' => $payload['title'],
                'description' => $payload['description'] ?? null,
                'start_location_name' => $payload['start_location_name'],
                'start_latitude' => $payload['start_latitude'] ?? null,
                'start_longitude' => $payload['start_longitude'] ?? null,
                'end_location_name' => $payload['end_location_name'],
                'end_latitude' => $payload['end_latitude'] ?? null,
                'end_longitude' => $payload['end_longitude'] ?? null,
                'start_date' => $payload['start_date'],
                'end_date' => $payload['end_date'],
                'status' => $payload['status'] ?? TripStatus::Draft,
                'cover_image_url' => $payload['cover_image_url'] ?? null,
                'version' => 1,
            ]);

            TripMember::query()->create([
                'trip_id' => $trip->id,
                'user_id' => $user->id,
                'role' => TripMemberRole::Owner,
                'joined_at' => now(),
            ]);

            return $this->detailForUser($trip->fresh(), $user);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(Trip $trip, array $payload, User $user): Trip
    {
        $trip->fill([
            'title' => $payload['title'] ?? $trip->title,
            'description' => array_key_exists('description', $payload) ? $payload['description'] : $trip->description,
            'start_location_name' => $payload['start_location_name'] ?? $trip->start_location_name,
            'start_latitude' => array_key_exists('start_latitude', $payload) ? $payload['start_latitude'] : $trip->start_latitude,
            'start_longitude' => array_key_exists('start_longitude', $payload) ? $payload['start_longitude'] : $trip->start_longitude,
            'end_location_name' => $payload['end_location_name'] ?? $trip->end_location_name,
            'end_latitude' => array_key_exists('end_latitude', $payload) ? $payload['end_latitude'] : $trip->end_latitude,
            'end_longitude' => array_key_exists('end_longitude', $payload) ? $payload['end_longitude'] : $trip->end_longitude,
            'start_date' => $payload['start_date'] ?? $trip->start_date,
            'end_date' => $payload['end_date'] ?? $trip->end_date,
            'status' => $payload['status'] ?? $trip->status,
            'cover_image_url' => array_key_exists('cover_image_url', $payload) ? $payload['cover_image_url'] : $trip->cover_image_url,
            'version' => $trip->version + 1,
        ]);
        $trip->save();

        return $this->detailForUser($trip->fresh(), $user);
    }

    public function delete(Trip $trip): void
    {
        $trip->delete();
    }

    public function detailForUser(Trip $trip, User $user): Trip
    {
        return $trip->load([
            'owner',
            'members.user',
            'pool.savedPlace.location',
            'pool.addedBy',
            'pool.hearts',
            'invites',
        ])->loadCount([
            'members',
            'pool',
        ])->setAttribute(
            'pending_invites_count',
            $trip->invites()->where('status', \App\Enums\TripInviteStatus::Pending->value)->count(),
        );
    }

    protected function queryForUser(User $user): Builder
    {
        return Trip::query()
            ->whereHas('members', fn (Builder $query) => $query->where('user_id', $user->id))
            ->with(['owner', 'members.user'])
            ->withCount(['members', 'pool']);
    }
}
