<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import { computed, reactive, ref } from 'vue'
import AppShell from '../Layouts/AppShell.vue'
import { money } from '../lib/format.js'

const props = defineProps({
  app: { type: Object, required: true },
  vendor: { type: Object, default: null },
  menu: { type: Array, default: () => [] },
  pricingDraft: { type: Boolean, default: true },
})

// cart: menuItemId -> { item, qty }
const cart = reactive({})
const showCheckout = ref(false)
const submitting = ref(false)

const form = reactive({
  customer_name: '',
  contact_phone: '',
  fulfilment: 'pickup',
  payment_method: 'cash',
  address: '',
  notes: '',
})

function add(item) {
  if (!cart[item.id]) cart[item.id] = { item, qty: 0 }
  cart[item.id].qty++
}
function remove(item) {
  if (!cart[item.id]) return
  cart[item.id].qty--
  if (cart[item.id].qty <= 0) delete cart[item.id]
}

const lines = computed(() => Object.values(cart))
const count = computed(() => lines.value.reduce((n, l) => n + l.qty, 0))
const subtotalCents = computed(() => lines.value.reduce((n, l) => n + l.qty * l.item.price_cents, 0))
const canPlace = computed(() =>
  count.value > 0 && form.customer_name.trim() !== '' && form.contact_phone.trim() !== '' && !submitting.value,
)

function placeOrder() {
  if (!canPlace.value) return
  submitting.value = true
  router.post('/order', {
    vendor_id: props.vendor.id,
    customer_name: form.customer_name,
    contact_phone: form.contact_phone,
    fulfilment: form.fulfilment,
    payment_method: form.payment_method,
    address: form.address,
    notes: form.notes,
    lines: lines.value.map((l) => ({ menu_item_id: l.item.id, quantity: l.qty })),
  }, {
    onFinish: () => { submitting.value = false },
  })
}
</script>

<template>
  <Head :title="vendor ? `${vendor.name} — ${app.name}` : app.name" />
  <AppShell :app="app">
    <template v-if="vendor">
      <p class="wsn-eyebrow">
        <Link :href="`/c/${(vendor.community || '').toLowerCase()}`">← {{ vendor.community }}</Link>
      </p>
      <div class="card-row">
        <h1 class="wsn-h1">{{ vendor.name }}</h1>
        <span class="badge" :class="vendor.is_open ? 'badge-open' : 'badge-closed'">
          {{ vendor.is_open ? 'Open' : 'Closed' }}
        </span>
      </div>
      <p class="wsn-lead">{{ vendor.description }}</p>

      <div v-if="pricingDraft" class="draft-banner">
        <strong>Draft pricing</strong> — prices below are drafts to be confirmed with Meedjims.
      </div>

      <!-- Menu -->
      <template v-if="!showCheckout">
        <section v-for="group in menu" :key="group.category">
          <h2 class="menu-cat-title">{{ group.category }}</h2>
          <div class="card">
            <div v-for="item in group.items" :key="item.id" class="menu-item">
              <div>
                <div class="menu-item-name">{{ item.name }}</div>
                <div class="menu-item-sub">
                  <span class="price">{{ money(item.price_cents) }}</span>
                  <span class="badge badge-draft" style="margin-left:.4rem">draft</span>
                </div>
              </div>
              <div class="row-actions">
                <button v-if="cart[item.id]" class="btn btn-step" @click="remove(item)" aria-label="remove">−</button>
                <span v-if="cart[item.id]" class="qty">{{ cart[item.id].qty }}</span>
                <button class="btn btn-step btn-primary" @click="add(item)" aria-label="add">+</button>
              </div>
            </div>
          </div>
        </section>
      </template>

      <!-- Checkout -->
      <template v-else>
        <h2 class="wsn-h2">Your order</h2>
        <div class="card">
          <ul class="order-lines">
            <li v-for="l in lines" :key="l.item.id">
              <span>{{ l.qty }} × {{ l.item.name }}</span>
              <span class="price">{{ money(l.qty * l.item.price_cents) }}</span>
            </li>
          </ul>
          <div class="card-row" style="border-top:1px solid var(--line);padding-top:.5rem;margin-top:.3rem">
            <strong>Subtotal <span class="badge badge-draft">draft</span></strong>
            <strong class="price">{{ money(subtotalCents) }}</strong>
          </div>
        </div>

        <h2 class="wsn-h2">Your details</h2>
        <div class="field">
          <label>Name</label>
          <input v-model="form.customer_name" type="text" placeholder="Your name" />
        </div>
        <div class="field">
          <label>Phone</label>
          <input v-model="form.contact_phone" type="tel" placeholder="705-…" />
        </div>
        <div class="field">
          <label>Pickup or delivery</label>
          <div class="seg">
            <button class="btn" :class="{ on: form.fulfilment === 'pickup' }" @click="form.fulfilment = 'pickup'">Pickup</button>
            <button class="btn" :class="{ on: form.fulfilment === 'delivery' }" @click="form.fulfilment = 'delivery'">Delivery</button>
          </div>
        </div>
        <div v-if="form.fulfilment === 'delivery'" class="field">
          <label>Delivery address</label>
          <input v-model="form.address" type="text" placeholder="Street, community" />
        </div>
        <div class="field">
          <label>Payment (collected on {{ form.fulfilment }})</label>
          <div class="seg">
            <button class="btn" :class="{ on: form.payment_method === 'cash' }" @click="form.payment_method = 'cash'">Cash</button>
            <button class="btn" :class="{ on: form.payment_method === 'etransfer' }" @click="form.payment_method = 'etransfer'">E-transfer</button>
          </div>
        </div>
        <div class="field">
          <label>Notes (optional)</label>
          <textarea v-model="form.notes" placeholder="e.g. no onions"></textarea>
        </div>
        <button class="btn" @click="showCheckout = false">← Back to menu</button>
      </template>
    </template>

    <div v-else class="empty">Vendor not found.</div>

    <!-- Sticky cart bar -->
    <div v-if="vendor && count > 0" class="cart-bar">
      <div class="cart-bar-inner">
        <div>
          <div><strong>{{ count }}</strong> item{{ count === 1 ? '' : 's' }}</div>
          <div class="muted" style="font-size:.8rem">{{ money(subtotalCents) }} · draft</div>
        </div>
        <button v-if="!showCheckout" class="btn btn-primary btn-block" @click="showCheckout = true">
          Review order
        </button>
        <button v-else class="btn btn-primary btn-block" :disabled="!canPlace" @click="placeOrder">
          {{ submitting ? 'Placing…' : 'Place order' }}
        </button>
      </div>
    </div>
  </AppShell>
</template>
