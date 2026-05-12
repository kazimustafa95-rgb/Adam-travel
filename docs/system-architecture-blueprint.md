# Adam Travel Utility App
## System Architecture Blueprint

## 1. Source Context

This blueprint is based on:

- The attached FSD and wireframe notes in `Adam - Travel Utility App.txt`
- The expanded V1 feature additions in the same document
- The Figma file `Adam - Travel Utility App` at node `598:1044`

Important source reconciliation:

- The product is referred to as `Travel Utility App`, `Adam`, and `PilotIQ`
- The Figma frames show a `PilotIQ` brand mark on some screens
- For implementation, the team should lock one canonical product name early to avoid API, CMS, billing, and legal copy drift

Recommended implementation assumption:

- Marketing name: `Adam Travel`
- Internal app code: `pilotiq`

This workspace is currently empty, so the architecture below is greenfield and not constrained by existing code.

## 2. Product Goal

### 2.1 What Problem The App Solves

Travel inspiration is usually fragmented across Instagram posts, TikTok captions, reels, blogs, browser tabs, and chat threads. Users save content, but later cannot reliably:

- extract the actual place
- remember why they saved it
- organize it on a map
- turn it into a trip
- access it offline while traveling

This product turns unstructured travel inspiration into structured, map-ready travel data.

### 2.2 Main User Journey

1. A user signs up and learns the core loop quickly.
2. The user pastes a public link or raw text into the import flow.
3. The system extracts a place, resolves coordinates, and generates a concise summary.
4. The place appears on the user’s map and in their saved locations.
5. The user groups saved places into a trip.
6. The user optionally invites collaborators to contribute places to the shared pool.
7. The owner finalizes the itinerary.
8. The trip remains accessible offline during travel.
9. Completed trips become part of a memory timeline.

### 2.3 Core Business Logic

- `Import` is the raw ingestion event.
- `Location` is the normalized geo entity.
- `SavedPlace` is the user-owned save of a place.
- `Trip` is the planning container.
- `TripPlace` is a place added into a trip’s shared pool.
- `ItineraryItem` is a scheduled trip place assigned to a day/time.

The critical business separation is:

- saved places are not automatically itinerary items
- trip pool items are not automatically scheduled
- only the owner can finalize itinerary ordering and timing

That separation prevents the product from collapsing into messy auto-scheduling behavior.

### 2.4 MVP Scope

The real MVP is not “all travel features.” It is:

- email auth and onboarding
- import from URL or text
- AI/NLP extraction with manual correction
- saved map pins and list directory
- trip creation
- shared trip pool with owner/editor/viewer roles
- owner-only itinerary scheduling
- lightweight AI summaries and route suggestions
- offline access to trip essentials
- proximity reminders on app-open only
- simple paywall
- lightweight super admin panel

### 2.5 Future Scalability

The architecture should allow later expansion into:

- native mobile share extensions
- richer route optimization
- public collections
- social discovery
- push notifications
- background geofencing
- smarter collaborative ranking
- more providers for maps, AI, and geocoding

The implementation should therefore be modular, event-driven where useful, and provider-agnostic at the service layer.

## 3. System Boundaries

### 3.1 Primary Surfaces

- Mobile app client
- Laravel API backend
- Background worker layer for imports, AI, geocoding, suggestions, snapshots
- Web-based admin panel

### 3.2 Recommended Platform Decisions

- Backend: Laravel 12
- API Auth: Sanctum personal access tokens for mobile
- Admin Auth: session guard with CSRF protection
- UI stack: Blade shell + Vue 3 islands/components + Tailwind CSS + Vite
- Database: MySQL 8
- Cache/Queue: Redis + Horizon
- Maps: Mapbox recommended for offline map packs
- Billing: RevenueCat recommended for mobile subscriptions, synced into Laravel
- AI/NLP: OpenAI-compatible extraction and summarization, wrapped behind services

## 4. Figma Analysis

The Figma file is a wireframe prototype rather than a polished design system, but it is still strong enough to define product structure.

### 4.1 Layout System

Observed patterns:

- Mobile frames are mostly iPhone-sized portrait canvases around `430 x 932`
- Screens are grouped by journey sections rather than atomic components
- The app uses a simple single-column mobile layout
- Most screens rely on top header + content stack + bottom navigation
- The map screens use floating controls and bottom drawers instead of deep page transitions

Implementation implications:

- Build a consistent mobile spacing scale with `4/8/12/16/20/24/32`
- Use reusable page shells:
  - auth shell
  - map shell
  - list shell
  - modal/bottom-sheet shell
  - trip planner shell

### 4.2 Navigation

Observed:

- Persistent mobile bottom nav appears across core screens
- Tabs visually map to `Map`, `Save/Import`, `Trips`, `Offline`, and `Profile`
- Search and filter states appear layered on top of the home flow

Architecture impact:

- API resources should be scoped to these navigation roots
- Mobile data payloads should support “dashboard hydration” to avoid 4 to 6 network calls on app open

### 4.3 Typography

Observed:

- Wireframes use a clean sans-serif hierarchy
- Large titles are minimal
- Card titles and metadata dominate over long paragraphs

Implementation rule:

- Keep summaries short
- Optimize cards for scannability, not editorial reading

### 4.4 Colors

Observed:

- Light theme dominant
- Cyan/teal primary action color
- Neutral white surfaces
- Dark text and soft gray borders/dividers
- Some admin variations appear in light and dark concepts, but the mobile app is clearly light-first

Implementation rule:

- Define CSS variables from day one
- Use semantic tokens rather than raw hex in components

### 4.5 Card Styles

Observed:

- Image-first trip and place cards
- Compact metadata rows
- Rounded cards
- Clear CTA blocks
- Repeated list-card patterns for places, suggestions, and timeline items

Implementation rule:

- Build a unified `AppCard` pattern with variants:
  - place
  - trip
  - suggestion
  - timeline
  - metric

### 4.6 Table Styles

Observed from the admin wireframes:

- Lightweight data tables
- No analytics-heavy charts
- Status pills and action buttons
- Narrow operational focus

Implementation rule:

- Prefer reusable server-side paginated table components
- Keep admin actions modal-driven and auditable

### 4.7 Modal And Bottom Sheet Patterns

Observed:

- Import uses a sheet/modal pattern
- Saved locations and location details appear in drawers/sheets
- Role management and itinerary actions appear modal-friendly

Implementation rule:

- Build a single bottom-sheet component with size variants:
  - compact
  - medium
  - full-height

### 4.8 Responsive Behavior

Observed:

- The Figma is mobile-first
- Admin is desktop/tablet oriented
- No complex tablet-specific layouts are shown for mobile

Implementation rule:

- Mobile app APIs should stay view-agnostic
- Admin panel should support desktop first, then degrade cleanly to tablet width

### 4.9 Map Interaction UX

Observed:

- Map is the primary dashboard
- Floating controls include zoom and current-location actions
- Map context is reinforced by drawers with place summaries

Implementation rule:

- Map data should load incrementally by viewport
- Place clustering and bounds queries should be first-class in the backend contract

### 4.10 Empty, Loading, And Error States

Observed from wireframes and FSD:

- empty map state before any saved pins
- import processing state
- import failure state
- manual correction state
- offline active indicator

Implementation rule:

- These states are not optional polish
- They are central to trust, especially because import accuracy is the make-or-break feature

## 5. Mobile Module Analysis

Each module below includes purpose, user flow, backend needs, APIs, data, edge cases, async work, integrations, security, and performance expectations.

### 5.1 Authentication

- Purpose: create secure account access for mobile users.
- Figma surfaces: splash, welcome, sign up, login, forgot password, reset confirmation.
- User flow:
  - user opens app
  - user selects sign up or login
  - user authenticates
  - user lands in onboarding or home
- Backend requirements:
  - register/login/logout
  - password reset email flow
  - token issuance and revocation
  - account status enforcement
