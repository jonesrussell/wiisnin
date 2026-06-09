<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AppShell from '../Layouts/AppShell.vue'
import VendorCard from '../components/VendorCard.vue'

defineProps({
  app: { type: Object, required: true },
  community: { type: Object, required: true },
  vendors: { type: Array, default: () => [] },
})

const TINTS = ['#E8612C', '#1D9E75', '#3C6E89', '#C46A2B']
function tint(i) { return TINTS[i % TINTS.length] }
</script>

<template>
  <Head :title="`${community.name} — ${app.name}`" />
  <AppShell :app="app">
    <template #header-right><Link href="/" class="back">‹ Near you</Link></template>

    <h1 class="h1">{{ community.name }}</h1>
    <p class="lead">Kitchens serving {{ community.name }}.</p>

    <div class="vgrid">
      <VendorCard v-for="(v, i) in vendors" :key="v.id" :v="v" :tint="tint(i)" />
    </div>

    <div v-if="vendors.length === 0" class="empty">No kitchens here yet in {{ community.name }}.</div>
  </AppShell>
</template>
