<template>
  <div class="pw-app">
    <header class="pw-app-header">
      <h1>Parlament Winterthur</h1>
      <p>Fraktionsarbeit, Sitzungen und Beschlüsse in einer Oberfläche.</p>
    </header>

    <section class="pw-panel">
      <nav class="pw-nav" aria-label="Hauptnavigation">
        <button
          v-for="ansicht in ansichten"
          :key="ansicht.key"
          type="button"
          :aria-pressed="aktiveAnsicht === ansicht.key ? 'true' : 'false'"
          :class="['button', 'pw-nav-btn', { aktiv: aktiveAnsicht === ansicht.key }]"
          @click="aktiveAnsicht = ansicht.key"
        >
          {{ ansicht.bezeichnung }}
        </button>
      </nav>

      <div v-if="syncMeldung" class="pw-sync-meldung" :class="syncFehler ? 'fehler' : 'erfolg'">
        {{ syncMeldung }}
      </div>

      <div class="pw-panel-content">
        <Geschaeftsliste
          v-if="aktiveAnsicht === 'geschaefte'"
          :mitglieder="mitglieder"
          @aktualisiert="ladeGeschaefte"
        />
        <Sitzungsliste
          v-else-if="aktiveAnsicht === 'sitzungen'"
        />
        <Mitgliederliste
          v-else-if="aktiveAnsicht === 'mitglieder'"
          :mitglieder="mitglieder"
        />
        <Kommissionsliste
          v-else-if="aktiveAnsicht === 'kommissionen'"
        />
      </div>
    </section>
  </div>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { subscribeRealtime } from './realtime'
import Geschaeftsliste from './components/Geschaeftsliste.vue'
import Sitzungsliste from './components/Sitzungsliste.vue'
import Mitgliederliste from './components/Mitgliederliste.vue'
import Kommissionsliste from './components/Kommissionsliste.vue'

export default {
  name: 'ParliamentWinterthurApp',
  components: {
    Geschaeftsliste,
    Sitzungsliste,
    Mitgliederliste,
    Kommissionsliste,
  },
  data() {
    return {
      aktiveAnsicht: 'geschaefte',
      mitglieder: [],
      syncMeldung: '',
      syncFehler: false,
      ansichten: [
        { key: 'geschaefte',  bezeichnung: 'Geschäfte' },
        { key: 'sitzungen',   bezeichnung: 'Sitzungen' },
        { key: 'mitglieder',  bezeichnung: 'Mitglieder' },
        { key: 'kommissionen', bezeichnung: 'Kommissionen' },
      ],
      unsubRealtime: null,
    }
  },
  mounted() {
    this.ladeMitglieder()
    this.unsubRealtime = subscribeRealtime(this.handleRealtimeEvent)
  },
  beforeUnmount() {
    if (this.unsubRealtime) {
      this.unsubRealtime()
      this.unsubRealtime = null
    }
  },
  methods: {
    async ladeMitglieder() {
      try {
        const { data } = await axios.get(generateUrl('/apps/parlwin/mitglieder'))
        this.mitglieder = [...data].sort((a, b) => {
          if ((a.aktiv !== false) !== (b.aktiv !== false)) {
            return (a.aktiv !== false) ? -1 : 1
          }
          const nameA = `${a.vorname || ''} ${a.name || ''}`.trim()
          const nameB = `${b.vorname || ''} ${b.name || ''}`.trim()
          return nameA.localeCompare(nameB)
        })
      } catch (fehler) {
        console.error('Fehler beim Laden der Mitglieder:', fehler)
      }
    },
    async ladeGeschaefte() {
      // Wird durch Geschaeftsliste selbst geladen; hier nur als Hook
    },
    handleRealtimeEvent(event) {
      const type = event?.type || ''
      if (
        type === 'sync.completed' ||
        type === 'mitglieder.updated' ||
        type === 'fraktion.roles.updated'
      ) {
        this.ladeMitglieder()
      }
    },
  },
}
</script>
