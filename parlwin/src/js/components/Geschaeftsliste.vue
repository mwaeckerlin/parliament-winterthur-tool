<template>
  <div class="pw-geschaefte">
    <div class="pw-toolbar">
      <h2>Politische Geschäfte</h2>
      <div class="pw-filter">
        <div class="pw-filter-feld pw-filter-wide">
          <label for="pw-suche-feld">Suche</label>
          <input
            id="pw-suche-feld"
            v-model="suche"
            type="text"
            placeholder="Suchen..."
            class="pw-suche"
          />
        </div>
        <div class="pw-filter-feld">
          <label for="pw-filter-status">Status (Mehrfachauswahl)</label>
          <select id="pw-filter-status" v-model="filterStatus" class="pw-select pw-select-multi" multiple :size="5">
            <option v-for="s in alleStatus" :key="s" :value="s">{{ s }}</option>
          </select>
        </div>
        <div class="pw-filter-feld">
          <label for="pw-filter-typ">Typ (Mehrfachauswahl)</label>
          <select id="pw-filter-typ" v-model="filterTyp" class="pw-select pw-select-multi" multiple :size="5">
            <option v-for="t in alleTypen" :key="t" :value="t">{{ t }}</option>
          </select>
        </div>
        <div class="pw-filter-feld">
          <label for="pw-filter-zustaendig">Zuständige (Mehrfachauswahl)</label>
          <select id="pw-filter-zustaendig" v-model="filterZustaendige" class="pw-select pw-select-multi" multiple :size="5">
            <option v-for="person in aktiveZustaendigeOptionen" :key="`aktiv-${person.value}`" :value="person.value">
              {{ person.label }}
            </option>
            <option v-if="aktiveZustaendigeOptionen.length > 0 && inaktiveZustaendigeOptionen.length > 0" disabled>──────────</option>
            <option v-for="person in inaktiveZustaendigeOptionen" :key="`inaktiv-${person.value}`" :value="person.value">
              {{ person.label }}
            </option>
          </select>
        </div>
        <div class="pw-filter-feld">
          <label for="pw-filter-beschluss">Aktueller Beschluss (Mehrfachauswahl)</label>
          <select id="pw-filter-beschluss" v-model="filterBeschluss" class="pw-select pw-select-multi" multiple :size="5">
            <option v-for="b in alleBeschluesse" :key="b.code" :value="b.code">{{ b.label }}</option>
          </select>
        </div>
        <div class="pw-filter-feld">
          <label for="pw-filter-entscheidungsbedarf">Entscheidungsbedarf</label>
          <select id="pw-filter-entscheidungsbedarf" v-model="filterEntscheidungsbedarf" class="pw-select">
            <option value="">Alle</option>
            <option value="1">Nur Entscheid nötig</option>
            <option value="0">Nur ohne offenen Entscheid</option>
          </select>
        </div>
        <div class="pw-filter-feld">
          <label class="pw-filter-checkbox" for="pw-filter-show-erledigt">
            <input id="pw-filter-show-erledigt" v-model="zeigeErledigte" type="checkbox" />
            Erledigte anzeigen
          </label>
        </div>
        <div class="pw-filter-feld pw-filter-feld-actions">
          <button type="button" class="button pw-filter-clear" @click="resetFilter">
            Filter zurücksetzen
          </button>
        </div>
      </div>
    </div>

    <div v-if="laden" class="pw-laden">Daten werden geladen...</div>

    <div v-else class="pw-table-wrap">
      <table class="pw-tabelle">
        <colgroup>
          <col class="pw-col-nr">
          <col class="pw-col-titel">
          <col class="pw-col-typ">
          <col class="pw-col-status">
          <col class="pw-col-datum">
          <col class="pw-col-zustaendig">
          <col class="pw-col-beschluss">
          <col class="pw-col-fraktionsstatus">
        </colgroup>
        <thead>
          <tr>
            <th @click="sortiereNach('nummer')" class="pw-sortierbar">Nr.</th>
            <th @click="sortiereNach('titel')" class="pw-sortierbar">Titel</th>
            <th @click="sortiereNach('typ')" class="pw-sortierbar">Typ</th>
            <th @click="sortiereNach('status')" class="pw-sortierbar">Status</th>
            <th @click="sortiereNach('datum')" class="pw-sortierbar">Datum</th>
            <th>Zuständig</th>
            <th>Aktueller Beschluss</th>
            <th>Fraktionsstatus</th>
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
            <td data-label="Nr."><span class="pw-cell-ellipsis" :title="g.nummer">{{ g.nummer }}</span></td>
            <td class="pw-titel" data-label="Titel">
              <span class="pw-cell-ellipsis" :title="g.titel">{{ g.titel }}</span>
              <a v-if="g.url" :href="g.url" target="_blank" @click.stop class="pw-inline-link" title="Extern öffnen">↗</a>
            </td>
            <td data-label="Typ"><span class="pw-cell-ellipsis" :title="g.typ">{{ g.typ }}</span></td>
            <td data-label="Status">
              <span :class="'pw-status-' + statusKlasse(g.status)" :title="g.status">{{ g.status }}</span>
            </td>
            <td data-label="Datum"><span class="pw-cell-ellipsis" :title="formatieredatum(g.datum)">{{ formatieredatum(g.datum) }}</span></td>
            <td data-label="Zuständig"><span class="pw-cell-ellipsis" :title="g.hauptZustaendigePerson">{{ g.hauptZustaendigePerson }}</span></td>
            <td data-label="Aktueller Beschluss">
              <span class="pw-cell-ellipsis" :title="g.letzterBeschluss?.titel || ''">{{ g.letzterBeschluss?.titel || '' }}</span>
            </td>
            <td data-label="Fraktionsstatus">
              <span :class="'pw-status-' + fraktionsstatusKlasse(g.fraktionsstatus)">
                {{ fraktionsstatusLabel(g.fraktionsstatus) }}
              </span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="ausgewaehlteGeschaeftId" class="pw-modal-overlay" @click.self="schliesseDetail">
      <div class="pw-modal">
        <div class="pw-modal-kopf">
          <h3>Geschäft bearbeiten</h3>
          <button type="button" class="button pw-btn-schliessen" aria-label="Dialog schliessen" @click="schliesseDetail">✕</button>
        </div>
        <GeschaeftDetail
          :geschaeft-id="ausgewaehlteGeschaeftId"
          :mitglieder="mitglieder"
          @gespeichert="nachSpeichern"
        />
      </div>
    </div>
  </div>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { subscribeRealtime } from '../realtime'
import GeschaeftDetail from './GeschaeftDetail.vue'

export default {
  name: 'Geschaeftsliste',
  components: { GeschaeftDetail },
  props: {
    mitglieder: { type: Array, default: () => [] },
  },
  emits: ['aktualisiert'],
  data() {
    return {
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
    aktiveZustaendigeOptionen() {
      return this.zustaendigeOptionen.filter((person) => person.aktiv)
    },
    inaktiveZustaendigeOptionen() {
      return this.zustaendigeOptionen.filter((person) => !person.aktiv)
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
