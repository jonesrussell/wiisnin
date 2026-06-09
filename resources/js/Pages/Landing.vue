<script setup>
import { Head } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import AppShell from '../Layouts/AppShell.vue'
import VendorCard from '../components/VendorCard.vue'
import { useI18n } from '../i18n.js'

const props = defineProps({
  app: { type: Object, required: true },
  communities: { type: Array, default: () => [] },
})

// Card thumbnail tints so listings look warm without photos.
const TINTS = ['#E8612C', '#1D9E75', '#3C6E89', '#C46A2B', '#15795A', '#C94E1E', '#1D9E75', '#3C6E89']

const { locale } = useI18n()
const located = ref(false)        // has the user shared location / chosen browse?
const loading = ref(false)
const denied = ref(false)
const coords = ref(null)          // { lat, lng } | null
const community = ref('All')
const search = ref('')
const vendors = ref([])
let searchTimer = null

const locTitle = computed(() => coords.value ? 'Kitchens near you' : 'All communities')
const locSub = computed(() => coords.value ? 'Closest to you first' : 'Location off · browse everything')
// Town chips come from the served-communities list (canonical 8 towns).
const filters = computed(() => ['All', ...props.communities.map((c) => c.name)])

async function load() {
  loading.value = true
  const p = new URLSearchParams()
  if (coords.value) { p.set('lat', coords.value.lat); p.set('lng', coords.value.lng) }
  if (community.value !== 'All') p.set('community', community.value)
  if (search.value.trim()) p.set('q', search.value.trim())
  if (locale.value === 'oj') p.set('lang', 'oj')
  try {
    const res = await fetch('/api/vendors?' + p.toString(), { credentials: 'same-origin' })
    const data = await res.json()
    vendors.value = data.vendors || []
  } catch (e) { vendors.value = [] }
  loading.value = false
}

function useLocation() {
  denied.value = false
  if (!('geolocation' in navigator)) { browseAll(); return }
  loading.value = true; located.value = true
  navigator.geolocation.getCurrentPosition(
    (pos) => { coords.value = { lat: pos.coords.latitude, lng: pos.coords.longitude }; load() },
    () => { denied.value = true; coords.value = null; load() },
    { timeout: 8000 },
  )
}
function browseAll() { located.value = true; coords.value = null; load() }
function changeLocation() { located.value = false; vendors.value = [] }

function onSearch() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(load, 250)
}
function setCommunity(c) { community.value = c; load() }
watch(locale, () => { if (located.value) load() })

function tint(i) { return TINTS[i % TINTS.length] }
</script>

<template>
  <Head :title="`${app.name} — North Shore food ordering`" />
  <AppShell :app="app">
    <template #header-right>
      <span v-if="located" class="pill ghost">{{ coords ? 'Near you' : 'All' }}</span>
    </template>

    <!-- Permission / intro -->
    <template v-if="!located">
      <div class="perm">
        <div class="ring" aria-hidden="true">◎</div>
        <h2>Find food near you</h2>
        <p>Boozhoo! Share your location and Wiisnin shows the kitchens closest to you across the North Shore — Sagamok, Massey, Espanola and the towns between — closest first.</p>
        <button class="cta" style="width:100%;margin:0 0 10px" @click="useLocation">Use my location</button>
        <button class="back" style="font-size:15px" @click="browseAll">Browse all communities</button>
      </div>
    </template>

    <!-- Near you / browse -->
    <template v-else>
      <div class="locbar">
        <div class="locdot" aria-hidden="true">◎</div>
        <div>
          <b>{{ locTitle }}</b>
          <p>{{ denied ? 'Location unavailable · browsing all' : locSub }}</p>
        </div>
        <button class="chg" @click="changeLocation">Change</button>
      </div>

      <div class="searchwrap">
        <span class="si" aria-hidden="true">⌕</span>
        <input v-model="search" @input="onSearch" type="search" placeholder="Search kitchens or dishes — taco, soup, pizza…" aria-label="Search" />
      </div>

      <div class="filters">
        <button v-for="c in filters" :key="c" class="chip" :class="{ act: community === c }" @click="setCommunity(c)">{{ c }}</button>
      </div>

      <div class="sech">{{ search.trim() ? `Results for "${search.trim()}"` : (community === 'All' ? (coords ? 'Closest to you' : 'All kitchens') : ('In ' + community)) }}</div>

      <template v-if="loading">
        <div class="vgrid">
          <div v-for="n in 3" :key="n" class="vcard">
            <div class="vcard-main" style="cursor:default">
              <div class="vthumb skel"></div>
              <div style="flex:1"><div class="skel" style="height:14px;width:60%;margin-bottom:8px"></div><div class="skel" style="height:11px;width:80%"></div></div>
            </div>
          </div>
        </div>
      </template>

      <template v-else>
        <div class="vgrid">
          <VendorCard v-for="(v, i) in vendors" :key="v.id" :v="v" :tint="tint(i)" />
        </div>

        <div v-if="vendors.length === 0" class="empty">
          No kitchens match yet — try “taco”, “soup” or “pizza”.
        </div>
      </template>
    </template>
  </AppShell>
</template>
