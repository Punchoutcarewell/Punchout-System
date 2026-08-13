# Changelog

Notable changes to the Carewell PunchOut catalogue, grouped by day rather than by release: this project does not tag versions, `main` is the running state. Each entry links the change to its actual commit(s) where useful for digging into the "why."

## 2026-08-13

### Changed
- The browser-facing start URL moved from `/punchout/start?token=...` to `/punchout/setup/{token}`, sharing a path with `POST /punchout/setup` and disambiguated by HTTP method. The cookie-based session is unchanged; the query-string fallback for browsers that block third-party cookies inside Coupa's iframe is unchanged.
- Every PunchOut endpoint (`POST /punchout/setup`, `GET /punchout/setup/{token}`, `POST /punchout/order`) moved under `/api`, at GPCS's request: anything processing raw cXML rather than serving HTML sits under `/api` in this deployment, whether or not the caller is a browser. Route names, session handling, and the cookie/query-string fallback redirect all stayed the same.

### Added
- A "Generate" action on the shared secret field in Admin's PunchOut Credential form, filling a random 64-character value in the same format `SessionManager` already issues.
- Revoke / Reactivate actions on the PunchOut Credential table, toggling `is_active` with a confirmation step, so a compromised or retired credential can be disabled without deleting its row.
- `GET /api/punchout/setup/{token}` now also accepts a credential's shared secret directly in place of a session token: Coupa can reach the storefront with a single GET, no cXML `PunchOutSetupRequest` POST first. A session is created on the spot and the buyer lands on `/storefront?token=...` referencing it, same as the real cXML flow. The secret and a new "Return URL" field (where the finished cart posts back to Coupa) are both managed in Admin's PunchOut Credential form; changing either takes effect on the very next request.

## 2026-08-12

### Added
- Product Import and Export on the Admin Products page, next to "New product." Import accepts CSV or Excel (`.xlsx`/`.xls`), same column layout either way, and reports created/updated/skipped-row counts as a notification. Export offers CSV or Excel, a full snapshot of every product column including `stock_quantity` and `is_active`.
- `phpoffice/phpspreadsheet` dependency to back the Excel side of import/export.
- Azure App Service `startup.sh` (nginx document root and rewrite rule for Laravel's `public/` entry point).

### Fixed
- An unrelated `league/commonmark` security advisory (a `laravel/framework` transitive dependency), picked up while touching `composer.lock`.

## 2026-08-07

### Added
- `php artisan data:migrate-sqlite-to-mysql`, a one-off command that copies every real data table from the local SQLite database into a configured MySQL connection, in foreign-key order, skipping Laravel's own queue/cache/session infrastructure tables. Safe to re-run: a table already populated on the destination is left alone.
- `database/schema/mysql-schema.sql`, a consolidated schema dump alongside the migration files, for reviewing or standing up the schema without running the app.

### Fixed
- `punchout_credentials`' composite unique index (`environment`, `to_domain`, `to_identity`, `from_domain`, `from_identity`) exceeded InnoDB's 3072-byte key limit under `utf8mb4` once `from_domain`/`from_identity` joined it, a limit SQLite never enforced. Only ever surfaced when migrating to MySQL.

## 2026-08-06

### Added
- Internal stock tracking: `products.stock_quantity`, decremented automatically and atomically whenever a purchase order is received. Never blocks or rejects the order: a shortfall is allowed to take stock negative, and that negative value is itself the count of extra units Admin needs to bring in, surfaced via a coloured stock column, a "needs restock" filter, and a dashboard stat.
- One-click activate/deactivate for products directly from the Admin list table (previously required opening Edit).
- UNSPSC classification code as a search field in the storefront catalogue, matching the empty-results copy that already promised it.

## 2026-08-05

The busiest day: a full audit pass (critical through low severity) plus the storefront's visual redesign.

### Security
- Fixed the session cookie's `SameSite` attribute for iframe embedding (C1), configured trusted proxies (C2), hardened `punchout:simulate` (C3), and closed an unauthenticated `/punchout/order` endpoint (C4).
- Validated the buyer's `From`/`Sender` identity (H2) and fixed shared-secret redaction to use DOM parsing instead of regex (H3).

### Fixed
- Cart and purchase-order concurrency races, and made cart transfer recoverable with a visible fallback if the automatic post to Coupa silently fails (H4-H7).
- Rate-limited requests now get a well-formed cXML fault instead of Laravel's default 429 page (H8).
- SKUs are URL-encoded in cart API calls, and cart errors now surface to the buyer instead of failing silently (H9, M1).
- The client-side session countdown now actually redirects to the session-expired page when it lapses (M2).
- `OrderRequest` line numbers and quantities are validated instead of silently coerced (M3).
- `Money` precision handling and the currency exponent table (M4).
- `activeContractPrice()` is now deterministic when two contract prices share the same `effective_from` date (M5).
- Purchase orders are linked back to the punchout session that produced them (M7).
- Received purchase orders are reconciled against the catalogue (SKU exists, price matches, line total matches), flagged for Admin review rather than blocking receipt (M8).
- `punchout_logs.session_id` indexed, old rows pruned on a schedule (M9).
- `punchout:doctor` now catches an empty `frame-ancestors` config at deploy time (M11).
- A batch of lower-severity items: security headers, a cart quantity cap, dropped setup fields, an N+1 query, lazy cart evaluation, unescaped `LIKE` wildcards in search, and a duplicated `payloadId`.

### Added
- Admin: a preview-token generator (mint a storefront preview link without a real Coupa round trip), an informative dashboard (active sessions, POs received today, discrepancy-flagged count, active products/credentials), and a configurable site logo.
- Cart line items now show product thumbnails.
- `pack_size` is now optional on products: leave it blank for a product not sold in packs (price is per unit), set it for a product where quantity means "count of packs" and price is per pack. Previously quantity was always a raw piece count, which was wrong for any packed product.
- Full storefront UI redesign: clean, minimal, white background, `#0158E6` blue accent, pill-shaped buttons and steppers, redesigned product cards, cart, and category sidebar.

## 2026-08-04

### Added
- Product image upload in the Admin panel, replacing a plain text image-path field.

## 2026-08-03

### Added
- Initial Laravel 12 scaffold.
- `Shared` module: `Money` and `UnspscCode` value objects, base exception hierarchy.
- `Punchout` module: cXML setup/start/order-request round trip, credential validation, session lifecycle, wire logging.
- `Catalog` module: products, categories, UNSPSC reference data, contract pricing, search, CSV import.
- `Cart` module: cart state, quantity rules, the same-origin JSON cart API, the protocol-neutral snapshot `OrderMessageBuilder` consumes.
- `Orders` module: purchase orders received from Coupa, queued notification email, idempotent on `po_number`.
- `Storefront` module: Vue 3 + Inertia.js pages, closing the setup-to-cart-to-Coupa loop.
- `Admin` module: Filament v3 panel over the Product, Category, ContractPrice, PunchoutCredential, and PurchaseOrder models.
