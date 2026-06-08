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
let es = null
let poll = null

async function refetch() {
  if (!props.vendor) return
  try {
    const res = await fetch(`/api/vendor/${props.vendor.id}/orders`, { credentials: 'same-origin' })
    if (res.ok) {
      const data = await res.json()
      orders.value = data.orders || []
    }
  } catch (e) { /* keep current list */ }
}

async function advance(order, to) {
  try {
    const res = await fetch(`/vendor/orders/${order.id}/transition`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify({ to }),
    })
    if (res.ok) await refetch()
  } catch (e) { /* ignore */ }
}

function startPolling() {
  if (poll) return
  poll = setInterval(refetch, 5000)
}

onMounted(() => {
  if (props.mercure.url && props.mercure.topic && 'EventSource' in window) {
    const url = `${props.mercure.url}?topic=${encodeURIComponent(props.mercure.topic)}`
    es = new EventSource(url)
    es.onopen = () => { connected.value = true }
    es.onmessage = () => { refetch() }
    es.onerror = () => { connected.value = false; startPolling() }
  } else {
    startPolling()
  }
})

onUnmounted(() => {
  if (es) es.close()
  if (poll) clearInterval(poll)
})
</script>

<template>
  <Head :title="`Order inbox — ${app.name}`" />
  <AppShell :app="app">
    <div class="inbox-head">
      <div>
        <p class="wsn-eyebrow">Vendor inbox</p>
        <h1 class="wsn-h1" style="margin:0">{{ vendor ? vendor.name : 'Orders' }}</h1>
      </div>
      <span class="live-dot" :class="{ on: connected }">
        <i></i>{{ connected ? 'Live' : 'Polling' }}
      </span>
    </div>
    <p class="wsn-lead">New orders appear here automatically — no refresh needed.</p>

    <div v-if="orders.length === 0" class="empty">
      No orders yet. Place one from the customer side and watch it land here.
    </div>

    <article v-for="order in orders" :key="order.id" class="card order-card">
      <div class="card-row">
        <strong class="price">{{ order.reference }}</strong>
        <span class="badge badge-status">{{ order.status }}</span>
      </div>
      <p class="meta">
        {{ order.customer_name || 'Guest' }} · {{ order.contact_phone }} ·
        {{ order.fulfilment }} · pay {{ order.payment_method }}
        <span class="muted">· {{ timeAgo(order.placed_at) }}</span>
      </p>

      <ul class="order-lines">
        <li v-for="(l, i) in order.items" :key="i">
          <span>{{ l.quantity }} × {{ l.name }}</span>
          <span class="price">{{ money(l.line_total_cents) }}</span>
        </li>
      </ul>
      <div class="card-row" style="border-top:1px solid var(--line);padding-top:.4rem">
        <span class="muted" v-if="order.notes">“{{ order.notes }}”</span><span v-else></span>
        <strong class="price">{{ money(order.total_cents) }} <span class="badge badge-draft">draft</span></strong>
      </div>

      <div class="row-actions" style="margin-top:.7rem;flex-wrap:wrap">
        <button
          v-for="t in order.transitions"
          :key="t.id"
          class="btn btn-sm"
          :class="{ 'btn-primary': t.to !== 'cancelled' }"
          @click="advance(order, t.to)"
        >{{ t.label }}</button>
        <span v-if="!order.transitions || order.transitions.length === 0" class="muted">— done —</span>
      </div>
    </article>
  </AppShell>
</template>
