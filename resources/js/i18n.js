import { ref } from 'vue'

// Client-side UI chrome strings. CULTURAL RULE: `oj` (Nishnaabemwin) contains
// ONLY community-confirmed words; every other key falls back to `en`. The list
// of English-only keys (needsTranslation) is the "translation needed" seam for
// Russell / the community to fill — do NOT machine-translate.
const en = {
  greeting: 'Welcome',
  thanks: 'Thank you',
  tagline: "wiisnin · let's eat · North Shore",
  findNearYou: 'Find food near you',
  useLocation: 'Use my location',
  browseAll: 'Browse all communities',
  nearYou: 'Near you',
  allCommunities: 'All communities',
  change: 'Change',
  searchPlaceholder: 'Search kitchens or dishes — taco, soup, pizza, italian…',
  draftPricing: 'Draft pricing — to be confirmed',
  reviews: 'Reviews',
  leaveReview: 'Leave a review',
}
const oj = {
  greeting: 'Boozhoo',
  thanks: 'Miigwech',
  // brand stays "Wiisnin". All other keys intentionally absent → English fallback.
}

const STORAGE_KEY = 'wsn_lang'
const initial = (typeof localStorage !== 'undefined' && localStorage.getItem(STORAGE_KEY)) || 'en'
const locale = ref(initial === 'oj' ? 'oj' : 'en')

export function setLocale(l) {
  locale.value = l === 'oj' ? 'oj' : 'en'
  try { localStorage.setItem(STORAGE_KEY, locale.value) } catch (e) {}
  document.cookie = `${STORAGE_KEY}=${locale.value};path=/;max-age=31536000;samesite=lax`
}

export function t(key) {
  const dict = locale.value === 'oj' ? oj : en
  return dict[key] ?? en[key] ?? key
}

// Keys present in English but not yet translated to Nishnaabemwin.
export const needsTranslation = Object.keys(en).filter((k) => !(k in oj))

export function useI18n() {
  return { locale, setLocale, t, needsTranslation }
}
