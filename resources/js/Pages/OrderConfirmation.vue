<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AppShell from '../Layouts/AppShell.vue'
import { money } from '../lib/format.js'

defineProps({
  app: { type: Object, required: true },
  order: { type: Object, default: null },
  error: { type: String, default: null },
})
</script>

<template>
  <Head :title="`Order — ${app.name}`" />
  <AppShell :app="app">
    <template v-if="order">
      <div class="conf">
        <div class="check" aria-hidden="true">✓</div>
        <h2>Miigwech, {{ order.customer_name || 'friend' }}!</h2>
        <div class="ref">Order {{ order.reference }}</div>
        <p>Meedjims Foodland has been notified and will start your order.</p>
        <p style="color:var(--muted)">You'll get a text when it's ready for {{ order.fulfilment }}.</p>
      </div>

      <div class="orow" v-for="(l, i) in order.items" :key="i"><span>{{ l.quantity }}× {{ l.name }}</span><span>{{ money(l.line_total_cents) }}</span></div>
      <div class="orow tot"><span>Total <span class="draft">draft</span></span><span>{{ money(order.total_cents) }}</span></div>
      <p class="lead" style="text-align:center;margin-top:14px">Pay {{ order.payment_method }} on {{ order.fulfilment }}. Pricing is draft, confirmed with the kitchen.</p>
      <Link href="/" class="cta" style="background:var(--orange)">Back to start</Link>
    </template>

    <template v-else>
      <div class="error" style="margin-top:18px">{{ error || 'We could not place that order.' }}</div>
      <Link href="/" class="cta" style="background:var(--orange)">Back home</Link>
    </template>
  </AppShell>
</template>
