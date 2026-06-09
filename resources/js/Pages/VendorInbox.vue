<script setup>
import { Head } from '@inertiajs/vue3'
import { onMounted, onUnmounted, ref } from 'vue'
import AppShell from '../Layouts/AppShell.vue'
import { money, timeAgo } from '../lib/format.js'

const props = defineProps({
  app: { type: Object, required: true },
  vendor: { type: Object, default: null },
  orders: { type: Array, default: () => [] },
  mercure: { type: Object, default: () => ({ url: '', topic: '' }) },
})

const orders = ref([...props.orders])
const connected = ref(false)
const freshIds = ref(new Set())
let es = null
let poll = null

async function refetch() {
  if (!props.vendor) return
  try {
    const res = await fetch(`/api/vendor/${props.vendor.id}/orders`, { credentials: 'same-origin' })
    if (!res.ok) return
    const data = await res.json()
    const prev = new Set(orders.value.map((o) => o.id))
    const next = data.orders || []
    next.forEach((o) => { if (!prev.has(o.id)) markFresh(o.id) })
    orders.value = next
  } catch (e) { /* keep */ }
}
function markFresh(id) {
  const s = new Set(freshIds.value); s.add(id); freshIds.value = s
  setTimeout(() => { const n = new Set(freshIds.value); n.delete(id); freshIds.value = n }, 2600)
}
async function advance(order, to) {
  try {
    const res = await fetch(`/vendor/orders/${order.id}/transition`, {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin',
      body: JSON.stringify({ to }),
    })
    if (res.ok) await refetch()
  } catch (e) { /* ignore */ }
}
function startPolling() { if (!poll) poll = setInterval(refetch, 5000) }

onMounted(() => {
  if (props.mercure.url && props.mercure.topic && 'EventSource' in window) {
    es = new EventSource(`${props.mercure.url}?topic=${encodeURIComponent(props.mercure.topic)}`)
    es.onopen = () => { connected.value = true }
    es.onmessage = () => refetch()
    es.onerror = () => { connected.value = false; startPolling() }
  } else { startPolling() }
})
onUnmounted(() => { if (es) es.close(); if (poll) clearInterval(poll) })
</script>

<template>
  <Head :title="`Order inbox — ${app.name}`" />
  <AppShell :app="app">
    <template #header-right>
      <span class="live" :class="{ on: connected }"><i></i>{{ connected ? 'Live' : 'Polling' }}</span>
    </template>

    <div class="inbox-head">
      <h2 class="h2" style="margin:8px 0">{{ vendor ? vendor.name + ' · orders' : 'Orders' }}</h2>
    </div>
    <p class="lead">New orders appear here automatically — no refresh needed.</p>

    <div v-if="orders.length === 0" class="empty">No orders yet. Place one from a phone and watch it land here.</div>

    <article v-for="order in orders" :key="order.id" class="ordcard" :class="{ fresh: freshIds.has(order.id) }">
      <div class="top">
        <h4>{{ order.reference }} · {{ order.customer_name || 'Guest' }}</h4>
        <span class="tag" :class="order.status === 'completed' || order.status === 'cancelled' ? 'closed' : 'open'">{{ order.status }}</span>
      </div>
      <p class="meta">{{ order.items.map(l => `${l.quantity}× ${l.name}`).join(' · ') }}</p>
      <p class="meta">{{ order.fulfilment }} · {{ order.payment_method }}<span v-if="order.notes"> · “{{ order.notes }}”</span> · {{ timeAgo(order.placed_at) }}</p>
      <div style="font-family:var(--font-display);font-weight:800;margin-top:6px">{{ money(order.total_cents) }} <span class="draft">draft</span></div>
      <div style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap">
        <button v-for="t in order.transitions" :key="t.id" class="statusbtn" :class="{ alt: t.to === 'cancelled' }" @click="advance(order, t.to)">{{ t.label }}</button>
        <span v-if="!order.transitions || order.transitions.length === 0" class="meta">— done —</span>
      </div>
    </article>
  </AppShell>
</template>
