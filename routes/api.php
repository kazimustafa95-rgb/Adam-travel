<?php

use App\Http\Controllers\Api\V1\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Api\V1\Auth\NewPasswordController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetOtpController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetLinkController;
use App\Http\Controllers\Api\V1\Auth\RegisteredUserController;
use App\Http\Controllers\Api\V1\Auth\SocialAuthController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\FriendRequestController;
use App\Http\Controllers\Api\V1\ImportController;
use App\Http\Controllers\Api\V1\InvitationController;
use App\Http\Controllers\Api\V1\ItineraryController;
use App\Http\Controllers\Api\V1\ItineraryItemController;
use App\Http\Controllers\Api\V1\MapPinsController;
use App\Http\Controllers\Api\V1\MetaController;
use App\Http\Controllers\Api\V1\OfflinePackageController;
use App\Http\Controllers\Api\V1\OnboardingController;
use App\Http\Controllers\Api\V1\PlanController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\ProfileHomeController;
use App\Http\Controllers\Api\V1\ProximityController;
use App\Http\Controllers\Api\V1\RevenueCatWebhookController;
use App\Http\Controllers\Api\V1\SavedPlaceController;
use App\Http\Controllers\Api\V1\SavedPlaceSearchController;
use App\Http\Controllers\Api\V1\SettingsController;
use App\Http\Controllers\Api\V1\SupportController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use App\Http\Controllers\Api\V1\SyncController;
use App\Http\Controllers\Api\V1\TimelineController;
use App\Http\Controllers\Api\V1\TripAiItineraryController;
use App\Http\Controllers\Api\V1\TripBalanceController;
use App\Http\Controllers\Api\V1\TripController;
use App\Http\Controllers\Api\V1\TripInviteController;
use App\Http\Controllers\Api\V1\TripMemberController;
use App\Http\Controllers\Api\V1\TripPlaceController;
use App\Http\Controllers\Api\V1\TripPlaceHeartController;
use App\Http\Controllers\Api\V1\TripSuggestionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->name('api.v1.')
    ->group(function (): void {
        Route::get('/meta', MetaController::class)->name('meta');
        Route::post('/billing/webhooks/revenuecat', RevenueCatWebhookController::class)->name('billing.webhooks.revenuecat');

        Route::prefix('auth')
            ->name('auth.')
            ->group(function (): void {
                Route::post('/register', [RegisteredUserController::class, 'store'])
                    ->middleware('throttle:auth-login')
                    ->name('register');
                Route::post('/login', [AuthenticatedSessionController::class, 'store'])
                    ->middleware('throttle:auth-login')
                    ->name('login');
                Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
                    ->middleware('throttle:password-reset-request')
                    ->name('forgot-password');
                Route::post('/password-otp/request', [PasswordResetOtpController::class, 'store'])
                    ->middleware('throttle:password-reset-request')
                    ->name('password-otp.request');
                Route::post('/password-otp/verify', [PasswordResetOtpController::class, 'verify'])
                    ->middleware('throttle:password-reset-verify')
                    ->name('password-otp.verify');
                Route::post('/reset-password', [NewPasswordController::class, 'store'])
                    ->middleware('throttle:password-reset-request')
                    ->name('reset-password');
                Route::post('/social/{provider}', [SocialAuthController::class, 'store'])
                    ->middleware('throttle:auth-login')
                    ->where('provider', 'google|apple')
                    ->name('social.store');

                Route::middleware(['auth:sanctum', 'active.user'])->group(function (): void {
                    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
                });
            });

        Route::middleware(['auth:sanctum', 'active.user'])->group(function (): void {
            Route::get('/dashboard', DashboardController::class)->name('dashboard');
            Route::get('/profile', ProfileHomeController::class)->name('profile.home');
            Route::post('/imports', [ImportController::class, 'store'])
                ->middleware('throttle:imports-submission')
                ->name('imports.store');
            Route::get('/imports/{import}', [ImportController::class, 'show'])->name('imports.show');
            Route::post('/imports/{import}/retry', [ImportController::class, 'retry'])
                ->middleware('throttle:imports-submission')
                ->name('imports.retry');
            Route::patch('/imports/{import}/manual-override', [ImportController::class, 'manualOverride'])
                ->name('imports.manual-override');
            Route::post('/imports/{import}/confirm', [ImportController::class, 'confirm'])->name('imports.confirm');
            Route::get('/me', [ProfileController::class, 'show'])->name('me.show');
            Route::patch('/me', [ProfileController::class, 'update'])->name('me.update');
            Route::delete('/me', [ProfileController::class, 'destroy'])->name('me.destroy');

            Route::get('/map/pins', MapPinsController::class)
                ->middleware('throttle:proximity-check')
                ->name('map.pins');
            Route::post('/proximity/check', ProximityController::class)
                ->middleware('throttle:proximity-check')
                ->name('proximity.check');

            Route::get('/saved-places/search', SavedPlaceSearchController::class)->name('saved-places.search');
            Route::get('/saved-places', [SavedPlaceController::class, 'index'])->name('saved-places.index');
            Route::post('/saved-places', [SavedPlaceController::class, 'store'])
                ->middleware('throttle:imports-submission')
                ->name('saved-places.store');
            Route::get('/saved-places/{savedPlace}', [SavedPlaceController::class, 'show'])->name('saved-places.show');
            Route::patch('/saved-places/{savedPlace}', [SavedPlaceController::class, 'update'])->name('saved-places.update');
            Route::delete('/saved-places/{savedPlace}', [SavedPlaceController::class, 'destroy'])->name('saved-places.destroy');

            Route::get('/trips', [TripController::class, 'index'])->name('trips.index');
            Route::post('/trips', [TripController::class, 'store'])->name('trips.store');
            Route::get('/trips/{trip}', [TripController::class, 'show'])->name('trips.show');
            Route::patch('/trips/{trip}', [TripController::class, 'update'])->name('trips.update');
            Route::delete('/trips/{trip}', [TripController::class, 'destroy'])->name('trips.destroy');

            Route::post('/trips/{trip}/invites', [TripInviteController::class, 'store'])->name('trips.invites.store');
            Route::delete('/trips/{trip}/invites/{invite}', [TripInviteController::class, 'destroy'])->name('trips.invites.destroy');
            Route::post('/trip-invites/{token}/accept', [TripInviteController::class, 'accept'])->name('trip-invites.accept');
            Route::get('/profile/invitations', [InvitationController::class, 'index'])->name('profile.invitations.index');
            Route::post('/profile/invitations/friends/accept-all', [InvitationController::class, 'acceptAllFriends'])->name('profile.invitations.friends.accept-all');
            Route::post('/profile/invitations/trips/{invite}/accept', [InvitationController::class, 'acceptTrip'])->name('profile.invitations.trips.accept');
            Route::post('/profile/invitations/trips/{invite}/decline', [InvitationController::class, 'declineTrip'])->name('profile.invitations.trips.decline');
            Route::post('/profile/invitations/friends/{friendRequest}/accept', [InvitationController::class, 'acceptFriend'])->name('profile.invitations.friends.accept');
            Route::post('/profile/invitations/friends/{friendRequest}/decline', [InvitationController::class, 'declineFriend'])->name('profile.invitations.friends.decline');
            Route::get('/friends', [FriendRequestController::class, 'index'])->name('friends.index');
            Route::post('/friends/requests', [FriendRequestController::class, 'store'])->name('friends.requests.store');
            Route::delete('/friends/requests/{friendRequest}', [FriendRequestController::class, 'destroy'])->name('friends.requests.destroy');

            Route::patch('/trips/{trip}/members/{member}', [TripMemberController::class, 'update'])->name('trips.members.update');
            Route::delete('/trips/{trip}/members/{member}', [TripMemberController::class, 'destroy'])->name('trips.members.destroy');

            Route::get('/trips/{trip}/pool', [TripPlaceController::class, 'index'])->name('trips.pool.index');
            Route::post('/trips/{trip}/pool', [TripPlaceController::class, 'store'])->name('trips.pool.store');
            Route::patch('/trips/{trip}/pool/{tripPlace}', [TripPlaceController::class, 'update'])->name('trips.pool.update');
            Route::delete('/trips/{trip}/pool/{tripPlace}', [TripPlaceController::class, 'destroy'])->name('trips.pool.destroy');
            Route::post('/trips/{trip}/pool/{tripPlace}/heart', [TripPlaceHeartController::class, 'store'])->name('trips.pool.heart.store');
            Route::delete('/trips/{trip}/pool/{tripPlace}/heart', [TripPlaceHeartController::class, 'destroy'])->name('trips.pool.heart.destroy');
            Route::get('/trips/{trip}/ai-itinerary', [TripAiItineraryController::class, 'show'])->name('trips.ai-itinerary.show');
            Route::post('/trips/{trip}/ai-itinerary/generate', [TripAiItineraryController::class, 'generate'])
                ->middleware('throttle:ai-generation')
                ->name('trips.ai-itinerary.generate');
            Route::post('/trips/{trip}/ai-itinerary/apply', [TripAiItineraryController::class, 'apply'])->name('trips.ai-itinerary.apply');
            Route::get('/trips/{trip}/itinerary', [ItineraryController::class, 'index'])->name('trips.itinerary.index');
            Route::post('/trips/{trip}/itinerary/days', [ItineraryController::class, 'storeDay'])->name('trips.itinerary.days.store');
            Route::put('/trips/{trip}/itinerary/reorder', [ItineraryController::class, 'reorder'])->name('trips.itinerary.reorder');
            Route::post('/trips/{trip}/itinerary/items', [ItineraryItemController::class, 'store'])->name('trips.itinerary.items.store');
            Route::patch('/trips/{trip}/itinerary/items/{itineraryItem}', [ItineraryItemController::class, 'update'])->name('trips.itinerary.items.update');
            Route::delete('/trips/{trip}/itinerary/items/{itineraryItem}', [ItineraryItemController::class, 'destroy'])->name('trips.itinerary.items.destroy');
            Route::get('/trips/{trip}/suggestions', [TripSuggestionController::class, 'index'])->name('trips.suggestions.index');
            Route::post('/trips/{trip}/suggestions/generate', [TripSuggestionController::class, 'generate'])
                ->middleware('throttle:ai-generation')
                ->name('trips.suggestions.generate');
            Route::post('/trips/{trip}/suggestions/{suggestion}/add', [TripSuggestionController::class, 'add'])->name('trips.suggestions.add');
            Route::post('/trips/{trip}/suggestions/{suggestion}/dismiss', [TripSuggestionController::class, 'dismiss'])->name('trips.suggestions.dismiss');
            Route::get('/trips/{trip}/balance', TripBalanceController::class)->name('trips.balance.show');
            Route::get('/offline/packages', [OfflinePackageController::class, 'index'])->name('offline.packages.index');
            Route::post('/offline/packages/trips/{trip}', [OfflinePackageController::class, 'storeTrip'])->name('offline.packages.trips.store');
            Route::get('/sync', [SyncController::class, 'index'])->name('sync.index');
            Route::post('/sync/push', [SyncController::class, 'store'])->name('sync.store');
            Route::get('/timeline', [TimelineController::class, 'index'])->name('timeline.index');
            Route::get('/timeline/{trip}', [TimelineController::class, 'show'])->name('timeline.show');
            Route::get('/plans', PlanController::class)->name('plans.index');
            Route::get('/subscription', [SubscriptionController::class, 'show'])->name('subscription.show');
            Route::post('/subscription/checkout-preview', [SubscriptionController::class, 'checkoutPreview'])->name('subscription.checkout-preview');
            Route::get('/subscription/activated', [SubscriptionController::class, 'activated'])->name('subscription.activated');
            Route::post('/subscription/restore', [SubscriptionController::class, 'restore'])->name('subscription.restore');

            Route::get('/onboarding', [OnboardingController::class, 'show'])->name('onboarding.show');
            Route::put('/onboarding', [OnboardingController::class, 'update'])->name('onboarding.update');

            Route::get('/support', [SupportController::class, 'show'])->name('support.show');
            Route::get('/support-tickets', [SupportController::class, 'index'])->name('support-tickets.index');
            Route::post('/support-tickets', [SupportController::class, 'store'])->name('support-tickets.store');
            Route::get('/settings', [SettingsController::class, 'show'])->name('settings.show');
            Route::patch('/settings', [SettingsController::class, 'update'])->name('settings.update');
        });
    });
