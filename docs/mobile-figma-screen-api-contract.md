# Adam Travel Mobile Figma To API Contract

## Scope

This document maps the mobile Figma screens to backend APIs so the mobile developer can build screen-by-screen without guessing.

This contract is intentionally organized by the Figma modules requested for mobile:

- Authentication
- Home
- Import Flow
- Tap Pin
- Location Details & Organization
- Trips
- Single
- Owner
- Editor
- Viewer
- Manage Members (Owner)
- Profile
- Friends

Offline is intentionally excluded from this document because that module is being handled on the app side.

## Base Rules

- Base URL: `{{base_url}}/api/v1`
- Auth: Bearer token after login/register/social login
- Standard response envelope:
  - `success`
  - `message`
  - `data`
  - `meta`
  - `errors`
- Pagination screens use:
  - `meta.current_page`
  - `meta.per_page`
  - `meta.total`
  - `meta.last_page`

## Authentication

### Splash Screen

- Backend API: none
- Mobile behavior:
  - Check whether `access_token` exists
  - If yes, open authenticated flow
  - If no, open sign-in or sign-up flow

### Create Account

- Screen intent: manual sign-up
- API: `POST /auth/register`
- Request:
  - `name`
  - `email`
  - `password`
  - `password_confirmation`
  - `device_name`
  - `device_platform`
  - `device_identifier`
- Use from response:
  - `data.token`
  - `data.user`
  - `data.preference`

### Sign In

- Screen intent: email/password login
- API: `POST /auth/login`
- Request:
  - `email`
  - `password`
  - `device_name`
  - `device_platform`
  - `device_identifier`
- Use from response:
  - `data.token`
  - `data.user`

### Sign In With Google

- Screen intent: social login button
- API: `POST /auth/social/google`
- Important:
  - Backend expects Firebase SSO
  - Send `firebase_id_token`, not a raw Google token
- Request:
  - `firebase_id_token`
  - `device_name`
  - `device_platform`
  - `device_identifier`
- Use from response:
  - `data.token`
  - `data.user`

### Sign In With Apple

- Screen intent: social login button
- API: `POST /auth/social/apple`
- Important:
  - Backend expects Firebase SSO
  - Send `firebase_id_token`, not a raw Apple token
- Request:
  - `firebase_id_token`
  - `device_name`
  - `device_platform`
  - `device_identifier`
  - optional `name`
  - optional `email`
- Use from response:
  - `data.token`
  - `data.user`

### Reset Password - Email Entry

- Screen intent: enter account email
- Preferred API for Figma OTP flow: `POST /auth/password-otp/request`
- Request:
  - `email`
- Use from response:
  - `data.challenge_id`

### Verify OTP / Verify Your Email

- Screen intent: 4-digit or code verification step
- API: `POST /auth/password-otp/verify`
- Request:
  - `email`
  - `challenge_id`
  - `code`
- Use from response:
  - `data.reset_token`

### Create New Password

- Screen intent: final password reset submit
- API: `POST /auth/reset-password`
- Request:
  - `email`
  - `token`
  - `password`
  - `password_confirmation`

### Location Permission Explanation

- Screen intent: explain why location is needed
- Backend API: no direct permission API
- Mobile behavior:
  - Ask OS permission locally
  - After the permission flow is complete, mark onboarding done
- API for completion state:
  - `GET /onboarding`
  - `PUT /onboarding`
- `PUT /onboarding` request:
  - `completed: true`

### Log Out

- API: `POST /auth/logout`

## Home

### Home Empty State

- Screen intent: first-state map with no places yet
- Primary API: `GET /dashboard`
- Use these response blocks:
  - `data.summary`
  - `data.empty_states`
  - `data.quick_actions`
  - `data.search`
- Mobile rules:
  - if `data.empty_states.has_saved_places` is `false`, show empty-state card
  - use quick actions for `Add Pin` and `Import`

### Home Populated Map

- Screen intent: saved places already exist and show as pins
- APIs:
  - `GET /dashboard`
  - `GET /map/pins`
