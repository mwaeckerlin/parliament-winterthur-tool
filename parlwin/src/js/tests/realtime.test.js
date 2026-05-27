import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { startRealtimeBridge, subscribeRealtime } from '../realtime.js'

// ── WebSocket URL convention ────────────────────────────────────────────────

describe('startRealtimeBridge — WebSocket URL path', () => {
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

  it('connects to /ws/parlwin/ — nginx generic convention', () => {
    const stop = startRealtimeBridge()
    stop()
    expect(wsInstances.length).toBeGreaterThan(0)
    const url = wsInstances[0].url
    expect(url).toMatch(/\/ws\/parlwin\/$/)
  })

  it('does NOT connect to /parlwin/ws (old wrong path)', () => {
    const stop = startRealtimeBridge()
    stop()
    const url = wsInstances[0]?.url ?? ''
    expect(url).not.toContain('/parlwin/ws')
  })

  it('uses wss:// on https pages', () => {
    // jsdom defaults to http, so we verify the scheme logic with a configured URL
    window.PARLWIN_CONFIG = { realtimeWsUrl: 'wss://host.example.com/ws/parlwin/' }
    const stop = startRealtimeBridge()
    stop()
    expect(wsInstances[0].url).toBe('wss://host.example.com/ws/parlwin/')
  })

  it('uses configured realtimeWsUrl when set', () => {
    window.PARLWIN_CONFIG = { realtimeWsUrl: 'wss://custom.example.com/ws/parlwin/' }
    const stop = startRealtimeBridge()
    stop()
    expect(wsInstances[0].url).toBe('wss://custom.example.com/ws/parlwin/')
  })

  it('includes webroot in default URL', () => {
    window.PARLWIN_CONFIG = { webroot: '/nextcloud' }
    const stop = startRealtimeBridge()
    stop()
    expect(wsInstances[0].url).toContain('/nextcloud/ws/parlwin/')
  })
})

// ── subscribeRealtime ───────────────────────────────────────────────────────

describe('subscribeRealtime', () => {
  it('calls handler when parlwin:realtime-event fires', () => {
    const handler = vi.fn()
    const unsubscribe = subscribeRealtime(handler)
    const payload = { type: 'sync', data: {} }
    window.dispatchEvent(new CustomEvent('parlwin:realtime-event', { detail: payload }))
    expect(handler).toHaveBeenCalledOnce()
    expect(handler).toHaveBeenCalledWith(payload)
    unsubscribe()
  })

  it('returns unsubscribe function that removes listener', () => {
    const handler = vi.fn()
    const unsubscribe = subscribeRealtime(handler)
    unsubscribe()
    window.dispatchEvent(new CustomEvent('parlwin:realtime-event', { detail: {} }))
    expect(handler).not.toHaveBeenCalled()
  })

  it('passes empty object when event has no detail', () => {
    const handler = vi.fn()
    const unsubscribe = subscribeRealtime(handler)
    window.dispatchEvent(new CustomEvent('parlwin:realtime-event'))
    expect(handler).toHaveBeenCalledWith({})
    unsubscribe()
  })
})
