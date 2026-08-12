# Carewell PunchOut Catalogue

A PunchOut catalogue integration for Carewell Group, connecting to Amazon Business buyers through Amazon's Coupa procurement instance. Buyers click through from Coupa, shop this storefront, and their cart is transferred back into Coupa as a requisition, using the cXML PunchOut protocol.

This is not a general purpose storefront with a protocol bolted on. The cXML layer is the actual product, the storefront is the interface to it. See `carewell-punchout-architecture.md` (kept alongside this repository, not inside it) for the full system design and the reasoning behind it. See `CHANGELOG.md` for what changed and when.

## Stack

- Laravel 12, PHP 8.2+
- MySQL for local development, sqlite (in-memory) for the test suite. `config/database.php` still defaults to sqlite for a fresh clone, see "Getting started" below for switching a local database over to MySQL. Confirm the actual staging/production database driver with whoever currently owns deployment, that has moved between Azure App Service and GoDaddy hosting since this was first written and is tracked outside this module list.
- cXML 1.2, the only protocol Coupa's own configuration accepts
- Pest for testing, PHPStan at level 6, Pint for code style

## Architecture

A modular monolith, one deployable application internally split into modules with hard boundaries. A module's internals are private, other modules only depend on its `Contracts/` interfaces and the DTOs they return, never on its Eloquent models directly. This is what makes it safe to change one module without breaking another.