- `GET /map/pins` query options:
  - `north`
  - `south`
  - `east`
  - `west`
  - `category`
  - `saved_place_collection_id`
  - `is_favorite`
  - `q`
  - `latitude`
  - `longitude`
  - `radius_meters`
  - `limit`
- Use these keys from pin payload:
  - `id`
  - `title`
  - `category`
  - `is_favorite`
  - `region_label`
  - `saved_place_collection_id`
  - `latitude`
  - `longitude`
  - `city`
  - `country_code`

### Home Smart Nearby Card / Nearby Summary

- Screen intent: nearby suggestion card on home
- API: `GET /dashboard?latitude={lat}&longitude={lng}&radius_meters={meters}`
- Use:
  - `data.smart_banner`
  - `data.smart_banner.nearby_places`
  - `data.notifications.unread_count`

### Search Screen

- Screen intent: typed query, recent searches, trending content, nearby places
- API: `GET /home/search`
- Query:
  - `q`
  - `latitude`
  - `longitude`
  - `radius_meters`
  - `limit`
- Use from response:
  - `data.query`
  - `data.recent_searches`
  - `data.trending_now`
  - `data.nearby_places`
  - `data.results`
  - `data.empty_state`

### Save Recent Search

- Screen intent: store recent queries when search is executed
- API: `POST /home/searches`
- Request:
  - `q`
  - `result_count`

### Clear Recent Searches

- Screen intent: clear button on recent searches
- API: `DELETE /home/searches`

### Notifications Screen

- Screen intent: list grouped notifications
- API: `GET /notifications`
- Query:
  - `unread_only`
- Use from response:
  - `data.summary.unread_count`
  - `data.summary.total_count`
  - `data.groups`
- Each group contains:
  - `label`
  - `items`

### Mark Single Notification Read

- API: `POST /notifications/{notification}/read`

### Mark All Notifications Read

- API: `POST /notifications/read-all`

## Import Flow

### Home Import Action

- Screen intent: import from link/text action launched from home
- Backend API to create processing job: `POST /imports`

### Import Submission

- API: `POST /imports`
- Request:
  - one of:
    - `source_url`
    - `raw_text`
- Use from response:
  - `data.id`
  - `data.status`
  - `data.candidates`

### Import Processing State

- Screen intent: show pending, processing, manual review, ready to confirm, failed
- API: `GET /imports/{import}`
- Use:
  - `data.status`
  - `data.error_code`
  - `data.error_message`
  - `data.candidates`

### Manual Override Screen

- Screen intent: user fixes extracted data manually
- API: `PATCH /imports/{import}/manual-override`
- Request:
  - `place_name`
  - `category`
  - `city`
  - `region`
  - `country`
  - `latitude`
  - `longitude`
  - `summary`

### Confirm Extracted Place

- Screen intent: user accepts extracted location into saved places
- API: `POST /imports/{import}/confirm`
- Request:
  - `candidate_id`
  - `category`
  - `title_override`
  - `notes`
  - `region_label`
  - `saved_place_collection_id`
  - `is_favorite`
  - `visibility`
- Use from response:
  - `data.saved_place`

### Retry Failed Import

- API: `POST /imports/{import}/retry`

## Tap Pin

### Tap Pin On Map

- Screen intent: open selected place from map pin
- API chain:
  - `GET /map/pins`
  - user taps `saved_place.id`
  - `GET /saved-places/{savedPlace}`

### Filter Bottom Sheet

- Screen intent: filter pins by category, favorites, collection, radius
- Primary API for filter metadata: `GET /dashboard`
- Apply API: `GET /map/pins`
- Use from dashboard:
  - `data.filters.categories`
  - `data.filters.collections`
  - `data.filters.favorites_count`
  - `data.filters.radius_presets`

### Nearby Tap Pin Variant

- Screen intent: nearby suggestion selection
- API:
  - `GET /home/search?latitude={lat}&longitude={lng}&radius_meters={meters}`
- Use:
  - `data.nearby_places[].saved_place.id`

## Location Details & Organization

### Location Details Screen

