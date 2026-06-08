import '../css/app.css'
import { createApp, h } from 'vue'
import { createInertiaApp } from '@inertiajs/vue3'

// Waaseyaa's RootTemplateRenderer emits the Inertia page payload inside a
// <script type="application/json" data-page="app"> element rather than the
// conventional `data-page` attribute on the mount node, so read it explicitly
// and hand it to Inertia as the initial page. (See WAASEYAA-FRICTION.md.)
const pageEl = document.querySelector('script[data-page="app"]')
const initialPage = pageEl?.textContent ? JSON.parse(pageEl.textContent) : undefined

createInertiaApp({
  page: initialPage,
  resolve: (name) => {
    const pages = import.meta.glob('./Pages/**/*.vue', { eager: true })
    const page = pages[`./Pages/${name}.vue`]
    if (!page) {
      throw new Error(`Inertia page not found: ${name}`)
    }
    return page as { default: unknown }
  },
  setup({ el, App, props, plugin }) {
    createApp({ render: () => h(App, props) })
      .use(plugin)
      .mount(el)
  },
})
