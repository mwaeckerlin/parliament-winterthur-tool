/**
 * Parlament Winterthur Tool – Vue.js Frontend-Einstiegspunkt
 */

import { createApp } from 'vue'
import App from './App.vue'
import { startRealtimeBridge } from './realtime'

const app = createApp(App)
app.mount('#parlwin-root')
startRealtimeBridge()