- Screen intent: full detail card for one saved place
- API: `GET /saved-places/{savedPlace}`
- Use from response:
  - base place fields from `SavedPlaceResource`
  - `hero_image_url`
  - `preview_summary`
  - `selected_import_candidate`
  - `trip_links`
  - `actions`

### Create Saved Place Manually

- Screen intent: add pin manually from home
- API: `POST /saved-places`
- Request:
  - `location` object or `location_id`
  - `category`
  - `title_override`
  - `notes`
  - `region_label`
  - `saved_place_collection_id`
  - `is_favorite`
  - `visibility`

### Update Saved Place

- Screen intent: edit title, note, favorite, category, collection
- API: `PATCH /saved-places/{savedPlace}`

### Delete Saved Place

- Screen intent: delete action
- API: `DELETE /saved-places/{savedPlace}`

### Search Saved Places

- Screen intent: saved-place list or search result state
- API: `GET /saved-places/search`
- Query:
  - `q`
  - `limit`

### Saved Place List

- Screen intent: list organization screen
- API: `GET /saved-places`
- Query:
  - `q`
  - `category`
  - `region_label`
  - `saved_place_collection_id`
  - `visibility`
  - `is_favorite`
  - `sort`
  - `per_page`

### Select Category / Select Collection Screen

- Screen intent: user assigns place into a named collection
- API: `GET /saved-place-collections?saved_place_id={savedPlace}`
- Use:
  - `data.selected_saved_place_collection_id`
  - `data.collections`

### Create New Category / New Collection Screen

- Screen intent: create user-defined bucket before assigning places
- API: `POST /saved-place-collections`
- Request:
  - `name`
  - `description`
  - `color_hex`
  - `sort_order`
  - optional `saved_place_ids`
- Use from response:
  - `data.id`
  - `data.name`

### Categorize Existing Saved Place

- Screen intent: save selected category/collection onto place
- API: `POST /saved-places/{savedPlace}/categorize`
- Request:
  - `saved_place_collection_id`

### Add To Trip Sheet

- Screen intent: show trips available for this saved place
- API: `GET /saved-places/{savedPlace}/trip-options`
- Use from each item:
  - `id`
  - `title`
  - `cover_image_url`
  - `start_date`
  - `end_date`
  - `member_count`
  - `pool_count`
  - `current_user_role`
  - `already_added`
  - `can_add`

### Confirm Add To Trip

- Screen intent: final add-to-trip action
- API: `POST /saved-places/{savedPlace}/trip-links`
- Request:
  - `trip_id`
  - `trip_category`
  - `notes`
- Use from response:
  - `data.id`
  - `data.trip_id`
  - `data.saved_place_id`

### Saved Confirmation / Saved Container Screen

- Screen intent: temporary success state after organize/save
- Backend API: no dedicated endpoint
- Mobile behavior:
  - use success response from:
    - `POST /saved-places`
    - `POST /saved-places/{savedPlace}/categorize`
    - `POST /saved-places/{savedPlace}/trip-links`

## Trips

### Trip List Screen

- API: `GET /trips`
- Query:
  - `q`
  - `status`
  - `per_page`
- Use:
  - `id`
  - `title`
  - `start_date`
  - `end_date`
  - `cover_image_url`
  - `current_user_role`
  - `member_count`
  - `pool_count`
  - `pending_invites_count`

### Create Trip Screen

- API: `POST /trips`
- Request:
  - `title`
  - `description`
  - `start_location_name`
  - `start_latitude`
  - `start_longitude`
  - `end_location_name`
  - `end_latitude`
  - `end_longitude`
  - `start_date`
  - `end_date`
  - `status`
  - `cover_image_url`

### Accept Invite Via Token

- Screen intent: external or deep-link trip invite accept
- API: `POST /trip-invites/{token}/accept`

## Single

### Single Trip Overview

- Screen intent: load one trip regardless of role
- API: `GET /trips/{trip}`
- Use:
  - all `TripResource` fields
  - `current_user_role`
  - `owner`
  - `members`
  - `pool`
  - `invites`

### Shared Pool Tab

