<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AppShell from '../Layouts/AppShell.vue'

defineProps({
  app: { type: Object, required: true },
  community: { type: Object, required: true },
  vendors: { type: Array, required: true },
})
</script>

<template>
  <Head :title="`${community.name} — ${app.name}`" />
  <AppShell :app="app">
    <p class="wsn-eyebrow"><Link href="/">← All communities</Link></p>
    <h1 class="wsn-h1">{{ community.name }}</h1>
    <p class="wsn-lead">Vendors serving {{ community.name }}.</p>

    <Link v-for="v in vendors" :key="v.id" class="card" :href="`/vendor/${v.slug}`">
      <div class="card-row">
        <div>
          <h3>{{ v.name }}</h3>
          <p>{{ v.description }}</p>
        </div>
        <span class="badge" :class="v.is_open ? 'badge-open' : 'badge-closed'">
          {{ v.is_open ? 'Open' : 'Closed' }}
        </span>
      </div>
    </Link>

    <div v-if="vendors.length === 0" class="empty">
      No vendors here yet — Wiisnin is just getting started in {{ community.name }}.
    </div>
  </AppShell>
</template>
