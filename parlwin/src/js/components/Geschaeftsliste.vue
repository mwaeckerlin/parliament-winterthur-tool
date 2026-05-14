<template>
  <Teleport v-if="filterReady" to="#pw-search-slot">
    <NcTextField :value="suche" label="Suche" placeholder="Nr. oder Titel" trailing-button-icon="close" :show-trailing-button="!!suche" @update:value="suche = $event" @trailing-button-click="suche = ''" />
  </Teleport>
  <Teleport v-if="filterReady" to="#pw-filter-slot">
    <div class="pw-filter-body">
      <NcSelect v-model="entscheidungsbedarfOption" :options="entscheidungsbedarfOptions" :clearable="false" input-label="Entscheidungsbedarf" />
      <NcSelect :model-value="filterStatus" :options="alleStatus" multiple :close-on-select="false" :selectable="opt => !filterStatus.includes(opt)" input-label="Status" placeholder="Alle" @update:model-value="filterStatus = $event || []" />
      <NcSelect :model-value="filterTyp" :options="alleTypen" multiple :close-on-select="false" :selectable="opt => !filterTyp.includes(opt)" input-label="Typ" placeholder="Alle" @update:model-value="filterTyp = $event || []" />
      <NcSelect :model-value="filterZustaendige" :options="zustaendigeLabels" multiple :close-on-select="false" :selectable="opt => !filterZustaendige.includes(opt)" input-label="Zuständigkeit" placeholder="Alle" @update:model-value="filterZustaendige = $event || []" />
      <NcSelect :model-value="filterBeschlussOptions" :options="beschlussOptionsList" multiple :close-on-select="false" :selectable="opt => !filterBeschluss.includes(opt.value)" input-label="Beschluss" placeholder="Alle" @update:model-value="filterBeschluss = ($event || []).map(o => o.value)" />
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
          <table class="pw-tabelle" lang="de">
        <thead>
          <tr>
            <th @click="sortiereNach('nummer')" class="pw-sortierbar pw-col-nr">Nr.</th>
            <th @click="sortiereNach('titel')" class="pw-sortierbar pw-col-titel">Titel</th>
            <th @click="sortiereNach('typ')" class="pw-sortierbar">Typ</th>
            <th @click="sortiereNach('status')" class="pw-sortierbar">Status</th>
            <th @click="sortiereNach('datum')" class="pw-sortierbar">Datum</th>
            <th>Zuständig</th>
            <th>Beschluss</th>
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
              @keydown.enter.prevent="oeffneDetail(g.id)"
              @keydown.space.prevent="oeffneDetail(g.id)"
            >
              <td data-label="Nr." class="pw-col-nr">{{ g.nummer }}</td>
              <td class="pw-titel pw-col-titel" data-label="Titel">
                <a v-if="g.url" :href="g.url" target="_blank" @click.stop class="pw-inline-link" title="Extern öffnen">↗</a>
                {{ g.titel }}
              </td>
              <td data-label="Typ">{{ g.typ }}</td>
              <td data-label="Status">
                <span :class="['pw-status-' + statusKlasse(g.status), 'pw-status-text']" :title="g.status">{{ g.status }}</span>
              </td>
              <td data-label="Datum">{{ formatieredatum(g.datum) }}</td>
              <td data-label="Zuständig">{{ g.hauptZustaendigePerson || '—' }}</td>
              <td data-label="Beschluss">{{ g.letzterBeschluss?.titel || '—' }}</td>
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
            @keydown.enter.prevent="oeffneDetail(g.id)"
            @keydown.space.prevent="oeffneDetail(g.id)"
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

            <p class="pw-card-note">{{ g.letzterBeschluss?.titel || 'Noch kein Fraktionsbeschluss erfasst' }}</p>
          </article>
        </div>

        <NcEmptyContent v-if="gefilterteGeschaefte.length === 0" name="Keine Geschäfte gefunden" />
      </template>
    </section>

    <div v-if="ausgewaehlteGeschaeftId" class="pw-modal-overlay" @click.self="schliesseDetail">
      <div class="pw-modal">
        <div class="pw-modal-kopf">
          <div>
            <p class="pw-modal-kicker">Geschäft bearbeiten</p>
            <h3>{{ ausgewaehltesGeschaeft?.titel || 'Geschäft' }}</h3>
          </div>
          <button type="button" class="button pw-btn-schliessen" aria-label="Dialog schliessen" @click="schliesseDetail">✕</button>
        </div>
        <GeschaeftDetail
          :geschaeft-id="ausgewaehlteGeschaeftId"
          :mitglieder="mitglieder"
          @gespeichert="nachSpeichern"
        />
      </div>
    </div>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { subscribeRealtime } from '../realtime'
import GeschaeftDetail from './GeschaeftDetail.vue'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'

export default {
  name: 'Geschaeftsliste',
  components: { GeschaeftDetail, NcTextField, NcSelect, NcCheckboxRadioSwitch, NcButton, NcLoadingIcon, NcEmptyContent },
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
      filterStatus: [],
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
    alleTypen() {
      return [...new Set(this.geschaefte.map(g => g.typ).filter(Boolean))].sort()
    },
    alleBeschluesse() {
      const seen = new Map()
      this.geschaefte.forEach(g => {
        const b = g.letzterBeschluss
        if (b?.aktionCode && !seen.has(b.aktionCode)) {
          seen.set(b.aktionCode, { code: b.aktionCode, label: b.titel || b.aktionCode })
        }
      })
      return [...seen.values()].sort((a, b) => a.label.localeCompare(b.label))
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
        const av = a[this.sortFeld] || ''
        const bv = b[this.sortFeld] || ''
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
    vollerName(m) {
      return `${m.vorname || ''} ${m.name || ''}`.trim()
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
      this.schliesseDetail()
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
      return s.includes('erledigt') || s.includes('abgeschlossen')
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
