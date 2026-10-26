# AGENTS.md — African Boutique MVP

## Mission
Build a production-minded African fashion and accessories catalog and order-request MVP. The site is NOT an online payment checkout. Customers place orders without accounts, staff call to confirm, verify payment manually, then deliver or arrange pickup.

## Stack
- Laravel 12, PHP 8.2+, MySQL 8+
- Inertia.js + React + TypeScript + Tailwind CSS for public storefront
- Filament (compatible stable version) for staff administration
- Spatie Laravel Permission for staff roles, Spatie Media Library for product media
- Laravel queues, scheduler, notifications, policies, feature tests
- One Laravel monolith; do not introduce a separate API, microservices, Docker, or payment gateway unless explicitly requested.

## Markets and inventory
- Two markets: Canada (CA, CAD) and Cameroon (CM, XAF).
- Market-specific prices, catalog availability, shipping zones, pickup points, and physical stock locations.
- Stock physically stored in BOTH countries, independently. No automatic cross-border fulfillment or transfers in MVP.
- Use `markets`, `stock_locations`, `inventories` (by stock_location + variant), `market_prices` (by market + variant), and `market_products`.
- Each product has at least one variant (default variant if no size/color). SKU unique.
- Use integer monetary minor units and currency code snapshots. CAD uses 2 fraction digits; XAF uses 0. Never mix currency totals.
- A market can have multiple stock locations. Reserve from one explicitly selected location for each order item; record location on order items or reservations. Avoid double-counting aggregate availability.

## Orders and payments
- Guests can order without creating accounts. Phone required; email optional.
- Order placement is NOT payment. Staff call customer and confirm final shipping fee and total.
- No shipment, pickup handover, or physical stock decrement before verified full payment.
- Separate order, payment and fulfillment statuses. Define PHP backed enums and enforce allowed transitions in services.
- Price, SKU, product name, variant and currency snapshots on order items.
- Place order and reserve inventory atomically using DB transactions and row locks. Prevent overselling under concurrency.
- Define reservation expiry (configurable, initial default 24 hours) and a scheduled, idempotent release job. Confirmed orders can have a separate payment deadline. Recheck inventory on reactivation.
- Physical stock = quantity_on_hand; available = on_hand - reserved. On completed handover, decrement both on_hand and reserved exactly once; cancellation/expiry releases reserved only. Record stock movements and status history.
- Verify payments manually, store references and verifier; never treat customer-provided screenshot alone as verification. Support partial payments in data model, but fulfillment requires full verified net payment.
- Handle cancellations, refunds, payment arriving after expiry, and idempotent retries safely. Do not silently refund or auto-restore stock for already completed orders.
- No public order lookup by guessable order number alone: use signed links or secure random access tokens.

## Functional scope
1. Staff auth, roles and permissions; two market seeders.
2. Catalog: categories, cultural collections, products, media, variants, market prices/availability.
3. Inventory: physical locations, stock levels, reservations, movements, low-stock alerts.
4. Storefront: market selector, homepage, catalog, search, filters, product detail, session cart.
5. Guest checkout: contact, delivery or pickup, order number, confirmation page.
6. Filament order workflows: call confirmation, payment recording/verification, packing, shipment or pickup, cancellation.
7. Delivery zones, fees and pickup points per market.
8. Email when available, Filament notifications, manual WhatsApp contact link (no automatic WhatsApp API).
9. Dashboard and basic CMS pages/banners.
10. Tests and operational docs.

## Engineering conventions
- Use Laravel migrations, Eloquent relationships, factories, seeders, Form Requests, Policies, queued Notifications, services/actions and feature tests.
- Business rules belong in services, not controllers, Filament resources, or React components.
- Every mutating workflow checks authorization, validates input, and has relevant tests.
- Keep database constraints and indexes (unique market/variant price, unique location/variant inventory, unique SKU, order number, nonnegative quantities).
- Use `lockForUpdate()` with consistent lock ordering for reservation and release; retries must be idempotent.
- Prefer soft-delete/archival for referenced catalog records; preserve order history.
- Avoid storing payment credentials, card numbers, or unnecessary personal data.
- Make public pages mobile-first, accessible and SEO-friendly. French UI first; organize strings for later English localization.
- Do not invent tax or legal compliance rules: keep configurable, document Canada/Cameroon review needs.
- Read existing files before changes; do not overwrite user modifications or commit secrets. Update README and tests with each module.

## Definition of done per phase
- Migrations run on a fresh MySQL database and rollback where practical.
- Seeders are repeatable without duplicates.
- Feature tests cover success, validation failure, unauthorized access, and concurrency/idempotency where applicable.
- `php artisan test`, formatting and frontend build succeed (or failures are reported precisely).
- Summarize files changed, commands executed, risks and next phase. Commit only if explicitly requested.

## Implementation phases
1. Bootstrap, dependencies, auth, roles, market and stock-location seeders.
2. Catalog models/migrations, Filament CRUD, media, market pricing.
3. Inventory services and concurrency tests.
4. Public React/Inertia storefront and session cart.
5. Guest checkout, transactional reservations, order workflow.
6. Manual payment verification and fulfillment.
7. Notifications, CMS, dashboards, security hardening and end-to-end tests.

## Workflow for Codex
- Work on ONE phase at a time. Start with a brief plan and inspect repository state.
- Before writing code, identify ambiguities and propose safe defaults.
- After each phase run tests/build, report exact results, and stop for review.
