export function vollerName(m) {
  return `${m.vorname || ''} ${m.name || ''}`.trim()
}

export function personKey(m) {
  const externId = m.externId || m.extern_id || ''
  if (externId) return `mitglied:${externId}`
  return `name:${vollerName(m)}`
}

export function parseNotizen(raw) {
  if (!raw) return []
  try {
    const arr = typeof raw === 'string' ? JSON.parse(raw) : raw
    return Array.isArray(arr) ? arr : []
  } catch {
    return []
  }
}
