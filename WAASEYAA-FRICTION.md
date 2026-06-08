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

_(populated as Phase 1 is built)_