- API: `GET /trips/{trip}/pool`

### Itinerary Tab

- API: `GET /trips/{trip}/itinerary`

### AI Itinerary Read View

- API: `GET /trips/{trip}/ai-itinerary`

### Suggestions List

- API: `GET /trips/{trip}/suggestions`

### Trip Balance / Coverage Screen

- API: `GET /trips/{trip}/balance`

### Archived Trip Detail / Bali Retreat Style Screen

- Screen intent: read-only archived itinerary detail
- API: `GET /timeline/{trip}`
- Use:
  - `is_read_only`
  - `itinerary_days`
  - `primary_country_flag`
  - `places_count`
  - `nights_count`

## Owner

### Edit Trip

- API: `PATCH /trips/{trip}`

### Delete Trip

- API: `DELETE /trips/{trip}`

### Create Member Invitation

- API: `POST /trips/{trip}/invites`
- Request:
  - `email`
  - `role`

### Cancel Pending Invitation

- API: `DELETE /trips/{trip}/invites/{invite}`

### Generate AI Itinerary

- API: `POST /trips/{trip}/ai-itinerary/generate`
- Optional request:
  - `force_refresh`

### Apply AI Itinerary

- API: `POST /trips/{trip}/ai-itinerary/apply`
- Request:
  - `trip_ai_run_id`

### Create Itinerary Day

- API: `POST /trips/{trip}/itinerary/days`
- Request:
  - `day_number`
  - `trip_date`
  - `title`
  - `notes`

### Reorder Itinerary

- API: `PUT /trips/{trip}/itinerary/reorder`
- Request:
  - `version`
  - `days[]`

### Create Itinerary Item

- API: `POST /trips/{trip}/itinerary/items`
- Request:
  - `itinerary_day_id`
  - `trip_place_id`
  - `starts_at`
  - `ends_at`
  - `notes`

### Update Itinerary Item

- API: `PATCH /trips/{trip}/itinerary/items/{itineraryItem}`

### Delete Itinerary Item

- API: `DELETE /trips/{trip}/itinerary/items/{itineraryItem}`

### Generate Suggestions

- API: `POST /trips/{trip}/suggestions/generate`
- Optional request:
  - `limit`
  - `force_refresh`

## Editor

### Add Shared Pool Item

- API: `POST /trips/{trip}/pool`
- Request:
  - `saved_place_id`
  - `trip_category`
  - `notes`

### Update Shared Pool Item

- API: `PATCH /trips/{trip}/pool/{tripPlace}`

### Remove Shared Pool Item

- API: `DELETE /trips/{trip}/pool/{tripPlace}`

### Heart Shared Pool Item

- API: `POST /trips/{trip}/pool/{tripPlace}/heart`

### Unheart Shared Pool Item

- API: `DELETE /trips/{trip}/pool/{tripPlace}/heart`

### Generate Suggestions

- API: `POST /trips/{trip}/suggestions/generate`

### Accept Suggestion Into Pool

- API: `POST /trips/{trip}/suggestions/{suggestion}/add`

### Dismiss Suggestion

- API: `POST /trips/{trip}/suggestions/{suggestion}/dismiss`

## Viewer

### Viewer Trip Screen

- APIs:
  - `GET /trips/{trip}`
  - `GET /trips/{trip}/pool`
  - `GET /trips/{trip}/itinerary`
  - `GET /trips/{trip}/ai-itinerary`
  - `GET /trips/{trip}/suggestions`
  - `GET /trips/{trip}/balance`

### Important Viewer Rule

- Viewer is read-only
- Do not show owner/editor mutation actions when `current_user_role = viewer`

## Manage Members (Owner)

### Update Member Role

- API: `PATCH /trips/{trip}/members/{member}`
- Request:
  - `role`

### Remove Member

- API: `DELETE /trips/{trip}/members/{member}`

## Profile

### Profile Home

- API: `GET /profile`
- Use from response:
  - `data.user`
  - `data.stats`
  - `data.activity`
  - `data.subscription`

### My Profile / Edit Profile

