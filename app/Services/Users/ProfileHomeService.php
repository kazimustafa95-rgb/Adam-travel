<?php

namespace App\Services\Users;

use App\Enums\FriendRequestStatus;
use App\Enums\TripInviteStatus;
use App\Http\Resources\Api\V1\SubscriptionPlanResource;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\CmsPage;
use App\Models\FriendRequest;
use App\Models\Friendship;
use App\Models\Location;
use App\Models\SavedPlace;
use App\Models\Trip;
use App\Models\TripInvite;
use App\Models\User;
use App\Models\UserPreference;
use App\Services\Billing\SubscriptionService;
use App\Services\Trips\TimelineService;
use Illuminate\Database\Eloquent\Builder;

class ProfileHomeService
{
    public function __construct(
        protected SubscriptionService $subscriptionService,
        protected TimelineService $timelineService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function home(User $user): array
    {
        $subscriptionSummary = $this->subscriptionService->currentSummary($user);
        $tripCount = Trip::query()
            ->whereHas('members', fn (Builder $query) => $query->where('user_id', $user->id))
            ->count();
        $savedPlaceCount = SavedPlace::query()->where('user_id', $user->id)->count();
        $countryCount = Location::query()
            ->whereHas('savedPlaces', fn (Builder $query) => $query->where('user_id', $user->id))
            ->whereNotNull('country_code')
            ->distinct('country_code')
            ->count('country_code');
        $tripInvitationCount = TripInvite::query()
            ->where('status', TripInviteStatus::Pending->value)
            ->whereNotNull('email')
            ->whereRaw('LOWER(email) = ?', [strtolower($user->email)])
            ->whereDoesntHave('trip.members', fn (Builder $query) => $query->where('user_id', $user->id))
            ->where(function (Builder $query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->count();
        $friendRequestCount = FriendRequest::query()
            ->where('recipient_user_id', $user->id)
            ->where('status', FriendRequestStatus::Pending->value)
            ->count();
        $friendCount = Friendship::query()->where('user_id', $user->id)->count();

        return [
            'user' => (new UserResource($user->loadMissing('preference', 'socialAccounts')))->resolve(),
            'stats' => [
                'trips_count' => $tripCount,
                'saved_places_count' => $savedPlaceCount,
                'countries_count' => $countryCount,
            ],
            'activity' => [
                'friends_count' => $friendCount,
                'trip_invitations_count' => $tripInvitationCount,
                'friend_requests_count' => $friendRequestCount,
                'timeline_count' => $this->timelineService->timelineCountForUser($user),
            ],
            'subscription' => [
                'is_premium' => $subscriptionSummary['plan']->code !== 'free',
                'label' => $subscriptionSummary['plan']->code === 'free' ? 'Upgrade to Premium' : $subscriptionSummary['plan']->name,
                'plan' => (new SubscriptionPlanResource($subscriptionSummary['plan']))->resolve(),
                'paywall' => $subscriptionSummary['paywall'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function settingsPayload(User $user, UserPreference $preference): array
    {
        $pages = CmsPage::query()
            ->whereIn('slug', ['privacy-policy', 'terms-of-service', 'help-center'])
            ->where('is_published', true)
            ->orderBy('title')
            ->get()
            ->map(fn (CmsPage $page) => [
                'slug' => $page->slug,
                'title' => $page->title,
                'content' => $page->content,
            ])
            ->values()
            ->all();

        return array_merge($preference->only([
            'distance_unit',
            'map_style',
            'default_radius_meters',
            'notifications_enabled',
            'offline_auto_sync',
            'theme',
            'version',
        ]), [
            'account' => [
                'user' => (new UserResource($user->loadMissing('socialAccounts')))->resolve(),
            ],
            'sections' => [
                'privacy' => 'Location, data sharing',
                'notifications' => 'Push, reminders',
                'data_management' => 'Storage, cache',
                'about' => 'Version '.config('app.version', '2.4.1'),
            ],
            'pages' => $pages,
            'danger_zone' => [
                'can_delete_account' => true,
                'requires_current_password' => true,
            ],
            'app' => [
                'name' => config('app.name'),
                'version' => config('app.version', '2.4.1'),
            ],
        ]);
    }
}
