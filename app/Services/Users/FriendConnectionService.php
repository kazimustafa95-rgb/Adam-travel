<?php

namespace App\Services\Users;

use App\Enums\FriendRequestStatus;
use App\Models\FriendRequest;
use App\Models\Friendship;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FriendConnectionService
{
    public function pendingCountForUser(User $user): int
    {
        return FriendRequest::query()
            ->where('recipient_user_id', $user->id)
            ->where('status', FriendRequestStatus::Pending->value)
            ->count();
    }

    public function friendsCountForUser(User $user): int
    {
        return Friendship::query()->where('user_id', $user->id)->count();
    }

    /**
     * @return Collection<int, FriendRequest>
     */
    public function incomingRequests(User $user): Collection
    {
        return FriendRequest::query()
            ->where('recipient_user_id', $user->id)
            ->where('status', FriendRequestStatus::Pending->value)
            ->with([
                'sender.socialAccounts',
                'recipient.socialAccounts',
            ])
            ->latest('id')
            ->get();
    }

    /**
     * @return Collection<int, Friendship>
     */
    public function friendships(User $user): Collection
    {
        return Friendship::query()
            ->where('user_id', $user->id)
            ->with('friend.socialAccounts')
            ->orderByDesc('connected_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function sendRequest(User $sender, array $payload): FriendRequest
    {
        $recipient = $this->resolveRecipient($sender, $payload);

        if ($this->areFriends($sender, $recipient)) {
            throw ValidationException::withMessages([
                'recipient' => ['You are already connected with this traveler.'],
            ]);
        }

        $reversePending = FriendRequest::query()
            ->where('sender_user_id', $recipient->id)
            ->where('recipient_user_id', $sender->id)
            ->where('status', FriendRequestStatus::Pending->value)
            ->first();

        if ($reversePending) {
            return $this->accept($reversePending, $sender);
        }

        $request = FriendRequest::query()->firstOrNew([
            'sender_user_id' => $sender->id,
            'recipient_user_id' => $recipient->id,
        ]);

        if ($request->exists && $request->status === FriendRequestStatus::Pending) {
            throw ValidationException::withMessages([
                'recipient' => ['A pending request already exists for this traveler.'],
            ]);
        }

        $request->fill([
            'status' => FriendRequestStatus::Pending,
            'responded_at' => null,
        ])->save();

        return $request->fresh(['sender.socialAccounts', 'recipient.socialAccounts']);
    }

    public function accept(FriendRequest $friendRequest, User $recipient): FriendRequest
    {
        $this->assertIncomingPendingRequest($friendRequest, $recipient);

        return DB::transaction(function () use ($friendRequest): FriendRequest {
            $friendRequest->forceFill([
                'status' => FriendRequestStatus::Accepted,
                'responded_at' => now(),
            ])->save();

            Friendship::query()->firstOrCreate(
                [
                    'user_id' => $friendRequest->sender_user_id,
                    'friend_user_id' => $friendRequest->recipient_user_id,
                ],
                [
                    'friend_request_id' => $friendRequest->id,
                    'connected_at' => now(),
                ],
            );

            Friendship::query()->firstOrCreate(
                [
                    'user_id' => $friendRequest->recipient_user_id,
                    'friend_user_id' => $friendRequest->sender_user_id,
                ],
                [
                    'friend_request_id' => $friendRequest->id,
                    'connected_at' => now(),
                ],
            );

            return $friendRequest->fresh(['sender.socialAccounts', 'recipient.socialAccounts']);
        });
    }

    public function decline(FriendRequest $friendRequest, User $actor): FriendRequest
    {
        if ($friendRequest->status !== FriendRequestStatus::Pending) {
            throw ValidationException::withMessages([
                'friend_request' => ['This friend request is no longer pending.'],
            ]);
        }

        if (! in_array($actor->id, [$friendRequest->sender_user_id, $friendRequest->recipient_user_id], true)) {
            throw ValidationException::withMessages([
                'friend_request' => ['You do not have access to this friend request.'],
            ]);
        }

        $status = $actor->id === $friendRequest->sender_user_id
            ? FriendRequestStatus::Canceled
            : FriendRequestStatus::Declined;

        $friendRequest->forceFill([
            'status' => $status,
            'responded_at' => now(),
        ])->save();

        return $friendRequest->fresh(['sender.socialAccounts', 'recipient.socialAccounts']);
    }

    public function acceptAll(User $user): int
    {
        $requests = $this->incomingRequests($user);
        $accepted = 0;

        foreach ($requests as $request) {
            $this->accept($request, $user);
            $accepted++;
        }

        return $accepted;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function resolveRecipient(User $sender, array $payload): User
    {
        $recipient = null;

        if (! empty($payload['recipient_user_id'])) {
            $recipient = User::query()->find((int) $payload['recipient_user_id']);
        }

        if (! $recipient && ! empty($payload['recipient_email'])) {
            $recipient = User::query()
                ->where('email', strtolower((string) $payload['recipient_email']))
                ->first();
        }

        if (! $recipient) {
            throw ValidationException::withMessages([
                'recipient' => ['The requested traveler could not be found.'],
            ]);
        }

        if ($recipient->id === $sender->id) {
            throw ValidationException::withMessages([
                'recipient' => ['You cannot send a friend request to yourself.'],
            ]);
        }

        return $recipient;
    }

    protected function areFriends(User $user, User $friend): bool
    {
        return Friendship::query()
            ->where('user_id', $user->id)
            ->where('friend_user_id', $friend->id)
            ->exists();
    }

    protected function assertIncomingPendingRequest(FriendRequest $friendRequest, User $recipient): void
    {
        if ($friendRequest->recipient_user_id !== $recipient->id) {
            throw ValidationException::withMessages([
                'friend_request' => ['This friend request does not belong to the authenticated user.'],
            ]);
        }

        if ($friendRequest->status !== FriendRequestStatus::Pending) {
            throw ValidationException::withMessages([
                'friend_request' => ['This friend request is no longer pending.'],
            ]);
        }
    }
}
