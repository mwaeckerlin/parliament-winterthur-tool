<template>
  <Teleport v-if="filterReady" to="#pw-search-slot">
    <NcTextField v-model="suche" label="Suche" placeholder="Nr. oder Titel" trailing-button-icon="close" :show-trailing-button="!!suche" @trailing-button-click="suche = ''" />
  </Teleport>
  <Teleport v-if="filterReady" to="#pw-filter-slot">
    <div class="pw-filter-body">
      <NcSelect v-model="entscheidungsbedarfOption" :options="entscheidungsbedarfOptions" :clearable="false" input-label="Entscheidungsbedarf" />
      <PwMultiSelect :model-value="filterStatus" :options="alleStatus" input-label="Status" placeholder="Alle" @update:model-value="filterStatus = $event || []" />
      <PwMultiSelect :model-value="filterTyp" :options="alleTypen" input-label="Typ" placeholder="Alle" @update:model-value="filterTyp = $event || []" />
      <PwMultiSelect :model-value="filterZustaendige" :options="zustaendigeLabels" input-label="Zuständigkeit" placeholder="Alle" @update:model-value="filterZustaendige = $event || []" />
      <PwMultiSelect :model-value="filterBeschlussOptions" :options="beschlussOptionsList" input-label="Beschluss" placeholder="Alle" @update:model-value="filterBeschluss = ($event || []).map(o => o.value)" />
      <NcCheckboxRadioSwitch v-model="zeigeErledigte" type="switch">
        Erledigte anzeigen
      </NcCheckboxRadioSwitch>
      <NcButton type="tertiary" wide @click="resetFilter">Filter zurücksetzen</NcButton>
    </div>
  </Teleport>

  <section class="pw-view-content pw-geschaefte">
      <header class="pw-view-header">
        <h2 class="pw-view-title">Geschäfte</h2>
        <span class="pw-view-count">{{ gefilterteGeschaefte.length }}</span>
      </header>
      <div v-if="laden" class="pw-laden"><NcLoadingIcon :size="32" /></div>

      <template v-else>
        <div class="pw-table-wrap pw-table-desktop">
          <table class="pw-tabelle pw-tabelle-geschaefte" :class="{ 'pw-tabelle-mit-status': statusSpalteAnzeigen }" lang="de">
        <thead>
          <tr>
            <th @click="sortiereNach('nummer')" class="pw-sortierbar pw-col-nr">Nr.</th>
            <th @click="sortiereNach('titel')" class="pw-sortierbar pw-col-titel">Titel</th>
            <th v-if="statusSpalteAnzeigen" @click="sortiereNach('status')" class="pw-sortierbar pw-col-status">Status</th>
            <th class="pw-col-zustaendig">Zuständig</th>
            <th class="pw-col-beschluss">Beschluss</th>
          </tr>
        </thead>
          <tbody>
            <tr
              v-for="g in gefilterteGeschaefte"
              :key="g.id"
              :class="['pw-table-row-clickable', { 'pw-geloescht': g.geloescht }]"
              tabindex="0"
              role="button"
              :aria-label="`Geschäft ${g.nummer || ''} öffnen`"
              @click="oeffneDetail(g.id)"
              @keydown="zeilenKeydown($event, g.id)"
            >
              <td data-label="Nr." class="pw-col-nr">
                <strong>{{ g.nummer }}</strong>
                <span class="pw-col-nr-datum">{{ formatieredatumKurz(g.datum) }}</span>
                <span class="pw-col-nr-typ">{{ g.typ }}</span>
              </td>
              <td class="pw-titel pw-col-titel" data-label="Titel">
                <a v-if="g.url" :href="g.url" target="_blank" @click.stop class="pw-inline-link" title="Extern öffnen">↗</a>
                {{ g.titel }}
                <span v-if="erstunterzeichner(g)" class="pw-col-einreicher">{{ erstunterzeichner(g) }}</span>
              </td>
              <td v-if="statusSpalteAnzeigen" data-label="Status" class="pw-col-status">
                <span :class="['pw-status-' + statusKlasse(g.status), 'pw-status-text']" :title="g.status">{{ g.status }}</span>
              </td>
              <td data-label="Zuständig" class="pw-col-inline-edit pw-col-zustaendig" @click.stop>
                <PwMultiSelect
                  class="pw-inline-select"
                  :model-value="zustaendigOptionenFuer(g)"
                  :options="zustaendigeOptionenFuerSelect"
                  :clearable="true"
                  placeholder="—"
                  label="label"
                  @update:model-value="aenderungZustaendig(g, $event || [])"
                />
              </td>
              <td data-label="Beschluss" class="pw-col-inline-edit pw-col-beschluss" @click.stop>
                <BeschlussWidget
                  class="pw-inline-beschluss"
                  :model-value="beschlussOptionFuer(g)"
                  :options="beschlussOptionenFuer(g)"
                  placeholder="—"
                  @update:model-value="aenderungBeschluss(g, $event)"
                />
              </td>
            </tr>
          </tbody>
          </table>
        </div>

        <div class="pw-card-grid pw-card-mobile">
          <article
            v-for="g in gefilterteGeschaefte"
            :key="`card-${g.id}`"
            class="pw-data-card pw-geschaeft-card"
            :class="{ 'pw-geloescht': g.geloescht }"
            tabindex="0"
            role="button"
            @click="oeffneDetail(g.id)"
            @keydown="zeilenKeydown($event, g.id)"
          >
            <div class="pw-data-card-header">
              <div>
                <p class="pw-data-card-kicker">
                  <a v-if="g.url" :href="g.url" target="_blank" @click.stop class="pw-inline-link" title="Extern öffnen">↗</a>
                  {{ g.nummer || 'Ohne Nummer' }}
                </p>
                <h3>{{ g.titel }}</h3>
              </div>
              <span :class="'pw-status-' + statusKlasse(g.status)">{{ g.status || '—' }}</span>
            </div>

            <div class="pw-data-card-grid">
              <div class="pw-data-pair">
                <span>Typ</span>
                <strong>{{ g.typ || '—' }}</strong>
              </div>
              <div class="pw-data-pair">
                <span>Datum</span>
                <strong>{{ formatieredatum(g.datum) || '—' }}</strong>
              </div>
              <div class="pw-data-pair">
                <span>Zuständigkeit</span>
                <strong>{{ g.hauptZustaendigePerson || '—' }}</strong>
              </div>
            </div>

            <p class="pw-card-note">{{ g.letzterBeschluss?.text || g.letzterBeschluss?.titel || 'Noch kein Fraktionsbeschluss erfasst' }}</p>
          </article>
        </div>

        <NcEmptyContent v-if="gefilterteGeschaefte.length === 0" name="Keine Geschäfte gefunden" />
      </template>
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
import axios from '@nextcloud/axios'
import { subscribeRealtime } from '../realtime'
import { vollerName, personKey } from '../utils'
import GeschaeftDetail from './GeschaeftDetail.vue'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import PwMultiSelect from './PwMultiSelect.vue'
import BeschlussWidget from './BeschlussWidget.vue'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'

