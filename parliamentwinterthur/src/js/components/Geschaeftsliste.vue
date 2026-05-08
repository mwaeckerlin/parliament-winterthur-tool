<template>
  <div class="pw-geschaefte">
    <div class="pw-toolbar">
      <h2>Politische Geschäfte</h2>
      <div class="pw-filter">
        <input
          v-model="suche"
          type="text"
          placeholder="Suchen..."
          class="pw-suche"
        />
        <select v-model="filterStatus" class="pw-select">
          <option value="">Alle Status</option>
          <option v-for="s in alleStatus" :key="s" :value="s">{{ s }}</option>
        </select>
        <select v-model="filterTyp" class="pw-select">
          <option value="">Alle Typen</option>
          <option v-for="t in alleTypen" :key="t" :value="t">{{ t }}</option>
        </select>
        <select v-model="filterZustaendige" class="pw-select">
          <option value="">Alle Zuständigen</option>
          <option v-for="m in mitglieder" :key="m.id" :value="m.vorname + ' ' + m.name">
            {{ m.vorname }} {{ m.name }}
          </option>
        </select>
      </div>
    </div>

    <div v-if="laden" class="pw-laden">Daten werden geladen...</div>

    <table v-else class="pw-tabelle">
      <thead>
        <tr>
          <th @click="sortiereNach('nummer')" class="pw-sortierbar">
            Nr. <span v-if="sortFeld === 'nummer'">{{ sortRichtung === 'asc' ? '▲' : '▼' }}</span>
          </th>
          <th @click="sortiereNach('titel')" class="pw-sortierbar">
            Titel <span v-if="sortFeld === 'titel'">{{ sortRichtung === 'asc' ? '▲' : '▼' }}</span>
          </th>
          <th @click="sortiereNach('typ')" class="pw-sortierbar">Typ</th>
          <th @click="sortiereNach('status')" class="pw-sortierbar">Status</th>
          <th @click="sortiereNach('datum')" class="pw-sortierbar">Datum</th>
          <th>Zuständig</th>
          <th>Entscheid</th>
          <th>Aktionen</th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="g in gefilterteGeschaefte"
          :key="g.id"
          :class="{ 'pw-geloescht': g.geloescht }"
          @click="oeffneDetail(g)"
        >
          <td>{{ g.nummer }}</td>
          <td class="pw-titel">
            <a v-if="g.url" :href="g.url" target="_blank" @click.stop>↗</a>
            {{ g.titel }}
          </td>
          <td>{{ g.typ }}</td>
          <td>
            <span :class="'pw-status-' + statusKlasse(g.status)">{{ g.status }}</span>
          </td>
          <td>{{ formatieredatum(g.datum) }}</td>
          <td>{{ g.zustaendigePerson }}</td>
          <td>{{ g.entscheidFraktion }}</td>
          <td>
            <button class="pw-btn-klein" @click.stop="oeffneDetail(g)">✏️</button>
          </td>
        </tr>
      </tbody>
    </table>

    <!-- Detail-Dialog -->
    <div v-if="ausgewaehltesGeschaeft" class="pw-modal-overlay" @click.self="schliesseDetail">
      <div class="pw-modal">
        <div class="pw-modal-kopf">
          <h3>{{ ausgewaehltesGeschaeft.titel }}</h3>
          <button class="pw-btn-schliessen" @click="schliesseDetail">✕</button>
        </div>
        <GeschaeftDetail
          :geschaeft="ausgewaehltesGeschaeft"
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
      filterStatus: '',
      filterTyp: '',
      filterZustaendige: '',
      sortFeld: 'datum',
      sortRichtung: 'desc',
      ausgewaehltesGeschaeft: null,
    }
  },
  computed: {
    alleStatus() {
      return [...new Set(this.geschaefte.map(g => g.status).filter(Boolean))].sort()
    },
    alleTypen() {
      return [...new Set(this.geschaefte.map(g => g.typ).filter(Boolean))].sort()
    },
    gefilterteGeschaefte() {
      let liste = [...this.geschaefte]

      if (this.suche) {
        const s = this.suche.toLowerCase()
        liste = liste.filter(g =>
          (g.titel || '').toLowerCase().includes(s) ||
          (g.nummer || '').toLowerCase().includes(s) ||
          (g.bemerkungen || '').toLowerCase().includes(s)
        )
      }
      if (this.filterStatus) {
        liste = liste.filter(g => g.status === this.filterStatus)
      }
      if (this.filterTyp) {
        liste = liste.filter(g => g.typ === this.filterTyp)
      }
      if (this.filterZustaendige) {
        liste = liste.filter(g => g.zustaendigePerson === this.filterZustaendige)
      }

      liste.sort((a, b) => {
        const av = a[this.sortFeld] || ''
        const bv = b[this.sortFeld] || ''
        return this.sortRichtung === 'asc'
          ? av.localeCompare(bv)
          : bv.localeCompare(av)
      })

      return liste
    },
  },
  mounted() {
    this.ladeGeschaefte()
  },
  methods: {
    async ladeGeschaefte() {
      this.laden = true
      try {
        const { data } = await axios.get(generateUrl('/apps/parliamentwinterthur/geschaefte'), {
          params: { limit: 500 },
        })
        this.geschaefte = data
      } catch (fehler) {
        console.error('Fehler beim Laden der Geschäfte:', fehler)
      } finally {
        this.laden = false
      }
    },
    sortiereNach(feld) {
      if (this.sortFeld === feld) {
        this.sortRichtung = this.sortRichtung === 'asc' ? 'desc' : 'asc'
      } else {
        this.sortFeld = feld
        this.sortRichtung = 'asc'
      }
    },
    oeffneDetail(geschaeft) {
      this.ausgewaehltesGeschaeft = { ...geschaeft }
    },
    schliesseDetail() {
      this.ausgewaehltesGeschaeft = null
    },
    nachSpeichern(aktualisiertes) {
      const idx = this.geschaefte.findIndex(g => g.id === aktualisiertes.id)
      if (idx >= 0) {
        this.geschaefte[idx] = aktualisiertes
        this.geschaefte = [...this.geschaefte]
      }
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
      if (s.includes('erledigt') || s.includes('abgeschlossen')) return 'erledigt'
      if (s.includes('abgelehnt') || s.includes('zurückgezogen')) return 'abgelehnt'
      return 'neutral'
    },
  },
}
</script>
