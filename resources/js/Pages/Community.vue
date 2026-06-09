<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import AppShell from '../Layouts/AppShell.vue'

defineProps({
  app: { type: Object, required: true },
  community: { type: Object, required: true },
  vendors: { type: Array, default: () => [] },
})

const TINTS = ['#E8612C', '#1D9E75', '#3C6E89', '#C46A2B']
function open(v) {
  if (v.is_partner) router.visit('/vendor/' + v.slug)
}
</script>

<template>
  <Head :title="`${community.name} — ${app.name}`" />
  <AppShell :app="app">
    <template #header-right><Link href="/" class="back">‹ Near you</Link></template>

    <h1 class="h1">{{ community.name }}</h1>
    <p class="lead">Kitchens serving {{ community.name }}.</p>

    <button v-for="(v, i) in vendors" :key="v.id" class="vcard" :class="{ sample: !v.is_partner }" @click="open(v)">
      <div class="vthumb" :style="{ background: TINTS[i % TINTS.length] }"></div>
      <div style="flex:1">
        <h3>{{ v.name }}</h3>
        <p>{{ v.cuisine || v.description }}</p>
        <div class="tags">
          <span class="tag" :class="v.is_open ? 'open' : 'closed'">{{ v.is_open ? 'Open now' : 'Closed' }}</span>
          <span class="tag" :class="v.is_partner ? 'live' : 'sample'">{{ v.is_partner ? 'Order now' : 'Sample listing' }}</span>
        </div>
      </div>
    </button>

    <div v-if="vendors.length === 0" class="empty">No kitchens here yet in {{ community.name }}.</div>
  </AppShell>
</template>