export default {
  name: 'Geschaeftsliste',
  components: { GeschaeftDetail, NcTextField, NcSelect, PwMultiSelect, NcCheckboxRadioSwitch, NcButton, NcLoadingIcon, NcEmptyContent, BeschlussWidget },
  props: {
    mitglieder: { type: Array, default: () => [] },
  },
  emits: ['aktualisiert'],
  data() {
    return {
      filterReady: false,
      geschaefte: [],
      laden: true,
      suche: '',
      filterStatus: ['Behandlungsreif'],
      filterTyp: [],
      filterZustaendige: [],
      filterBeschluss: [],
      filterEntscheidungsbedarf: '',
      zeigeErledigte: false,
      sortFeld: 'datum',
      sortRichtung: 'desc',
      ausgewaehlteGeschaeftId: null,
      unsubRealtime: null,
      reloadTimer: null,
    }
  },
  computed: {
    entscheidungsbedarfOptions() {
      return [
        { label: 'Alle', value: '' },
        { label: 'Nur Entscheid nötig', value: '1' },
        { label: 'Nur ohne offenen Entscheid', value: '0' },
      ]
    },
    entscheidungsbedarfOption: {
      get() { return this.entscheidungsbedarfOptions.find(o => o.value === this.filterEntscheidungsbedarf) || this.entscheidungsbedarfOptions[0] },
      set(v) { this.filterEntscheidungsbedarf = v ? v.value : '' },
    },
    alleStatus() {
      return [...new Set(this.geschaefte.map(g => g.status).filter(Boolean))].sort()
    },
    statusSpalteAnzeigen() {
      // Wenn genau ein Status gefiltert ist, wäre die Spalte redundant.
      return !(Array.isArray(this.filterStatus) && this.filterStatus.length === 1)
    },
    alleTypen() {
      return [...new Set(this.geschaefte.map(g => g.typ).filter(Boolean))].sort()
    },
    alleBeschluesse() {
      const seen = new Map()
      let hatOhneBeschluss = false
      this.geschaefte.forEach(g => {
        const b = g.letzterBeschluss
        const code = b?.aktionCode || ''
        if (!code) {
          hatOhneBeschluss = true
          return
        }
        if (!seen.has(code)) {
          seen.set(code, { code, label: b.titel || code })
        }
      })
      const liste = [...seen.values()].sort((a, b) => a.label.localeCompare(b.label))
      if (hatOhneBeschluss) {
        // Spezialeintrag für Geschäfte ohne erfassten Beschluss: matcht den
        // Leerstring-Vergleich in `gefilterteGeschaefte`.
        liste.unshift({ code: '', label: '—' })
      }
      return liste
    },
    zustaendigeOptionen() {
      const map = new Map()
      this.mitglieder.forEach((mitglied) => {
        const label = this.vollerName(mitglied)
        if (!label) {
          return
        }
        map.set(label, {
          value: label,
          label,
          aktiv: mitglied.aktiv !== false,
        })
      })
      this.geschaefte.forEach((geschaeft) => {
        const label = (geschaeft.hauptZustaendigePerson || '').trim()
        if (!label || map.has(label)) {
          return
        }
        map.set(label, {
          value: label,
          label,
          aktiv: false,
        })
      })
      return [...map.values()].sort((a, b) => {
        if (a.aktiv !== b.aktiv) {
          return a.aktiv ? -1 : 1
        }
        return a.label.localeCompare(b.label)
      })
    },
    zustaendigeLabels() {
      return this.zustaendigeOptionen.map((p) => p.label)
    },
    zustaendigeOptionenFuerSelect() {
      return this.mitglieder
        .filter((m) => m.aktiv !== false && !!(m.nextcloudUid || m.nextcloud_uid))
        .map((member) => ({
          label: this.vollerName(member),
          value: this.personKey(member),
          mitglied: member,
        }))
        .filter((o) => !!o.label)
        .sort((a, b) => a.label.localeCompare(b.label))
    },
    beschlussOptionsList() {
      return this.alleBeschluesse.map((b) => ({ label: b.label, value: b.code }))
    },
    filterBeschlussOptions() {
      return this.beschlussOptionsList.filter((o) => this.filterBeschluss.includes(o.value))
    },
    ausgewaehltesGeschaeft() {
      return this.geschaefte.find((geschaeft) => geschaeft.id === this.ausgewaehlteGeschaeftId) || null
    },
    gefilterteGeschaefte() {
      let liste = [...this.geschaefte]

      if (!this.zeigeErledigte) {
        liste = liste.filter((g) => !this.istErledigtStatus(g.status || ''))
      }

      if (this.suche) {
        const s = this.suche.toLowerCase()
        liste = liste.filter(g =>
          (g.titel || '').toLowerCase().includes(s) ||
          (g.nummer || '').toLowerCase().includes(s)
        )
      }
      if (this.filterStatus.length > 0) {
        liste = liste.filter(g => this.filterStatus.includes(g.status))
      }
      if (this.filterTyp.length > 0) {
        liste = liste.filter(g => this.filterTyp.includes(g.typ))
      }
      if (this.filterZustaendige.length > 0) {
        liste = liste.filter(g => this.filterZustaendige.includes(g.hauptZustaendigePerson || ''))
      }
      if (this.filterBeschluss.length > 0) {
        liste = liste.filter(g => this.filterBeschluss.includes(g.letzterBeschluss?.aktionCode || ''))
      }

      liste.sort((a, b) => {
        const av = this.sortWert(a, this.sortFeld)
        const bv = this.sortWert(b, this.sortFeld)
        return this.sortRichtung === 'asc' ? av.localeCompare(bv) : bv.localeCompare(av)
      })

      return liste
    },
  },
  watch: {
    filterEntscheidungsbedarf() {
      this.ladeGeschaefte()
    },
    zeigeErledigte() {
      this.ladeGeschaefte()
    },
  },
  mounted() {
    this.$nextTick(() => { this.filterReady = true })
    this.initialisiereAnsicht()
    this.unsubRealtime = subscribeRealtime(this.handleRealtimeEvent)
  },
  beforeUnmount() {
    if (this.unsubRealtime) {
      this.unsubRealtime()
      this.unsubRealtime = null
    }
    if (this.reloadTimer) {
      window.clearTimeout(this.reloadTimer)
      this.reloadTimer = null
    }
  },
  methods: {
    toggleMehrfachFilter(feld, wert, checked) {
      const liste = Array.isArray(this[feld]) ? [...this[feld]] : []
      const index = liste.indexOf(wert)
      const soll = checked === undefined ? index < 0 : !!checked
      if (soll && index < 0) liste.push(wert)
      else if (!soll && index >= 0) liste.splice(index, 1)
      this[feld] = liste
    },
    vollerName,
    personKey,
    zustaendigOptionenFuer(geschaeft) {
      const zust = Array.isArray(geschaeft.zustaendigkeiten) ? geschaeft.zustaendigkeiten : []
      return zust.map((z) => {
        const treffer = this.zustaendigeOptionenFuerSelect.find((o) => o.value === z.personKey)
        return treffer || { label: z.personName || z.personKey, value: z.personKey, mitglied: null }
      })
    },
    zeilenKeydown(event, id) {
      if (event.target.closest('input, select, textarea, [contenteditable], [role="combobox"], [role="listbox"], [role="option"]')) return
      if (event.key === ' ') { event.preventDefault(); this.oeffneDetail(id) }
      else if (event.key === 'Enter') { event.preventDefault(); this.oeffneDetail(id) }
    },
    beschlussOptionenFuer(geschaeft) {
      const erlaubt = Array.isArray(geschaeft.erlaubteBeschluesse) ? geschaeft.erlaubteBeschluesse : []
      return erlaubt.map((b) => ({ label: b.label || b.code, value: b.code }))
    },
    beschlussOptionFuer(geschaeft) {
      const lb = geschaeft.letzterBeschluss
      if (!lb) return null
      const code = lb.aktionCode || ''
      if (!code && lb.text) return { label: lb.text, value: '', freitext: true }
      if (!code) return null
      const optionen = this.beschlussOptionenFuer(geschaeft)
      return optionen.find((o) => o.value === code) || { label: lb.titel || code, value: code }
    },
    async aenderungZustaendig(geschaeft, optionen) {
      const optList = Array.isArray(optionen) ? optionen : (optionen ? [optionen] : [])
      const keys = optList.map((o) => o.value).filter(Boolean)
      const vorhandeneHaupt = (geschaeft.zustaendigkeiten || []).find((z) => z.istHaupt)?.personKey || ''
      const haupt = keys.includes(vorhandeneHaupt) ? vorhandeneHaupt : (keys[0] || '')
      const payload = keys.map((key) => {
        const member = this.mitglieder.find((m) => this.personKey(m) === key)
        const fallback = optList.find((o) => o.value === key)
        return {
          mitgliedExternId: member?.externId || member?.extern_id || '',
          personName: member ? this.vollerName(member) : (fallback?.label || ''),
        }
      })
      try {
        await axios.put(generateUrl(`/apps/parlwin/geschaefte/${geschaeft.id}`), {
          zustaendigkeiten: payload,
          haupt_person_key: haupt,
        })
        await this.ladeGeschaefte()
        this.$emit('aktualisiert')
      } catch (fehler) {
        console.error('Fehler beim Speichern der Zuständigkeit:', fehler)
      }
    },
    async aenderungBeschluss(geschaeft, option) {
      const code = option?.value || ''
      const text = option?.freitext ? (option.label || '') : ''
      try {
        if (code || text) {
          await axios.post(generateUrl(`/apps/parlwin/geschaefte/${geschaeft.id}/beschluesse`), { code, text })
        } else {
          await axios.delete(generateUrl(`/apps/parlwin/geschaefte/${geschaeft.id}/beschluesse`))
        }
        await this.ladeGeschaefte()
        this.$emit('aktualisiert')
      } catch (fehler) {
        console.error('Fehler beim Speichern des Beschlusses:', fehler)
      }
    },
    async initialisiereAnsicht() {
      try {
        const { data } = await axios.get(generateUrl('/apps/parlwin/settings/fraktionssitzung'))
        if (data?.modusAktiv && this.filterEntscheidungsbedarf === '') {
          this.filterEntscheidungsbedarf = '1'
          return
        }
      } catch (fehler) {
        // Fallback: ohne Kontext lädt die Liste normal.
      }
      this.ladeGeschaefte()
    },
    async ladeGeschaefte() {
      this.laden = true
      try {
        const params = { limit: 500 }
        params.show_erledigt = this.zeigeErledigte ? '1' : '0'
        if (this.filterEntscheidungsbedarf !== '') {
          params.entscheidungsbedarf = this.filterEntscheidungsbedarf
        }
        const { data } = await axios.get(generateUrl('/apps/parlwin/geschaefte'), { params })
        this.geschaefte = data
      } catch (fehler) {
        console.error('Fehler beim Laden der Geschäfte:', fehler)
      } finally {
        this.laden = false
      }
    },
    handleRealtimeEvent(event) {
      const type = event?.type || ''
      if (type === 'sync.completed' || type.startsWith('geschaefte.') || type === 'fraktionssitzung.updated') {
        this.scheduleReload()
      }
    },
    scheduleReload() {
      if (this.reloadTimer) return
      this.reloadTimer = window.setTimeout(async () => {
        this.reloadTimer = null
        await this.ladeGeschaefte()
      }, 250)
    },
    sortiereNach(feld) {
      if (this.sortFeld === feld) {
        this.sortRichtung = this.sortRichtung === 'asc' ? 'desc' : 'asc'
      } else {
        this.sortFeld = feld
        this.sortRichtung = 'asc'
      }
    },
    sortWert(g, feld) {
      const v = g[feld] || ''
      if (feld === 'nummer') {
        // Zweite Komponente nach dem Punkt auf 4 Stellen mit führenden Nullen
        // auffüllen, damit "2026.9" vor "2026.10" landet.
        return String(v).replace(/^(\d+)\.(\d+)/, (_, jahr, nr) => `${jahr}.${nr.padStart(4, '0')}`)
      }
      return String(v)
    },
    oeffneDetail(geschaeftId) {
      this.ausgewaehlteGeschaeftId = geschaeftId
    },
    resetFilter() {
      this.suche = ''
      this.filterStatus = []
      this.filterTyp = []
      this.filterZustaendige = []
      this.filterBeschluss = []
      this.filterEntscheidungsbedarf = ''
      this.zeigeErledigte = false
      this.ladeGeschaefte()
    },
    schliesseDetail() {
      this.ausgewaehlteGeschaeftId = null
    },
    async nachSpeichern() {
      await this.ladeGeschaefte()
      this.$emit('aktualisiert')
    },
    formatieredatum(datum) {
      if (!datum) return ''
      try {
        return new Date(datum).toLocaleDateString('de-CH')
      } catch {
        return datum
      }
    },
    erstunterzeichner(g) {
      const liste = g.einreicher
      if (!Array.isArray(liste) || liste.length === 0) return ''
      return liste.map(p => p.name).join(', ')
    },
    formatieredatumKurz(datum) {
      if (!datum) return ''
      try {
        const d = new Date(datum)
        const tag = String(d.getDate()).padStart(2, '0')
        const monat = String(d.getMonth() + 1).padStart(2, '0')
        const jahr = String(d.getFullYear()).slice(-2)
        return `${tag}.${monat}.${jahr}`
      } catch {
        return ''
      }
    },
    statusKlasse(status) {
      if (!status) return ''
      const s = status.toLowerCase()
      if (s.includes('pendent') || s.includes('offen') || s.includes('laufend')) return 'offen'
      if (this.istErledigtStatus(s)) return 'erledigt'
      if (s.includes('abgelehnt') || s.includes('zurückgezogen')) return 'abgelehnt'
      return 'neutral'
    },
    istErledigtStatus(status) {
      const s = (status || '').toLowerCase()
      // Regel: Status gilt als "erledigt", wenn er "erledigt", "abgeschlossen"
      // oder "aufgehoben" enthält (z.B. "Durch Rechtsmittelinstanz aufgehoben").
      return s.includes('erledigt') || s.includes('abgeschlossen') || s.includes('aufgehoben')
    },
    fraktionsstatusLabel(status) {
      if (status === 'neu_zu_entscheiden') return 'Neu zu entscheiden'
      if (status === 'entschieden') return 'Entschieden'
      return 'Offen'
    },
    fraktionsstatusKlasse(status) {
      if (status === 'neu_zu_entscheiden') return 'offen'
      if (status === 'entschieden') return 'erledigt'
      return 'neutral'
    },
  },
}
</script>
