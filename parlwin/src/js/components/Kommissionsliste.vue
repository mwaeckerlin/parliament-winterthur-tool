<template>
  <Teleport v-if="filterReady" to="#pw-search-slot">
    <NcTextField v-model="suche" label="Suche" placeholder="Name oder Beschreibung" trailing-button-icon="close" :show-trailing-button="!!suche" @trailing-button-click="suche = ''" />
  </Teleport>
  <Teleport v-if="filterReady" to="#pw-filter-slot">
    <FilterPanel @reset="filterZuruecksetzen">
      <NcCheckboxRadioSwitch v-model="nurAktive" type="switch">
        Nur aktive Kommissionen
      </NcCheckboxRadioSwitch>
      <NcCheckboxRadioSwitch v-model="nurAktiveMitglieder" type="switch">
        Nur aktive Mitglieder
      </NcCheckboxRadioSwitch>
    </FilterPanel>
  </Teleport>

  <section class="pw-view-content pw-kommissionen">
      <header class="pw-view-header">
        <h2 class="pw-view-title">Kommissionen</h2>
        <span class="pw-view-count">{{ gefilterteKommissionen.length }}</span>
      </header>
      <div v-if="laden" class="pw-laden"><NcLoadingIcon :size="32" /></div>

      <div v-else class="pw-card-grid">
        <div
          v-for="k in gefilterteKommissionen"
          :key="k.id"
          class="pw-kommission-karte"
          :class="{ 'ist-inaktiv': !istAktiv(k) }"
        >
        <div class="pw-kommission-kopf">
          <div>
            <!-- Der Name ist deutsch, auch wenn Nextcloud eine andere Sprache
                 eingestellt hat; nur so trennt der Browser ein langes Wort. -->
            <h3 lang="de">{{ kuerze(k.name) }}</h3>
            <p class="pw-kommission-status">{{ istAktiv(k) ? 'Aktiv' : 'Aufgelöst oder inaktiv' }}</p>
          </div>
          <!-- F141: Derselbe Dialog wie «+ Eigenes Geschäft» in der Ansicht
               «Geschäfte», mit dieser Kommission als Vorauswahl. Der Weg über
               die andere Ansicht und die Auswahl von Hand war zu umständlich.
               Eine inaktive Kommission nimmt keine neuen Geschäfte an. -->
          <NcButton
            v-if="istAktiv(k)"
            type="secondary"
            class="pw-kommission-neues-geschaeft"
            @click="neuesGeschaeftOeffnen(k)"
          >Neues Geschäft</NcButton>
        </div>
        <div class="pw-kommission-details">
          <p v-if="k.beschreibung">{{ k.beschreibung }}</p>
          <div v-if="geschaefteFuer(k).length" class="pw-kommission-geschaefte">
            <strong>Pendente Geschäfte ({{ geschaefteFuer(k).length }}):</strong>
            <ul class="pw-kommission-geschaeftsliste">
              <li
                v-for="g in geschaefteFuer(k)"
                :key="g.id"
                class="pw-kommission-geschaeft-eintrag"
                role="button"
                tabindex="0"
                @click="oeffneDetail(g.id)"
                @keydown.enter.prevent="oeffneDetail(g.id)"
                @keydown.space.prevent="oeffneDetail(g.id)"
              >
                <span class="pw-nr">{{ g.nummer }}</span>
                <a v-if="g.url" :href="g.url" target="_blank" class="pw-inline-link" title="Extern öffnen" @click.stop>↗</a>
                <span class="pw-titel">{{ g.titel }}</span>
              </li>
            </ul>
          </div>
          <div v-if="sichtbareMitglieder(k).length" class="pw-kommission-mitglieder">
            <!-- Die Mitglieder füllen die Karte und stehen darum eingeklappt;
                 ihre Zahl ist auch zugeklappt zu lesen (F118). -->
            <div
              class="pw-kommission-mitglieder-kopf"
              role="button"
              tabindex="0"
              :aria-expanded="String(offeneMitglieder.includes(k.id))"
              @click="toggleMitglieder(k.id)"
              @keydown.enter.prevent="toggleMitglieder(k.id)"
              @keydown.space.prevent="toggleMitglieder(k.id)"
            >
              <strong>Mitglieder ({{ sichtbareMitglieder(k).length }}<template v-if="!nurAktiveMitglieder && aktiveMitgliederZahl(k) !== sichtbareMitglieder(k).length"> / {{ aktiveMitgliederZahl(k) }} aktiv</template>):</strong>
              <span class="pw-toggle">{{ offeneMitglieder.includes(k.id) ? '▲' : '▼' }}</span>
            </div>
            <div v-if="offeneMitglieder.includes(k.id)" class="pw-kommission-mitglied-liste">
              <article
                v-for="mitglied in sichtbareMitglieder(k)"
                :key="mitglied.externId || mitglied.label"
                class="pw-kommission-mitglied-karte"
                :class="{ 'pw-kommission-mitglied-inaktiv': mitglied.aktiv === false, 'pw-kommission-mitglied-eigen': mitglied.eigeneFraktion }"
              >
                <div class="pw-kommission-mitglied-zeile pw-kommission-mitglied-kopf">
                  <strong>{{ mitglied.label }}</strong>
                  <span v-if="mitglied.funktion" class="pw-kommission-mitglied-rolle">{{ mitglied.funktion }}</span>
                </div>
                <div v-if="mitglied.partei" class="pw-kommission-mitglied-zeile">{{ kuerze(mitglied.partei) }}</div>
                <div v-if="mitglied.fraktion" class="pw-kommission-mitglied-zeile">{{ kuerze(mitglied.fraktion) }}</div>
                <a v-if="mitglied.email" :href="'mailto:' + mitglied.email" class="pw-kommission-mitglied-zeile pw-kommission-mitglied-email">{{ mitglied.email }}</a>
              </article>
            </div>
          </div>
          <p v-else class="pw-hinweis">Keine Mitglieder synchronisiert.</p>
          <p v-if="!geschaefteFuer(k).length" class="pw-hinweis">
            Keine Geschäfte mit Status «Bei Kommission {{ kuerze(k.name) }} pendent»
            und keines, das dieser Kommission zugewiesen ist.
          </p>
          <div v-if="!istAktiv(k)" class="pw-inline-note">
            Diese Kommission erscheint nur, weil historische Daten vorhanden sind.
          </div>
        </div>
        </div>
        <NcEmptyContent v-if="gefilterteKommissionen.length === 0" name="Keine Kommissionen gefunden" />
      </div>
    </section>

    <GeschaeftMaske
      :geschaeft-id="ausgewaehlteGeschaeftId"
      :bestand="bestand"
      :mitglieder="mitglieder"
      :vorauswahl-kommission="neueGeschaeftKommission"
      @schliessen="schliesseDetail"
      @erstellt="nachErstellen"
      @gespeichert="nachSpeichern"
      @oeffne-geschaeft="oeffneDetail"
    />
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import { getCurrentUser } from '@nextcloud/auth'
import axios from '@nextcloud/axios'
import { kuerze } from '../utils'
import { subscribeRealtime } from '../realtime'
import { listeAbgleichen } from '../nachladen'
import { BESTAND_SCHLUESSEL, bestandAnbinden } from '../geschaefte-bestand.js'
import GeschaeftMaske from './GeschaeftMaske.vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import FilterPanel from './FilterPanel.vue'