| Module | Status | Owns |
|---|---|---|
| `Shared` | Done | `Money` and `UnspscCode` value objects, base exception hierarchy, `site_settings` (the configurable logo) |
| `Punchout` | Done | cXML setup/start/order-request round trip, credential validation, session lifecycle, wire logging |
| `Catalog` | Done | Products, categories, UNSPSC reference data, contract pricing, search (name, SKU, description, UNSPSC code), stock tracking (`InventoryService`), CSV/Excel import and export, `catalog:validate` |
| `Cart` | Done | Cart state, quantity rules (packs vs. plain units), the same-origin JSON cart API, the protocol-neutral snapshot Punchout's `OrderMessageBuilder` consumes |
| `Orders` | Done | Purchase orders received from Coupa (via Punchout's `OrderRequestController`), catalogue reconciliation, inventory deduction, queued notification email, idempotent on `po_number` |
| `Storefront` | Done | Vue 3 + Inertia.js pages, the composition layer over Catalog, Cart, and Punchout, the transfer-to-Coupa flow |
| `Admin` | Done | Filament v3 panel: Product (stock, activate/deactivate, import/export), Category, ContractPrice, PunchoutCredential (write-only secret), PurchaseOrder (read-only), a preview-token generator, dashboard, and site settings |

Each module lives under `app/Modules/<Name>/` with its own `Contracts/`, `Models/`, `Services/`, `database/migrations/`, and its own service provider that registers its bindings, migrations, routes, and console commands. Nothing about a module needs to be scattered across a central kernel file to add it.

## Getting started

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan storage:link
npm run build
```

Local development runs on sqlite by default (`database/database.sqlite`, created automatically). No further setup is needed to run the app or the test suite. Database credentials for any other environment (MySQL locally, or whatever staging/production currently run) are environment-driven, never committed.

To switch a local install from sqlite to MySQL: create a database and a dedicated user (do not use `root` in `.env`), point `DB_CONNECTION`/`DB_HOST`/`DB_PORT`/`DB_DATABASE`/`DB_USERNAME`/`DB_PASSWORD` at it, run `php artisan migrate`, then `php artisan data:migrate-sqlite-to-mysql` if there is existing sqlite data worth keeping (it copies every real data table over once, skipping Laravel's own queue/cache/session tables, safe to re-run since a table already populated on the destination is left alone).

`storage:link` is required for product images: the Admin panel's product image upload writes to the `public` disk (`storage/app/public`), served through the `public/storage` symlink that command creates. Without it, uploaded images 404/403 on every environment, this is easy to forget since nothing else in local dev depends on it.

The storefront's frontend (`resources/js`) is a Vue 3 + Inertia.js single page app, built by Vite and served as static assets from the same origin, there is no separate Node server in production. Run `npm run dev` for hot module reload while working on it, `npm run build` before any commit that touches `resources/js`.

Punchout credentials (test and production) are managed entirely through the database, via the Admin module's Filament resource. There is deliberately no other way to set them, they never live in a migration, seeder, or `.env` file.

To create the first admin login:

```bash
php artisan make:filament-user
```

Then sign in at `/admin`. Every `User` row is an admin by definition, there is no separate buyer account system anywhere in this application, punchout buyers only ever carry a session token, never a login.

Production and staging need the standard Laravel scheduler cron entry (`* * * * * php artisan schedule:run`), it drives `punchout_logs` pruning (see `routes/console.php`, `PUNCHOUT_LOG_RETENTION_DAYS`). Nothing runs it in local development, an unpruned local sqlite database is not a concern.

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
php artisan punchout:simulate               # exercises the setup, start, and order-request round trip against this app's own endpoints
php artisan punchout:doctor                 # deployment sanity checks for punchout config that fails silently rather than loudly, run this in the deploy pipeline
php artisan catalog:import <path>           # import a catalogue CSV, producing a report rather than failing silently (CSV and Excel both work from the Admin Products page's Import button, this command is CSV-only)
php artisan catalog:validate                # fail if any active product is missing a UNSPSC code, contract price, unit of measure, or description
php artisan data:migrate-sqlite-to-mysql    # one-off: copy every real data table from the local sqlite file into the configured MySQL connection
```

## A note on the TypeScript version pin

`typescript` in `package.json` is pinned to `~6.0.3` deliberately, not left on a caret range. TypeScript 7 replaced the classic JS compiler package layout with a native one that no longer exposes the `./lib/tsc` subpath `vue-tsc` shells out to, so `npx vue-tsc --noEmit` fails immediately on TypeScript 7 with `ERR_PACKAGE_PATH_NOT_EXPORTED`. Do not widen this pin until `vue-tsc` publishes a release that supports TypeScript 7.

## A note on open items

- `OrderRequestParser` (the inbound purchase order) is built against the standard cXML `OrderRequest` structure, no sample PO payload exists in any of the source documents this project was built from, so this has not been validated against a real Coupa-issued specimen yet. The PO transmission channel itself (CSP, email, or cXML) is still unconfirmed with GPCS.
- `ContractPrice` (`config/cart.php`'s `default_currency` sibling assumption) is scoped only to a product and a date range, no buyer or customer column, assuming one contract catalogue serves every buyer. Whether Carewell's Amazon contract actually varies pricing by buyer, business unit, or country is an open question for GPCS.
- `ORDERS_NOTIFICATION_EMAIL` (`config/orders.php`) defaults to a placeholder address, set it in `.env` before this matters in any real environment.

All three are flagged directly in the relevant code's docblocks and should be resolved once GPCS answers those questions.

Separately, an internal gap rather than a GPCS question: PunchoutCredentialResource and ContractPriceResource have no audit trail of who changed a credential or a contract price, or when. Worth adding once there is a concrete need to answer that question, see PunchoutCredentialResource's docblock.

## A note on where this code lives

This repository is pushed to two remotes that are not kept in lockstep automatically:

- `origin`, the original GitHub repository this project was built in.
- `azure`, `https://github.com/acutehimanshu/punchout-systems`, where the actual Azure/GoDaddy deployment pipeline lives. Only its `UAT` branch is meant to receive our work, `main`/`master` there belong to whoever owns deployment and carry their own history (build workflow, hosting config) that does not exist on `origin`.

`azure/UAT` and `origin/main` have unrelated git histories by now, a normal `git push` there will be rejected; reconciling the two branches has needed a manual merge more than once already. Before force-pushing `origin/main` onto `azure/UAT` again, fetch `azure` first and check what is actually on `UAT` and `azure/main`, past experience is that deployment-specific work lands there that does not exist anywhere in this repository's own history and is easy to silently overwrite.
