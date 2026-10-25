<template>
  <NcContent app-name="parlwin">
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
          @click="ansichtWechseln(ansicht.key)"
        >
          <template #icon>
            <NcIconSvgWrapper :path="ansicht.icon" :size="20" />
          </template>
        </NcAppNavigationItem>
        <li class="pw-nav-filter-item">
          <div id="pw-filter-slot" class="pw-nav-filter"></div>
        </li>
      </template>
      <template v-if="version" #footer>
        <div class="pw-nav-version">v{{ version }}</div>
      </template>
    </NcAppNavigation>

    <!-- F131: Vollbild. Der Knopf ist das Geschwister des Griffs, der die
         Seitenleiste einklappt: dieselbe Komponente, derselbe Behälter,
         dieselbe Ebene, dasselbe CSS — nur das Symbol ist ein anderes. Er
         erscheint auf flachem Schirm und dort, wo der Browser das Vollbild
         anbietet; dieses nimmt die Adressleiste des Browsers und seine
         Schaltflächen weg, und eine Webseite darf dorthin nur auf eine Geste
         des Benutzers hin. -->
    <Teleport v-if="vollbildKnopfZeigen" to=".app-navigation-toggle-wrapper" :disabled="!navigationBereit">
      <NcButton
        class="pw-vollbild-knopf"
        variant="tertiary"
        :aria-pressed="imVollbild"
        :title="imVollbild ? 'Vollbild verlassen' : 'Vollbild'"
        :aria-label="imVollbild ? 'Vollbild verlassen' : 'Vollbild'"
        @click="vollbildUmschalten"
      >
        <template #icon>
          <NcIconSvgWrapper :path="imVollbild ? mdiFullscreenExit : mdiFullscreen" />
        </template>
      </NcButton>
    </Teleport>

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
        :fraktionen="fraktionen"
        :kommissionen="kommissionen"
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
      <Vorstoesseliste
        v-else-if="aktiveAnsicht === 'vorstoesse'"
        :mitglieder="mitglieder"
        :fraktionen="fraktionen"
      />
      <Budgetliste
        v-else-if="aktiveAnsicht === 'budget'"
        :kommissionen="kommissionen"
        :mitglieder="mitglieder"
        :fraktionen="fraktionen"
      />
      <Fragestunde
        v-else-if="aktiveAnsicht === 'fragestunde'"
        :mitglieder="mitglieder"
      />
      <Sitzungstypenliste
        v-else-if="aktiveAnsicht === 'sitzungstypen'"
        :mitglieder="mitglieder"
        :fraktionen="fraktionen"
        :kommissionen="kommissionen"
      />
      <Bedienungsanleitung v-else-if="aktiveAnsicht === 'anleitung'" />
      <Changelog v-else-if="aktiveAnsicht === 'changelog'" />
      <Protokoll v-else-if="aktiveAnsicht === 'protokoll'" />
    </NcAppContent>
  </NcContent>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import NcContent from '@nextcloud/vue/components/NcContent'
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import {
  mdiClipboardTextOutline,
  mdiCalendarBlankOutline,
  mdiAccountGroupOutline,
  mdiBankOutline,
  mdiBullhornOutline,
  mdiFileDocumentEditOutline,
  mdiCashMultiple,
  mdiBookOpenOutline,
  mdiHistory,
  mdiClipboardListOutline,
  mdiCommentQuestionOutline,
  mdiFullscreen,
  mdiFullscreenExit,
} from '@mdi/js'
import { subscribeRealtime } from './realtime'
import Geschaeftsliste from './components/Geschaeftsliste.vue'
import Sitzungsliste from './components/Sitzungsliste.vue'
import Mitgliederliste from './components/Mitgliederliste.vue'
import Kommissionsliste from './components/Kommissionsliste.vue'
import Vorstoesseliste from './components/Vorstoesseliste.vue'
import Sitzungstypenliste from './components/Sitzungstypenliste.vue'
import Budgetliste from './components/Budgetliste.vue'
import Fragestunde from './components/Fragestunde.vue'
import Bedienungsanleitung from './components/Bedienungsanleitung.vue'
import Changelog from './components/Changelog.vue'
import Protokoll from './components/Protokoll.vue'

