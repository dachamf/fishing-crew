# Fishing Crew API & Frontend

A Laravel-based backend with a companion frontend for managing fishing crews, profiles, and groups. This repository contains the API (Laravel) and a frontend app directory.

Current date: 2026-02-07

## Features
- User registration, login, logout (Laravel Sanctum token auth)
- Password reset flow (forgot password + reset via email link)
- Email verification flow with deep-link redirect to frontend
- Profile management with avatars (S3 upload)
- Groups management with roles (owner/moderator/member) and admin visibility
- Events with RSVP, check-in, photos
- Fishing Sessions (open/close/finalize)
- Catches with photos (EXIF extraction, image variants)
- Session & Catch confirmations (nominate, approve/reject, withdraw decision)
- Leaderboard (seasonal rankings)
- Achievements (3 badges)
- Activity feed & notifications
- Weather integration (OpenMeteo API)
- Dashboard with aggregated data
- Dark theme support (full CSS overhaul, logo inversion, responsive components)
- SSR authentication with header forwarding

## Tech Stack
- PHP 8.2+, Laravel 12
- MySQL (prod), SQLite (dev)
- Nuxt 3 (4.0.3), Vue 3.5, TypeScript, Tailwind v4, DaisyUI v5
- Laravel Sanctum for token-based API authentication
- AWS S3 for media storage
- MapLibre GL for maps, Chart.js for charts

## Prerequisites
- PHP 8.2+
- Composer 2+
- Node.js 18+ and PNPM
- A running database (MySQL/SQLite)

## Getting Started (Backend)
1. Clone and install dependencies
   - composer install
2. Environment
   - cp .env.example .env
   - Set APP_URL (e.g., http://localhost:8000)
   - Set FRONTEND_URL (e.g., http://localhost:3000)
   - Configure DB_* and MAIL_* variables
   - If using Sanctum with SPA, configure SANCTUM_STATEFUL_DOMAINS and SESSION_DOMAIN accordingly
3. App key
   - php artisan key:generate
4. Database
   - php artisan migrate
   - Optionally: php artisan db:seed
5. Serve the API
   - php artisan serve

## Getting Started (Frontend)
There is a frontendApp directory in the repo. If you are using it:
1. cd frontendApp
2. pnpm install (or npm install)
3. pnpm run dev (or npm run dev)
4. Ensure the dev server URL matches FRONTEND_URL in the backend .env

The repository also contains a root-level package.json and node_modules, which may be used for asset building if the frontend is colocated. Prefer frontendApp for SPA if present.

## DDEV Development

This project uses DDEV for local development. Both backend and frontend run inside the DDEV container.

### URLs
- **API**: https://api.fishermen-crew.ddev.site
- **Frontend**: https://app.fishermen-crew.ddev.site:3000

### Quick Start with DDEV
```bash
ddev start
ddev fe install    # Install frontend dependencies
ddev fe dev        # Start Nuxt dev server
```

### Frontend Commands (`ddev fe`)
| Command | Alias | Description |
|---------|-------|-------------|
| `ddev fe install` | `ddev fe i` | Install pnpm dependencies |
| `ddev fe dev` | `ddev fe d` | Start Nuxt dev server with HMR |
| `ddev fe build` | `ddev fe b` | Build for production |
| `ddev fe lint` | `ddev fe l` | Run ESLint |
| `ddev fe lint:fix` | `ddev fe lf` | Run ESLint with --fix |
| `ddev fe type-check` | `ddev fe tc` | Run TypeScript type check |

### Alternative: Direct Nuxt Command
```bash
ddev nuxt          # Start dev server
ddev nuxt build    # Production build
ddev nuxt generate # Generate static site
```

### View Logs
```bash
ddev logs -f       # All logs
```

## Important Environment Variables
- FRONTEND_URL: Used for redirect after email verification
  - Defaults: config/app.php sets frontend_url to FRONTEND_URL or http://localhost:3000
- APP_URL: Backend base URL
- MAIL_*: Configure mail transport so verification emails can be sent
- SANCTUM_STATEFUL_DOMAINS and SESSION_DOMAIN: Required for SPA auth with Sanctum

## Email Verification Flow
- Verification link endpoint: GET /api/auth/verify-email/{id}/{hash}
  - Controller: app/Http/Controllers/api/Auth/EmailVerificationController.php
  - On success, redirects to: FRONTEND_URL/verify?status=success
  - If already verified: FRONTEND_URL/verify?status=already-verified
  - Invalid link returns a 403 JSON response
- Resend verification notification: POST /api/auth/email/verification-notification (auth:sanctum)

## Authentication
- Uses Laravel Sanctum with HttpOnly cookie-based token auth
- Cookie domain resolved dynamically (supports cross-subdomain: `api.fishingcrew.app` ↔ `fishingcrew.app`)
- SSR-compatible: headers (cookie, origin, referer) forwarded during server-side rendering
- Typical flow:
  - Login: POST /api/auth/login → sets HttpOnly auth cookie
  - Authenticated API calls include the cookie automatically
  - Logout: POST /api/auth/logout → clears cookie and revokes token

## Password Reset Flow
- Forgot password: POST /api/auth/forgot-password (email) → sends reset link email
- Reset link points to frontend: `/reset-password?token=...&email=...`
- Reset: POST /api/auth/reset-password (token, email, password, password_confirmation)
- Both endpoints are rate-limited
- Frontend pages: `/forgotPassword` and `/resetPassword`

## Useful Commands

### Backend (Laravel)
```bash
ddev artisan test           # Run tests
ddev artisan migrate        # Run migrations
ddev artisan db:seed        # Seed database
ddev artisan optimize:clear # Clear caches
ddev composer require ...   # Add packages
```

### Frontend (Nuxt)
```bash
ddev fe dev        # Dev server with HMR
ddev fe build      # Production build
ddev fe lint:fix   # Lint and fix
ddev fe type-check # TypeScript check
```

## Project Structure Highlights
- app/Http/Controllers/api/Auth/*: Auth controllers (Register, Login, Logout, Email Verification)
- app/Http/Controllers/api/v1/*: Domain controllers (23 total)
- routes/api/v1/*: Route files by domain (core, sessions, catches, groups_events, stats_leaderboard, profile_account, species)
- app/Models/*: 14 Eloquent models
- app/Services/*: Business logic (PhotoProcessor, SessionReviewService, Weather)
- config/app.php: frontend_url configuration
- frontendApp/: Nuxt 3 SPA
  - pages/: 27 routes
  - components/: 42 components
  - composables/: 32 composables (data fetching, auth, UI)

## Tasks and Roadmap
See TASKS.md for a prioritized, actionable task list across backend, auth/security, domain features, testing/QA, DX, and docs.

## Contributing
PRs are welcome. Please:
- Follow PSR-12 coding standards
- Add tests when possible
- Update documentation as needed

## License
This project is open-sourced software licensed under the MIT license.