- APIs:
  - `POST /api/v1/auth/register`
  - `POST /api/v1/auth/login`
  - `POST /api/v1/auth/forgot-password`
  - `POST /api/v1/auth/reset-password`
  - `POST /api/v1/auth/logout`
  - `GET /api/v1/me`
- Tables:
  - `users`
  - `personal_access_tokens`
  - `password_reset_tokens`
  - `user_devices`
- Relationships:
  - user has many tokens
  - user has many devices
- Validation:
  - unique email
  - strong password rules
  - device name required for token issuance
- Edge cases:
  - suspended users must be blocked post-login and mid-session
  - duplicate accounts
  - repeated reset spam
- Queue/background jobs:
  - send password reset mail
  - send welcome email optional
- Third-party services:
  - transactional mail provider
- Security:
  - throttled login
  - hash passwords with Laravel defaults
  - revoke tokens on suspicious activity
- Performance:
  - keep auth endpoints lean
  - cache account status if needed

### 5.2 Onboarding

- Purpose: explain the app’s core loop with minimal friction.
- Figma surfaces: welcome and 2 to 3 onboarding slides, permission explainer.
- User flow:
  - first successful auth
  - user swipes key benefit slides
  - app requests location permission only with context
  - onboarding completion recorded
- Backend requirements:
  - persist onboarding completion
  - persist permission preference hints if needed
- APIs:
  - `GET /api/v1/onboarding`
  - `PUT /api/v1/onboarding`
- Tables:
  - `users`
  - `user_preferences`
- Relationships:
  - user has one preferences row
- Validation:
  - completion flag
  - optional selected display preferences
- Edge cases:
  - user skips onboarding
  - user denies location permission
- Queue/background jobs:
  - none required
- Third-party services:
  - OS-level location permission only
- Security:
  - keep only harmless preference data
- Performance:
  - deliver onboarding payload inline with `me` response where possible

### 5.3 Map Dashboard

- Purpose: act as the home screen and primary utility surface.
- Figma surfaces: populated map, empty map, search active state, filter pills, bottom nav, proximity reminder banner.
- User flow:
  - user lands on home
  - app loads recent pins, trips, filters, and optional proximity banner
  - user pans/zooms/searches/filters
  - user taps a pin for details
- Backend requirements:
  - viewport-based place loading
  - clustering support
  - search across saved places
  - recent trips summary
  - proximity evaluation entry point
- APIs:
  - `GET /api/v1/dashboard`
  - `GET /api/v1/map/pins`
  - `GET /api/v1/saved-places/search`
  - `POST /api/v1/proximity/check`
- Tables:
  - `saved_places`
  - `locations`
  - `trips`
  - `proximity_prompt_logs`
- Relationships:
  - saved place belongs to user and location
  - saved place may belong to many trips through trip places
- Validation:
  - map bounds must be valid lat/lng values
  - radius limits for proximity checks
- Edge cases:
  - no saved places
  - place coordinates missing
  - user denies current location access
  - too many pins in dense regions
- Queue/background jobs:
  - optional cluster cache refresh
- Third-party services:
  - map provider SDK
- Security:
  - never expose another user’s places through bounds queries
- Performance:
  - use map bounds queries
  - spatial indexes
  - cluster pins server-side or via client-ready aggregated payloads

### 5.4 Import Engine

- Purpose: capture URLs or free text and start the extraction pipeline.
- Figma surfaces: import sheet, processing state, success review, manual override, error state.
- User flow:
  - user pastes URL or text
  - import record created
  - status becomes processing
  - result becomes success, manual review, or failed
  - user confirms or edits
- Backend requirements:
  - source normalization
  - URL validation
  - import status machine
  - retry logic
- APIs:
  - `POST /api/v1/imports`
  - `GET /api/v1/imports/{import}`
  - `POST /api/v1/imports/{import}/retry`
  - `POST /api/v1/imports/{import}/confirm`
  - `PATCH /api/v1/imports/{import}/manual-override`
- Tables:
  - `imports`
  - `import_candidates`
  - `ai_requests`
- Relationships:
  - import belongs to user
  - import has many extraction candidates
- Validation:
  - either `source_url` or `raw_text` required
  - max payload size
  - supported URL schemes only
- Edge cases:
  - unsupported host
  - dead URL
  - extracted multiple possible locations
  - no location found
  - duplicate import
- Queue/background jobs:
  - full import pipeline
- Third-party services:
  - content fetch/parser
  - AI extraction model
  - geocoding provider
- Security:
  - sanitize fetched text
  - protect against SSRF by whitelisting fetch strategy and blocking private IP targets
  - rate limit imports
- Performance:
  - fully async processing
  - store raw provider payloads as JSON for debugging, not repeated re-fetch

### 5.5 NLP Extraction Flow

- Purpose: transform raw text or URLs into structured travel places.
- Figma surfaces: processing, success, manual override, error.
- User flow:
  - content fetched and normalized
  - model extracts place candidates, category hints, summary cues
  - geocoder resolves coordinates
  - confidence scored
  - user approves or manually edits
- Backend requirements:
  - provider abstraction
  - confidence scoring
  - structured extraction schema
  - manual correction path
- APIs:
  - internal flow behind import endpoints
  - optional admin inspection endpoint
- Tables:
  - `imports`
  - `import_candidates`
  - `locations`
  - `ai_requests`
- Relationships:
  - candidate may resolve to one location
- Validation:
  - AI response must conform to expected JSON schema
  - coordinates must be valid
- Edge cases:
  - place names in multiple countries
  - captions with itinerary lists instead of one place
  - slang or emoji-heavy input
  - hotel or restaurant names without city context
- Queue/background jobs:
  - fetch source
  - extract structured entities
  - geocode
  - summarize
- Third-party services:
  - LLM
  - geocoder
- Security:
  - redact sensitive user data from prompts
  - log prompts/responses carefully
- Performance:
  - use short prompts and deterministic schemas
  - retry only failed substeps instead of full pipeline when possible

### 5.6 Saved Locations

- Purpose: let users browse, search, filter, categorize, and manage their saved places.
- Figma surfaces: recently saved drawer, saved locations directory, category/grouping states.
- User flow:
  - user opens saved list
  - filters by trip, region, category, wishlist, etc.
  - edits tags/category/notes
  - optionally adds place to trip pool
- Backend requirements:
  - server-side filtering
  - search
  - sorting
  - grouping metadata
- APIs:
  - `GET /api/v1/saved-places`
  - `POST /api/v1/saved-places`
  - `GET /api/v1/saved-places/{savedPlace}`
  - `PATCH /api/v1/saved-places/{savedPlace}`
  - `DELETE /api/v1/saved-places/{savedPlace}`
  - `POST /api/v1/saved-places/{savedPlace}/tags`
- Tables:
  - `saved_places`
  - `saved_place_tags`
  - `tags`
  - `locations`
- Relationships:
  - saved place belongs to user and location
  - saved place has many tags
- Validation:
  - category enum
  - notes length
  - tag uniqueness per user
- Edge cases:
  - duplicate save of same place
  - moderated location becomes unavailable
  - deleted location still referenced in trip history
- Queue/background jobs:
  - optional thumbnail refresh
- Third-party services:
  - none required beyond maps/media
- Security:
  - owner-only CRUD
- Performance:
  - paginate lists
  - eager load location and lightweight trip counts

### 5.7 Location Details

- Purpose: give users context on why a place matters.
- Figma surfaces: pin detail sheet, AI summary, map snippet, categorize actions.
- User flow:
  - user taps a pin or card
  - sheet shows summary, category, source, trip membership
  - user edits category or adds to trip
- Backend requirements:
  - normalized location details
  - source attribution
  - summary retrieval
- APIs:
  - `GET /api/v1/saved-places/{savedPlace}`
  - `POST /api/v1/saved-places/{savedPlace}/trip-links`
- Tables:
  - `saved_places`
  - `locations`
  - `imports`
  - `trip_places`
