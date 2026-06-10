<script setup>
import { Link } from '@inertiajs/vue3'
import { onMounted } from 'vue'
import { useI18n } from '../i18n.js'
import { initAnalytics } from '../analytics.js'

defineProps({
  app: { type: Object, required: true },
})

const { locale, setLocale, t } = useI18n()

// First-party analytics: pageview per navigation + engagement beacon. Guarded
// internally (DNT/GPC, init-once) so calling it from every page is safe.
onMounted(initAnalytics)
</script>

<template>
  <div class="wsn-app">
    <header class="wsn-header">
      <Link href="/" class="wsn-brand">
        {{ app.name }}
        <small>{{ t('tagline') }}</small>
      </Link>
      <div style="display:flex;align-items:center;gap:8px;margin-left:auto">
        <slot name="header-right" />
        <span class="greet">{{ t('greeting') }}</span>
        <div class="langtoggle" role="group" aria-label="Language">
          <button :class="{ on: locale === 'en' }" @click="setLocale('en')" aria-label="English">EN</button>
          <button :class="{ on: locale === 'oj' }" @click="setLocale('oj')" aria-label="Nishnaabemwin">Nish</button>
        </div>
      </div>
    </header>

    <main class="wsn-wrap">
      <slot />
    </main>

    <footer class="wsn-foot">
      <slot name="footer">
        <p>Pay cash or e-transfer on pickup or delivery · order-taking only</p>
        <p class="foot-trust">
          Listings use public information. Are we wrong about your business?
          <a href="mailto:jonesrussell42@gmail.com?subject=Wiisnin%20correction%20or%20new%20place">Suggest a correction / add a place</a>
          to update or remove it.
        </p>
      </slot>
    </footer>
  </div>
</template>
