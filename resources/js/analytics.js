/*
 * analytics.js — first-party, privacy-respecting analytics for wiisnin.ca.
 * Modeled on oiatc-analytics.js. No cookies, no fingerprinting, no third parties.
 *
 * Skipped entirely under Do Not Track / Global Privacy Control. Beacons POST to
 * /api/collect with credentials:'omit' (no cookies sent). Wiisnin is an Inertia
 * SPA, so a fresh pageview fires on each client navigation, and per-view
 * engagement (max scroll %, dwell) flushes on navigation + page hide.
 */
import { router } from '@inertiajs/vue3'

const dnt =
  (typeof navigator !== 'undefined' && (navigator.doNotTrack === '1' || navigator.globalPrivacyControl === true)) ||
  (typeof window !== 'undefined' && window.doNotTrack === '1')

function uuid() {
  return (typeof crypto !== 'undefined' && crypto.randomUUID && crypto.randomUUID()) ||
    (Date.now().toString(36) + Math.random().toString(36).slice(2))
}

function post(payload, beacon) {
  const json = JSON.stringify(payload)
  try {
    if (beacon && navigator.sendBeacon) {
      navigator.sendBeacon('/api/collect', new Blob([json], { type: 'application/json' }))
      return
    }
    fetch('/api/collect', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: json,
      keepalive: true,
      credentials: 'omit',
    })
  } catch (e) { /* ignore */ }
}

let viewId = null
let viewStart = 0
let maxScroll = 0
let engagementSent = true
let lastPath = null
let started = false

function computeScrollPct() {
  const doc = document.documentElement
  const body = document.body
  const scrollTop = window.pageYOffset || doc.scrollTop || (body && body.scrollTop) || 0
  const viewportHeight = window.innerHeight || doc.clientHeight || 0
  const documentHeight = Math.max(doc.scrollHeight, body ? body.scrollHeight : 0, doc.offsetHeight, doc.clientHeight)
  if (documentHeight <= 0) return 0
  let pct = Math.round(((scrollTop + viewportHeight) / documentHeight) * 100)
  if (pct < 0) pct = 0
  if (pct > 100) pct = 100
  return pct
}

let ticking = false
function onScroll() {
  if (ticking) return
  ticking = true
  requestAnimationFrame(() => {
    ticking = false
    const pct = computeScrollPct()
    if (pct > maxScroll) maxScroll = pct
  })
}

function flushEngagement() {
  if (engagementSent || !viewId) return
  engagementSent = true
  post({ t: 'engagement', v: viewId, s: maxScroll, d: Date.now() - viewStart }, true)
}

function startView(path) {
  if (path && path === lastPath) return // dedupe (e.g. navigate event for the current page)
  flushEngagement() // close out the previous view
  lastPath = path || location.pathname
  viewId = uuid()
  viewStart = Date.now()
  maxScroll = 0
  engagementSent = false
  post({ t: 'pageview', p: lastPath, r: document.referrer || '', v: viewId })
  maxScroll = computeScrollPct()
}

export function initAnalytics() {
  if (dnt || started || typeof window === 'undefined') return
  started = true
  window.addEventListener('scroll', onScroll, { passive: true })
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'hidden') flushEngagement()
  })
  window.addEventListener('pagehide', flushEngagement)
  // A fresh pageview on each successful Inertia navigation.
  router.on('navigate', () => startView(location.pathname))
  startView(location.pathname)
}

/** Fire a Wiisnin action event: track('vendor_view'|'call'|'directions'|'demand', {slug}) or track('search', {q}). */
export function track(type, data) {
  if (dnt) return
  post(Object.assign({ t: type, v: viewId || '' }, data || {}))
}