- Relationships:
  - saved place may originate from one import
  - saved place may appear in many trip pools
- Validation:
  - note/category updates
- Edge cases:
  - summary unavailable
  - location resolved manually and differs from import text
- Queue/background jobs:
  - regenerate summary if manual override changes place substantially
- Third-party services:
  - map preview provider
- Security:
  - protect raw source data visibility if it contains personal notes
- Performance:
  - cache detail resource briefly if heavily accessed

### 5.8 Trips

- Purpose: transform saved places into structured trip planning.
- Figma surfaces: trips dashboard, create trip, collaboration modal, shared pool, itinerary builder, trip map, balance widget, day tabs.
- User flow:
  - owner creates trip
  - invites collaborators
  - collaborators add places to shared pool
  - users heart places
  - owner schedules itinerary
  - trip becomes active/completed/archived
- Backend requirements:
  - trip CRUD
  - invitation issuance and acceptance
  - role enforcement
  - shared pool vs itinerary separation
  - owner-only scheduling
- APIs:
  - `GET /api/v1/trips`
  - `POST /api/v1/trips`
  - `GET /api/v1/trips/{trip}`
  - `PATCH /api/v1/trips/{trip}`
  - `DELETE /api/v1/trips/{trip}`
  - `POST /api/v1/trips/{trip}/invites`
  - `POST /api/v1/trip-invites/{token}/accept`
  - `PATCH /api/v1/trips/{trip}/members/{member}`
  - `DELETE /api/v1/trips/{trip}/members/{member}`
  - `GET /api/v1/trips/{trip}/pool`
  - `POST /api/v1/trips/{trip}/pool`
  - `PATCH /api/v1/trips/{trip}/pool/{tripPlace}`
  - `DELETE /api/v1/trips/{trip}/pool/{tripPlace}`
  - `POST /api/v1/trips/{trip}/pool/{tripPlace}/heart`
  - `GET /api/v1/trips/{trip}/itinerary`
  - `PUT /api/v1/trips/{trip}/itinerary/reorder`
- Tables:
  - `trips`
  - `trip_members`
  - `trip_invites`
  - `trip_places`
  - `trip_place_hearts`
  - `itinerary_days`
  - `itinerary_items`
- Relationships:
  - trip has many members
  - trip has many trip places
  - trip has many itinerary days
  - itinerary day has many itinerary items
- Validation:
  - start date <= end date
  - unique owner per trip
  - one member row per user per trip
  - only owner can schedule itinerary
- Edge cases:
  - invite accepted after role revoked
  - collaborator removed while editing
  - itinerary item references deleted pool item
  - owner deletes trip with collaborators
- Queue/background jobs:
  - optional trip snapshot generation
  - activity/event emission
- Third-party services:
  - email for invite delivery
- Security:
  - strict policy checks per role
  - owner-only destructive actions on itinerary
- Performance:
  - preload pool counts, member counts, and day summaries
  - use optimistic concurrency for reorder operations

### 5.9 AI Itinerary

- Purpose: generate a route-aware baseline itinerary from saved trip places.
- Figma surfaces: itinerary view, trip map, AI generation entry points.
- User flow:
  - owner requests AI itinerary help
  - system proposes day grouping/order
  - owner reviews and edits
- Backend requirements:
  - route-aware suggestion engine
  - day bucketing based on date range
  - editable draft result
- APIs:
  - `POST /api/v1/trips/{trip}/ai-itinerary/generate`
  - `GET /api/v1/trips/{trip}/ai-itinerary`
  - `POST /api/v1/trips/{trip}/ai-itinerary/apply`
- Tables:
  - `trip_ai_runs`
  - `itinerary_days`
  - `itinerary_items`
- Relationships:
  - AI run belongs to trip and requester
- Validation:
  - trip must have sufficient places
  - date range required
- Edge cases:
  - not enough places
  - owner requests generation twice concurrently
  - suggested schedule exceeds open hours or travel constraints
- Queue/background jobs:
  - itinerary generation
- Third-party services:
  - AI provider
  - optional routing provider
- Security:
  - owner/editor visibility may differ, but apply action should be owner-only
- Performance:
  - queue AI generation
  - cache latest draft

### 5.10 AI Suggestions

- Purpose: suggest missing points of interest and fill route gaps.
- Figma surfaces: route suggestions list, add suggestion to trip, curated discovery states.
- User flow:
  - user opens suggestions for a trip
  - system shows nearby gap-fill suggestions
  - user adds or dismisses suggestions
- Backend requirements:
  - route corridor or destination-area search
  - categorization and score explanation
  - dedupe against existing trip places
- APIs:
  - `GET /api/v1/trips/{trip}/suggestions`
  - `POST /api/v1/trips/{trip}/suggestions/generate`
  - `POST /api/v1/trips/{trip}/suggestions/{suggestion}/add`
  - `POST /api/v1/trips/{trip}/suggestions/{suggestion}/dismiss`
- Tables:
  - `trip_suggestions`
  - `locations`
  - `trip_places`
- Relationships:
  - suggestion belongs to trip
  - suggestion may reference a location
- Validation:
  - trip must have origin/destination or enough place context
- Edge cases:
  - duplicate suggestions
  - stale suggestion after trip edits
  - premium gating
- Queue/background jobs:
  - suggestion generation
- Third-party services:
  - AI provider
  - places/routing provider
- Security:
  - enforce trip membership
- Performance:
  - cache generated suggestions per trip version

### 5.11 Offline Mode

- Purpose: preserve trip utility when network connectivity is weak or absent.
- Figma surfaces: offline active banner, offline downloads management, cached map/trip views.
- User flow:
  - user downloads trip/region package
  - app stores map data and travel content locally
  - app uses local cache offline
  - app syncs when connectivity returns
- Backend requirements:
  - offline manifest endpoints
  - sync cursors and conflict strategy
  - downloadable trip package metadata
- APIs:
  - `GET /api/v1/offline/packages`
  - `POST /api/v1/offline/packages/trips/{trip}`
  - `GET /api/v1/sync?cursor=...`
  - `POST /api/v1/sync/push`
- Tables:
  - `offline_packages`
  - `user_devices`
  - version columns on syncable domain tables
- Relationships:
  - offline package belongs to user and optionally trip
- Validation:
  - package scope rules
  - per-plan download limits
- Edge cases:
  - offline edits conflict with remote trip changes
  - package expires after moderation or revocation
- Queue/background jobs:
  - package manifest generation
  - map snapshot generation
- Third-party services:
  - map provider offline SDK
- Security:
  - signed package manifests
  - device-bound downloads if needed
- Performance:
  - delta syncs only
  - avoid full data reloads

### 5.12 Settings

- Purpose: account control, preferences, privacy, and support access.
- Figma surfaces: profile, settings, account management.
- User flow:
  - user updates name/preferences
  - user reviews privacy/help/policy text
  - user opens support ticket or logs out
- Backend requirements:
  - profile update
  - notification/display preferences
  - account deletion request flow if desired
- APIs:
  - `GET /api/v1/me`
  - `PATCH /api/v1/me`
  - `GET /api/v1/settings`
  - `PATCH /api/v1/settings`
  - `POST /api/v1/support-tickets`
- Tables:
  - `users`
  - `user_preferences`
  - `support_tickets`
- Relationships:
  - user has one preferences row
  - user has many support tickets
- Validation:
  - preference enums
  - support message length
- Edge cases:
  - account suspended
  - delete account with active subscription
- Queue/background jobs:
  - send support acknowledgement mail
- Third-party services:
  - mail provider
- Security:
  - explicit confirmation for destructive account actions
- Performance:
  - low complexity endpoints

### 5.13 Subscription And Paywall

- Purpose: monetize premium value without blocking core evaluation.
- Figma surfaces: premium screen with feature comparison and upgrade CTA.
- User flow:
  - user hits free-tier limit or opens paywall
  - app presents plan benefits
  - mobile purchase completes externally
  - backend entitlements update
