<template>
  <section class="pw-budget-sitzung">
    <h4>Budgetdebatte: Sitzungsanträge {{ jahr }}</h4>
    <div v-if="laden" class="pw-laden"><NcLoadingIcon :size="24" /></div>
    <p v-else-if="!hatAntraege" class="pw-hinweis">Noch keine Sitzungsanträge erfasst.</p>
    <template v-else>
      <div v-for="dep in gruppenMitAntraegen" :key="dep.name" class="pw-budget-departement">
        <h5 class="pw-budget-dep-titel">{{ dep.name }}</h5>
        <article v-for="g in dep.gruppen" :key="g.code" class="pw-data-card">
          <div class="pw-data-card-header">
            <div>
              <p class="pw-data-card-kicker">Produktegruppe {{ g.code }}</p>
              <h3>{{ g.name }}</h3>
            </div>
          </div>
          <ul class="pw-budget-antraege">
            <li v-for="a in antraegeFuer(g.code)" :key="a.id">
              <span class="pw-antrag-betrag" :class="diffKlasse(a.betragDelta)">{{ fr(a.betragDelta) }}</span>
              <span class="pw-antrag-steller">{{ a.antragsteller }}</span>
              <span class="pw-antrag-begruendung">{{ a.begruendung }}</span>
              <span class="pw-antrag-entscheid" :class="'pw-entscheid-' + a.entscheid">{{ entscheidLabel(a.entscheid) }}</span>
              <span class="pw-entscheid-knoepfe">
                <NcButton type="tertiary" :aria-label="'Antrag angenommen'" @click="entscheidSetzen(a.id, a.entscheid === 'angenommen' ? 'offen' : 'angenommen')">✓</NcButton>
                <NcButton type="tertiary" :aria-label="'Antrag abgelehnt'" @click="entscheidSetzen(a.id, a.entscheid === 'abgelehnt' ? 'offen' : 'abgelehnt')">✕</NcButton>
              </span>
            </li>
          </ul>
        </article>
      </div>
    </template>
  </section>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import { subscribeRealtime } from '../realtime'

/**
 * Spiegelt die offiziellen Budget-Sitzungsanträge (phase=sitzung) eines
 * Budgetjahres in die Sitzungsansicht (F93). Dieselben Elemente wie in der
 * Budget-Ansicht: Antrag mit Betrag, Antragsteller, Begründung und Live-
 * Entscheid (angenommen/abgelehnt/offen), cross-session per Realtime.
 */
export default {
  name: 'BudgetSitzungsantraege',
  components: { NcButton, NcLoadingIcon },
  props: {
    jahr: { type: Number, required: true },
  },
  data() {
    return { ansicht: null, laden: false, unsubRealtime: null }
  },
  computed: {
    gruppenMitAntraegen() {
      const map = new Map()
      for (const g of (this.ansicht ? this.ansicht.produktegruppen : [])) {
        if (!this.antraegeFuer(g.code).length) { continue }
        const k = g.departement || 'Ohne Zuordnung'
        if (!map.has(k)) { map.set(k, []) }
        map.get(k).push(g)
      }
      return [...map.entries()].map(([name, gruppen]) => ({ name, gruppen }))
    },
    hatAntraege() {
      return (this.ansicht ? this.ansicht.antraege : []).some(a => (a.phase || 'fraktion') === 'sitzung')
    },
  },
  mounted() {
    this.laden = true
    this.laean()
    this.unsubRealtime = subscribeRealtime((e) => {
      if ((e?.type || '') === 'budget.updated') { this.laean() }
    })
  },
  beforeUnmount() {
    if (this.unsubRealtime) { this.unsubRealtime() }
  },
  methods: {
    async laean() {
      try {
        const { data } = await axios.get(generateUrl('/apps/parlwin/budget/' + this.jahr), { params: { phase: 'sitzung' } })
        this.ansicht = data
      } catch (f) {
        console.error('Budget-Sitzungsanträge laden fehlgeschlagen', f)
        this.ansicht = null
      } finally {
        this.laden = false
      }
    },
    antraegeFuer(code) {
      return (this.ansicht ? this.ansicht.antraege : []).filter(a =>
        a.bereich === 'globalbudget' && a.zielRef === code && (a.phase || 'fraktion') === 'sitzung')
    },
    entscheidLabel(e) {
      return { angenommen: 'angenommen', abgelehnt: 'abgelehnt' }[e] || 'offen'
    },
    diffKlasse(v) {
      return v < 0 ? 'pw-negativ' : (v > 0 ? 'pw-positiv' : '')
    },
    fr(v) {
      const n = Math.round(Number(v) || 0)
      const vz = n < 0 ? '−' : ''
      return vz + 'CHF ' + Math.abs(n).toLocaleString('de-CH').replace(/ /g, '’').replace(/'/g, '’')
    },
    async entscheidSetzen(id, status) {
      try {
        await axios.put(generateUrl('/apps/parlwin/budget/antraege/' + id + '/entscheid'), { status })
        await this.laean()
      } catch (f) {
        console.error('Entscheid setzen fehlgeschlagen', f)
      }
    },
  },
}
</script>