export default {
  name: 'Kommissionsliste',
  components: { GeschaeftMaske, NcButton, NcTextField, NcCheckboxRadioSwitch, NcLoadingIcon, NcEmptyContent, FilterPanel },
  inject: { geteilterBestand: { from: BESTAND_SCHLUESSEL, default: null } },
  props: {
    mitglieder: { type: Array, default: () => [] },
  },
  emits: ['aktualisiert'],
  data() {
    return {
      filterReady: false,
      kommissionen: [],
      geschaefte: [],
      laden: true,
      suche: '',
      nurAktive: true,
      nurAktiveMitglieder: true,
      offeneMitglieder: [],
      // Der Zustand des Anwenders, während der Druck alle Karten offen hat.
      offeneVorDemDruck: null,
      unsubRealtime: null,
      // Das Geschäft in der Maske: null bei geschlossener Maske, 0 für ein neues.
      ausgewaehlteGeschaeftId: null,
      // F141: Während ein neues Geschäft an einer Kommission erfasst wird, steht
      // ihr Name als Vorauswahl in der Maske.
      neueGeschaeftKommission: '',
    }
  },
  computed: {
    detailOffen() {
      return this.ausgewaehlteGeschaeftId !== null
    },
    gefilterteKommissionen() {
      let liste = [...this.kommissionen]
      if (this.nurAktive) {
        liste = liste.filter((kommission) => this.istAktiv(kommission))
      }
      if (!this.suche) return liste
      const s = this.suche.toLowerCase()
      return liste.filter(k => {
        if ((k.name || '').toLowerCase().includes(s)) return true
        if ((k.beschreibung || '').toLowerCase().includes(s)) return true
        // Mitgliedernamen / Partei / Fraktion durchsuchen.
        const mitglieder = Array.isArray(k.mitgliederArray) ? k.mitgliederArray : []
        if (mitglieder.some(m =>
          (m.label || '').toLowerCase().includes(s) ||
          (m.partei || '').toLowerCase().includes(s) ||
          (m.fraktion || '').toLowerCase().includes(s) ||
          (m.email || '').toLowerCase().includes(s) ||
          (m.funktion || '').toLowerCase().includes(s),
        )) return true
        // Pendente Geschäfte: Nr (GGR-Nr) und Titel.
        const geschaefte = this.geschaefteFuer(k)
        if (geschaefte.some(g =>
          (g.nummer || '').toLowerCase().includes(s) ||
          (g.titel || '').toLowerCase().includes(s),
        )) return true
        return false
      })
    },
    ausgewaehltesGeschaeft() {
      if (!this.ausgewaehlteGeschaeftId) return null
      return this.geschaefte.find(g => String(g.id) === String(this.ausgewaehlteGeschaeftId)) || null
    },
    eigeneFraktion() {
      const user = getCurrentUser()
      const uid = user?.uid
      if (!uid) return ''
      const treffer = (this.mitglieder || []).find((m) => {
        const u = m.nextcloudUid || m.nextcloud_uid || ''
        return u && String(u).toLowerCase() === String(uid).toLowerCase()
      })
      return treffer ? (treffer.fraktion || '') : ''
    },
  },
  watch: {
    mitglieder() {
      this.aktualisiereMitgliederArrays()
    },
    eigeneFraktion() {
      this.aktualisiereMitgliederArrays()
    },
    suche(neu) {
      // Die Geschäfte stehen ohnehin offen; aufzuklappen sind allein die
      // Mitglieder, und auch nur dort, wo einer von ihnen der Treffer ist —
      // sonst öffnete eine Suche nach einem Geschäft überall die Mitglieder.
      const term = (neu || '').trim().toLowerCase()
      if (!term) return
      const trifftMitglied = (k) => {
        const mitglieder = Array.isArray(k.mitgliederArray) ? k.mitgliederArray : []
        return mitglieder.some(m =>
          (m.label || '').toLowerCase().includes(term) ||
          (m.partei || '').toLowerCase().includes(term) ||
          (m.fraktion || '').toLowerCase().includes(term) ||
          (m.email || '').toLowerCase().includes(term) ||
          (m.funktion || '').toLowerCase().includes(term),
        )
      }
      const treffer = this.gefilterteKommissionen.filter(trifftMitglied).map(k => k.id)
      const zusaetzlich = treffer.filter(id => !this.offeneMitglieder.includes(id))
      if (zusaetzlich.length) {
        this.offeneMitglieder = [...this.offeneMitglieder, ...zusaetzlich]
      }
    },
  },
  created() {
    const { bestand, eigener } = bestandAnbinden(this, this.geteilterBestand, () => this.geschaefteUebernehmen())
    this.bestand = bestand
    this.eigenerBestand = eigener
  },
  mounted() {
    this.$nextTick(() => { this.filterReady = true })
    this.ladeKommissionen()
    this.ladeGeschaefte()
    this.unsubRealtime = subscribeRealtime(this.handleRealtimeEvent)
    // Eine eingeklappte Mitgliederliste steht gar nicht im Dokument, also
    // kann kein Stylesheet sie auf das Papier holen: Auf dem Ausdruck stand
    // «Mitglieder (12):» und darunter nichts. Vor dem Druck klappen alle auf,
    // danach steht wieder der Zustand des Anwenders da.
    window.addEventListener('beforeprint', this.vorDemDruck)
    window.addEventListener('afterprint', this.nachDemDruck)
  },
  beforeUnmount() {
    window.removeEventListener('beforeprint', this.vorDemDruck)
    window.removeEventListener('afterprint', this.nachDemDruck)
    if (this.eigenerBestand) { this.bestand.abbrechen() }
    if (this.unsubRealtime) {
      this.unsubRealtime()
      this.unsubRealtime = null
    }
  },
  methods: {
    kuerze,
    vorDemDruck() {
      this.offeneVorDemDruck = [...this.offeneMitglieder]
      this.offeneMitglieder = this.kommissionen.map(k => k.id)
    },
    nachDemDruck() {
      if (this.offeneVorDemDruck) {
        this.offeneMitglieder = this.offeneVorDemDruck
        this.offeneVorDemDruck = null
      }
    },
    filterZuruecksetzen() {
      this.suche = ''
      this.nurAktive = true
      this.nurAktiveMitglieder = true
    },
    /**
     * Lädt die Kommissionen. Nach einem Ereignis (`hintergrund`) bleibt die
     * Ansicht stehen und wird danach an Ort und Stelle angeglichen; nur beim
     * ersten Laden erscheint der Lade-Zustand.
     */
    async ladeKommissionen(hintergrund = false) {
      if (!hintergrund) this.laden = true
      try {
        const { data } = await axios.get(generateUrl('/apps/parlwin/kommissionen'))
        const neu = (Array.isArray(data) ? data : []).map(k => ({
          ...k,
          mitgliederArray: this.parseMitglieder(k.mitglieder),
        }))
        if (hintergrund) {
          listeAbgleichen(this.kommissionen, neu)
        } else {
          this.kommissionen = neu
        }
      } catch (e) {
        console.error('Fehler beim Laden der Kommissionen:', e)
      } finally {
        if (!hintergrund) this.laden = false
      }
    },
    aktualisiereMitgliederArrays() {
      this.kommissionen = this.kommissionen.map((k) => ({
        ...k,
        mitgliederArray: this.parseMitglieder(k.mitglieder),
      }))
    },
    /**
     * ALLE Geschäfte, seitenweise, aus der Liste, die die Ansichten teilen
     * (F150). Ein fester Deckel (früher 1000) liess bei einem gewachsenen
     * Bestand pendente Geschäfte einer Kommission stillschweigend verschwinden.
     */
    async ladeGeschaefte() {
      await this.bestand.laden()
      this.geschaefteUebernehmen()
    },
    geschaefteUebernehmen() {
      listeAbgleichen(this.geschaefte, this.bestand.zustand.geschaefte)
    },
    parseMitglieder(raw) {
      if (!raw) return []
      let arr
      try {
        arr = typeof raw === 'string' ? JSON.parse(raw) : raw
      } catch {
        return []
      }
      if (!Array.isArray(arr)) return []
      const eigeneFraktion = (this.eigeneFraktion || '').toLowerCase()
      return arr.map((eintrag) => {
        // Neues Schema: Objekt mit externId, label, funktion, partei, aktiv, datumVon, datumBis
        if (eintrag && typeof eintrag === 'object') {
          const externId = String(eintrag.externId ?? eintrag.extern_id ?? eintrag.id ?? '')
          const treffer = externId
            ? this.mitglieder.find((m) => String(m.externId || m.extern_id || '') === externId)
            : null
          const label = (eintrag.label && String(eintrag.label).trim())
            || (treffer ? `${treffer.vorname || ''} ${treffer.name || ''}`.trim() : '')
            || externId
          const fraktion = treffer ? (treffer.fraktion || '') : ''
          return {
            externId,
            label,
            funktion: eintrag.funktion || '',
            partei: eintrag.partei || (treffer ? treffer.partei || '' : ''),
            fraktion,
            email: treffer ? (treffer.email || '') : '',
            datumVon: eintrag.datumVon || '',
            datumBis: eintrag.datumBis || '',
            aktiv: eintrag.aktiv !== false,
            eigeneFraktion: !!eigeneFraktion && fraktion.toLowerCase() === eigeneFraktion,
          }
        }
        // Altes Schema: nur externId-String
        const externId = String(eintrag ?? '')
        const treffer = this.mitglieder.find((m) => String(m.externId || m.extern_id || '') === externId)
        const fraktion = treffer ? (treffer.fraktion || '') : ''
        return {
          externId,
          label: treffer ? `${treffer.vorname || ''} ${treffer.name || ''}`.trim() : externId,
          funktion: '',
          partei: treffer ? treffer.partei || '' : '',
          fraktion,
          email: treffer ? (treffer.email || '') : '',
          datumVon: '',
          datumBis: '',
          aktiv: true,
          eigeneFraktion: !!eigeneFraktion && fraktion.toLowerCase() === eigeneFraktion,
        }
      })
    },
    sichtbareMitglieder(kommission) {
      const arr = kommission?.mitgliederArray || []
      const liste = this.nurAktiveMitglieder ? arr.filter(m => m.aktiv !== false) : arr
      // Sortierung:
      //   0. Präsident
      //   1. Vizepräsident
      //   2. Mitglied der eigenen Fraktion
      //   3. Alle anderen Mitglieder
      //   4. Protokollführer und andere Rollen
      const funktionsRang = (m) => {
        const v = (m.funktion || '').toLowerCase().trim()
        const istPraesident = v.includes('präsident') || v.includes('praesident')
        const istVize = v.includes('vize') || v.includes('stellvert')
        if (istPraesident && istVize) return 1
        if (istPraesident) return 0
        const istMitglied = v === '' || v === 'mitglied'
        if (istMitglied) {
          return m.eigeneFraktion ? 2 : 3
        }
        return 4
      }
      return [...liste].sort((a, b) => {
        if ((a.aktiv !== false) !== (b.aktiv !== false)) return a.aktiv !== false ? -1 : 1
        const rA = funktionsRang(a)
        const rB = funktionsRang(b)
        if (rA !== rB) return rA - rB
        return (a.label || '').localeCompare(b.label || '', 'de')
      })
    },
    aktiveMitgliederZahl(kommission) {
      const arr = kommission?.mitgliederArray || []
      return arr.filter(m => m.aktiv !== false).length
    },
    istAktiv(kommission) {
      if (!kommission || kommission.geloescht === true) return false
      // Zuerst: das ausdrückliche Kennzeichen «aktiv» aus der Parlamentswebseite
      if (kommission.aktiv === false) return false
      // Sekundär: datumBis in der Vergangenheit ⇒ aufgelöst
      const bis = kommission.datumBis || ''
      if (bis) {
        const heute = new Date().toISOString().slice(0, 10)
        if (bis < heute) return false
      }
      return true
    },
    /**
     * Liefert alle Geschäfte dieser Kommission, aus zwei Quellen:
     *
     * 1. **Ausdrücklich zugewiesen:** Im Feld «Kommission» des Geschäfts steht
     *    der Name dieser Kommission. So weist die Fraktion ein eigenes Geschäft
     *    zu; dessen Status ist «Pendent» und nennt keine Kommission.
     * 2. **Aus dem Status des Parlaments**, nach der folgenden Strategie.
     *
     * Beispiel-Status-Strings vom Parlament:
     *   - "Bei der Aufsichtskommission pendent"
     *   - "Bei der Kommission Soziales und Sicherheit pendent"
     *
     * Beispiel-Kommissionsnamen:
     *   - "Aufsichtskommission"
     *   - "Sachkommission Soziales und Sicherheit"
     *   - "Spezialkommission Pensionskasse"
     *
     * Strategie:
     *   1. Status muss überhaupt ein "*kommission*"-Wort enthalten.
     *   2. Alle "*kommission*"-Wörter werden auf beiden Seiten entfernt
     *      (also "Sachkommission", "Spezialkommission", "Kommission", ...).
     *   3. Bleiben Tokens vom Kommissionsnamen übrig (z.B. "Soziales",
     *      "Sicherheit"), müssen alle im strippten Status vorkommen.
     *   4. Bleibt nach dem Strip nichts vom Namen übrig (z.B. nur
     *      "Aufsichtskommission"), wird das Ursprungs-Wort direkt im
     *      Status gesucht.
     */
    geschaefteFuer(kommission) {
      if (!kommission?.name) return []
      const stripKommissionWords = (s) => (s || '')
        .toLowerCase()
        .replace(/[,.;:()/]/g, ' ')
        .replace(/\S*kommission\S*/g, ' ')
        .replace(/\s+/g, ' ')
        .trim()
      const stopwords = new Set([
        'und', 'der', 'die', 'das', 'den', 'dem', 'bei', 'pendent',
        'für', 'fuer', 'in', 'im', 'zur', 'zum', 'von', 'vom',
      ])
      const tokenize = (s) => stripKommissionWords(s)
        .split(/\s+/)
        .filter(t => t.length >= 3 && !stopwords.has(t))
      const knameRaw = (kommission.name || '').toLowerCase().trim()
      const knameTokens = tokenize(kommission.name)
      return this.geschaefte.filter(g => {
        if ((g.kommission || '').toLowerCase().trim() === knameRaw) return true
        const s = (g.status || '').toLowerCase()
        if (!/\S*kommission\S*/.test(s)) return false
        if (knameTokens.length === 0) {
          // Kommissionsname besteht nur aus "*kommission"-Wörtern
          // (z.B. "Aufsichtskommission"): direkt im Status suchen.
          return s.includes(knameRaw)
        }
        const sStripped = stripKommissionWords(g.status)
        return knameTokens.every(t => sStripped.includes(t))
      })
    },
    handleRealtimeEvent(event) {
      const type = event?.type || ''
      // Die geteilte Liste der Geschäfte bekommt ihre Ereignisse von App.vue.
      if (this.eigenerBestand) { this.bestand.ereignis(event) }
      if (type === 'sync.completed') {
        this.ladeKommissionen(true)
      }
    },
    toggleMitglieder(id) {
      if (this.offeneMitglieder.includes(id)) {
        this.offeneMitglieder = this.offeneMitglieder.filter(i => i !== id)
      } else {
        this.offeneMitglieder.push(id)
      }
    },
    oeffneDetail(geschaeftId) {
      this.neueGeschaeftKommission = ''
      this.ausgewaehlteGeschaeftId = geschaeftId
    },
    /**
     * F141: Dieselbe Maske wie «+ Eigenes Geschäft», mit dieser Kommission
     * schon im Feld. Die Maske legt mit der ID 0 ein neues Geschäft an.
     */
    neuesGeschaeftOeffnen(kommission) {
      this.neueGeschaeftKommission = kommission && kommission.name ? kommission.name : ''
      this.ausgewaehlteGeschaeftId = 0
    },
    schliesseDetail() {
      this.ausgewaehlteGeschaeftId = null
      this.neueGeschaeftKommission = ''
    },
    // Die Maske hat das Geschäft schon in der geteilten Liste nachgeladen.
    nachSpeichern() {
      this.$emit('aktualisiert')
    },
    // Nach dem Anlegen geht dieselbe Maske in die Bearbeitung über, wie in
    // «Geschäfte», sodass sich Dokumente und Notizen unmittelbar anschliessen.
    nachErstellen(id) {
      this.neueGeschaeftKommission = ''
      this.ausgewaehlteGeschaeftId = id || null
      this.$emit('aktualisiert')
    },
  },
}
</script>