- Backend requirements:
  - entitlement checks
  - usage limit enforcement
  - webhook-driven subscription sync
- APIs:
  - `GET /api/v1/plans`
  - `GET /api/v1/subscription`
  - `POST /api/v1/subscription/restore`
- Tables:
  - `subscription_plans`
  - `subscriptions`
  - `subscription_events`
- Relationships:
  - user has many subscription events
  - user has one active subscription
- Validation:
  - valid provider identifiers
- Edge cases:
  - grace period
  - canceled but still active
  - provider webhook delay
- Queue/background jobs:
  - webhook processing
  - entitlement refresh
- Third-party services:
  - RevenueCat recommended
- Security:
  - server never trusts the client alone for active plan state
- Performance:
  - cache active entitlement per user

### 5.14 Travel Memory Timeline

- Purpose: convert completed or past trips into a lightweight archive.
- Figma surfaces: timeline feed, summary card, map snapshot cards.
- User flow:
  - trip passes or is marked completed
  - trip appears in timeline
  - user opens a summary card to revisit trip details
- Backend requirements:
  - chronological query
  - snapshot metadata
  - collaborator visibility rules
- APIs:
  - `GET /api/v1/timeline`
  - `GET /api/v1/timeline/{trip}`
- Tables:
  - `trips`
  - `trip_snapshots`
- Relationships:
  - snapshot belongs to trip
- Validation:
  - completed or active past trip logic
- Edge cases:
  - collaborator removed after trip end
  - moderated place removed from history
- Queue/background jobs:
  - map snapshot generation
  - trip summary generation
- Third-party services:
  - static map snapshot provider
- Security:
  - respect trip membership at query time
- Performance:
  - pre-generated summary metadata is preferable

### 5.15 Smart Proximity Reminder

- Purpose: create lightweight engagement without background tracking.
- Figma surfaces: home banner/prompt with dismiss state.
- User flow:
  - app opens or returns to foreground
  - client sends current coordinates if permission granted
  - backend returns 0 to 3 nearby saved places
  - client shows banner if cooldown allows
- Backend requirements:
  - nearest-place query
  - configurable radius
  - cooldown enforcement
- APIs:
  - `POST /api/v1/proximity/check`
- Tables:
  - `saved_places`
  - `locations`
  - `proximity_prompt_logs`
- Relationships:
  - prompt log belongs to user and optionally saved place
- Validation:
  - lat/lng required and valid
  - radius capped
- Edge cases:
  - permission denied
  - user near too many places
  - prompt fatigue
- Queue/background jobs:
  - none required
- Third-party services:
  - none beyond current location from OS
- Security:
  - do not store high-frequency movement history
- Performance:
  - nearest-neighbor query must use spatial indexing

## 6. Admin Panel Analysis

The admin is intentionally operational, not analytical. It should stay constrained.

### 6.1 Dashboard

- Business purpose: give super admin a quick platform health snapshot.
- UI components:
  - metric cards
  - recent imports
  - recent tickets
  - recent moderation actions
- Tables:
  - read-heavy from `users`, `saved_places`, `imports`, `support_tickets`, `activity_logs`
- Filters/search:
  - date range optional but simple
- Actions:
  - drill into users/imports/tickets
- Permissions:
  - super admin only
- API/backend logic:
  - aggregated counters
  - last 10 recent items
- Audit logging:
  - dashboard views need not be logged
- Scalability:
  - cache metrics for 1 to 5 minutes

### 6.2 User Management

- Business purpose: monitor and moderate user accounts.
- UI components:
  - server-side data table
  - profile drawer/modal
  - suspend/disable buttons
- Tables:
  - `users`
  - `user_preferences`
  - `subscriptions`
  - `activity_logs`
- Filters/search:
  - email
  - name
  - status
  - plan
  - created date
- Actions:
  - suspend
  - disable
  - re-enable
  - view saved counts/trip counts/import counts
- Permissions:
  - super admin only
- API/backend logic:
  - account status mutation service
  - token revocation on disable
- Audit logging:
  - mandatory for every status change
- Scalability:
  - indexes on email, status, created_at

### 6.3 Import Monitoring

- Business purpose: troubleshoot extraction problems and review raw source inputs.
- UI components:
  - import table
  - status pill
  - detail modal showing raw input, extracted candidate, and error messages
- Tables:
  - `imports`
  - `import_candidates`
  - `ai_requests`
- Filters/search:
  - status
  - host/domain
  - user
  - created range
- Actions:
  - retry import
  - inspect failure
  - moderate abusive input
- Permissions:
  - super admin only
- API/backend logic:
  - import read model
  - guarded retry endpoint
- Audit logging:
  - retry and moderation actions logged
- Scalability:
  - paginate strictly
  - separate raw payload column storage from table views if volume grows

### 6.4 Location Moderation

- Business purpose: remove problematic or policy-violating place records.
- UI components:
  - location table
  - moderation action modal
  - reason capture field
- Tables:
  - `locations`
  - `saved_places`
  - `moderation_actions`
- Filters/search:
  - place name
  - country
  - moderation state
  - source type
- Actions:
  - soft-remove visibility
  - mark safe
  - inspect affected users count
- Permissions:
  - super admin only
- API/backend logic:
  - moderation service should preserve auditability
  - should not hard-delete historical references immediately
- Audit logging:
  - required for every removal
- Scalability:
  - use soft deletes/moderation flags instead of destructive deletes

### 6.5 Trips Management

- Business purpose: support issue resolution around collaboration, ownership, and trip data.
- UI components:
  - trip table
  - member list modal
  - itinerary summary view
- Tables:
  - `trips`
  - `trip_members`
  - `trip_places`
  - `itinerary_items`
- Filters/search:
  - owner
  - destination
  - status
  - collaborator count
- Actions:
  - inspect only by default
  - optional emergency lock/archive if abuse occurs
- Permissions:
  - super admin only
- API/backend logic:
  - read-heavy support tools
- Audit logging:
  - any emergency override action logged
- Scalability:
  - aggregate counts in read queries

### 6.6 Support Tickets

- Business purpose: track user issues in a simple operational queue.
- UI components:
  - ticket table
  - detail drawer
  - open/resolved toggle
- Tables:
  - `support_tickets`
  - `users`
- Filters/search:
  - status
  - user
  - created date
- Actions:
  - mark resolved
  - reopen
- Permissions:
  - super admin only
- API/backend logic:
  - simple state transitions
- Audit logging:
  - state changes logged
- Scalability:
  - narrow MVP scope is enough

### 6.7 Activity Logs

- Business purpose: preserve accountability for admin actions.
- UI components:
  - chronological table
  - actor, action, target, timestamp, metadata summary
- Tables:
  - `activity_logs`
- Filters/search:
  - actor
  - action type
  - target type
  - date range
- Actions:
  - view only
- Permissions:
  - super admin only
- API/backend logic:
  - append-only writes
- Audit logging:
  - this is the audit log
- Scalability:
  - archive older logs later if needed

### 6.8 CMS And App Configuration

- Business purpose: let non-technical admins edit copy and policy text safely.
- UI components:
  - page editor
  - key-value app settings form
- Tables:
  - `cms_pages`
  - `app_settings`
- Filters/search:
  - page slug
  - section
- Actions:
  - edit content
  - publish/unpublish
- Permissions:
  - super admin only
- API/backend logic:
  - validation around known keys
  - versioning desirable for policy pages
- Audit logging:
  - every publish/update action logged
- Scalability:
  - config should stay content-only, never ops-level secrets

## 7. Database Architecture

### 7.1 Design Principles

- Keep user-owned data separate from canonical geo data
- Model trips as collaboration containers, not just itinerary lists
- Keep shared pool and scheduled itinerary as separate aggregates
- Use soft deletes on user-generated records where recovery or auditability matters
- Use spatial or geo-friendly indexes for map and proximity features
- Add `version` columns to syncable records to support offline conflict detection

