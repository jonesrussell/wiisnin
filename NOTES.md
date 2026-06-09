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

## Live demo (Meedjims) — deployed on the Pi

Deployed to the Raspberry Pi alongside oiatc.ca / fnprocure.ca, following
`waaseyaa-infra/runbooks/03-add-a-site.md`: Docker Compose → Caddy (plain HTTP) →
Cloudflare Tunnel, plus a `dunglas/mercure` hub for the live inbox.

- App image built from `github.com/jonesrussell/wiisnin` (pinned `WIISNIN_REF`).
- Infra change is on branch **`feat/wiisnin`** of `waaseyaa-infra` (PR open) — kept
  off `main` so it doesn't trigger the other sites' deploy workflows. The Pi is
  checked out on that branch; **merge the PR to make it durable** (that does
  trigger the routine idempotent rebuilds of the other sites).
- Verified end-to-end on the Pi via `Host: wiisnin.ca` (pre-DNS method): landing,
  menu, order placement, vendor inbox API, Mercure SSE (200). DB reset to a clean
  seeded state (empty inbox) for the demo.

**Customer:** `https://wiisnin.ca/` → tap Sagamok → Meedjims → add items → Review
order → Place order → WSN-NNNNNN confirmation.

**Vendor inbox (the wow moment):** `https://wiisnin.ca/vendor` → passphrase
**`meedjims`** (env `WIISNIN_VENDOR_PASSPHRASE`). New orders appear live over
Mercure (no refresh); Accept → Preparing → Ready → Completed buttons advance status.

**Going public (your action — Cloudflare/registrar):** the tunnel is
dashboard-managed, so DNS is added there, not in a zone file. Tunnel id
`c2de9904-2ac8-479d-b01e-3a533c19fa1c` → hostname
`c2de9904-2ac8-479d-b01e-3a533c19fa1c.cfargotunnel.com`.
- Fastest test (no registrar change): Zero Trust → Networks → Tunnels →
  `oiatc-pi` → Public Hostnames → Add `wiisnin` . `oiatc.ca` → HTTP → `caddy:80`.
  `https://wiisnin.oiatc.ca` works immediately (Caddy already serves it).
- Real domain: add `wiisnin.ca` as a Cloudflare site (point the registrar's
  nameservers to the ones Cloudflare assigns), then add tunnel public hostnames
  `wiisnin.ca` and `www.wiisnin.ca` → HTTP → `caddy:80` (Cloudflare auto-creates
  the proxied CNAME to the tunnel).

Redeploy a new app build: bump `WIISNIN_REF` in `compose/docker-compose.yml`, then
on the Pi `docker compose build wiisnin-app && docker compose up -d wiisnin-app`
and `docker compose exec -u www-data wiisnin-app vendor/bin/waaseyaa db:init --sync-schema`.

---

## TODO

- [ ] Merge the `feat/wiisnin` PR on `waaseyaa-infra` to make the deploy durable.
- [ ] Add the Cloudflare tunnel public hostname for `wiisnin.ca` (+ `www`) and point
      the registrar's nameservers at Cloudflare (see above).
- [ ] Rename composer package `waaseyaa/waaseyaa` → `jonesrussell/wiisnin`.
- [x] Create the GitHub remote `jonesrussell/wiisnin` and push — done (public).
- [ ] Confirm Meedjims Foodland's real menu prices with the family (currently DRAFT,
      badged "to be confirmed" everywhere).
- [x] **Meedjims real photos** — DONE. The 3 shots (building, corn soup, poutine) were
      dropped into `…\local-eats\meedjims-photos\` and wired in: optimized JPEGs under
      `public/img/meedjims/` (building.jpg 960w hero, corn-soup.jpg, poutine.jpg ≤800w,
      og-meedjims.jpg 1200×630 share crop). Building → vendor hero (`.hero--photo` scrim) +
      Meedjims og:image (`SeoInjector::vendorOgImage`); corn-soup → Corn soup, poutine → Poutine
      (`Catalog::VENDOR_PHOTOS`). All other items (incl. Cheese fries) keep colour-tint placeholders.
      (The 3rd shot was first mis-labelled "cheese fries" — it's poutine; remapped + file renamed.)
- [ ] Future: SMS notification channel (Twilio) — interface stub only this session.
- [ ] Optional CI: a `deploy-wiisnin.yml` workflow (needs `gh auth refresh -s workflow`).

---

## Redesign + framework features (Bright & friendly)

Design system (`resources/css/app.css`, self-hosted fonts via `@fontsource`):
- Palette: cream `#FFFDF8`, ink `#2B2622`, **orange `#E8612C`** (CTAs/wordmark), **teal `#1D9E75`** (accents/cart), tan/soft tints. All as CSS custom properties.
- Type: **Nunito** (display/wordmark/headings), **Inter** (body) — bundled into `/build`, no runtime CDN.
- Mobile-first cards, sticky cart bar, draft-price badges everywhere, skeletons, focus-visible, prefers-reduced-motion.
- Cultural note: a light "Boozhoo!/Miigwech" warmth only; **floral/visual motifs need Russell's + community sign-off** before adding — left out deliberately.

