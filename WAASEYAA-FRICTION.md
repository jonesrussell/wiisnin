# Waaseyaa friction log — Wiisnin build

This file is a primary deliverable of the Wiisnin build: a deliberate stress test of
the Waaseyaa framework ahead of its beta. Each entry is something that was awkward,
surprising, or broken while building a real app, with enough specifics to act on.

Framework baseline: `waaseyaa/framework` **0.1.0-alpha.192** (PHP 8.5.5, Composer 2.9.5,
Windows 11). Reference apps consulted: `fnpi-waaseyaa`, `oiatc-waaseyaa`.

Legend: 🔴 blocker (had to work around to proceed) · 🟡 papercut · 🔵 docs/DX gap.

---

## Phase 0 — scaffold + Inertia

### 🔴 F-01 — `v0.1.0` stable tag outranks `0.1.0-alpha.19x` but ships *older* code
The skeleton's `composer.json` pins `waaseyaa/framework: "^0.1.0-alpha.150"`. With
`minimum-stability: dev` + `prefer-stable: true`, Composer resolves this to the **stable
`v0.1.0` tag**, which is semver-higher than any `0.1.0-alpha.NNN` prerelease — but that
stable tag pins the layer packages to **alpha.158**, code that predates the current
alpha.19x line by ~34 releases. So a fresh `composer create-project` silently installs a
months-stale framework.

- **Impact:** everything below (F-02) follows from getting the stale set.
- **Fix applied:** pinned `^0.1.0-alpha.190` for `framework` and the add-on packages,
  then `composer update "waaseyaa/*" --with-all-dependencies` → consistent alpha.192.
- **Suggested upstream fix:** don't publish a `v0.1.0` stable tag while alpha.19x is the
  real head, or have the skeleton track `^0.1.0-alpha.<latest>`.

### 🔴 F-02 — Optional packages require Foundation classes the stale set doesn't have
`composer require waaseyaa/inertia` (and `groups`/`notification`/`mercure`) pulls them at
alpha.175+, which reference `Waaseyaa\Foundation\ServiceProvider\Capability\HasMiddlewareInterface`.
That interface (and the whole `ServiceProvider/Capability/` namespace) does **not exist**
in foundation alpha.158. Result: `php bin/waaseyaa optimize:manifest` fatals with
`Interface "...HasMiddlewareInterface" not found` during manifest compilation — the package
discovery scanner reflects every provider class and dies on the first dangling interface.

- **Root cause:** version skew between the framework meta-set and the standalone add-ons.
- **Fix applied:** align the whole tree to alpha.192 (see F-01).
- **Suggested upstream fix:** the add-on packages should declare a `waaseyaa/foundation`
  constraint tight enough (`^0.1.0-alpha.175`) that Composer refuses the bad combination
  up front instead of failing at runtime.