### 7.2 Core Tables

#### Identity And Access

`users`

- Columns:
  - `id`
  - `uuid`
  - `name`
  - `email`
  - `password`
  - `status` enum: `active`, `suspended`, `disabled`
  - `email_verified_at`
  - `onboarding_completed_at`
  - `last_seen_at`
  - `last_proximity_prompt_at`
  - `remember_token`
  - timestamps
  - soft deletes
- Why it exists: primary account identity for mobile users.

`user_preferences`

- Columns:
  - `id`
  - `user_id`
  - `distance_unit` enum: `km`, `mi`
  - `map_style` string nullable
  - `default_radius_meters`
  - `notifications_enabled`
  - `offline_auto_sync`
  - `theme` enum nullable
  - timestamps
- Why it exists: isolates mutable preferences from identity.

`user_devices`

- Columns:
  - `id`
  - `user_id`
  - `device_name`
  - `device_platform`
  - `device_identifier_hash`
  - `last_synced_at`
  - `last_ip`
  - timestamps
- Why it exists: device-aware auth, sync, and security visibility.

`admins`

- Columns:
  - `id`
  - `name`
  - `email`
  - `password`
  - `role` enum: `super_admin`
  - `last_login_at`
  - timestamps
- Why it exists: separates web admin authentication from mobile user auth.

#### Billing

`subscription_plans`

- Columns:
  - `id`
  - `code`
  - `name`
  - `provider_product_id`
  - `is_active`
  - `monthly_price`
  - `yearly_price`
  - `features_json`
  - timestamps
- Why it exists: centralized entitlement configuration.

`subscriptions`

- Columns:
  - `id`
  - `user_id`
  - `subscription_plan_id`
  - `provider`
  - `provider_customer_id`
  - `provider_subscription_id`
  - `status` enum: `trialing`, `active`, `grace`, `expired`, `canceled`
  - `renews_at`
  - `expires_at`
  - `raw_payload` json
  - timestamps
- Why it exists: authoritative entitlement state on the backend.

`subscription_events`

- Columns:
  - `id`
  - `user_id`
  - `provider`
  - `event_type`
  - `payload` json
  - `processed_at`
  - timestamps
- Why it exists: webhook audit and replay safety.

#### Imports And AI

`imports`

- Columns:
  - `id`
  - `uuid`
  - `user_id`
  - `source_type` enum: `url`, `text`, `manual`
  - `source_url` nullable
  - `source_host` nullable
  - `raw_text` longText nullable
  - `normalized_text` longText nullable
  - `status` enum: `pending`, `processing`, `awaiting_confirmation`, `manual_review`, `completed`, `failed`, `moderated`
  - `error_code` nullable
  - `error_message` nullable
  - `confidence_score` decimal nullable
  - `processed_at` nullable
  - timestamps
  - soft deletes
- Why it exists: tracks the entire import lifecycle.

`import_candidates`

- Columns:
  - `id`
  - `import_id`
  - `candidate_rank`
  - `place_name`
  - `category` enum nullable
  - `city`
  - `country`
  - `latitude`
  - `longitude`
  - `provider_place_id` nullable
  - `summary` text nullable
  - `confidence_score`
  - `metadata` json nullable
  - `selected_at` nullable
  - timestamps
- Why it exists: supports ambiguity and manual review.

`ai_requests`

- Columns:
  - `id`
  - `user_id` nullable
  - `context_type` enum: `import`, `summary`, `itinerary`, `suggestion`, `balance`
  - `context_id` nullable
  - `provider`
  - `model`
  - `prompt_tokens` nullable
  - `completion_tokens` nullable
  - `status` enum: `pending`, `completed`, `failed`
  - `request_hash`
  - `response_excerpt` text nullable
  - `error_message` nullable
  - timestamps
- Why it exists: observability, cost control, and debugging.

#### Geo And Saved Content

`locations`

- Columns:
  - `id`
  - `uuid`
  - `name`
  - `slug` nullable
  - `category` enum nullable
  - `address_line` nullable
  - `city` nullable
  - `region` nullable
  - `country_code` nullable
  - `postal_code` nullable
  - `latitude`
  - `longitude`
  - `geo_point` point
  - `provider_place_id` nullable
  - `provider_source`
  - `metadata` json nullable
  - `is_moderated_hidden`
  - timestamps
  - soft deletes
- Why it exists: canonical location entity reused across users and trips.

`saved_places`

- Columns:
  - `id`
  - `uuid`
  - `user_id`
  - `location_id`
  - `import_id` nullable
  - `title_override` nullable
  - `notes` text nullable
  - `category` enum: `hotel`, `restaurant`, `activity`, `viewpoint`, `transport`, `shopping`, `other`
  - `region_label` nullable
  - `is_favorite`
  - `visibility` enum: `private`, `trip_shared`
  - `version`
  - timestamps
  - soft deletes
- Why it exists: user-specific ownership, notes, categorization, and sync state.

`tags`

- Columns:
  - `id`
  - `user_id`
  - `name`
  - `slug`
  - timestamps
- Why it exists: user-defined categorization beyond fixed enums.

`saved_place_tags`

- Columns:
  - `saved_place_id`
  - `tag_id`
- Why it exists: many-to-many tagging.

#### Trips And Collaboration

`trips`

- Columns:
  - `id`
  - `uuid`
  - `owner_user_id`
  - `title`
  - `slug`
  - `description` text nullable
  - `start_location_name`
  - `start_latitude` nullable
  - `start_longitude` nullable
  - `end_location_name`
  - `end_latitude` nullable
  - `end_longitude` nullable
  - `start_date`
  - `end_date`
  - `status` enum: `draft`, `active`, `completed`, `archived`
  - `cover_image_url` nullable
  - `version`
  - timestamps
  - soft deletes
- Why it exists: primary planning aggregate.

`trip_members`

- Columns:
  - `id`
  - `trip_id`
  - `user_id`
  - `role` enum: `owner`, `editor`, `viewer`
  - `joined_at`
  - timestamps
- Why it exists: collaboration and authorization.

`trip_invites`

- Columns:
  - `id`
  - `trip_id`
  - `invited_by_user_id`
  - `email` nullable
  - `token`
  - `role` enum: `editor`, `viewer`
  - `status` enum: `pending`, `accepted`, `revoked`, `expired`
  - `expires_at`
  - `accepted_at` nullable
  - timestamps
- Why it exists: invitation lifecycle and auditability.

`trip_places`

- Columns:
  - `id`
  - `uuid`
  - `trip_id`
  - `saved_place_id`
  - `added_by_user_id`
  - `source` enum: `manual`, `saved_place`, `ai_suggestion`
  - `trip_category` enum nullable
  - `notes` text nullable
  - `is_removed`
  - `version`
  - timestamps
  - soft deletes
- Why it exists: shared pool entries separate from scheduling.

`trip_place_hearts`

- Columns:
  - `id`
  - `trip_place_id`
  - `user_id`
  - timestamps
- Why it exists: simple preference signaling without complex voting.

`itinerary_days`

- Columns:
  - `id`
  - `trip_id`
  - `day_number`
  - `date`
  - timestamps
- Why it exists: scheduled trip days.

`itinerary_items`

- Columns:
  - `id`
  - `itinerary_day_id`
  - `trip_place_id`
  - `starts_at` nullable
  - `ends_at` nullable
  - `sort_order`
  - `travel_mode` enum nullable
  - `notes` text nullable
  - `version`
  - timestamps
- Why it exists: actual day-by-day plan, separate from pool.

`trip_ai_runs`

- Columns:
  - `id`
  - `trip_id`
  - `requested_by_user_id`
  - `type` enum: `itinerary`, `suggestions`, `balance`
  - `status` enum: `pending`, `completed`, `failed`
  - `input_hash`
  - `result_payload` json nullable
  - `error_message` nullable
  - timestamps
