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
const pageTitle = computed(() => {
  if (!props.vendor) return props.app.name
  return orderable.value
    ? `Order from ${props.vendor.name} — ${props.vendor.community} · ${props.app.name}`
    : `${props.vendor.name} — ${props.vendor.community} · ${props.app.name}`
})

function stars(n) { const r = Math.round(n || 0); return '★★★★★'.slice(0, r) + '☆☆☆☆☆'.slice(0, 5 - r) }
function telHref(p) { return 'tel:' + String(p || '').replace(/[^0-9+]/g, '') }

// The framework sets a non-HttpOnly XSRF-TOKEN cookie on every HTML page load;
// echo it back as X-XSRF-TOKEN so the server can verify the request is in-app.
function xsrfToken() {
  const m = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/)
  return m ? m[1] : ''
}
function deviceId() {
  let id = ''
  try {
    id = localStorage.getItem('wsn_device') || ''
    if (!id) { id = 'd-' + Math.random().toString(36).slice(2) + Date.now().toString(36); localStorage.setItem('wsn_device', id) }
  } catch (e) { /* private mode */ }
  return id
}

// --- Reviews (partner only) ---------------------------------------------
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

// --- Demand signal (non-partner) ----------------------------------------
const demandCount = ref(props.vendor?.demand ?? 0)
const demandBusy = ref(false)
const voted = ref((() => {
  try { return !!props.vendor && localStorage.getItem('wsn_demand_' + props.vendor.slug) === '1' } catch (e) { return false }
})())

async function voteDemand() {
  if (voted.value || demandBusy.value) return
  demandBusy.value = true
  try {
    const res = await fetch(`/vendor/${props.vendor.slug}/demand`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-XSRF-TOKEN': xsrfToken() },
      credentials: 'same-origin',
      body: JSON.stringify({ device_id: deviceId() }),
    })
    const data = await res.json()
    if (res.ok && data.ok) {
      demandCount.value = data.count
      voted.value = true
      try { localStorage.setItem('wsn_demand_' + props.vendor.slug, '1') } catch (e) { /* ignore */ }
    }
  } catch (e) { /* ignore */ } finally { demandBusy.value = false }
}

// --- Owner claim (non-partner) ------------------------------------------
const showClaim = ref(false)
const claim = reactive({ owner_name: '', phone: '', email: '', note: '' })
const claimSubmitting = ref(false)
const claimError = ref('')
const claimDone = ref(false)
const canClaim = computed(() => claim.owner_name.trim() && (claim.phone.trim() || claim.email.trim()) && !claimSubmitting.value)

async function submitClaim() {
  if (!canClaim.value) return
  claimSubmitting.value = true
  claimError.value = ''
  try {
    const res = await fetch(`/vendor/${props.vendor.slug}/claim`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-XSRF-TOKEN': xsrfToken() },
      credentials: 'same-origin',
      body: JSON.stringify({ owner_name: claim.owner_name.trim(), phone: claim.phone.trim(), email: claim.email.trim(), note: claim.note.trim() }),
    })
    const data = await res.json()
    if (!res.ok || !data.ok) { claimError.value = data.error || 'Could not send that — please try again.'; return }
    claimDone.value = true
  } catch (e) {
    claimError.value = 'Could not send that — please try again.'
  } finally {
    claimSubmitting.value = false
  }
}

