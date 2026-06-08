# Wiisnin — build notes

**Wiisnin** (Nishnaabemwin / Sagamok dialect for *"eat"*) is a local food-ordering web app
for the North Shore communities of **Massey, Sagamok, Espanola, and Spanish**. It is built
on the [Waaseyaa framework](https://github.com/waaseyaa/framework) and doubles as a
pre-beta stress test of that framework (see [WAASEYAA-FRICTION.md](WAASEYAA-FRICTION.md)).

- Composer package: `jonesrussell/wiisnin` · PHP namespace: `App\` (skeleton default) /
  domain code under `App\`. App title: **Wiisnin**.
- Pilot vendor: **Meedjims Foodland**, Sagamok First Nation (705-865-1537).
- MVP scope: **order-taking only**. No payments, drivers, or maps. Pay cash / e-transfer
  offline.

> **Note on package name:** the scaffold's `composer.json` still carries the skeleton name
> `waaseyaa/waaseyaa`. Rename to `jonesrussell/wiisnin` is tracked as a TODO (kept as-is so
> far to avoid churning provenance tooling mid-build).

---

## How to run it locally

Prereqs: PHP 8.5+, Composer 2.x, Node 20+ / npm.

```powershell
# 1. PHP dependencies (already installed in this tree)
composer install

# 2. Generate .env (the skeleton's post-create chmod step fails on Windows; run setup directly)
php bin/post-create-setup.php

# 3. Front-end assets (Inertia + Vue, built into public/build/)
npm install
npm run build            # production build; or `npm run dev` for Vite HMR (set VITE_DEV_SERVER)

# 4. Rebuild the framework discovery manifest after provider/entity changes
php vendor/bin/waaseyaa.bat optimize:manifest    # Windows; `bin/waaseyaa` elsewhere

# 5. Serve
php -S 127.0.0.1:8080 -t public
#   then open http://127.0.0.1:8080/
```

Tests:

```powershell
php vendor/phpunit/phpunit/phpunit                 # full suite
php vendor/phpunit/phpunit/phpunit --testsuite Integration
```

A preview config named **`wiisnin`** lives in `.claude/launch.json` (PHP built-in server
on port 8080, docroot `public/`).

### Front-end wiring (how Inertia renders here)

- A controller returns `Inertia::render('Component', [...props])` — an `InertiaResponse`.
  The framework's `ControllerDispatcher` turns it into full HTML (initial load) or a JSON
  page object (`X-Inertia` XHR). Controllers do **not** build a Symfony `Response` by hand.
- Vue pages live in `resources/js/Pages/*.vue`; the entry is `resources/js/app.ts`.
- Build output → `public/build/` (+ `.vite/manifest.json`), served at `/build/...`.
- `public/index.php` does `chdir($projectRoot)` so the Inertia asset resolver finds the
  manifest (see WAASEYAA-FRICTION F-04).

---

## Key decisions

- **Framework pinned to `0.1.0-alpha.192`** (the current 19x head), matching the working
  `fnpi-waaseyaa` app. The skeleton's default constraint resolved to a stale `v0.1.0` tag;
  see WAASEYAA-FRICTION F-01/F-02.
- **Inertia + Vue** for the customer app (per spec), not Twig SSR — even though the existing
  Waaseyaa reference apps use Twig. This is intentional: exercising the `inertia` package is
  part of the stress test.
- **`SiteServiceProvider`** owns public routes (registered in
  `composer.json → extra.waaseyaa.providers`); the scaffold's placeholder `AppServiceProvider`
  + `HomeController` were removed.
- **Communities** are defined once in `App\Support\Communities` and will be mirrored into a
  `community` taxonomy in Phase 1 — single source of truth for the four names.

---

## Status

### Phase 0 — scaffold ✅
- Project created from the `waaseyaa/waaseyaa` skeleton; git initialised.
- Baseline green: `optimize:manifest` compiles (75 providers); `phpunit` clean.
- `SiteServiceProvider` registered; `inertia` package added.
- Inertia + Vue landing page lists the four communities — **verified rendering in-browser**
  (Vue mounts, no console errors).
- Landing route built **test-first** (`tests/Integration/LandingPageTest.php`): red → green.

### Phase 1 — entities + access (skeleton) ✅
- Entities defined, registered, and materialized (`schema:sync`).
- Order workflow (workflows package), three roles + group-scoped access policies,
  and new-order notifications (Mercure + mail channels, SMS stub) all in place.
- Order-placement flow and group-scoped access are covered by tests (test-first).
- Pilot data seeded: Meedjims Foodland + menu + community/menu-category taxonomy.

Seed / verify:
```powershell
php vendor/bin/waaseyaa.bat app:seed        # idempotent: Meedjims + menu + taxonomy + group
php vendor/bin/waaseyaa.bat app:selfcheck   # round-trip entities through storage
php vendor/bin/waaseyaa.bat schema:check    # detect schema drift
```

---

## Entities

Custom content entities (`src/Entity/`), all registered in `config/entity-types.php`.
Non-key fields are stored in the `_data` JSON blob and marked `FieldStorage::Data`
so `findBy()` can filter them (see WAASEYAA-FRICTION F-10). Money is integer cents.

| Entity | Type id | Key fields / references |
|--------|---------|--------------------------|
| **Vendor** | `vendor` | name, slug, `community_tid`→term, description, hours, `is_open`, `owner_group_id`→group, contact phone/email, `logo_mid`→media. Implements `NotifiableInterface`. |
| **MenuItem** | `menu_item` | `vendor_id`→vendor, `category_tid`→term, name, description, `price_cents`, `photo_mid`→media, `available` |
| **Order** | `order` | reference (WSN-NNNNNN), `customer_uid`→user, `vendor_id`→vendor, `status` (workflow), fulfilment, address, `community_tid`, contact phone, payment_method, notes, subtotal/total cents, placed_at/updated_at |
| **OrderItem** | `order_item` | `order_id`→order, `menu_item_id`→menu_item, name snapshot, quantity, `unit_price_cents` (snapshot), `line_total_cents`, line note |
| **GroupMembership** | `group_membership` | `group_id`→group, `user_id`→user, role (owner/staff) — app-level membership for the groups package |

Taxonomy (taxonomy package): `community` vocabulary (Massey, Sagamok, Espanola,
Spanish) and `menu_category` vocabulary (Native cuisine, Grill, Daily specials).
Vendor groups use the groups package's `group` entity.

**Order workflow** (`src/Domain/Order/OrderWorkflow.php`, workflows package):
`placed → accepted → preparing → ready → completed`; `cancelled` reachable from
`placed` or `accepted`.

**Roles & access** (`src/Access/`): `admin` (framework `administrator`),
`vendor_staff` (scoped to their vendor's group via `VendorStaffDirectory` +
`group_membership`), `customer`. Policies: `VendorAccessPolicy`,
`MenuItemAccessPolicy`, `OrderAccessPolicy`, assembled via
`CommerceAccess::handler()`.

**Notifications** (`src/Notification/`): `OrderPlacedNotification` delivered over a
`MercureChannel` (app adapter over `MercurePublisher`) and the package
`MailChannel`. `SmsChannel` + `SmsSenderInterface` are stubs for a future Twilio
channel (not implemented).

---

## TODO

- [ ] Rename composer package `waaseyaa/waaseyaa` → `jonesrussell/wiisnin`.
- [ ] Create the GitHub remote `jonesrussell/wiisnin` and push (this tree had `.git`
      stripped; a fresh repo was initialised locally).
- [ ] Confirm Meedjims Foodland's real menu prices with the family (seed uses clearly
      marked placeholders).
- [ ] Future: SMS notification channel (Twilio) — interface stub only this session.