- Why it exists: AI generation traceability and caching.

`trip_suggestions`

- Columns:
  - `id`
  - `trip_id`
  - `trip_ai_run_id` nullable
  - `location_id` nullable
  - `title`
  - `category` nullable
  - `summary` text nullable
  - `score` decimal nullable
  - `distance_meters` nullable
  - `status` enum: `suggested`, `accepted`, `dismissed`
  - `raw_payload` json nullable
  - timestamps
- Why it exists: suggestion lifecycle and dedupe.

`trip_snapshots`

- Columns:
  - `id`
  - `trip_id`
  - `snapshot_type` enum: `timeline_map`, `cover_summary`
  - `image_url` nullable
  - `summary_payload` json nullable
  - `generated_at`
  - timestamps
- Why it exists: efficient timeline rendering and offline summary cards.

#### Offline And Proximity

`offline_packages`

- Columns:
  - `id`
  - `user_id`
  - `trip_id` nullable
  - `package_scope` enum: `trip`, `region`
  - `scope_reference` string nullable
  - `manifest_version`
  - `status` enum: `queued`, `ready`, `expired`
  - `expires_at` nullable
  - timestamps
- Why it exists: tracks downloadable offline scopes.

`proximity_prompt_logs`

- Columns:
  - `id`
  - `user_id`
  - `saved_place_id` nullable
  - `latitude`
  - `longitude`
  - `distance_meters`
  - `shown_at`
  - `dismissed_at` nullable
  - timestamps
- Why it exists: cooldown logic and light analytics.

#### Support, CMS, Moderation, Audit

`support_tickets`

- Columns:
  - `id`
  - `user_id`
  - `subject`
  - `message`
  - `status` enum: `open`, `resolved`
  - `resolved_by_admin_id` nullable
  - `resolved_at` nullable
  - timestamps
- Why it exists: lightweight user issue tracking.

`moderation_actions`

- Columns:
  - `id`
  - `admin_id`
  - `target_type`
  - `target_id`
  - `action` enum: `hide`, `restore`, `remove`, `suspend_user`, `disable_user`
  - `reason`
  - `metadata` json nullable
  - timestamps
- Why it exists: explicit moderation record beyond generic activity logs.

`activity_logs`

- Columns:
  - `id`
  - `actor_type`
  - `actor_id`
  - `action`
  - `target_type`
  - `target_id`
  - `metadata` json nullable
  - `created_at`
- Why it exists: operational audit trail.

`cms_pages`

- Columns:
  - `id`
  - `slug`
  - `title`
  - `content` longText
  - `is_published`
  - `published_at` nullable
  - timestamps
- Why it exists: policy/help/information content.

`app_settings`

- Columns:
  - `id`
  - `key`
  - `value` json
  - `group_name`
  - timestamps
- Why it exists: safe, non-technical configuration storage.

### 7.3 Key Relationships

- user has one preferences row
- user has many saved places
- location has many saved places
- import belongs to user and may produce many candidates
- saved place belongs to one import optionally
- trip belongs to one owner
- trip has many members
- trip has many pool items
- trip has many itinerary days
- itinerary day has many itinerary items
- trip place may receive many hearts
- trip has many AI runs and suggestions

### 7.4 Required Indexes

- `users.email` unique
- `users.status`
- `imports.user_id, status, created_at`
- `imports.source_host`
- `locations.geo_point` spatial index
- `locations.name`
- `saved_places.user_id, category, created_at`
- `saved_places.location_id`
- `saved_places.version`
- `trips.owner_user_id, start_date`
- `trip_members.trip_id, user_id` unique
- `trip_invites.token` unique
- `trip_places.trip_id, deleted_at`
- `trip_place_hearts.trip_place_id, user_id` unique
- `itinerary_items.itinerary_day_id, sort_order`
- `trip_suggestions.trip_id, status`
- `proximity_prompt_logs.user_id, shown_at`
- `support_tickets.status, created_at`
- `activity_logs.target_type, target_id, created_at`

## 8. API Architecture

### 8.1 Conventions

- Base path: `/api/v1`
- Auth:
  - mobile: Sanctum bearer token
  - admin web: session + CSRF
- Response envelope:

```json
{
  "success": true,
  "message": "Saved place created.",
  "data": {},
  "meta": {},
  "errors": []
}
```

- Validation failure:

```json
{
  "success": false,
  "message": "Validation failed.",
  "data": null,
  "meta": {},
  "errors": {
    "email": ["The email has already been taken."]
  }
}
```

- Pagination style:

```json
{
  "success": true,
  "message": null,
  "data": [],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 143,
    "last_page": 8
  },
  "errors": []
}
```

### 8.2 Mobile Auth Endpoints

- `POST /auth/register`
- `POST /auth/login`
- `POST /auth/logout`
- `POST /auth/forgot-password`
- `POST /auth/reset-password`
- `GET /me`
- `PATCH /me`

### 8.3 Onboarding And Preferences

- `GET /onboarding`
- `PUT /onboarding`
- `GET /settings`
- `PATCH /settings`

### 8.4 Dashboard, Search, And Map

- `GET /dashboard`
  - returns:
    - profile summary
    - recent trips
    - recent saved places
    - empty-state flags
    - proximity prompt payload when applicable
- `GET /map/pins?bounds=...&filters[...]`
- `GET /saved-places/search?q=...`
- `POST /proximity/check`

Example request:

```json
{
  "latitude": 35.6895,
  "longitude": 139.6917,
  "radius_meters": 3000
}
```

### 8.5 Imports

- `POST /imports`
- `GET /imports/{id}`
- `POST /imports/{id}/retry`
- `POST /imports/{id}/confirm`
- `PATCH /imports/{id}/manual-override`

Create import payload:

```json
{
  "source_type": "url",
  "source_url": "https://example.com/post/123"
}
```

Manual override payload:

```json
{
  "candidate": {
    "place_name": "Senso-ji Temple",
    "category": "activity",
    "city": "Tokyo",
    "country": "JP",
    "latitude": 35.7148,
    "longitude": 139.7967,
    "summary": "Historic temple district with strong visitor appeal."
  }
}
```

### 8.6 Saved Places

- `GET /saved-places`
- `POST /saved-places`
- `GET /saved-places/{id}`
- `PATCH /saved-places/{id}`
- `DELETE /saved-places/{id}`
- `POST /saved-places/{id}/tags`
- `DELETE /saved-places/{id}/tags/{tag}`

Query examples:

- `GET /saved-places?filter[category]=restaurant`
- `GET /saved-places?filter[tag]=wishlist`
- `GET /saved-places?sort=-created_at`

### 8.7 Trips And Collaboration

- `GET /trips`
- `POST /trips`
- `GET /trips/{id}`
- `PATCH /trips/{id}`
- `DELETE /trips/{id}`
- `POST /trips/{id}/invites`
- `POST /trip-invites/{token}/accept`
- `PATCH /trips/{id}/members/{memberId}`
- `DELETE /trips/{id}/members/{memberId}`
- `GET /trips/{id}/pool`
- `POST /trips/{id}/pool`
- `PATCH /trips/{id}/pool/{tripPlaceId}`
- `DELETE /trips/{id}/pool/{tripPlaceId}`
- `POST /trips/{id}/pool/{tripPlaceId}/heart`
- `DELETE /trips/{id}/pool/{tripPlaceId}/heart`

Create trip payload:

```json
{
  "title": "Japan Spring 2027",
  "start_location_name": "Tokyo",
  "end_location_name": "Kyoto",
  "start_date": "2027-03-10",
  "end_date": "2027-03-18"
}
```

Invite payload:

```json
{
  "email": "friend@example.com",
  "role": "editor"
}
```

### 8.8 Itinerary

- `GET /trips/{id}/itinerary`
- `POST /trips/{id}/itinerary/days`
- `PUT /trips/{id}/itinerary/reorder`
- `POST /trips/{id}/itinerary/items`
- `PATCH /trips/{id}/itinerary/items/{itemId}`
- `DELETE /trips/{id}/itinerary/items/{itemId}`