// --- Ordering (partner) -------------------------------------------------
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
  <Head :title="pageTitle" />
  <AppShell :app="app">
    <template #header-right>
      <Link href="/" class="back">‹ Near you</Link>
    </template>

    <div class="page-narrow">
    <template v-if="vendor">
      <div class="hero" :class="{ 'hero--photo': vendor.image }" :style="vendor.image ? { backgroundImage: `url(${vendor.image})` } : null">
        <div class="hero-body">
          <h2>{{ vendor.name }}</h2>
          <p>{{ vendor.cuisine }}<template v-if="vendor.community"> · {{ vendor.community }}</template></p>
          <p v-if="ratingSum.count > 0" class="rating-sum">
            <span class="stars">{{ stars(ratingSum.average) }}</span>
            <b>{{ ratingSum.average }}</b> · {{ ratingSum.count }} review{{ ratingSum.count === 1 ? '' : 's' }}
          </p>
        </div>
      </div>

      <!-- Info + actions: every vendor (call / directions / hours / open). -->
      <div class="infocard" v-if="!showCheckout">
        <div class="infometa">
          <span class="tag" :class="vendor.is_partner ? 'live' : (vendor.opening_soon ? 'opening' : 'soon')">{{ vendor.is_partner ? 'Order now' : (vendor.opening_soon ? 'Opening soon' : 'Ordering coming soon') }}</span>
          <span v-if="vendor.open !== null && vendor.open !== undefined" class="tag" :class="vendor.open ? 'open' : 'closed'">{{ vendor.open ? 'Open now' : 'Closed' }}</span>
          <span v-if="vendor.hours" class="hours">🕑 {{ vendor.hours }}</span>
          <span v-if="vendor.address" class="addr">📍 {{ vendor.address }}</span>
        </div>
        <div class="infoactions">
          <a v-if="vendor.contact_phone" class="vaction big" :href="telHref(vendor.contact_phone)">📞 Call</a>
          <a v-if="vendor.maps_url" class="vaction big" :href="vendor.maps_url" target="_blank" rel="noopener">🧭 Directions</a>
        </div>
      </div>

      <!-- ===================== PARTNER: ordering ===================== -->
      <template v-if="orderable">
        <div v-if="pricingDraft" class="draftbar">Draft pricing — to be confirmed with {{ vendor.name }}</div>

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
              <div class="stepper">
                <button type="button" v-if="cart[item.id]" class="add" style="background:var(--soft-orange);color:var(--orange-deep)" @click="remove(item)" aria-label="remove one">−</button>
                <span v-if="cart[item.id]" class="qty">{{ cart[item.id].qty }}</span>
                <button type="button" class="add" @click="add(item)" :aria-label="`add ${item.name}`">+</button>
              </div>
            </div>
          </section>

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

            <div v-if="rvThanks" class="draftbar">Miigwech — thanks for your review!</div>
            <form v-else class="reviewform" @submit.prevent="submitReview">
              <h4>Leave a review</h4>
              <div class="starpick" role="radiogroup" aria-label="Your rating">
                <button v-for="n in 5" :key="n" type="button" class="star" :class="{ on: n <= rv.rating }"
                        :aria-label="`${n} star${n === 1 ? '' : 's'}`" @click="rv.rating = n">★</button>
              </div>
              <div class="field"><label>Your name <input v-model="rv.author_name" type="text" placeholder="Your name" /></label></div>
              <div class="field"><label>Your review <textarea v-model="rv.body" rows="2" placeholder="How was it?"></textarea></label></div>
              <p v-if="rvError" class="samplebar">{{ rvError }}</p>
              <button class="cta" type="submit" :disabled="!canReview">{{ rvSubmitting ? 'Saving…' : 'Post review' }}</button>
            </form>
          </section>
        </template>

        <!-- Checkout -->
        <template v-else>
          <h3 class="h2" style="margin-top:8px">Almost there</h3>
          <div class="orow" v-for="l in lines" :key="l.item.id"><span>{{ l.qty }}× {{ l.item.name }}</span><span>{{ money(l.qty * l.item.price_cents) }}</span></div>
          <div class="orow tot"><span>Total <span class="draft">draft</span></span><span>{{ money(subtotal) }}</span></div>
          <div style="height:10px"></div>
          <div class="field"><label>Your name <input v-model="form.customer_name" type="text" placeholder="Your name" /></label></div>
          <div class="field"><label>Phone <input v-model="form.contact_phone" type="tel" placeholder="705-…" /></label></div>
          <div class="field"><span class="field-label">Pickup or delivery</span><div class="toggle">
            <button type="button" :class="{ act: form.fulfilment === 'pickup' }" @click="form.fulfilment = 'pickup'">Pickup</button>
            <button type="button" :class="{ act: form.fulfilment === 'delivery' }" @click="form.fulfilment = 'delivery'">Delivery</button></div></div>
          <div v-if="form.fulfilment === 'delivery'" class="field"><label>Delivery address <input v-model="form.address" type="text" placeholder="Street, community" /></label></div>
          <div class="field"><span class="field-label">Payment (on {{ form.fulfilment }})</span><div class="toggle">
            <button type="button" :class="{ act: form.payment_method === 'cash' }" @click="form.payment_method = 'cash'">Cash</button>
            <button type="button" :class="{ act: form.payment_method === 'etransfer' }" @click="form.payment_method = 'etransfer'">e-Transfer</button></div></div>
          <div class="field"><label>Notes for the kitchen <textarea v-model="form.notes" rows="2" placeholder="e.g. extra gravy"></textarea></label></div>
          <button class="back" style="margin:0 16px" @click="showCheckout = false">‹ Back to menu</button>
        </template>
      </template>

      <!-- =================== NON-PARTNER: info listing =================== -->
      <template v-else>
        <div class="draftbar">
          <template v-if="vendor.opening_soon">Opening soon — {{ vendor.name }} is getting ready to open. Tap “I'd order here” below to say you want them on Wiisnin.</template>
          <template v-else>Ordering coming soon — {{ vendor.name }} isn't on Wiisnin for ordering yet. Call ahead or get directions above.</template>
        </div>

        <!-- Demand signal -->
        <section class="demandbox">
          <p v-if="demandCount > 0" class="demand-count">👍 {{ demandCount }} {{ demandCount === 1 ? 'person wants' : 'people want' }} ordering here</p>
          <button v-if="!voted" class="cta demandbtn" :disabled="demandBusy" @click="voteDemand">{{ demandBusy ? 'Saving…' : "I'd order here 👍" }}</button>
          <p v-else class="muted">You're in 👍 — we'll let {{ vendor.name }} know there's demand.</p>
        </section>

        <!-- Owner claim -->
        <section class="claimbox">
          <template v-if="claimDone">
            <div class="draftbar">Miigwech! We've got your details and will reach out about setting up ordering.</div>
          </template>
          <template v-else>
            <button v-if="!showClaim" class="ownercta" @click="showClaim = true">Are you the owner? Set up ordering →</button>
            <form v-else class="reviewform" @submit.prevent="submitClaim">
              <h4>Set up ordering for {{ vendor.name }}</h4>
              <p class="muted" style="margin:0 0 10px">Tell us how to reach you and we'll help get {{ vendor.name }} taking orders on Wiisnin.</p>
              <div class="field"><label>Your name <input v-model="claim.owner_name" type="text" placeholder="Your name" /></label></div>
              <div class="field"><label>Phone <input v-model="claim.phone" type="tel" placeholder="705-…" /></label></div>
              <div class="field"><label>Email <input v-model="claim.email" type="email" placeholder="you@example.com" /></label></div>
              <div class="field"><label>Anything else? (optional) <textarea v-model="claim.note" rows="2" placeholder="Best time to call, etc."></textarea></label></div>
              <p v-if="claimError" class="samplebar">{{ claimError }}</p>
              <button class="cta" type="submit" :disabled="!canClaim">{{ claimSubmitting ? 'Sending…' : 'Send' }}</button>
              <button class="back" type="button" style="margin-top:10px" @click="showClaim = false">Cancel</button>
            </form>
          </template>
        </section>
      </template>
    </template>

    <div v-else class="empty">Kitchen not found.</div>
    </div>

    <!-- Sticky cart (partner only) -->
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
