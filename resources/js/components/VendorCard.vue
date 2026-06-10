<script setup>
import { computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { track } from '../analytics.js'

const props = defineProps({
  v: { type: Object, required: true },
  tint: { type: String, default: '#E8612C' },
})

function stars(n) { const r = Math.round(n || 0); return '★★★★★'.slice(0, r) + '☆☆☆☆☆'.slice(0, 5 - r) }
function telHref(p) { return 'tel:' + String(p || '').replace(/[^0-9+]/g, '') }

const dist = computed(() => {
  const d = props.v.distance_km
  if (d == null) return ''
  if (d < 1) return 'nearby'
  return (d < 10 ? d : Math.round(d)) + ' km'
})
const thumbStyle = computed(() => props.v.image
  ? { backgroundImage: `url(${props.v.image})`, backgroundSize: 'cover', backgroundPosition: 'center' }
  : { background: props.tint })

function open() { router.visit('/vendor/' + props.v.slug) }
</script>

<template>
  <div class="vcard">
    <button class="vcard-main" @click="open">
      <div class="vthumb" :style="thumbStyle">
        <span v-if="dist" class="distbadge">{{ dist }}</span>
      </div>
      <div class="vinfo">
        <h3>{{ v.name }}</h3>
        <p>{{ v.community }}<template v-if="v.cuisine"> · {{ v.cuisine }}</template></p>
        <div class="tags">
          <span v-if="v.open !== null && v.open !== undefined" class="tag" :class="v.open ? 'open' : 'closed'">{{ v.open ? 'Open now' : 'Closed' }}</span>
          <span class="tag" :class="v.is_partner ? 'live' : (v.opening_soon ? 'opening' : 'soon')">{{ v.is_partner ? 'Order now' : (v.opening_soon ? 'Opening soon' : 'Ordering coming soon') }}</span>
          <span v-if="v.rating && v.rating.count > 0" class="tag rate"><span class="stars">{{ stars(v.rating.average) }}</span> {{ v.rating.average }} ({{ v.rating.count }})</span>
          <span v-if="v.demand > 0" class="tag demand">👍 {{ v.demand }} want ordering</span>
        </div>
      </div>
    </button>
    <div v-if="v.contact_phone || v.maps_url" class="vactions">
      <a v-if="v.contact_phone" class="vaction" :href="telHref(v.contact_phone)" @click.stop="track('call', { slug: v.slug })">📞 Call</a>
      <a v-if="v.maps_url" class="vaction" :href="v.maps_url" target="_blank" rel="noopener" @click.stop="track('directions', { slug: v.slug })">🧭 Directions</a>
    </div>
  </div>
</template>
