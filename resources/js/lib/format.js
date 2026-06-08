export function money(cents) {
  return '$' + (Number(cents || 0) / 100).toFixed(2)
}

export function timeAgo(unix) {
  if (!unix) return ''
  const s = Math.max(0, Math.floor(Date.now() / 1000 - unix))
  if (s < 60) return 'just now'
  if (s < 3600) return Math.floor(s / 60) + ' min ago'
  if (s < 86400) return Math.floor(s / 3600) + ' h ago'
  return new Date(unix * 1000).toLocaleString()
}