Reorder payload:

```json
{
  "version": 12,
  "days": [
    {
      "day_id": 101,
      "items": [
        { "trip_place_id": 501, "sort_order": 1, "starts_at": "2027-03-10T10:30:00Z" },
        { "trip_place_id": 502, "sort_order": 2, "starts_at": "2027-03-10T13:00:00Z" }
      ]
    }
  ]
}
```

### 8.9 AI Features

- `POST /trips/{id}/ai-itinerary/generate`
- `GET /trips/{id}/ai-itinerary`
- `POST /trips/{id}/ai-itinerary/apply`
- `GET /trips/{id}/suggestions`
- `POST /trips/{id}/suggestions/generate`
- `POST /trips/{id}/suggestions/{suggestionId}/add`
- `POST /trips/{id}/suggestions/{suggestionId}/dismiss`
- `GET /trips/{id}/balance`

### 8.10 Timeline And Offline

- `GET /timeline`
- `GET /timeline/{tripId}`
- `GET /offline/packages`
- `POST /offline/packages/trips/{tripId}`
- `GET /sync?cursor=...`
- `POST /sync/push`

### 8.11 Subscription And Support

- `GET /plans`
- `GET /subscription`
- `POST /subscription/restore`
- `POST /support-tickets`
- `GET /support-tickets`

### 8.12 Admin JSON Endpoints

If the admin uses Vue-enhanced tables inside Blade, expose web-guarded JSON routes under `/admin/api/v1`:

- `/admin/api/v1/dashboard`
- `/admin/api/v1/users`
- `/admin/api/v1/users/{id}`
- `/admin/api/v1/users/{id}/status`
- `/admin/api/v1/imports`
- `/admin/api/v1/imports/{id}`
- `/admin/api/v1/imports/{id}/retry`
- `/admin/api/v1/locations`
- `/admin/api/v1/locations/{id}/moderate`
- `/admin/api/v1/trips`
- `/admin/api/v1/support-tickets`
- `/admin/api/v1/support-tickets/{id}`
- `/admin/api/v1/activity-logs`
- `/admin/api/v1/cms-pages`
- `/admin/api/v1/app-settings`

### 8.13 Validation And Security Rules

- Use FormRequests for every mutating endpoint
- Use policies for `SavedPlace`, `Trip`, `TripMember`, `TripPlace`, `ItineraryItem`
- Use rate limits:
  - auth
  - imports
  - proximity checks
  - AI generation
- Return `409 Conflict` for sync/version mismatches
- Use cursor or page pagination for high-volume admin lists

## 9. AI And NLP System Analysis

### 9.1 Import Processing Pipeline

Recommended job chain:

1. `CreateImportRecord`
2. `FetchImportSourceContentJob`
3. `NormalizeImportTextJob`
4. `ExtractPlaceCandidatesJob`
5. `ResolveCandidateCoordinatesJob`
6. `GeneratePlaceSummaryJob`
7. `ScoreAndFinalizeImportJob`

### 9.2 Extraction Strategy

For reliable behavior, the extraction layer should request structured output only:

- place name
- category
- city
- country
- summary
- confidence
- reason

The system should not trust freeform natural language output.

### 9.3 Geolocation Extraction

Flow:

- use detected city/country/place name
- query geocoder
- score ambiguity
- if ambiguity remains, create multiple candidates
- force manual review rather than silently picking the wrong place

### 9.4 AI Summaries

Summaries should be short, factual, and non-hallucinatory:

- 1 to 3 lines max in app UI
- source-grounded
- no invented opening hours or pricing

### 9.5 Suggestions Engine

Trip suggestions should consider:

- trip start/end
- existing place categories
- empty category gaps
- route corridor or destination-area clustering
- duplicate detection against existing saved places and trip places

### 9.6 Queue Architecture

Suggested queues:

- `imports`
- `ai`
- `geocoding`
- `snapshots`
- `emails`
- `default`

### 9.7 Retry Handling

- Retry fetch failures separately from AI failures
- Retry AI failures with capped attempts
- Store substep state so the pipeline can resume, not restart blindly
- Permanent failures should land in `manual_review` or `failed`

### 9.8 Failure Handling

Failure classes:

- invalid source
- fetch blocked
- extraction ambiguous
- no location found
- geocoder failure
- provider timeout

User-facing handling:

- actionable message
- retry option when meaningful
- manual override when possible

### 9.9 Manual Override Logic

Manual override is a first-class system path, not a fallback patch.

Rules:

- user can edit extracted place name, category, and coordinates
- original import data remains preserved for auditability
- manual values become the selected candidate
- downstream saved place generation uses manual values

## 10. Offline System Analysis

### 10.1 Offline Map Storage

Recommended approach:

- use map provider offline packs on device
- backend stores package manifests and entitlement limits
- backend does not serve tile blobs directly unless later required

### 10.2 Cached Trip Data

Cache these locally:

- trip metadata
- member roles
- trip pool
- itinerary days/items
- saved place essentials
- summaries
- static snapshots

### 10.3 Sync Strategy

Recommended sync model:

- pull by `cursor` or `updated_since`
- push local mutations with `version`
- server rejects stale writes with `409`
- client prompts refresh/merge

### 10.4 Conflict Handling

Different entities need different rules:

- user preferences: last write wins is acceptable
- trip pool edits: version check + simple retry
- itinerary edits: owner-only significantly reduces conflict surface
- deleted records: soft-delete markers must sync before hard cleanup

### 10.5 Offline Downloads Architecture

- device requests offline package for trip/region
- backend returns manifest version and resource set
- client stores downloaded artifact references
- entitlement and expiry rules enforced on next sync

## 11. Security Analysis

### 11.1 Sanctum Auth

- mobile tokens should be personal access tokens
- revoke all tokens on account disable
- rotate tokens when needed

### 11.2 Admin Guard Separation

- admin should use its own session guard and table
- do not reuse mobile tokens for admin access

### 11.3 Policies

Required policies:

- `SavedPlacePolicy`
- `TripPolicy`
- `TripMemberPolicy`
- `TripPlacePolicy`
- `ItineraryItemPolicy`
- `SupportTicketPolicy`

### 11.4 Rate Limiting

- login and forgot password
- import submission
- AI itinerary generation
- suggestion generation
- proximity checks

### 11.5 Validation

- FormRequests on all writes
- enums/constants for statuses and roles
- strict URL validation
- bounds validation for geo queries

### 11.6 XSS Protection

- escape all Blade output
- sanitize CMS rich text if rich text is allowed
- do not trust AI-generated text as safe HTML

### 11.7 SQL Injection Prevention

- Eloquent and query builder only
- no string-built raw SQL except carefully parameterized geo operations

### 11.8 File And URL Handling

- if fetching remote URLs, block private network ranges and file schemes
- never allow arbitrary server-side browsing
- strip tracking params where sensible for storage cleanliness

### 11.9 Queue Security

- avoid putting secrets or full PII in plain queue payloads
- use IDs and rehydrate from database inside jobs
- ensure failed-job logs do not leak raw prompts or private data unnecessarily

## 12. Scalability Analysis

### 12.1 Redis And Horizon

- queue all slow work
- separate AI/import jobs from mail/default
- monitor throughput and failures with Horizon

### 12.2 API Scaling

- stateless mobile API behind load balancer
- Redis-backed rate limiting and cache
- no sticky sessions required for mobile API

### 12.3 Database Indexing

- spatial indexes for geo search
- compound indexes for user-scoped lists
- status/date indexes for admin operations

### 12.4 Caching Strategy

Cache:

- dashboard summary
- recent trips
- active subscription entitlement
- admin dashboard counters
- latest trip balance summary

Do not over-cache user-owned mutable trip details without version awareness.

### 12.5 AI Request Optimization

