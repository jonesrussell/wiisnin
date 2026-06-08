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
      <div style="text-align:center;margin:1rem 0 1.4rem">
        <div class="tick" aria-hidden="true">✓</div>
        <p class="wsn-eyebrow">Order placed</p>
        <div class="confirm-ref">{{ order.reference }}</div>
        <p class="wsn-lead">Thanks, {{ order.customer_name }}! The vendor has been notified.</p>
      </div>

      <div class="card">
        <ul class="order-lines">
          <li v-for="(l, i) in order.items" :key="i">
            <span>{{ l.quantity }} × {{ l.name }}</span>
            <span class="price">{{ money(l.line_total_cents) }}</span>
          </li>
        </ul>
        <div class="card-row" style="border-top:1px solid var(--line);padding-top:.5rem;margin-top:.3rem">
          <strong>Total <span class="badge badge-draft">draft</span></strong>
          <strong class="price">{{ money(order.total_cents) }}</strong>
        </div>
        <p class="meta" style="margin-top:.6rem">
          {{ order.fulfilment }} · pay {{ order.payment_method }} on {{ order.fulfilment }} · {{ order.contact_phone }}
        </p>
      </div>

      <p class="muted" style="font-size:.82rem;text-align:center">
        Pricing is draft and will be confirmed with Meedjims before any payment.
      </p>
      <Link href="/" class="btn btn-block" style="margin-top:1rem;text-align:center">Back home</Link>
    </template>

    <template v-else>
      <div class="error" style="margin-top:1rem">{{ error || 'We could not place that order.' }}</div>
      <Link href="/" class="btn btn-block">Back home</Link>
    </template>
  </AppShell>
</template>
