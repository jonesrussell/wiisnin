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

const orderable = computed(() => props.vendor && props.vendor.is_partner)

const cart = reactive({})
const showCheckout = ref(false)
const submitting = ref(false)
const form = reactive({ customer_name: '', contact_phone: '', fulfilment: 'pickup', payment_method: 'cash', address: '', notes: '' })

function add(item) { if (!cart[item.id]) cart[item.id] = { item, qty: 0 }; cart[item.id].qty++ }
function remove(item) { if (!cart[item.id]) return; cart[item.id].qty--; if (cart[item.id].qty <= 0) delete cart[item.id] }
const lines = computed(() => Object.values(cart))
const count = computed(() => lines.value.reduce((n, l) => n + l.qty, 0))
const subtotal = computed(() => lines.value.reduce((n, l) => n + l.qty * l.item.price_cents, 0))
const canPlace = computed(() => count.value > 0 && form.customer_name.trim() && form.contact_phone.trim() && !submitting.value)

function placeOrder() {
  if (!canPlace.value) return
  submitting.value = true
  router.post('/order', {
    vendor_id: props.vendor.id, customer_name: form.customer_name, contact_phone: form.contact_phone,
    fulfilment: form.fulfilment, payment_method: form.payment_method, address: form.address, notes: form.notes,
    lines: lines.value.map((l) => ({ menu_item_id: l.item.id, quantity: l.qty })),
  }, { onFinish: () => { submitting.value = false } })
}
</script>

<template>
  <Head :title="vendor ? `Order from ${vendor.name} — ${vendor.community} · ${app.name}` : app.name" />
  <AppShell :app="app">
    <template #header-right>
      <Link href="/" class="back">‹ Near you</Link>
    </template>

    <template v-if="vendor">
      <div class="hero">
        <h2>{{ vendor.name }}</h2>
        <p>{{ vendor.cuisine }} · {{ vendor.is_open ? 'Open now' : 'Closed' }} · pickup or delivery</p>
      </div>

      <p v-if="!orderable" class="samplebar">
        Sample listing — {{ vendor.name }} isn't a Wiisnin partner yet. Browse the menu; ordering opens when they join.
      </p>
      <div v-if="pricingDraft" class="draftbar">Draft pricing — to be confirmed with {{ vendor.name }}</div>

      <!-- Menu -->
      <template v-if="!showCheckout">
        <section v-for="group in menu" :key="group.category">
          <h3 class="menu-cat-title">{{ group.category }}</h3>
          <div class="item" v-for="item in group.items" :key="item.id">
            <div class="ithumb" aria-hidden="true"></div>
            <div style="flex:1">
              <h4>{{ item.name }}</h4>
              <p v-if="item.description">{{ item.description }}</p>
            </div>
            <div style="text-align:right">
              <div class="price">{{ money(item.price_cents) }}</div>
              <span class="draft">draft</span>
            </div>
            <div v-if="orderable" class="stepper">
              <button v-if="cart[item.id]" class="add" style="background:var(--soft-orange);color:var(--orange-deep)" @click="remove(item)" aria-label="remove one">−</button>
              <span v-if="cart[item.id]" class="qty">{{ cart[item.id].qty }}</span>
              <button class="add" @click="add(item)" :aria-label="`add ${item.name}`">+</button>
            </div>
          </div>
        </section>
      </template>

      <!-- Checkout -->
      <template v-else>
        <h3 class="h2" style="margin-top:8px">Almost there</h3>
        <div class="orow" v-for="l in lines" :key="l.item.id"><span>{{ l.qty }}× {{ l.item.name }}</span><span>{{ money(l.qty * l.item.price_cents) }}</span></div>
        <div class="orow tot"><span>Total <span class="draft">draft</span></span><span>{{ money(subtotal) }}</span></div>
        <div style="height:10px"></div>
        <div class="field"><label>Your name</label><input v-model="form.customer_name" type="text" placeholder="Your name" /></div>
        <div class="field"><label>Phone</label><input v-model="form.contact_phone" type="tel" placeholder="705-…" /></div>
        <div class="field"><label>Pickup or delivery</label><div class="toggle">
          <button :class="{ act: form.fulfilment === 'pickup' }" @click="form.fulfilment = 'pickup'">Pickup</button>
          <button :class="{ act: form.fulfilment === 'delivery' }" @click="form.fulfilment = 'delivery'">Delivery</button></div></div>
        <div v-if="form.fulfilment === 'delivery'" class="field"><label>Delivery address</label><input v-model="form.address" type="text" placeholder="Street, community" /></div>
        <div class="field"><label>Payment (on {{ form.fulfilment }})</label><div class="toggle">
          <button :class="{ act: form.payment_method === 'cash' }" @click="form.payment_method = 'cash'">Cash</button>
          <button :class="{ act: form.payment_method === 'etransfer' }" @click="form.payment_method = 'etransfer'">e-Transfer</button></div></div>
        <div class="field"><label>Notes for the kitchen</label><textarea v-model="form.notes" rows="2" placeholder="e.g. extra gravy"></textarea></div>
        <button class="back" style="margin:0 16px" @click="showCheckout = false">‹ Back to menu</button>
      </template>
    </template>

    <div v-else class="empty">Kitchen not found.</div>

    <!-- Sticky cart -->
    <div v-if="orderable && count > 0" class="cartbar">
      <div class="cartbar-inner">
        <button v-if="!showCheckout" class="cartbar-btn" @click="showCheckout = true">
          <span>{{ count }} item{{ count === 1 ? '' : 's' }} · draft</span><b>View order · {{ money(subtotal) }} →</b>
        </button>
        <button v-else class="cartbar-btn" :disabled="!canPlace" @click="placeOrder">
          <span>{{ count }} item{{ count === 1 ? '' : 's' }}</span><b>{{ submitting ? 'Placing…' : 'Place order · ' + money(subtotal) }}</b>
        </button>
      </div>
    </div>
  </AppShell>
</template>