- dedupe prompt runs by hashing normalized inputs
- cache last generated suggestions per trip version
- keep prompts short and structured
- log token usage

### 12.6 Background Jobs

- imports, suggestions, itinerary generation, snapshots, mail
- use job batching only where it truly improves observability

### 12.7 Search Optimization

MVP:

- MySQL full-text for names/notes/tags where useful
- prefix search on place names

Later:

- Meilisearch or Algolia for richer place and admin search

## 13. Technical Implementation Plan

### 13.1 Recommended Folder Structure

```text
app/
  Actions/
    Auth/
    Imports/
    Trips/
    Billing/
  Enums/
  Events/
  Exceptions/
  Http/
    Controllers/
      Api/
        V1/
          Auth/
          Dashboard/
          Imports/
          SavedPlaces/
          Trips/
          Timeline/
          Offline/
          Billing/
          Support/
      Admin/
        Auth/
        Dashboard/
        Users/
        Imports/
        Locations/
        Trips/
        Support/
        CMS/
    Middleware/
    Requests/
      Api/
      Admin/
    Resources/
      Api/
      Admin/
  Jobs/
    Imports/
    AI/
    Offline/
    Snapshots/
    Notifications/
  Listeners/
  Mail/
  Models/
  Notifications/
  Observers/
  Policies/
  Providers/
  Repositories/
    Contracts/
    Eloquent/
  Rules/
  Services/
    AI/
    Auth/
    Billing/
    Geo/
    Imports/
    Maps/
    Offline/
    Trips/
    Support/
  Support/
    Helpers/
    Pagination/
    Responses/
    Sync/
bootstrap/
config/
database/
  factories/
  migrations/
  seeders/
resources/
  css/
    admin.css
    app.css
  js/
    admin/
      app.js
      components/
      composables/
      pages/
      stores/
      utils/
    shared/
      components/
      constants/
      helpers/
resources/views/
  admin/
  components/
  layouts/
routes/
  api.php
  web.php
tests/
  Feature/
  Unit/
```

### 13.2 Laravel Architecture

Recommended style:

- Controllers stay thin
- FormRequests own validation
- Services own business workflows
- Repositories wrap complex data access, not trivial model calls
- API Resources standardize output
- Policies centralize permissions
- Enums represent statuses and roles
- Domain events fire on important state changes

### 13.3 Service Structure

Recommended services:

- `AuthService`
- `ImportService`
- `ImportExtractionService`
- `GeoResolutionService`
- `SavedPlaceService`
- `TripService`
- `TripInviteService`
- `TripPoolService`
- `ItineraryService`
- `TripSuggestionService`
- `TripBalanceService`
- `OfflinePackageService`
- `ProximityService`
- `SubscriptionService`
- `SupportTicketService`
- `CMSService`

### 13.4 Repository Structure

Use repositories for aggregates with complex queries:

- `SavedPlaceRepository`
- `TripRepository`
- `ImportRepository`
- `LocationRepository`
- `SupportTicketRepository`
- `AdminDashboardRepository`

Avoid repository bloat for simple CRUD on tiny admin config models.

### 13.5 Vue Component Structure

Admin Vue should focus on interaction-heavy pieces:

- `DataTable.vue`
- `FilterBar.vue`
- `MetricCard.vue`
- `StatusBadge.vue`
- `ConfirmModal.vue`
- `DrawerPanel.vue`
- `PaginationBar.vue`
- `ActivityTimeline.vue`
- `TripMemberManager.vue`
- `ImportDetailPanel.vue`
- `CMSPageEditor.vue`

Recommended admin page grouping:

- `pages/dashboard`
- `pages/users`
- `pages/imports`
- `pages/locations`
- `pages/trips`
- `pages/support`
- `pages/settings`

### 13.6 Tailwind Structure

- Define tokens in CSS variables
- Extend theme for brand colors, spacing, radius, shadows
- Create component classes for repeated admin UI:
  - cards
  - tables
  - pills
  - form controls
  - modal shells

### 13.7 Queue And Job Structure

Recommended jobs:

- `FetchImportSourceContentJob`
- `NormalizeImportTextJob`
- `ExtractPlaceCandidatesJob`
- `ResolveCandidateCoordinatesJob`
- `GeneratePlaceSummaryJob`
- `FinalizeImportJob`
- `GenerateTripItineraryJob`
- `GenerateTripSuggestionsJob`
- `GenerateTripSnapshotJob`
- `ProcessSubscriptionWebhookJob`
- `SendTripInviteJob`

### 13.8 Seeder Strategy

Seeders should be realistic, not toy data.

Seed:

- super admin account
- subscription plans
- CMS starter pages
- app settings
- demo users
- demo saved places in multiple countries
- demo imports with mixed statuses
- demo trips with collaborators
- demo support tickets
- demo moderation logs

### 13.9 Deployment Structure

Recommended runtime layout:

- web nodes for Laravel app
- Redis for cache and queues
- Horizon worker nodes
- MySQL primary
- scheduler/cron for Laravel schedule
- object storage for snapshots if used

Key operational pieces:

- Horizon supervisor
- queue retry policy
- health checks
- error monitoring
- structured logs

### 13.10 Environment Variables

```text
APP_NAME
APP_ENV
APP_KEY
APP_DEBUG
APP_URL

DB_CONNECTION
DB_HOST
DB_PORT
DB_DATABASE
DB_USERNAME
DB_PASSWORD

CACHE_STORE
QUEUE_CONNECTION
REDIS_HOST
REDIS_PASSWORD
REDIS_PORT

MAIL_MAILER
MAIL_HOST
MAIL_PORT
MAIL_USERNAME
MAIL_PASSWORD
MAIL_FROM_ADDRESS
MAIL_FROM_NAME

SANCTUM_STATEFUL_DOMAINS
SESSION_DRIVER
SESSION_DOMAIN

MAP_PROVIDER
MAPBOX_ACCESS_TOKEN
MAPBOX_SECRET
MAPBOX_STYLE_ID

GEOCODER_PROVIDER
GEOCODER_API_KEY

AI_PROVIDER
AI_API_KEY
AI_MODEL_EXTRACTION
AI_MODEL_SUMMARY
AI_MODEL_ITINERARY
AI_MODEL_SUGGESTIONS

BILLING_PROVIDER
REVENUECAT_API_KEY
REVENUECAT_WEBHOOK_SECRET

OFFLINE_PACKAGE_TTL_DAYS
PROXIMITY_DEFAULT_RADIUS_METERS
PROXIMITY_COOLDOWN_MINUTES

IMPORT_MAX_TEXT_LENGTH
IMPORT_ALLOWED_HOSTS

LOG_CHANNEL
SENTRY_DSN
```

## 14. Recommended Implementation Order

The safest build order is:

1. Foundation
   - Laravel install
   - auth guards
   - enums
   - response helpers
   - admin shell
2. Identity
   - mobile auth
   - onboarding
   - profile/preferences
3. Geo And Saved Content
   - locations
   - saved places
   - map endpoints
4. Import Pipeline
   - import records
   - async extraction
   - manual review
5. Trips
   - trip CRUD
   - members/invites
   - shared pool
6. Itinerary
   - days/items
   - reorder rules
7. AI Features
   - summaries
   - balance indicator
   - suggestions
   - itinerary generation
8. Offline And Proximity
   - packages
   - sync
   - proximity checks
9. Billing
   - plans
   - entitlements
   - paywall integration
10. Admin
   - dashboard
   - users
   - imports
   - moderation
   - support
   - CMS

## 15. Final Architectural Position

This product is not primarily a map app and not primarily a social app. It is an ingestion-and-planning system.

The architecture should therefore optimize for:

- extraction reliability
- map-friendly geo modeling
- clean separation of pool vs itinerary
- owner-safe collaboration
- offline-ready sync semantics
- auditable moderation
- provider-agnostic AI and maps services

If those foundations are implemented cleanly, the later UI and feature expansions will stay manageable.
