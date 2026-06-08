<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AppShell from '../Layouts/AppShell.vue'

defineProps({
  app: { type: Object, required: true },
  communities: { type: Array, required: true },
})
</script>

<template>
  <Head :title="`${app.name} — North Shore food ordering`" />
  <AppShell :app="app">
    <p class="wsn-eyebrow">Wiisnin · “eat” (Nishnaabemwin, Sagamok dialect)</p>
    <h1 class="wsn-h1">Order from your community</h1>
    <p class="wsn-lead">{{ app.tagline }} Choose your community to see what's open.</p>

    <Link v-for="c in communities" :key="c.slug" class="card" :href="`/c/${c.slug}`">
      <div class="card-row">
        <div>
          <h3>{{ c.name }}</h3>
          <p>{{ c.blurb }}</p>
        </div>
        <span aria-hidden="true" class="price">›</span>
      </div>
    </Link>
  </AppShell>
</template>
