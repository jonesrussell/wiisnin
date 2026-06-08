import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'node:path'

// Build output lands in public/build/ with a manifest. Waaseyaa's
// ViteAssetManager reads public/build/.vite/manifest.json and emits the hashed
// <script>/<link> tags into the Inertia root template. `base: '/build/'` makes
// runtime chunk URLs resolve under the same path the PHP server serves them from.
export default defineConfig({
  base: '/build/',
  plugins: [vue()],
  // The build target lives inside public/, so disable Vite's publicDir copy to
  // avoid it pulling public/index.php (and friends) into public/build/.
  publicDir: false,
  build: {
    manifest: true,
    outDir: resolve(__dirname, 'public/build'),
    emptyOutDir: true,
    rollupOptions: {
      // Matches the dev entrypoint ViteAssetManager hardcodes (resources/js/app.ts).
      input: resolve(__dirname, 'resources/js/app.ts'),
    },
  },
})
