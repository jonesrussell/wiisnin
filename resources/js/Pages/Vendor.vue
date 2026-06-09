<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import { computed, reactive, ref } from 'vue'
import AppShell from '../Layouts/AppShell.vue'
import { money } from '../lib/format.js'

const props = defineProps({
  app: { type: Object, required: true },
  vendor: { type: Object, default: null },
  menu: { type: Array, default: () => [] },
  reviews: { type: Array, default: () => [] },
  pricingDraft: { type: Boolean, default: true },
})

const orderable = computed(() => props.vendor && props.vendor.is_partner)

// --- Reviews -------------------------------------------------------------
function stars(n) { const r = Math.round(n || 0); return '★★★★★'.slice(0, r) + '☆☆☆☆☆'.slice(0, 5 - r) }
const reviewList = ref([...props.reviews])
const ratingSum = reactive({
  average: props.vendor?.rating?.average ?? null,
  count: props.vendor?.rating?.count ?? 0,
})
const rv = reactive({ author_name: '', rating: 5, body: '' })
const rvSubmitting = ref(false)
const rvError = ref('')
const rvThanks = ref(false)
const canReview = computed(() => rv.author_name.trim() && rv.rating >= 1 && rv.rating <= 5 && !rvSubmitting.value)

// The framework sets a non-HttpOnly XSRF-TOKEN cookie on every HTML page load;
// echo it back as X-XSRF-TOKEN so the server can verify the request is in-app
// (same token Inertia's axios sends on the order path).
function xsrfToken() {
  const m = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/)
  return m ? m[1] : ''
}

async function submitReview() {
  if (!canReview.value) return
  rvSubmitting.value = true
  rvError.value = ''
  try {
    const res = await fetch(`/vendor/${props.vendor.slug}/reviews`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-XSRF-TOKEN': xsrfToken() },
      credentials: 'same-origin',
      body: JSON.stringify({ author_name: rv.author_name.trim(), rating: rv.rating, body: rv.body.trim() }),
    })
    const data = await res.json()
    if (!res.ok || !data.ok) { rvError.value = data.error || 'Could not save your review.'; return }
    reviewList.value = data.reviews || reviewList.value
    if (data.summary) { ratingSum.average = data.summary.average; ratingSum.count = data.summary.count }
    rv.author_name = ''; rv.rating = 5; rv.body = ''
    rvThanks.value = true
  } catch (e) {
    rvError.value = 'Could not save your review.'
  } finally {
    rvSubmitting.value = false
  }
}

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

    <div class="page-narrow">
    <template v-if="vendor">
      <div class="hero" :class="{ 'hero--photo': vendor.image }" :style="vendor.image ? { backgroundImage: `url(${vendor.image})` } : null">
        <div class="hero-body">
          <h2>{{ vendor.name }}</h2>
          <p>{{ vendor.cuisine }} · {{ vendor.is_open ? 'Open now' : 'Closed' }} · pickup or delivery</p>
          <p v-if="ratingSum.count > 0" class="rating-sum">
            <span class="stars">{{ stars(ratingSum.average) }}</span>
            <b>{{ ratingSum.average }}</b> · {{ ratingSum.count }} review{{ ratingSum.count === 1 ? '' : 's' }}
          </p>
        </div>
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
            <div class="ithumb" :class="{ 'ithumb--photo': item.image }" :style="item.image ? { backgroundImage: `url(${item.image})` } : null" aria-hidden="true"></div>
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

        <!-- Reviews -->
        <section class="reviews">
          <h3 class="menu-cat-title">Reviews</h3>

          <div v-for="r in reviewList" :key="r.id" class="review">
            <div class="review-head">
              <span class="stars">{{ stars(r.rating) }}</span>
              <b>{{ r.author_name }}</b>
            </div>
            <p v-if="r.body">{{ r.body }}</p>
          </div>
          <p v-if="reviewList.length === 0" class="muted">No reviews yet.</p>

          <!-- Leave a review: partners only (honesty rule). -->
          <template v-if="orderable">
            <div v-if="rvThanks" class="draftbar">Miigwech — thanks for your review!</div>
            <form v-else class="reviewform" @submit.prevent="submitReview">
              <h4>Leave a review</h4>
              <div class="starpick" role="radiogroup" aria-label="Your rating">
                <button v-for="n in 5" :key="n" type="button" class="star" :class="{ on: n <= rv.rating }"
                        :aria-label="`${n} star${n === 1 ? '' : 's'}`" @click="rv.rating = n">★</button>
              </div>
              <div class="field"><label>Your name</label><input v-model="rv.author_name" type="text" placeholder="Your name" /></div>
              <div class="field"><label>Your review</label><textarea v-model="rv.body" rows="2" placeholder="How was it?"></textarea></div>
              <p v-if="rvError" class="samplebar">{{ rvError }}</p>
              <button class="cta" type="submit" :disabled="!canReview">{{ rvSubmitting ? 'Saving…' : 'Post review' }}</button>
            </form>
          </template>
          <p v-else class="muted">Reviews open when {{ vendor.name }} joins Wiisnin.</p>
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
    </div>

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
