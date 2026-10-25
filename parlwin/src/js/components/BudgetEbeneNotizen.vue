<template>
  <details class="pw-ebene-notizen" @toggle="aufgeklappt($event)">
    <summary>{{ titel }}<span v-if="anzahl"> ({{ anzahl }})</span></summary>
    <NotizenListe
      v-if="offen"
      :basis-url="basisUrl"
      :notizen="notizen"
      :schluessel="schluessel"
      :aktuelle-uid="aktuelleUid"
      @geaendert="laden"
    />
  </details>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import NotizenListe from './NotizenListe.vue'

/**
 * Notizen am übergeordneten Zusammenhang des Budgets (F124): am ganzen
 * Budgetjahr, an einem Departement und an einem Tab der Ansicht. Die Notizen
 * selbst sind dieselbe geteilte Notizenliste wie überall; diese Komponente
 * besorgt nur die Adresse und lädt die Notizen beim Aufklappen.
 */
export default {
  name: 'BudgetEbeneNotizen',
  components: { NotizenListe },
  props: {
    /** `budget_jahr`, `budget_departement` oder `budget_tab`. */
    ebene: { type: String, required: true },
    jahr: { type: [Number, String], required: true },
    /** Name des Departements oder des Tabs; beim ganzen Budget leer. */
    schluessel: { type: String, default: '' },
    titel: { type: String, default: 'Notizen' },
    aktuelleUid: { type: String, default: '' },
  },
  data() {
    return { offen: false, notizen: [] }
  },
  computed: {
    basisUrl() {
      return `budget/${this.jahr}/ebene/${this.ebene}`
    },
    anzahl() {
      return this.notizen.filter(n => !n.geloescht).length
    },
  },
  methods: {
    aufgeklappt(ereignis) {
      const offen = !!(ereignis && ereignis.target && ereignis.target.open)
      this.offen = offen
      if (offen) { this.laden() }
    },
    async laden() {
      try {
        const { data } = await axios.get(generateUrl(`/apps/parlwin/${this.basisUrl}/notizen`), {
          params: { schluessel: this.schluessel },
        })
        this.notizen = Array.isArray(data) ? data : []
      } catch (e) {
        this.notizen = []
      }
    },
  },
}
</script>

<style scoped lang="scss">
.pw-ebene-notizen > summary {
  cursor: pointer;
  font-weight: 600;
  padding-block: var(--pw-space-xs);
}
</style>
