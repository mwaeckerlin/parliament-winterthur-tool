<template>
  <NcAppNavigation>
    <template #list>
      <li class="pw-nav-search-item">
        <div id="pw-search-slot" class="pw-nav-search"></div>
      </li>
      <NcAppNavigationItem
        v-for="ansicht in ansichten"
        :key="ansicht.key"
        :name="ansicht.bezeichnung"
        :active="aktiveAnsicht === ansicht.key"
        @click="aktiveAnsicht = ansicht.key"
      >
        <template #icon>
          <span class="pw-nav-emoji" aria-hidden="true">{{ ansicht.icon }}</span>
        </template>
      </NcAppNavigationItem>
      <li class="pw-nav-filter-item">
        <div id="pw-filter-slot" class="pw-nav-filter"></div>
      </li>
    </template>
  </NcAppNavigation>

  <NcAppContent>
    <div v-if="syncMeldung" class="pw-sync-meldung" :class="syncFehler ? 'fehler' : 'erfolg'">
      {{ syncMeldung }}
    </div>

    <Geschaeftsliste
      v-if="aktiveAnsicht === 'geschaefte'"
      :mitglieder="mitglieder"
      @aktualisiert="ladeGeschaefte"
    />
    <Sitzungsliste
      v-else-if="aktiveAnsicht === 'sitzungen'"
      :mitglieder="mitglieder"
    />
    <Mitgliederliste
      v-else-if="aktiveAnsicht === 'mitglieder'"
      :mitglieder="mitglieder"
      :fraktionen="fraktionen"
      :kommissionen="kommissionen"
    />
    <Kommissionsliste
      v-else-if="aktiveAnsicht === 'kommissionen'"
      :mitglieder="mitglieder"
    />
  </NcAppContent>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import { subscribeRealtime } from './realtime'
import Geschaeftsliste from './components/Geschaeftsliste.vue'
import Sitzungsliste from './components/Sitzungsliste.vue'
import Mitgliederliste from './components/Mitgliederliste.vue'
import Kommissionsliste from './components/Kommissionsliste.vue'

export default {
  name: 'ParliamentWinterthurApp',
  components: {
    NcAppNavigation,
    NcAppNavigationItem,
    NcAppContent,
    Geschaeftsliste,
    Sitzungsliste,
    Mitgliederliste,
    Kommissionsliste,
  },
  provide() {
    // NcAppNavigation / NcAppContent in @nextcloud/vue v9 erwarten diesen Inject
    // normalerweise vom NcContent-Wrapper. Da wir ohne NcContent direkt im
    // Nextcloud-#content-Element mounten, liefern wir den No-Op selbst.
    return {
      'NcContent:setHasAppNavigation': () => {},
    }
  },
  data() {
    return {
      aktiveAnsicht: 'geschaefte',
      mitglieder: [],
      fraktionen: [],
      kommissionen: [],
      syncMeldung: '',
      syncFehler: false,
      ansichten: [
        { key: 'geschaefte', bezeichnung: 'Geschäfte', icon: '📋' },
        { key: 'sitzungen', bezeichnung: 'Sitzungen', icon: '📅' },
        { key: 'mitglieder', bezeichnung: 'Mitglieder', icon: '👥' },
        { key: 'kommissionen', bezeichnung: 'Kommissionen', icon: '🏛' },
      ],
      unsubRealtime: null,
    }
  },
  mounted() {
    this.ladeMitglieder()
    this.ladeFraktionen()
    this.ladeKommissionen()
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
    async ladeFraktionen() {
      try {
        const { data } = await axios.get(generateUrl('/apps/parlwin/fraktionen'))
        this.fraktionen = Array.isArray(data) ? data : []
      } catch (fehler) {
        console.error('Fehler beim Laden der Fraktionen:', fehler)
      }
    },
    async ladeKommissionen() {
      try {
        const { data } = await axios.get(generateUrl('/apps/parlwin/kommissionen'))
        this.kommissionen = Array.isArray(data) ? data : []
      } catch (fehler) {
        console.error('Fehler beim Laden der Kommissionen:', fehler)
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
        this.ladeFraktionen()
        this.ladeKommissionen()
      }
    },
  },
}
</script>
