# Fishing Crew API & Frontend

A Laravel-based backend with a companion frontend for managing fishing crews, profiles, and groups. This repository contains the API (Laravel) and a frontend app directory.

Current date: 2025-08-20

## Features
- User registration, login, logout (Laravel Sanctum)
- Email verification flow with deep-link redirect to frontend
- Profile management (v1 API)
- Groups management (v1 API)

## Tech Stack
- PHP 8.x, Laravel 11.x
- MySQL or PostgreSQL
- Node.js 18+ with Vite for frontend assets
- Laravel Sanctum for SPA/API authentication

## Prerequisites
- PHP 8.2+
- Composer 2+
- Node.js 18+ and PNPM or NPM
- A running database (MySQL/PostgreSQL/SQLite)

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
- Uses Laravel Sanctum
- Ensure SPA hosts are configured in sanctum.php and your .env
- Typical flow:
  - CSRF cookie (if using session-based SPA): GET /sanctum/csrf-cookie
  - Login: POST /login (or the configured route in routes/api.php or routes/web.php)
  - Authenticated API calls include session or token as configured

## Useful Commands
- Run tests: php artisan test
- Lint/format (if configured): vendor/bin/pint
- Clear caches: php artisan optimize:clear

## Project Structure Highlights
- app/Http/Controllers/api/Auth/*: Auth controllers (Register, Login, Logout, Email Verification)
- app/Http/Controllers/api/v1/*: Domain APIs (Profile, Groups)
- routes/api.php: API routes
- config/app.php: frontend_url configuration
- frontendApp/: Frontend SPA (if used)

## Tasks and Roadmap
See TASKS.md for a prioritized, actionable task list across backend, auth/security, domain features, testing/QA, DX, and docs.

## Contributing
PRs are welcome. Please:
- Follow PSR-12 coding standards
- Add tests when possible
- Update documentation as needed

## License
This project is open-sourced software licensed under the MIT license.
