import axios from '@nextcloud/axios'

/**
 * Robustes Nachladen bei schlechter Leitung: fehlgeschlagene Anfragen werden
 * automatisch wiederholt, bis sie klappen — so bleibt nichts dauerhaft «hängen».
 *
 * - Lesen (GET) ist idempotent und wird bei Netzwerk-/Timeout-Fehlern oder
 *   Serverfehlern (5xx) UNBEGRENZT wiederholt (mit wachsender Wartezeit), bis es
 *   klappt.
 * - Mutationen (POST/PUT/DELETE) werden nur bei echten Netzwerkfehlern (die
 *   Anfrage hat den Server nie erreicht) und nur wenige Male wiederholt — sonst
 *   könnte eine Aktion doppelt ausgeführt werden.
 * - 4xx (Client-Fehler) werden nie wiederholt; sie werden durch einen erneuten
 *   Versuch nicht besser.
 */
const MAX_DELAY = 20000
const BASE_DELAY = 800

export function maxVersuche(config) {
  return String(config?.method || 'get').toLowerCase() === 'get' ? Infinity : 4
}

export function istRetrybar(error) {
  if (!error || !error.config) {
    return false
  }
  // Kein Response = Netzwerk-/Timeout-Fehler (schlechte Leitung): immer wiederholen.
  if (!error.response) {
    return true
  }
  // Serverfehler nur bei lesenden (idempotenten) Anfragen wiederholen.
  const method = String(error.config.method || 'get').toLowerCase()
  return method === 'get' && Number(error.response.status) >= 500
}

export function wartezeit(versuch) {
  const d = BASE_DELAY * Math.pow(1.5, Math.max(0, versuch - 1))
  return Math.min(MAX_DELAY, Math.round(d * (0.5 + Math.random())))
}

/** Hängt den Retry-Interceptor an den geteilten Axios-Client. */
export function installiereRetry(client = axios) {
  client.interceptors.response.use(
    r => r,
    async error => {
      const config = error && error.config
      if (!config || config._pwKeinRetry || !istRetrybar(error)) {
        return Promise.reject(error)
      }
      config._pwRetries = (config._pwRetries || 0) + 1
      if (config._pwRetries > maxVersuche(config)) {
        return Promise.reject(error)
      }
      await new Promise(res => setTimeout(res, wartezeit(config._pwRetries)))
      return client(config)
    },
  )
}
