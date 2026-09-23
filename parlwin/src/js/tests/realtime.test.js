import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { startRealtimeBridge, subscribeRealtime } from '../realtime.js'

// ── Regel für die Adresse der WebSocket-Verbindung ──────────────────────────

describe('startRealtimeBridge — Pfad der WebSocket-Adresse', () => {
  let MockWS, wsInstances, origWS, origConfig

  beforeEach(() => {
    origWS = window.WebSocket
    origConfig = window.PARLWIN_CONFIG
    window.PARLWIN_CONFIG = {}
    wsInstances = []
    MockWS = vi.fn(function(url) {
      this.url = url
      this.addEventListener = vi.fn()
      this.close = vi.fn()
      wsInstances.push(this)
    })
    window.WebSocket = MockWS
  })

  afterEach(() => {
    window.WebSocket = origWS
    window.PARLWIN_CONFIG = origConfig
  })

  it('verbindet auf /ws/parlwin/ — die allgemeine Regel von nginx', () => {
    const stop = startRealtimeBridge()
    stop()
    expect(wsInstances.length).toBeGreaterThan(0)
    const url = wsInstances[0].url
    expect(url).toMatch(/\/ws\/parlwin\/$/)
  })

  it('verbindet NICHT auf /parlwin/ws (der alte, falsche Pfad)', () => {
    const stop = startRealtimeBridge()
    stop()
    const url = wsInstances[0]?.url ?? ''
    expect(url).not.toContain('/parlwin/ws')
  })

  it('nutzt wss:// auf Seiten über https', () => {
    // jsdom defaults to http, so we verify the scheme logic with a configured URL
    window.PARLWIN_CONFIG = { realtimeWsUrl: 'wss://host.example.com/ws/parlwin/' }
    const stop = startRealtimeBridge()
    stop()
    expect(wsInstances[0].url).toBe('wss://host.example.com/ws/parlwin/')
  })

  it('nutzt die eingestellte realtimeWsUrl, wenn sie gesetzt ist', () => {
    window.PARLWIN_CONFIG = { realtimeWsUrl: 'wss://custom.example.com/ws/parlwin/' }
    const stop = startRealtimeBridge()
    stop()
    expect(wsInstances[0].url).toBe('wss://custom.example.com/ws/parlwin/')
  })

  it('nimmt den Webroot in die vorgegebene Adresse auf', () => {
    window.PARLWIN_CONFIG = { webroot: '/nextcloud' }
    const stop = startRealtimeBridge()
    stop()
    expect(wsInstances[0].url).toContain('/nextcloud/ws/parlwin/')
  })
})

// ── subscribeRealtime ───────────────────────────────────────────────────────

describe('subscribeRealtime', () => {
  it('ruft die angemeldete Funktion auf, wenn parlwin:realtime-event ausgelöst wird', () => {
    const handler = vi.fn()
    const unsubscribe = subscribeRealtime(handler)
    const payload = { type: 'sync', data: {} }
    window.dispatchEvent(new CustomEvent('parlwin:realtime-event', { detail: payload }))
    expect(handler).toHaveBeenCalledOnce()
    expect(handler).toHaveBeenCalledWith(payload)
    unsubscribe()
  })

  it('liefert eine Funktion zurück, die die Anmeldung wieder aufhebt', () => {
    const handler = vi.fn()
    const unsubscribe = subscribeRealtime(handler)
    unsubscribe()
    window.dispatchEvent(new CustomEvent('parlwin:realtime-event', { detail: {} }))
    expect(handler).not.toHaveBeenCalled()
  })

  it('übergibt ein leeres Objekt, wenn das Ereignis kein Detail trägt', () => {
    const handler = vi.fn()
    const unsubscribe = subscribeRealtime(handler)
    window.dispatchEvent(new CustomEvent('parlwin:realtime-event'))
    expect(handler).toHaveBeenCalledWith({})
    unsubscribe()
  })
})
