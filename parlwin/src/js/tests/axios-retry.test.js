import { describe, it, expect, vi } from 'vitest'
import { istRetrybar, maxVersuche, wartezeit, installiereRetry } from '../axios-retry'

// Robustes Nachladen bei schlechter Leitung: fehlgeschlagene Anfragen werden
// wiederholt, bis sie klappen; Mutationen nur begrenzt, 4xx nie.
describe('axios-retry', () => {
  it('wiederholt Netzwerkfehler ohne Response (schlechte Leitung)', () => {
    expect(istRetrybar({ config: { method: 'get' } })).toBe(true)
    expect(istRetrybar({ config: { method: 'post' } })).toBe(true)
  })

  it('wiederholt Serverfehler (5xx) nur bei lesenden Anfragen', () => {
    expect(istRetrybar({ config: { method: 'get' }, response: { status: 503 } })).toBe(true)
    expect(istRetrybar({ config: { method: 'post' }, response: { status: 503 } })).toBe(false)
  })

  it('wiederholt Client-Fehler (4xx) nie', () => {
    expect(istRetrybar({ config: { method: 'get' }, response: { status: 404 } })).toBe(false)
  })

  it('lädt unbegrenzt, mutiert nur begrenzt', () => {
    expect(maxVersuche({ method: 'get' })).toBe(Infinity)
    expect(maxVersuche({ method: 'post' })).toBe(4)
  })

  it('wachsende, gedeckelte Wartezeit', () => {
    expect(wartezeit(1)).toBeGreaterThan(0)
    expect(wartezeit(50)).toBeLessThanOrEqual(20000)
  })

  it('der Interceptor wartet und wiederholt eine fehlgeschlagene GET-Anfrage', async () => {
    vi.useFakeTimers()
    let errHandler
    const client = vi.fn().mockResolvedValue({ data: 'ok' })
    client.interceptors = { response: { use: (_ok, err) => { errHandler = err } } }
    installiereRetry(client)
    const config = { method: 'get' }
    const p = errHandler({ config })
    await vi.runAllTimersAsync()
    const res = await p
    expect(client).toHaveBeenCalledWith(config)
    expect(res.data).toBe('ok')
    vi.useRealTimers()
  })

  it('gibt einen 4xx-Fehler ohne Wiederholung weiter', async () => {
    const client = vi.fn()
    client.interceptors = { response: { use: (_ok, err) => { client._err = err } } }
    installiereRetry(client)
    await expect(client._err({ config: { method: 'get' }, response: { status: 404 } })).rejects.toBeDefined()
    expect(client).not.toHaveBeenCalled()
  })
})
