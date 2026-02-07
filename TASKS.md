# Fishing Crew — Project Task List (2026-02-07)

This document outlines a prioritized, actionable task list based on the current repository analysis. It covers backend/API, authentication & security, media/storage, domain features, testing & QA, developer experience, and documentation.

Note: The list aims for pragmatic increments. Items are grouped by priority: P0 (critical/bugs), P1 (high value), P2 (nice-to-have/roadmap).

## Updates (2026-02-07)

- **Password Reset Flow (new)**:
  - Added `ForgotPasswordController` and `ResetPasswordController` (backend)
  - Routes: `POST /auth/forgot-password`, `POST /auth/reset-password` (rate-limited)
  - Reset URL customized in `AppServiceProvider` to point to Nuxt frontend
  - Frontend: new `forgotPassword.vue` and `resetPassword.vue` pages (public, Serbian UI)
  - Login page now links to "Zaboravljena lozinka" via NuxtLink
- **SSR Authentication (fixed)**:
  - Axios plugin now forwards `cookie`, `origin`, `referer` headers during SSR
  - Season session table components also forward SSR headers for authenticated data fetches
  - Auth middleware differentiates SSR vs CSR error handling (avoids false login redirects during SSR)
  - Added `ready` state in `useAuth()` to prevent race conditions with Axios 401 interceptor
- **Cookie Domain Resolution (fixed)**:
  - Login/Logout controllers now dynamically resolve cookie domain from config/request instead of hardcoded `null`
  - Handles cross-subdomain cookies (e.g., `api.fishingcrew.app` ↔ `fishingcrew.app`)
- **Dark Theme Overhaul**:
  - Extensive dark mode CSS for cards, modals, tables, inputs, alerts, badges, buttons
  - Logo inversion filter (`.app-logo` class) for dark backgrounds
  - Body and main layout now apply `bg-base-200 text-base-content` for consistent theming
- **Profile Menu UX**:
  - Explicit open/close state with `onClickOutside` and Escape key support
  - User display name is now a clickable link to profile (removed separate "Profil" menu item)
- **Responsive UI**:
  - Dashboard button groups stack vertically on mobile (`join-vertical sm:join-horizontal`)
  - Skeleton/photo grids use responsive breakpoints (`grid-cols-1 sm:grid-cols-2 lg:grid-cols-3`)
- **Code Cleanup**:
  - Removed `PhotoController` (photos served from S3 directly)
  - Removed 3 obsolete mailables (`SessionFinalizedMail`, `SessionReviewActionMail`, `SessionReviewRequestMail`)
  - Removed `ProfilePolicy` (unused)
  - Removed `rsvpConfirmDialog.vue` (HeadlessUI → moving to Radix Vue)
  - Removed `CatchesController::byEvent()` and `::mine()` endpoints
  - Cleaned unused imports across 7+ files

## Updates (2026-02-06)

- Fixed API base config for frontend dev and guarded notifications polling to only run when logged in.
- Auth/logout: fixed token deletion (Sanctum currentAccessToken) to avoid 500s on logout.
- Catch confirmations:
  - Added missing routes, fixed authorization, and added nomination notification.
  - Added assigned catch list UI and wiring for "assigned to me".
  - Added "withdraw decision" flow (API + UI) and status lock after decision.
- Session confirmations:
  - Fixed close-and-nominate to use session confirmations (new flow) + notifications.
  - Added withdraw decision for session confirmations.
  - Fixed assigned list/notification bell to show pending catches (not sessions).
- Routing fixes:
  - Added redirect `/sessions/assigned-to-me` → `/sessions/assigned`.
  - Fixed NaN route errors from invalid nested route match.
- Groups:
  - Admins can list all groups; non-admins see only their groups.
  - Group members endpoint returns profile + role; UI pages for group list, detail, and members.
  - Members list styled + search, with current user subtly highlighted.
- Misc:
  - Switched Vite CSS transformer to `postcss` to suppress `@property` warning.


## P0 — Critical fixes and correctness

- Fix User model relation typo
  - In app/Models/User.php, method `profle()` should be `profile()` to match usage elsewhere (e.g., controllers/resources). This likely breaks `$user->profile` access lazily in multiple places.