Location-first home (`Pages/Landing.vue` + `GET /api/vendors`):
- Browser Geolocation → vendors across all four communities **sorted by distance** (`geo` `GeoDistance::haversine`, km), with a "Browse all" fallback when denied. Community is an optional filter chip row.
- Dish/kitchen **search** via the `search` package (FTS5): "taco"→Meedjims, "pizza"→the pizzerias.

Free framework features:
- **path** — `/meedjims` (and `/<slug>` per vendor) resolve to the vendor page (PathAlias entities + `PathAliasResolver`; seeded aliases; route `priority(5)` + slug requirement).
- **seo** — server-rendered OpenGraph/Twitter tags in the raw `<head>` via `App\Http\SeoInjector` in `public/index.php` (middleware can't — see FRICTION F-26). Verify: `curl https://wiisnin.ca/ | grep og:`.
- **structured-import** — menu CSV import: `php bin/waaseyaa app:import-menu seed/meedjims-menu.sample.csv --vendor=meedjims-foodland` (columns category,item,description,price_cents,available). Sample at `seed/meedjims-menu.sample.csv`.

Vendors (seeded, `app:seed`, idempotent): **Meedjims Foodland (Sagamok)** = the only live, orderable partner (real 9-item menu, draft prices). Sample "not yet a partner" listings: Back Home Bistro, Tony V's Pizza (Massey); Cortina, Topper's Pizza, Deluxe Drive-In (Espanola); North Channel Pizza, Dixie Lee Chicken (Spanish). Sample vendors are browsable but **not orderable** (`OrderService` rejects non-partners; `is_partner` flag).

---

## Phase 2 — i18n (Anishinaabemowin) + reviews

### i18n — Anishinaabemowin (Nishnaabemwin / Eastern Ojibwe, Sagamok dialect)
- **Header toggle** EN ↔ Nish (`AppShell.vue` `.langtoggle`). Choice persists in
  `localStorage['wsn_lang']` **and** a `wsn_lang` cookie so the server can localize
  entity fields on the next request. Composable: `resources/js/i18n.js` (`useI18n()` →
  `locale`, `setLocale`, `t(key)`).
- **UI chrome** translatable client-side; **per-entity fields** (vendor + menu item
  name/description) via app `*_oj` blob fields with `Catalog::localized()` fallback
  (Nish when the `*_oj` field is non-empty, else English).
- Framework `i18n` `Translator` is server/Twig-side only — it doesn't reach the Vue SPA
  after hydration — so the client composable is a deliberate twin. See FRICTION **F-30**.

> **⚠️ CULTURAL RULE — translations are a community deliverable, not ours.**
> No Ojibwe/Nishnaabemwin words are invented, guessed, or machine-translated.
> The ONLY confirmed words in use: **Wiisnin** (eat), **Boozhoo**, **Aaniin**, **Miigwech**.
> Every other UI string and every entity `*_oj` field is **left English on purpose** — that
> English fallback IS the visible "translation needed" state.
> **Seam to fill in:** `resources/lang/oj.php` + the `oj` dictionary in `resources/js/i18n.js`
> (chrome strings), and the `name_oj` / `description_oj` fields per vendor & menu item (content).
> Real translations must come from **Russell / the Sagamok community / the
> LLC/anishinaabemowin corpus** — do not fill these from any other source.

### Reviews & ratings (`App\Entity\Review` + `ReviewService` + `ReviewController`)
- Customers leave **1–5 stars + text** on a vendor; **average + count** show on the vendor
  card (Landing) and the vendor page hero (`Vendor.vue`).
- **Honesty rule enforced:** only the live partner (**Meedjims**, `is_partner=true`) accepts
  reviews; sample listings reject with a clear message (`ReviewService::create` throws
  `DomainException`; `POST /vendor/{slug}/reviews` → 422). Verified locally + tested
  (`ReviewServiceTest`).
- **Moderation:** `status` visible|hidden; `POST /vendor/reviews/{id}/hide` is passphrase-gated
  (same `wsn_vendor` cookie/HMAC as the vendor inbox). Hidden reviews drop out of the average.
- `engagement` has no rating primitive (Comment/Reaction/Follow only) — see FRICTION **F-29**;
  Review is a small app entity, same call as the CSV importer (F-28).
- **CSRF (Phase 2b):** the review POST is token-protected. The framework's `CsrfMiddleware`
  *exempts* `application/json`, so it never covered this JSON endpoint (and the endpoint had a
  form fallback a forged form could hit) — see FRICTION **F-31**. `ReviewController` now
  validates the session token (sources mirror the middleware: `_csrf_token` field /
  `X-CSRF-Token` / URL-decoded `X-XSRF-TOKEN`); the Vue fetch echoes the framework's
  `XSRF-TOKEN` cookie as `X-XSRF-TOKEN` (same token Inertia's axios sends on `/order`).
  Token-less/bad → 403; in-app → 200. Tested in `tests/Integration/ReviewRouteCsrfTest.php`.

### Polish
- **Search** now matches cuisine too (already in the FTS body): "italian"→Cortina,
  "pizza"→pizzerias.
- **Distance** display: `<1 km` reads **"nearby"**; otherwise `N km` (1 decimal under 10 km,
  whole number above). Honest, no fake precision.
- **Responsive layout (mobile-first):** phones unchanged (single ~480px column). At ≥768px the
  centered content widens (`.wsn-wrap` 760 → 1040 at ≥1100px) and the near-you/community vendor
  list reflows into a CSS grid (`.vgrid`, `auto-fill minmax(320px,1fr)`) — 2 cols tablet, 3 cols
  desktop. Single-vendor/checkout/confirmation/inbox/login pages use `.page-narrow` (≤640px,
  centered) so they stay readable instead of stretching. Header tagline trimmed to
  "let's eat · North Shore". Verified 375 / 768 / 1280 px: no overflow, no desktop sea-of-white.

Out of scope (next phase, not built): real Nishnaabemwin translations (community deliverable —
seam left above); decorative Anishinaabe floral motifs (await Russell + community sign-off).
(Meedjims real photos — now wired in, see TODO above.)

---

## Phase 3 — verified directory + info MVP

**Verified seed** (`App\Support\VendorData`, source `AREA-VENDORS-VERIFIED.md`): **21 vendors
across 8 towns**, **all directory/info listings — no vendor is an ordering partner right now**
(Meedjims included). The ordering/menu/reviews code stays in place but dormant. Excluded the 3 flagged
unconfirmed/closed (Back Home Bistro, Tony V's, Deluxe Drive-In) **and** Jones General Store
(a shop, not an eatery). Communities (`App\Support\Communities`, chips + `/c/{slug}`): Sagamok,
Massey, Walford, Spanish (incl. Cutler), Webbwood, Espanola, McKerrow, Nairn Centre. Coordinates
= town centroids + per-vendor jitter (APPROXIMATE — refine with real geocoding). Seed stays
idempotent-by-slug; prod DB is reset+reseeded on the verified-data change.

**Info listings useful** (all vendors, card + detail): tap-to-**call** (`tel:`), **directions**
(Google Maps URL from address→coords), cuisine + town, and **open/closed computed from hours**
where hours exist (`App\Support\OpenHours`, America/Toronto). Hours exist for only Tim Hortons +
Roger Rabbit's; everyone else shows neither — **we never fake "Open now"**. Non-partner detail is
an info page (no menu).

**Conversion loop:** the old "Sample listing" badge is now **"Ordering coming soon"** (neutral).
- **Claim** (`claim_request` entity + `ClaimService` + `ClaimController`): POST `/vendor/{slug}/claim`
  (CSRF), stores a durable ClaimRequest + best-effort emails Russell (jonesrussell42@gmail.com via
  the mail channel — see FRICTION F-32). Read via `vendor/bin/waaseyaa app:claims`.
- **Demand** (`demand_vote` entity + `DemandService` + `DemandController`): POST `/vendor/{slug}/demand`
  (CSRF), one vote per device (localStorage `wsn_device` + server dedupe on vendor_slug+device_id).
  Count shows on card + detail. Ranked for outreach via `vendor/bin/waaseyaa app:demand`.
- Both POSTs reuse the shared `App\Http\Csrf` helper (X-XSRF-TOKEN); partners are rejected (422).
- Footer: "Suggest a correction / add a place" → mailto jonesrussell42@gmail.com; trust note added.

Tests: `OpenHoursTest`, `VerifiedSeedDataTest` (counts/exclusions), `DemandServiceTest`,
`DemandRouteCsrfTest`, `ClaimRouteCsrfTest`, `VendorPageTest` (honesty gating + open tri-state).

**Meedjims = "Opening soon" (demoted):** Meedjims' kitchen isn't open yet, so it's a directory
listing like the rest, but with a distinct **"Opening soon"** badge (`opening_soon=true` on the
vendor; drives `.tag.opening`) vs everyone else's **"Ordering coming soon"**. No menu, no cart,
no order button, no reviews, no draft prices, and **never "Open now"** (`openStatus` returns null
for it). It keeps its building photo, town, cuisine, Call, Directions, and the "I'd order here 👍"
demand button. Its menu/photos/reviews scaffolding is dormant — flip `partner` back to `true` in
`VendorData` to re-enable ordering in one step. `OrderService` also rejects non-partners, so
nothing is orderable end-to-end.

**Known follow-up (MVP-acceptable):** the claim/demand POSTs are CSRF-protected but **not
rate-limited** — a same-origin script (or a user replaying their own token) could spam rows /
inflate demand (device dedupe is client-supplied, advisory only). Fine for the alpha; add a
per-session/IP throttle before opening up. The demand count is an advisory signal, not a unique
metric.
