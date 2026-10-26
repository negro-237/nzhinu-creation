# African Boutique — Codex project starter

## 1. Prerequisites
PHP 8.2+ (matching selected Laravel/Filament versions), Composer, Node.js LTS, npm, MySQL 8+, Git. Check current Filament compatibility before installation.

## 2. Bootstrap (local terminal)
```bash
composer create-project laravel/laravel african-boutique "^12.0"
cd nzhinu-creation
npm install
composer require inertiajs/inertia-laravel
npm install @inertiajs/react react react-dom
npm install -D typescript @types/react @types/react-dom
composer require filament/filament spatie/laravel-permission spatie/laravel-medialibrary
php artisan filament:install --panels
php artisan vendor:publish --provider="Spatie\\Permission\\PermissionServiceProvider"
php artisan vendor:publish --provider="Spatie\\MediaLibrary\\MediaLibraryServiceProvider" --tag="medialibrary-migrations"
```

Set `.env` for MySQL, `APP_URL`, mail, and queue. Do not commit `.env`. Ask Codex to complete Inertia + React + TypeScript wiring based on the installed versions rather than assuming the frontend starter is configured.

## 3. Add AGENTS.md
Copy `AGENTS.md` into repository root before starting Codex.

## 4. First Codex prompt
Read `AGENTS.md` and inspect the fresh Laravel repository. Implement ONLY phase 1: verify installed dependencies, configure Inertia React TypeScript scaffolding and Filament panel, staff authentication and role-based authorization, markets and stock_locations migrations/models/factories/seeders, and basic tests. Seed CA/CAD and CM/XAF and one example stock location per country with clearly marked placeholder addresses. Do not build products or orders yet. Run migrations, tests and frontend build. Report all commands and any issues, then stop for my review.

## 5. Next prompts
- Phase 2: Implement catalog entities and Filament CRUD according to AGENTS.md, with market-specific prices and availability; add tests; stop for review.
- Phase 3: Implement physical inventory, reservation/release services, transactions, locks and concurrency/idempotency tests; stop for review.
- Phase 4: Implement accessible responsive French storefront, market switcher, catalog, filters, product page and session cart; stop for review.
- Phase 5: Implement guest checkout, contact/fulfillment details, order snapshots, order creation and reservation expiry; test all transitions; stop for review.
- Phase 6: Implement manual payment records/verification, fulfillment, pickup and delivery workflows, cancellation and audit history; stop for review.
- Phase 7: Implement notifications, dashboard, CMS pages/banners, SEO basics, final security review, full tests, README and deployment checklist; stop for review.

## 6. Decisions to verify before production
- Real warehouse addresses and pickup points in both countries.
- Delivery zones, pricing and couriers.
- Actual accepted payment methods and payment-verification workflow.
- Taxes, consumer protection, returns, privacy and marketing consent in both jurisdictions.
- Reservation timeout and confirmed-order payment deadline.
