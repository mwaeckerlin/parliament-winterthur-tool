<template>
  <Teleport v-if="filterReady" to="#pw-search-slot">
    <NcTextField v-model="suche" label="Suche" placeholder="Name oder Beschreibung" trailing-button-icon="close" :show-trailing-button="!!suche" @trailing-button-click="suche = ''" />
  </Teleport>
  <Teleport v-if="filterReady" to="#pw-filter-slot">
    <div class="pw-filter-body">
      <NcCheckboxRadioSwitch v-model="nurAktive" type="switch">
        Nur aktive Kommissionen
      </NcCheckboxRadioSwitch>
      <NcCheckboxRadioSwitch v-model="nurAktiveMitglieder" type="switch">
        Nur aktive Mitglieder
      </NcCheckboxRadioSwitch>
    </div>
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
        <div class="pw-kommission-kopf" @click="toggleKommission(k.id)">
          <div>
            <h3>{{ k.name }}</h3>
            <p class="pw-kommission-status">{{ istAktiv(k) ? 'Aktiv' : 'Aufgelöst oder inaktiv' }}</p>
          </div>
          <span class="pw-toggle">{{ offene.includes(k.id) ? '▲' : '▼' }}</span>
        </div>
        <div v-if="offene.includes(k.id)" class="pw-kommission-details">
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
            <strong>Mitglieder ({{ sichtbareMitglieder(k).length }}<template v-if="!nurAktiveMitglieder && aktiveMitgliederZahl(k) !== sichtbareMitglieder(k).length"> / {{ aktiveMitgliederZahl(k) }} aktiv</template>):</strong>
            <div class="pw-kommission-mitglied-liste">
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
                <div v-if="mitglied.partei" class="pw-kommission-mitglied-zeile">{{ mitglied.partei }}</div>
                <div v-if="mitglied.fraktion" class="pw-kommission-mitglied-zeile">{{ mitglied.fraktion }}</div>
                <a v-if="mitglied.email" :href="'mailto:' + mitglied.email" class="pw-kommission-mitglied-zeile pw-kommission-mitglied-email">{{ mitglied.email }}</a>
              </article>
            </div>
          </div>
          <p v-else class="pw-hinweis">Keine Mitglieder synchronisiert.</p>
          <p v-if="!geschaefteFuer(k).length" class="pw-hinweis">
            Keine Geschäfte mit Status „Bei Kommission {{ k.name }} pendent“.
          </p>
          <div v-if="!istAktiv(k)" class="pw-inline-note">
            Diese Kommission erscheint nur, weil historische Daten vorhanden sind.
          </div>
        </div>
        </div>
        <NcEmptyContent v-if="gefilterteKommissionen.length === 0" name="Keine Kommissionen gefunden" />
      </div>
    </section>

    <Teleport to="body">
      <div v-if="ausgewaehlteGeschaeftId" class="pw-modal-overlay" @click.self="schliesseDetail">
        <div class="pw-modal">
          <div class="pw-modal-kopf pw-modal-kopf-leer">
            <button type="button" class="button pw-btn-schliessen" aria-label="Dialog schliessen" @click="schliesseDetail">✕</button>
          </div>
          <GeschaeftDetail
            :geschaeft-id="ausgewaehlteGeschaeftId"
            :mitglieder="mitglieder"
            @gespeichert="nachSpeichern"
          />
        </div>
      </div>
    </Teleport>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import { getCurrentUser } from '@nextcloud/auth'
import axios from '@nextcloud/axios'
import { subscribeRealtime } from '../realtime'
import GeschaeftDetail from './GeschaeftDetail.vue'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'

export default {
  name: 'Kommissionsliste',
  components: { GeschaeftDetail, NcTextField, NcCheckboxRadioSwitch, NcLoadingIcon, NcEmptyContent },
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
      offene: [],
      unsubRealtime: null,
      ausgewaehlteGeschaeftId: null,
    }
  },
  computed: {
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
      // Bei aktiver Suche alle Treffer-Kommissionen automatisch aufklappen,
      // damit die gefundenen Mitglieder/Geschäfte sichtbar werden.
      const term = (neu || '').trim()
      if (!term) return
      const treffer = this.gefilterteKommissionen.map(k => k.id)
      const zusaetzlich = treffer.filter(id => !this.offene.includes(id))
      if (zusaetzlich.length) {
        this.offene = [...this.offene, ...zusaetzlich]
      }
    },
  },
  mounted() {
    this.$nextTick(() => { this.filterReady = true })
    this.ladeKommissionen()
    this.ladeGeschaefte()
    this.unsubRealtime = subscribeRealtime(this.handleRealtimeEvent)
  },
  beforeUnmount() {
    if (this.unsubRealtime) {
      this.unsubRealtime()
      this.unsubRealtime = null
    }
  },
  methods: {
    async ladeKommissionen() {
      this.laden = true
      try {
        const { data } = await axios.get(generateUrl('/apps/parlwin/kommissionen'))
        this.kommissionen = (Array.isArray(data) ? data : []).map(k => ({
          ...k,
          mitgliederArray: this.parseMitglieder(k.mitglieder),
        }))
      } catch (e) {
        console.error('Fehler beim Laden der Kommissionen:', e)
      } finally {
        this.laden = false
      }
    },
    aktualisiereMitgliederArrays() {
      this.kommissionen = this.kommissionen.map((k) => ({
        ...k,
        mitgliederArray: this.parseMitglieder(k.mitglieder),
      }))
    },
    async ladeGeschaefte() {
      try {
        const { data } = await axios.get(generateUrl('/apps/parlwin/geschaefte'), { params: { show_erledigt: 1, limit: 1000 } })
        this.geschaefte = Array.isArray(data) ? data : []
      } catch (e) {
        console.error('Fehler beim Laden der Geschäfte:', e)
        this.geschaefte = []
      }
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
      // Primär: explizites aktiv-Flag aus dem Scraper (vom Parlamentsserver)
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
     * Liefert alle Geschäfte, deren Status auf diese Kommission verweist.
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
      if (type === 'sync.completed') {
        this.ladeKommissionen()
        this.ladeGeschaefte()
      }
    },
    toggleKommission(id) {
      if (this.offene.includes(id)) {
        this.offene = this.offene.filter(i => i !== id)
      } else {
        this.offene.push(id)
      }
    },
    oeffneDetail(geschaeftId) {
      this.ausgewaehlteGeschaeftId = geschaeftId
    },
    schliesseDetail() {
      this.ausgewaehlteGeschaeftId = null
    },
    async nachSpeichern() {
      await this.ladeGeschaefte()
      this.$emit('aktualisiert')
    },
  },
}
</script>
