/**
 * Parlament Winterthur Tool – Vue.js Frontend-Einstiegspunkt
 */

import { createApp } from 'vue'
import App from './App.vue'
import { startRealtimeBridge } from './realtime'
import { installiereRetry } from './axios-retry'
import { fensterKennungAnhaengen } from './nachladen'

// Nachladen bei schlechter Leitung so lange wiederholen, bis es klappt.
installiereRetry()
// Jede Anfrage nennt ihr Fenster, damit es sein eigenes Ereignis erkennt.
fensterKennungAnhaengen()

const app = createApp(App)
app.mount('#parlwin-root')

// WebSocket status indicator
const statusEl = document.createElement('div')
statusEl.id = 'pw-ws-status'
statusEl.className = 'pw-ws-status pw-ws-connecting'
statusEl.title = 'WebSocket-Status: Verbindung wird hergestellt...'
const appShell = document.querySelector('#parlwin-root')
if (appShell && appShell.parentElement) {
  appShell.parentElement.appendChild(statusEl)
}

startRealtimeBridge()
