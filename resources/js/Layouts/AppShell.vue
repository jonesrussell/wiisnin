<script setup>
import { Link } from '@inertiajs/vue3'
import { useI18n } from '../i18n.js'

defineProps({
  app: { type: Object, required: true },
})

const { locale, setLocale, t } = useI18n()
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
      <slot name="footer">Pay cash or e-transfer on pickup or delivery · order-taking only</slot>
    </footer>
  </div>
</template>