- Verify email verification redirect base URL
  - EmailVerificationController::redirectToApp uses `config('app.frontend_url') ?? env('FRONTEND_URL', 'https://app.fishermen-crew.ddev.site')`. Ensure default aligns with config/app.php (defaults to http://localhost:3000) and remove inconsistent fallback URL or document it. Prefer a single source of truth.
- Sanctum/SPA auth configuration sanity check
  - Ensure .env contains correct SANCTUM_STATEFUL_DOMAINS and SESSION_DOMAIN per intended deployment; add example values to .env.example.
- Storage disk readiness for avatars (S3)
  - ProfileController assumes `s3` disk; ensure `filesystems.php` has `s3` configured and env vars present. Provide local fallback (e.g., `public` disk) in non-prod or document requirement.


## P1 — High-priority enhancements

- Authorization and Policy coverage
  - ~~ProfilePolicy removed (was unused)~~. Ensure profile update, avatar upload/delete, group/event operations respect authorization beyond membership checks (e.g., Gate/Policies where applicable). Consider adding targeted policy checks where needed.
- Validation improvements and consistency
  - EventsController@store: `location_geo` validated as array but not used; either remove or persist properly.
  - Events latitude/longitude: ensure consistent source of truth (if using location_geo). Consider custom rule to keep lat/lon within range.
  - CatchesController@store: consider stricter numeric validations (precision/scale) and require species where domain needs it.
- Rate limiting
  - Add throttling to sensitive endpoints (login, register, resend verification already has throttle; consider invite, catches confirm, events actions).
- Pagination defaults and query filters
  - Index endpoints return paginated data with page size 20; consider allowing `per_page` with caps and add additional filters (date ranges, status).
- Consistent JSON response shape
  - Standardize responses (e.g., use Resources for groups, events, catches) for consistency across clients.


## P2 — Domain features and UX

- Group invitations flow
  - Implement notifications or in-app messages when a user is invited (TODO noted in GroupsController@invite).
- Event postpone voting
  - Flesh out proposePostpone and votePostpone: persistence model for subjects/votes, thresholds, and state transition to postponed with audit trail.
- Catch confirmations and scoring
  - Implement scoring recalculation and notifications hooks (TODOs in CatchesController), and consider majority/threshold rules.
- Public profiles
  - Define and enforce a sanitized public view (ProfileController@showPublic has a note). Add privacy settings in Profile.settings to control exposure.


## Testing and QA

- Unit and feature tests
  - Auth: register/login/logout, email verification link validity, resend throttle, /auth/me endpoint.
  - Profiles: me, update, avatar upload/delete including S3 interactions (mock Storage).
  - Groups: CRUD, members listing order, invite rules (owner/moderator), membership checks.
  - Events: index/store/show, RSVP, check-in, and edge cases (invalid lat/lon, dates in past if not allowed).
  - Catches: create, confirm rules (no self-confirm, same group, status transitions), listByUser and listByAll summaries.
- Factories/Seeders
  - Ensure factories exist and are used in tests (GroupFactory, EventFactory present). Add missing factories (Profile, FishingCatch, User already standard). Create seeders for local dev data.


## Developer Experience & CI/CD

- .env.example completeness
  - Add FRONTEND_URL, SANCTUM_STATEFUL_DOMAINS, SESSION_DOMAIN, S3 keys, MAIL_* with sensible placeholders.
- Pint, PHPStan/Psalm
  - Enforce code style (vendor/bin/pint exists), consider adding PHPStan with baseline. Add composer scripts.
- GitHub Actions CI
  - Workflow for tests, pint, (optional) PHPStan, and frontend build if applicable.
- Local Dev
  - Optional Docker/Docker Compose for PHP+DB+Mailhog+MinIO; or document Laravel Sail usage.


## Documentation

- Expand README with API reference overview
  - Summaries of key endpoints under /api/auth and /api/v1, auth requirements, example requests/responses.
- Email verification flow details
  - Clarify link formation (signed routes), typical client behavior, and frontend deep-link handling.
- Storage configuration
  - Document S3 vs local, how avatar URLs are generated (temporaryUrl fallback), and CDN considerations.


## Known Code Smells / Cleanups

- Remove unused imports and dead code
  - Controllers occasionally import things not used; tidy for clarity.
- Consistent language
  - Mix of English/Serbian comments/strings. Decide on one for codebase consistency.
- Resource usage for all entities
  - Prefer JSON Resources over raw models for API responses to control output.


## Quick Wins (small PRs)

- Fix `User::profile()` typo (P0)
- Align EmailVerificationController fallback URL with config/app.php (P0)
- Add throttle to /api/auth/login (e.g., throttle:login) and register (P1)
- Add per_page query param with clamp to 100 for index endpoints (P1)
- Add README link to TASKS.md (P2)
