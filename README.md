# Carewell PunchOut Catalogue

A PunchOut catalogue integration for Carewell Group, connecting to Amazon Business buyers through Amazon's Coupa procurement instance. Buyers click through from Coupa, shop this storefront, and their cart is transferred back into Coupa as a requisition, using the cXML PunchOut protocol.

This is not a general purpose storefront with a protocol bolted on. The cXML layer is the actual product, the storefront is the interface to it. See `carewell-punchout-architecture.md` (kept alongside this repository, not inside it) for the full system design and the reasoning behind it.

## Stack

- Laravel 12, PHP 8.3+
- PostgreSQL in staging and production, sqlite for local development and the test suite
- cXML 1.2, the only protocol Coupa's own configuration accepts
- Pest for testing, PHPStan at level 6, Pint for code style

## Architecture

A modular monolith, one deployable application internally split into modules with hard boundaries. A module's internals are private, other modules only depend on its `Contracts/` interfaces and the DTOs they return, never on its Eloquent models directly. This is what makes it safe to change one module without breaking another.

| Module | Status | Owns |
|---|---|---|
| `Shared` | Done | `Money` and `UnspscCode` value objects, base exception hierarchy |
| `Punchout` | Done | cXML setup/start/order-request round trip, credential validation, session lifecycle, wire logging |
| `Catalog` | Done | Products, categories, UNSPSC reference data, contract pricing, search, CSV import, `catalog:validate` |
| `Cart` | Done | Cart state, quantity rules, the same-origin JSON cart API, the protocol-neutral snapshot Punchout's `OrderMessageBuilder` consumes |
| `Orders` | Done | Purchase orders received from Coupa (via Punchout's `OrderRequestController`), queued notification email, idempotent on `po_number` |
| `Storefront` | Done | Vue 3 + Inertia.js pages, the composition layer over Catalog, Cart, and Punchout, the transfer-to-Coupa flow |
| `Admin` | Done | Filament v3 panel: Product, Category, ContractPrice, PunchoutCredential (write-only secret), PurchaseOrder (read-only) |

Each module lives under `app/Modules/<Name>/` with its own `Contracts/`, `Models/`, `Services/`, `database/migrations/`, and its own service provider that registers its bindings, migrations, routes, and console commands. Nothing about a module needs to be scattered across a central kernel file to add it.

## Getting started

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run build
```

Local development runs on sqlite by default (`database/database.sqlite`, created automatically). No further setup is needed to run the app or the test suite. Staging and production are configured for PostgreSQL, credentials are environment-driven, never committed.

The storefront's frontend (`resources/js`) is a Vue 3 + Inertia.js single page app, built by Vite and served as static assets from the same origin, there is no separate Node server in production. Run `npm run dev` for hot module reload while working on it, `npm run build` before any commit that touches `resources/js`.

Punchout credentials (test and production) are managed entirely through the database, via the Admin module's Filament resource. There is deliberately no other way to set them, they never live in a migration, seeder, or `.env` file.

To create the first admin login:

```bash
php artisan make:filament-user
```

Then sign in at `/admin`. Every `User` row is an admin by definition, there is no separate buyer account system anywhere in this application, punchout buyers only ever carry a session token, never a login.

## Testing and quality gates

```bash
php vendor/bin/pest              # test suite
php vendor/bin/phpstan analyse   # static analysis, level 6
php vendor/bin/pint              # code style, --test to check without fixing
npx vue-tsc --noEmit             # frontend type check
```

All four run clean on every commit to `main`.

## Useful artisan commands

```bash
php artisan punchout:simulate            # exercises the setup, start, and order-request round trip against this app's own endpoints
php artisan catalog:import <path>        # import a catalogue CSV, producing a report rather than failing silently
php artisan catalog:validate             # fail if any active product is missing a UNSPSC code, contract price, unit of measure, or description
```

## A note on the TypeScript version pin

`typescript` in `package.json` is pinned to `~6.0.3` deliberately, not left on a caret range. TypeScript 7 replaced the classic JS compiler package layout with a native one that no longer exposes the `./lib/tsc` subpath `vue-tsc` shells out to, so `npx vue-tsc --noEmit` fails immediately on TypeScript 7 with `ERR_PACKAGE_PATH_NOT_EXPORTED`. Do not widen this pin until `vue-tsc` publishes a release that supports TypeScript 7.

## A note on open items

- `OrderRequestParser` (the inbound purchase order) is built against the standard cXML `OrderRequest` structure, no sample PO payload exists in any of the source documents this project was built from, so this has not been validated against a real Coupa-issued specimen yet.
- `OrderRequestController` does not currently validate a shared secret on the inbound PO, since it is not confirmed whether Coupa sends one on that message type, and the PO transmission channel itself (CSP, email, or cXML) is still unconfirmed with GPCS.
- `ORDERS_NOTIFICATION_EMAIL` (`config/orders.php`) defaults to a placeholder address, set it in `.env` before this matters in any real environment.

All three are flagged directly in the relevant code's docblocks and should be resolved once GPCS answers those questions.