export default {
  name: 'ParliamentWinterthurApp',
  components: {
    NcContent,
    NcAppNavigation,
    NcAppNavigationItem,
    NcAppContent,
    NcButton,
    NcIconSvgWrapper,
    Geschaeftsliste,
    Sitzungsliste,
    Mitgliederliste,
    Kommissionsliste,
    Vorstoesseliste,
    Sitzungstypenliste,
    Budgetliste,
    Fragestunde,
    Bedienungsanleitung,
    Changelog,
    Protokoll,
  },
  data() {
    return {
      aktiveAnsicht: 'geschaefte',
      mitglieder: [],
      fraktionen: [],
      kommissionen: [],
      syncMeldung: '',
      syncFehler: false,
      version: String(window.PARLWIN_CONFIG?.version || ''),
      ansichten: [
        { key: 'geschaefte', bezeichnung: 'Geschäfte', icon: mdiClipboardTextOutline },
        { key: 'sitzungen', bezeichnung: 'Sitzungen', icon: mdiCalendarBlankOutline },
        { key: 'kommissionen', bezeichnung: 'Kommissionen', icon: mdiBankOutline },
        { key: 'vorstoesse', bezeichnung: 'Vorstösse', icon: mdiBullhornOutline },
        { key: 'budget', bezeichnung: 'Budget', icon: mdiCashMultiple },
        { key: 'fragestunde', bezeichnung: 'Fragestunde', icon: mdiCommentQuestionOutline },
        { key: 'mitglieder', bezeichnung: 'Mitglieder', icon: mdiAccountGroupOutline },
        { key: 'sitzungstypen', bezeichnung: 'Sitzungstypen', icon: mdiFileDocumentEditOutline },
        { key: 'protokoll', bezeichnung: 'Protokoll', icon: mdiClipboardListOutline },
        { key: 'anleitung', bezeichnung: 'Bedienungsanleitung', icon: mdiBookOpenOutline },
        { key: 'changelog', bezeichnung: 'Änderungsverlauf', icon: mdiHistory },
      ],
      unsubRealtime: null,
      // F131: Vollbild. Der Knopf erscheint nur, wo der Browser es anbietet und
      // der Schirm flach ist; dort gewinnt er die Adressleiste und die
      // Schaltflächen des Browsers als Fläche dazu.
      mdiFullscreen,
      mdiFullscreenExit,
      vollbildVerfuegbar: false,
      flacherSchirm: false,
      imVollbild: false,
      flachListe: null,
      // Der Knopf zieht zum Griff der Navigationsleiste; dessen Behälter gibt
      // es erst, wenn die Leiste steht.
      navigationBereit: false,
    }
  },
  computed: {
    vollbildKnopfZeigen() {
      return this.vollbildVerfuegbar && this.flacherSchirm
    },
  },
  mounted() {
    // Deep-Link: eine im URL-Hash genannte Ansicht direkt öffnen (z.B. …/#budget),
    // und auf Vor-/Zurück-Navigation reagieren.
    this.ansichtAusHashAnwenden()
    window.addEventListener('hashchange', this.ansichtAusHashAnwenden)
    this.ladeMitglieder()
    this.ladeFraktionen()
    this.ladeKommissionen()
    this.unsubRealtime = subscribeRealtime(this.handleRealtimeEvent)
    this.vollbildEinrichten()
    this.navigationBereit = typeof document !== 'undefined'
      && !!document.querySelector('.app-navigation-toggle-wrapper')
  },
  beforeUnmount() {
    window.removeEventListener('hashchange', this.ansichtAusHashAnwenden)
    if (this.unsubRealtime) {
      this.unsubRealtime()
      this.unsubRealtime = null
    }
    if (this.flachListe) { this.flachListe.removeEventListener('change', this.flacherSchirmGewechselt) }
    if (typeof document !== 'undefined') { document.removeEventListener('fullscreenchange', this.vollbildGewechselt) }
  },
  methods: {
    /**
     * F131: Vollbild. Der Knopf erscheint nur, wo beides zutrifft — der Browser
     * bietet das Vollbild an, und der Schirm ist flach (dieselbe Grenze von
     * 56rem Bildhöhe wie im Stylesheet). Auf einem hohen Schirm gewinnt das
     * Vollbild zu wenig, um einen Knopf zu rechtfertigen.
     */
    vollbildEinrichten() {
      if (typeof document === 'undefined') { return }
      const el = document.documentElement
      this.vollbildVerfuegbar = !!(document.fullscreenEnabled && el && typeof el.requestFullscreen === 'function')
      this.imVollbild = !!document.fullscreenElement
      document.addEventListener('fullscreenchange', this.vollbildGewechselt)
      if (typeof window !== 'undefined' && typeof window.matchMedia === 'function') {
        this.flachListe = window.matchMedia('(max-height: 56rem)')
        this.flacherSchirmGewechselt()
        this.flachListe.addEventListener('change', this.flacherSchirmGewechselt)
      }
    },
    flacherSchirmGewechselt() {
      this.flacherSchirm = !!(this.flachListe && this.flachListe.matches)
    },
    // Der Browser verlässt das Vollbild auch von sich aus, etwa mit der
    // Zurück-Taste; der Knopf folgt diesem Zustand, statt ihn zu raten.
    vollbildGewechselt() {
      this.imVollbild = !!document.fullscreenElement
    },
    async vollbildUmschalten() {
      try {
        if (document.fullscreenElement) {
          await document.exitFullscreen()
        } else {
          await document.documentElement.requestFullscreen()
        }
      } catch (e) {
        // Lehnt der Browser ab (keine Geste, Richtlinie des Geräts), bleibt
        // alles, wie es war; der Zustand kommt aus dem Ereignis.
      }
    },
    // Ansicht wechseln und als Deep-Link in den URL-Hash schreiben (direkt
    // aufrufbar und teilbar, z.B. für Design-Scans einzelner Seiten).
    ansichtWechseln(key) {
      this.aktiveAnsicht = key
      if (window.location.hash.replace(/^#/, '') !== key) {
        window.location.hash = key
      }
    },
    // Ansicht aus dem URL-Hash übernehmen, wenn er einen gültigen Bereich nennt.
    ansichtAusHashAnwenden() {
      const key = window.location.hash.replace(/^#/, '')
      if (key && this.ansichten.some(a => a.key === key) && this.aktiveAnsicht !== key) {
        this.aktiveAnsicht = key
      }
    },
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