### 🟡 F-03 — Framework meta double-autoloads `packages/github` and `packages/engagement`
`waaseyaa/framework` alpha.192 is a meta-package that `require`s all 69 sub-packages
(correct — same vendor layout as fnpi). But its own `autoload.psr-4` *also* maps
`Waaseyaa\GitHub\` → `packages/github/src/` and `Waaseyaa\Engagement\` → `packages/engagement/src/`,
which duplicate the standalone `waaseyaa/github` and `waaseyaa/engagement` installs.
`composer dump-autoload` prints ~10 "Ambiguous class resolution" warnings.

- **Impact:** noise only — Composer uses the first match and the app runs. Present in
  `fnpi-waaseyaa` too, so it's pre-existing, not something this app introduced.
- **Suggested upstream fix:** drop those two psr-4 entries from the meta-package, or
  `exclude-from-classmap` the bundled `packages/` copies.

### 🔴 F-04 — Inertia asset base resolved from `getcwd()`, breaks under `php -S -t public`
`InertiaServiceProvider::register()` calls `registerWithRoot(null)`, which falls back to
`getcwd()` for the Vite asset base (`{cwd}/public`). Under `php -S 127.0.0.1:8080 -t public`
the worker's `getcwd()` is the **docroot** (`public/`), so `ViteAssetManager` looks for the
manifest at `public/public/build/.vite/manifest.json`, finds nothing, and emits an **empty**
`<head>` — no `<script>`/`<link>`, so Vue never boots. The page returns 200 with a blank
mount node and no error.

- **Fix applied:** `chdir($projectRoot)` at the top of `public/index.php` (the kernel
  already knows the project root; this just makes `getcwd()` agree). Robust across the
  built-in server and FPM.
- **Suggested upstream fix:** `InertiaServiceProvider` should prefer `$this->projectRoot`
  (set via `setKernelContext`) over `getcwd()`.

### 🟡 F-05 — Inertia root template emits payload in a `<script>` tag, not `data-page` attr
`RootTemplateRenderer` renders the page object as
`<script type="application/json" data-page="app">{json}</script>` next to `<div id="app">`,
rather than the Inertia-conventional `<div id="app" data-page="{json}">`. The stock
`@inertiajs/vue3` `createInertiaApp` reads `el.dataset.page` and would find nothing.

- **Fix applied:** the JS entry (`resources/js/app.ts`) reads the `<script data-page="app">`
  element explicitly and passes it as `createInertiaApp({ page })`.
- **Suggested upstream fix:** document this, or switch to the standard attribute so the
  default client bootstrap works unmodified.

### 🔵 F-06 — `ViteAssetManager` hardcodes the dev entrypoint and bundle name
`RootTemplateRenderer` calls `assetManager->assetTags()` with no args, so the dev-server
path is hardwired to bundle `build` and entrypoint `resources/js/app.ts`. A project that
names its entry `app.js` (or builds to a different folder) gets a 404 in dev mode with no
hint. We named the entry `resources/js/app.ts` and built to `public/build/` to match.

- **Suggested upstream fix:** make bundle/entrypoint configurable (env or provider option).

### 🟡 F-07 — Vite `publicDir` overlaps the build target
Building into `public/build/` while Vite's default `publicDir` is `public/` makes Vite copy
`public/index.php` into `public/build/` and warn. Set `publicDir: false` in `vite.config.js`.
Not a framework bug, but a predictable snag for the prescribed `inertia` + `public/` layout;
worth a note in the inertia README.

### 🔵 F-08 — Windows: `post-create-project-cmd` aborts on `chmod`
The skeleton's `post-create-project-cmd` runs `chmod +x bin/maintenance/*` before
`php bin/post-create-setup.php`. `chmod` isn't a PowerShell command, so the script chain
aborts and `.env` is never generated. Worked around with
`composer create-project ... --no-scripts` then `php bin/post-create-setup.php` manually.

- **Suggested upstream fix:** guard the `chmod` (skip on Windows) or move it into the PHP
  setup script which already runs cross-platform.

### 🔵 F-09 — Add-on package docs are stubs
`waaseyaa/inertia`'s README is a 10-line blurb; `groups`/`notification`/`mercure` are
similar. The real API (`Inertia::render`, `InertiaResponse`, the `ControllerDispatcher`
hand-off, `ViteAssetManager` manifest paths) had to be reverse-engineered from `vendor/`
source and unit tests. Fine for a dogfooder, rough for a beta audience.

---

## Phase 1 — entities, workflow, access, notifications

### 🔴 F-10 — `#[Field]` is `FieldStorage::Column` by default, but the default backend makes no columns
The default `sql-blob` storage backend stores **only the entity keys** (id, uuid,
bundle, label, langcode) as real columns; every other field goes into a `_data`
JSON blob. But `#[Field(stored:)]` defaults to `FieldStorage::Column`. The two
disagree silently: a full-entity load still works (the read path merges `_data`
back), but **`findBy(['vendor_id' => …])` emits `vendor.vendor_id` against a
column that doesn't exist** unless the field is declared `FieldStorage::Data`
(then the query uses `json_extract(_data,'$.vendor_id')`,
`SqlEntityQuery.php:275`). So the queryable-by-default mental model is inverted:
you must opt every queried field into `FieldStorage::Data`.

- **Fix applied:** marked all non-key fields `FieldStorage::Data`. Verified with
  `app:selfcheck` (blob-field `findBy(vendor_id)` returns the right rows).
- **Suggested upstream fix:** when the primary backend is `sql-blob`, either
  treat `Column` fields as `Data` automatically, or fail `schema:sync` loudly if
  a `Column` field has no column. The silent column-vs-blob split is a footgun.

### 🟡 F-11 — `relationship` package is graph-shaped; wrong tool for belongs-to
The `relationship` package models a relationship as its own entity record
(`from_entity_type/id` → `to_entity_type/id`). That's a knowledge-graph edge, not
a foreign key. For an order-taking domain (menu item belongs to vendor, line
belongs to order) it would mean an extra record + lookup per association and no
natural cascade. The lighter `entity_reference` field type couples to
target-existence validation. I used plain integer FK fields (`vendor_id`,
`order_id`, …) + `json_extract` queries instead — simplest and most honest for
this domain. Worth noting the spec said "use the relationship package"; it
doesn't fit simple ownership.

### 🟡 F-12 — entity type id `order` collides with the SQL reserved word
Registering an entity type `order` creates a table named `order`. The storage
layer quotes identifiers so save/find round-trip fine (verified), but it's a
latent footgun: any hand-written SQL must quote it, and some tools/migrations
won't. A framework lint warning for reserved-word type ids would help.

### 🔴 F-13 — `#[PolicyAttribute]` policies are auto-instantiated at boot via the container
Unlike fnpi (whose policies are no-arg and assembled by hand in a
`WorkspaceAccess::handler()` factory), the kernel **discovers `#[PolicyAttribute]`
policies and constructs them through the container at boot**. A policy with a
constructor dependency (here `VendorStaffDirectory`) hard-fails boot —
`Cannot resolve constructor parameter "directory" … not bound in the kernel
container` — until that dependency is bound in `ServiceProvider::register()`.

- **Fix applied:** bound `VendorStaffDirectory` as a singleton in
  `CommerceServiceProvider::register()`.
- **Suggested upstream:** document that policy constructor deps must be
  container-bound, and ideally degrade a single unconstructable policy to a clear
  per-policy error rather than failing the whole kernel boot.

### 🟡 F-14 — `groups` package has no membership concept
`groups` ships `Group` + `GroupType` but no membership table or API; "who is in
this group" is left entirely to the app. To scope `vendor_staff` to their
vendor's group I had to define a `GroupMembership` entity and query it myself.
Creating a `Group` with bundle `vendor` worked without pre-declaring a
`GroupType` (good). A first-class membership primitive would remove a lot of
boilerplate that every multi-tenant app will re-invent identically.

### 🔴 F-15 — no Mercure notification channel exists; dispatcher channels are build-time only
`notification`'s `NotificationServiceProvider` wires only `mail` + `database`
channels, and `mercure` ships only a `MercurePublisher` — there is **no Mercure
`ChannelInterface`**. To satisfy "notify via Mercure + mail" I wrote a
`MercureChannel` adapter and built my own `NotificationDispatcher` (it's a
`final` class taking the channel map at construction, with no hook to add a
channel to the package's existing instance). So adding any channel beyond
mail/database means replacing the dispatcher binding.

- **Suggested upstream:** ship a `MercureChannel` in the mercure package, and let
  channels be registered into the dispatcher via a tag/collector rather than a
  hardcoded `buildChannels()`.

### 🟢 F-16 — workflows value objects are clean (positive)
`Workflow` + `WorkflowState` + `WorkflowTransition` (hydrated from a states/
transitions array, with `getValidTransitions()` / `isTransitionAllowed()`) were a
pleasure — the Order state machine is ~25 lines and fully unit-testable with no
kernel. The heavier `EditorialWorkflowService` assumes node-ish entities with a
`workflow_state` field + editorial permissions, so for a plain `status` field I
used the `Workflow` definition directly via a thin app service. Good primitives;
just make the low-level path the documented default.

### 🔵 F-17 — taxonomy `Term` has a fixed field set (per-vendor vocab is awkward)
The spec wanted "a menu_category vocabulary scoped per vendor." `Term` can't
carry a custom `vendor_id` without extending the framework entity, so a single
shared `menu_category` vocabulary with app-layer per-vendor scoping is the
pragmatic skeleton choice. Per-vendor term scoping would need either per-vendor
vocabularies (id explosion) or extensible term fields.

### 🔵 F-18 — `schema:check` reports drift on framework-shipped tables out of the box
On a freshly recreated DB (`db:init --sync-schema`), `schema:check` still exits
non-zero because framework tables drift from their own entity definitions:
`oidc_client` (and, before the DB was recreated, the `audit_*` tables) are created
by migrations with `VARCHAR(n)` columns where the entity definitions expect
`TEXT`. None of Wiisnin's own tables drift. So the app can't use a clean
`schema:check` as a CI gate until the framework reconciles its migration-created
column types with its entity-derived expected types.

---

## Phase B — production deploy (Raspberry Pi: Docker + Caddy + Cloudflare Tunnel + Mercure-over-TLS)

### 🔴 F-19 — production boot guard makes `db:init` un-runnable on a first deploy (chicken-and-egg)
In `APP_ENV=production` the kernel's `DatabaseBootstrapper` refuses to boot when
the SQLite file is missing — **and that guard runs before CLI command dispatch**.
So the very command the error tells you to run can't load:
```
[Waaseyaa] Boot failed: Database not found at /app/storage/waaseyaa.sqlite. In
production, the database must already exist. Run "bin/waaseyaa db:init" ...
Unknown command: db:init
```
`db:init` is only registered after a full boot, which needs the DB, which
`db:init` is supposed to create. Workaround: `touch` an empty file first (a
0-byte file is a valid empty SQLite DB), then `db:init --sync-schema` succeeds.
- **Suggested fix:** let `db:init` (and only `db:init`) run on the minimal
  console / before the missing-DB guard, so the documented first-deploy command
  actually works. This will bite every first production deploy.

### 🔴 F-20 — `db:init` alone does not create entity tables; you need `db:init --sync-schema`
Runbook 03/05 prescribe `db:init` for production. But `db:init` applies
*migrations* only — it does **not** materialize entity-type tables (vendor,
order, menu_item, taxonomy_term, group, …). Those come from `schema:sync`. A
plain `db:init` left the app with no domain tables. `db:init --sync-schema` does
both. Worth making `--sync-schema` the deploy default (or documenting loudly).

### 🔴 F-21 — Mercure-over-TLS needs hand-assembly (no first-party channel; manual hub + key match)
Confirmed in production what #1624 reports: there is no first-party Mercure
notification channel, so a live SSE inbox required:
- a standalone `dunglas/mercure` service (multi-arch arm64 — fine on the Pi);
- the app's `MERCURE_JWT_SECRET` (HS256) set equal to the hub's
  `MERCURE_PUBLISHER_JWT_KEY` **and** `MERCURE_SUBSCRIBER_JWT_KEY`, plus the
  `anonymous` directive so browser `EventSource` can subscribe without a token;
- a Caddy route for `/.well-known/mercure` with **`encode` disabled** (gzip/zstd
  buffers SSE and breaks the stream);
- the framework `MercurePublisher` signs `mercure.publish ['*']` — the hub must
  accept that publisher key. None of this is documented; reverse-engineered from
  `MercurePublisher` source.

### 🟡 F-22 — no "PHP 8.5 + Vite" reference Dockerfile
The infra had a PHP-8.5 composer-only Dockerfile (fnpi) and a PHP-8.4 composer
+vite one (giiken), but none combining **8.5 + vite**. Had to merge them, and
remember the 8.5 gotcha that `pdo_sqlite`/`mbstring`/`opcache` are bundled and
re-installing them fails the build (only `intl`+`zip` need `docker-php-ext-install`).

### 🟡 F-23 — production CLI must run as `www-data` or the SQLite file is unwritable by php-fpm
Running `db:init`/`app:seed` as the container's default user (root) creates a
root-owned `waaseyaa.sqlite` (+ WAL/journal), which php-fpm (www-data) then can't
write at runtime. Had to `docker compose exec -u www-data …`. A note in the
deploy runbook (or an entrypoint that fixes ownership post-init) would help.

### 🟢 F-24 — the chdir() asset fix (F-04) carried cleanly into production
The `chdir($projectRoot)` workaround for the Inertia/Vite asset base worked
unchanged in the container (php-fpm cwd is `/app`, getcwd → `/app/public`), so
the built `/build/` assets resolved over the tunnel with no extra prod-specific
handling. Good — but it's still a workaround for F-04.

---

## Redesign phase — location-first UI + framework features (geo / search / path / seo / structured-import)

### 🟢 F-25 — geo, search and path packages wired cleanly
`Geo\GeoDistance::haversine(lat1,lng1,lat2,lng2)` (returns km, static, no map deps)
powered the distance sort; `search` (FTS5 `SearchIndexerInterface`/`SearchProviderInterface`,
auto-registered) indexed vendor name+cuisine+menu and matched dishes ("taco"→Meedjims,
"pizza"→4 pizzerias); `path` (`PathAlias` entity + `PathAliasResolver`) resolved
`/meedjims`→the vendor. Good primitives. Two integration snags below.

### 🔴 F-26 — no app hook to server-render `<head>` meta (SEO); middleware runs pre-controller
The `seo` package builds meta HTML, but getting it into the SERVED `<head>` (required —
scrapers don't run Vue `<Head>`) has no clean app seam:
- Provider middleware (`HasMiddlewareInterface`) runs in the kernel's **authorization
  pipeline**, whose inner handler returns an empty 200 (`HttpKernel.php:~363`) — it never
  sees the rendered controller HTML, so it can't post-process the page.
- The Inertia full-page renderer can't be overridden by an app provider: the kernel
  resolves `InertiaFullPageRendererInterface` from the **first** provider that binds it, and
  app providers are appended **after** package providers (PackageManifestCompiler ~L621), so
  the package renderer always wins.
- Worked around in `public/index.php` (the front controller — the one post-dispatch hook the
  app owns) with `App\Http\SeoInjector`, which reads the vendor from SQLite (read-only PDO)
  and rewrites `</head>`. Verified via curl: og:/twitter tags are in the raw HTML.
- **Suggested fix:** a post-dispatch response filter or a per-request "head contributions"
  bag the controller can populate, rendered by the root template.

### 🟡 F-27 — app catch-all route loses to the SSR page fallback by default
A `/{alias}` route registered by an app provider 404'd live (the SSR `render.page` fallback
out-ranked it) even though it matched in isolation. Fixed with `->priority(5)` +
`->requirement('alias','[a-z0-9][a-z0-9-]*')` so it beats the fallback without shadowing
static paths (`/robots.txt` etc.). Routing precedence between app routes and framework
fallbacks isn't documented.

### 🟡 F-28 — `structured-import` is GFM single-entity, not multi-row CSV
The package maps a 2-column GFM prompt→value table onto ONE entity's fields; it doesn't fit
a multi-row CSV of many menu items. Built a small `App\Import\MenuCsvImporter` (CSV rows →
MenuItem entities, resolving category→term) for the spec's `category,item,description,
price_cents,available` import instead. A first-class tabular/CSV importer would help.

---

## Phase 2 — i18n (Anishinaabemowin) + engagement (reviews)

### 🔴 F-29 — `engagement` has no star-rating / review primitive
`engagement` ships `Comment`, `Reaction`, and `Follow`. None carry a numeric rating:
`Reaction` is a typed flag (like/emoji) with no 1–5 scale, and `Comment` is free text with
no score. The spec's "1–5 stars + text review, average + count on the vendor" has no
matching primitive, so a `Comment` would need a parallel rating store keyed by comment id —
more plumbing than just owning the data.
- **Fix applied:** small app entity `App\Entity\Review` (vendor_id, author_uid, author_name,
  rating 1–5, body, `status` visible|hidden for moderation, created_at) + `ReviewService`
  (partner-only create, average/count summary, hide). Same call made for `structured-import`
  in F-28: when the package primitive doesn't fit, own the small entity.
- **Suggested upstream:** a first-class `Rating`/`Review` type in `engagement` (subject ref +
  numeric score + optional body + moderation status), or a `score` field on `Reaction`.

### 🔵 F-30 — `i18n` `Translator` is server-side (PHP/Twig); SPA chrome needs its own client layer
The `i18n` `Translator` (loads `resources/lang/{locale}.php`, falls back through the chain,
returns the key when missing) is built for server-rendered output. Wiisnin's chrome is an
Inertia/Vue SPA, so server-side `t()` calls don't reach Vue components after hydration. Had
to build a parallel client composable (`resources/js/i18n.js`: a `locale` ref persisted to
localStorage + a `wsn_lang` cookie so the server can localize entity fields on the next
request, with the same key-returns-when-missing fallback). Per-entity translated fields use
app `*_oj` blob fields with a `Catalog::localized()` fallback (oj when non-empty, else
English). Not a bug — but "how to drive `i18n` from an Inertia front end" (share the active
locale + the dictionary as Inertia props, or ship a JS twin of `Translator`) is an undocumented
gap every SPA-on-Waaseyaa app will hit.
- **Cultural note (not framework friction, but recorded):** no Ojibwe/Nishnaabemwin words are
  invented or machine-translated. `resources/lang/oj.php` and the JS `oj` dictionary contain
  ONLY confirmed words (Ahnii, Miigwech; "Wiisnin" = eat). Every other key falls
  back to English on purpose — that is the visible "translation needed" seam for Russell / the
  community to fill in. See NOTES.md.

---

## Phase 2b — security hardening

### 🔴 F-31 — `CsrfMiddleware` exempts `application/json`, leaving app JSON POST endpoints uncovered
`CsrfMiddleware` (user package) skips CSRF validation for any request whose `Content-Type`
starts with `application/json` or `application/vnd.api+json` (`CsrfMiddleware.php:25,179-186`),
on the reasoning that "browsers cannot send `application/json` from HTML forms." That holds
**only if the endpoint strictly requires a JSON body**. Our review endpoint posted JSON *and*
accepted a form-encoded fallback (`$request->request->all()`), so a forged cross-site HTML
form (`application/x-www-form-urlencoded`) would reach it **with no CSRF check** — the order
endpoint (form-based) was protected by the middleware, the review endpoint silently was not.
- **Fix applied:** validate the token in the controller (`ReviewController::hasValidCsrfToken`)
  mirroring the middleware's accepted sources (`_csrf_token` field / `X-CSRF-Token` /
  URL-decoded `X-XSRF-TOKEN`) against `$_SESSION['_csrf_token']`; the Vue fetch echoes the
  framework's non-HttpOnly `XSRF-TOKEN` cookie as `X-XSRF-TOKEN` (the same token Inertia's
  axios sends on the order path). Token-less / bad-token POST → 403; in-app POST succeeds.
  Tested in `tests/Integration/ReviewRouteCsrfTest.php` (5 cases).
- **Suggested upstream:** either (a) don't exempt JSON content types — modern CSRF guidance
  is to validate a token regardless of content type; or (b) expose the validation as a public
  helper / route option (`->csrf(true)`) so an app can opt a JSON route back into protection
  without re-implementing `hasValidToken()`. Today the middleware's `hasValidToken()` is
  private, so the controller has to duplicate it.
  - **Update (Phase 3):** extracted the validation into an app-side `App\Http\Csrf::valid()`
    helper now shared by the review, claim, and demand endpoints (ReviewController delegates to
    it too), so the duplication is gone app-side — but the upstream gap stands.

### 🔵 F-32 — mail has no transport configured; one-off email is silent best-effort
`MailServiceProvider` reads `config['mail']`, which Wiisnin doesn't set, so it falls back to
`LocalTransport` (logs to a file, no SMTP). So the owner-claim "email Russell" can't actually
deliver in prod without a transport. Resolving `MailerInterface` works (no crash), and
`NotificationDispatcher`/our send is wrapped in try/catch, so a missing transport never breaks the
request — but the email silently no-ops.
- **Fix applied:** the durable record is the stored `claim_request` entity (the email is
  best-effort on top), and Russell reads claims reliably via `vendor/bin/waaseyaa app:claims`
  (and demand via `app:demand`). Wire `MAIL_TRANSPORT=sendgrid` + key (or SMTP) to enable real
  delivery later.
- **Suggested upstream:** ship a default mail config stub + a louder boot warning when a
  state-changing notification targets the no-op LocalTransport in production.
