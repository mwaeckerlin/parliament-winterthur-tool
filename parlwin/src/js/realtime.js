const EVENT_NAME = 'parlwin:realtime-event'

function defaultWsUrl() {
  // Same-origin WebSocket via the /ws/<appid>/ reverse-proxy convention
  // shipped by mwaeckerlin/nextcloud:nginx.
  const scheme = window.location.protocol === 'https:' ? 'wss' : 'ws'
  const host = window.location.host || 'localhost'
  const webroot = String(window.PARLWIN_CONFIG?.webroot || '').replace(/\/$/, '')
  return `${scheme}://${host}${webroot}/ws/parlwin/`
}

function resolveWsUrl() {
  const configured = window.PARLWIN_CONFIG?.realtimeWsUrl
  if (typeof configured === 'string' && configured.trim() !== '') {
    return configured.trim()
  }
  return defaultWsUrl()
}

function updateWsStatus(state) {
  const el = document.getElementById('pw-ws-status')
  if (!el) return
  el.className = 'pw-ws-status pw-ws-' + state
  el.setAttribute('aria-label', state === 'connected' ? 'WebSocket verbunden' : 'WebSocket getrennt')
}

export function startRealtimeBridge() {
  const url = resolveWsUrl()
  if (!url) {
    return () => {}
  }

  let socket = null
  let reconnectDelayMs = 1000
  let reconnectTimer = null
  let stopped = false

  const clearTimer = () => {
    if (reconnectTimer !== null) {
      window.clearTimeout(reconnectTimer)
      reconnectTimer = null
    }
  }

  const scheduleReconnect = () => {
    if (stopped) return
    updateWsStatus('disconnected')
    clearTimer()
    reconnectTimer = window.setTimeout(connect, reconnectDelayMs)
    reconnectDelayMs = Math.min(reconnectDelayMs * 2, 15000)
  }

  const onMessage = (raw) => {
    try {
      const parsed = JSON.parse(raw.data)
      window.dispatchEvent(new CustomEvent(EVENT_NAME, { detail: parsed }))
    } catch (error) {
      console.error('Realtime-Nachricht konnte nicht geparst werden', error)
    }
  }

  const connect = () => {
    if (stopped) return
    try {
      socket = new WebSocket(url)
      socket.addEventListener('open', () => {
        reconnectDelayMs = 1000
        updateWsStatus('connected')
      })
      socket.addEventListener('message', onMessage)
      socket.addEventListener('close', scheduleReconnect)
      socket.addEventListener('error', scheduleReconnect)
    } catch (error) {
      console.error('Realtime-Verbindung konnte nicht aufgebaut werden', error)
      scheduleReconnect()
    }
  }

  connect()

  return () => {
    stopped = true
    clearTimer()
    if (socket) {
      socket.close()
      socket = null
    }
    updateWsStatus('disconnected')
  }
}

export function subscribeRealtime(handler) {
  const wrapped = (event) => {
    handler(event.detail || {})
  }
  window.addEventListener(EVENT_NAME, wrapped)
  return () => window.removeEventListener(EVENT_NAME, wrapped)
}