- APIs:
  - `GET /me`
  - `PATCH /me`
- `PATCH /me` request:
  - `name`
  - `email`

### Settings Screen

- APIs:
  - `GET /settings`
  - `PATCH /settings`
- Use from `GET /settings`:
  - account summary
  - notification settings
  - distance/map/theme preferences
  - CMS pages
  - app version
  - danger zone flags

### Help & Support

- API: `GET /support`
- Optional query:
  - `q`
- Use from response:
  - FAQ list
  - support contact block
  - support CTA data

### Create Support Ticket

- API: `POST /support-tickets`
- Request:
  - `subject`
  - `message`
  - `priority`

### Support Ticket History

- API: `GET /support-tickets`

### Upgrade To Premium

- APIs:
  - `GET /plans`
  - `GET /subscription`
- Use:
  - plan catalog
  - active plan
  - entitlements
  - paywall blocks

### Confirm Subscription

- Screen intent: show purchase summary before native billing action
- API: `POST /subscription/checkout-preview`
- Request:
  - `plan_code`
  - `billing_cycle`
  - optional payment method preview values
- Use:
  - `plan`
  - `subtotal`
  - `tax_amount`
  - `total_today`
  - `next_billing_at`
  - `payment_method`
  - `legal`

### Subscription Activated

- API: `GET /subscription/activated`
- Use:
  - `is_active`
  - `headline`
  - `message`
  - `badge`
  - `benefits`
  - `plan`

### Restore Subscription

- API: `POST /subscription/restore`
- Important:
  - Use this after native purchase restore flow or RevenueCat restore on app side
  - Backend records restore request and refresh intent

### Travel Memory Timeline

- APIs:
  - `GET /timeline`
  - `GET /timeline/{trip}`
- Use from list:
  - `title`
  - `date_range_label`
  - `places_count`
  - `nights_count`
  - `primary_country_flag`
  - `timeline_status_label`

### Delete Account

- API: `DELETE /me`
- Request:
  - `current_password`

## Friends

### Friends List

- API: `GET /friends`
- Use:
  - `data.friends`
  - current outgoing request state if needed

### Send Friend Request

- API: `POST /friends/requests`
- Request:
  - `recipient_email`

### Cancel Friend Request

- API: `DELETE /friends/requests/{friendRequest}`

### Friend Requests Screen

- API: `GET /profile/invitations?tab=friends`
- Use:
  - `data.counts`
  - `data.friend_requests`

### Accept Single Friend Request

- API: `POST /profile/invitations/friends/{friendRequest}/accept`

### Decline Single Friend Request

- API: `POST /profile/invitations/friends/{friendRequest}/decline`

### Accept All Friend Requests

- API: `POST /profile/invitations/friends/accept-all`

### Trip Invitations Screen

- API: `GET /profile/invitations?tab=trips`
- Use:
  - `data.counts`
  - `data.trip_invitations`

### Accept Trip Invitation From Inbox

- API: `POST /profile/invitations/trips/{invite}/accept`

### Decline Trip Invitation From Inbox

- API: `POST /profile/invitations/trips/{invite}/decline`

## Screens With No Dedicated Backend API

These Figma screens are primarily client-side and should not get extra backend endpoints unless product requirements change:

- Splash screen
- Native location-permission OS prompt itself
- Temporary success popups or bottom sheets after save
- Native purchase confirmation execution itself
  - mobile should use App Store / Play billing or RevenueCat SDK
  - backend handles preview, entitlement state, restore request, and webhook sync

## Mobile Build Order Recommendation

Build in this order so the mobile team can move screen-by-screen with the least blocking:

1. Authentication
2. Home
3. Import Flow
4. Tap Pin
5. Location Details & Organization
6. Trips
7. Profile
8. Friends

## Current Backend Gap Notes

These are not blockers for the current Figma contract, but the mobile developer should know them:

- Offline is intentionally excluded from this contract
- Location permission state is not stored as a separate backend field yet
- Purchase execution is app-side; backend does not directly charge cards
- Notification delivery is backend-readable, but push transport itself is not part of this API contract
