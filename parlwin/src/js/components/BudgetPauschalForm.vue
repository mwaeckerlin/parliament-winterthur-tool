<template>
  <div class="pw-pauschal-form pw-data-card">
    <!-- Löschen oben rechts, einheitliches ✕ (wie überall). -->
    <PwLoeschen class="pw-loeschen-ecke" label="Pauschalantrag löschen" @click="$emit('delete')" />
    <div class="pw-antrag-form-zeile">
      <NcSelect
        :model-value="zielTypGewaehlt"
        :options="zielTypen"
        :clearable="false"
        input-label="Ziel"
        @update:model-value="o => { zielTyp = o ? o.value : 'einsparungen'; autoSpeichern() }"
      />
      <NcTextField v-if="zielTyp === 'einsparungen'" v-model="betragMag" type="number" label="Einsparung CHF" @update:model-value="autoSpeichern" />
      <NcTextField v-if="zielTyp === 'einsparungen'" v-model="prozentMag" type="number" label="oder in Prozent" @update:model-value="autoSpeichern" />
      <NcTextField v-else-if="zielTyp !== 'schwarze_null'" v-model="zielBetragMag" type="number" :label="zielTyp === 'festes_defizit' ? 'Defizit CHF' : 'Ertrag CHF'" @update:model-value="autoSpeichern" />
      <NcCheckboxRadioSwitch :model-value="einreichen" type="switch" @update:model-value="v => { einreichen = v; autoSpeichern() }">
        {{ einreichen ? 'Einreichen' : 'Nicht einreichen' }}
      </NcCheckboxRadioSwitch>
    </div>
    <div class="pw-antrag-form-zeile">
      <NcSelect
        :model-value="antragstellerGewaehlt"
        :options="fraktionOptionen"
        :clearable="false"
        input-label="Antragsteller (Fraktion)"
        @update:model-value="o => { antragsteller = o ? o.value : ''; autoSpeichern() }"
      />
      <PwMultiSelect
        :model-value="ausnahmenGewaehlt"
        :options="gruppenOptionen"
        input-label="Ausnahmen (Produktegruppen)"
        @update:model-value="l => { ausnahmenSetzen(l); autoSpeichern() }"
      />
      <NcTextField v-model="begruendung" label="Begründung" @update:model-value="autoSpeichern" />
    </div>
  </div>
</template>

<script>
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import PwMultiSelect from './PwMultiSelect.vue'
import PwLoeschen from './PwLoeschen.vue'

// Ziel-Typ eines Pauschalantrags (F84/F85/F100): «Einsparungen» ist relativ
// (beliebig oft), die drei absoluten Ziele legen ein Gesamtergebnis fest (davon
// darf nur eines aktiv sein — das setzt der Server durch).
const ZIEL_TYPEN = [
  { value: 'einsparungen', label: 'Einsparungen' },
  { value: 'schwarze_null', label: 'Schwarze Null' },
  { value: 'fester_ertrag', label: 'Fester Ertrag' },
  { value: 'festes_defizit', label: 'Festes Defizit' },
]

// Die lokalen Editorfelder aus dem Pauschalantrag ableiten. Dieselbe Zuordnung
// beim Erzeugen und bei jeder Änderung von aussen, damit beide Wege nie
// auseinanderlaufen.
function felderAus(pauschal, eigeneFraktion) {
  return {
    zielTyp: pauschal.zielTyp || 'einsparungen',
    betragMag: pauschal.betrag ? String(Math.abs(pauschal.betrag)) : '',
    prozentMag: pauschal.prozent ? String(Math.abs(pauschal.prozent)) : '',
    zielBetragMag: pauschal.zielBetrag ? String(Math.abs(pauschal.zielBetrag)) : '',
    einreichen: (pauschal.haltung || 'einreichen') !== 'nicht_einreichen',
    // Antragsteller ist eine Fraktion (F94), vorbelegt mit der eigenen Fraktion.
    antragsteller: pauschal.antragsteller || eigeneFraktion || '',
    begruendung: pauschal.begruendung || '',
    ausnahmen: Array.isArray(pauschal.ausnahmen) ? [...pauschal.ausnahmen] : [],
  }
}

/**
 * Editor für einen einzelnen Pauschalantrag (F100). Ziel-Typ wählbar: eine
 * Einsparung (CHF oder Prozent des ursprünglichen Aufwands) oder ein absolutes
 * Ziel (schwarze Null / fester Ertrag / festes Defizit). Dazu Einreichen-Entscheid,
 * Antragsteller (Fraktion, F94), ausgenommene Produktegruppen (F101) und Begründung.
 * Änderungen werden automatisch übernommen (kein «Übernehmen»-Knopf), entprellt.
 */
export default {
  name: 'BudgetPauschalForm',
  components: { NcSelect, NcTextField, NcCheckboxRadioSwitch, PwMultiSelect, PwLoeschen },
  props: {
    pauschal: { type: Object, required: true },
    produktegruppen: { type: Array, default: () => [] },
    // Auswahlfraktionen für den Antragsteller (F94) und die eigene Fraktion als Vorbelegung.
    fraktionOptionen: { type: Array, default: () => [] },
    eigeneFraktion: { type: String, default: '' },
  },
  emits: ['save', 'delete'],
  data() {
    return { ...felderAus(this.pauschal, this.eigeneFraktion), speichernTimer: null }
  },
  computed: {
    zielTypen() { return ZIEL_TYPEN },
    zielTypGewaehlt() {
      return ZIEL_TYPEN.find(z => z.value === this.zielTyp) || ZIEL_TYPEN[0]
    },
    gruppenOptionen() {
      return this.produktegruppen.filter(g => !g.kuenstlich).map(g => ({ value: g.code, label: g.code + ' ' + (g.name || '') }))
    },
    ausnahmenGewaehlt() {
      return this.gruppenOptionen.filter(o => this.ausnahmen.includes(o.value))
    },
    antragstellerGewaehlt() {
      return this.fraktionOptionen.find(o => o.value === this.antragsteller) || null
    },
  },
  watch: {
    // F101: derselbe Pauschalantrag wird auch anderswo geändert — über den
    // Ausnahme-Schalter an der Produktegruppe, in einer parallelen Sitzung. Der
    // neue Stand kommt als Prop und wird hier übernommen, sonst zeigt der Editor
    // weiter den Stand vom Öffnen und schreibt ihn beim nächsten Speichern zurück.
    // Eine noch nicht gespeicherte Eingabe hat Vorrang: solange der Entprell-Timer
    // läuft, bleibt stehen, was gerade getippt wird.
    pauschal: {
      deep: true,
      handler() { this.uebernehmen() },
    },
    // Die eigene Fraktion kommt aus der Ansicht und kann nach dem Erzeugen
    // eintreffen; sie belegt den Antragsteller vor, solange keiner gesetzt ist.
    eigeneFraktion() { this.uebernehmen() },
  },
  beforeUnmount() {
    if (this.speichernTimer) { clearTimeout(this.speichernTimer) }
  },
  methods: {
    // Den Stand von aussen übernehmen. Eine noch nicht gespeicherte Eingabe hat
    // Vorrang: solange der Entprell-Timer läuft, bleibt stehen, was getippt wird.
    uebernehmen() {
      if (this.speichernTimer) { return }
      Object.assign(this, felderAus(this.pauschal, this.eigeneFraktion))
    },
    ausnahmenSetzen(liste) {
      this.ausnahmen = (Array.isArray(liste) ? liste : []).map(o => (o && o.value) || o)
    },
    // Auto-Save: kurz nach der letzten Änderung übernehmen, entprellt (0,6 s), damit
    // Tippen im Betrags-/Begründungsfeld nicht pro Tastendruck speichert.
    autoSpeichern() {
      if (this.speichernTimer) { clearTimeout(this.speichernTimer) }
      this.speichernTimer = setTimeout(() => this.speichern(), 600)
    },
    speichern() {
      // Der Timer ist abgelaufen: ab jetzt hat wieder der Stand von aussen Vorrang.
      // Bleibt er stehen, blockiert er «uebernehmen» für immer — dann kommt eine
      // Ausnahme, die unten an der Produktegruppe gesetzt wird, oben nie an.
      this.speichernTimer = null
      const felder = {
        zielModus: this.zielTyp,
        haltung: this.einreichen ? 'einreichen' : 'nicht_einreichen',
        antragsteller: this.antragsteller,
        begruendung: this.begruendung,
        ausnahmen: this.ausnahmen,
      }
      if (this.zielTyp === 'einsparungen') {
        // Einsparung ist immer eine Reduktion (negatives Vorzeichen); Prozent hat Vorrang.
        if (this.prozentMag !== '') {
          felder.prozent = -Math.abs(Number(this.prozentMag))
          felder.betrag = 0
        } else {
          felder.betrag = -Math.abs(Math.round(Number(this.betragMag) || 0))
          felder.prozent = 0
        }
        felder.zielBetrag = 0
      } else if (this.zielTyp === 'schwarze_null') {
        felder.zielBetrag = 0
        felder.betrag = 0
        felder.prozent = 0
      } else {
        // Fester Ertrag (positiv) oder festes Defizit (negativ) als Gesamtergebnis.
        const mag = Math.abs(Math.round(Number(this.zielBetragMag) || 0))
        felder.zielBetrag = this.zielTyp === 'festes_defizit' ? -mag : mag
        felder.betrag = 0
        felder.prozent = 0
      }
      this.$emit('save', felder)
    },
  },
}
</script>

<style scoped lang="scss">
.pw-pauschal-form { position: relative; display: flex; flex-direction: column; gap: 0.4rem; margin-block-start: 0.4rem; }
.pw-pauschal-form .pw-antrag-form-zeile { display: flex; gap: 0.5rem; align-items: flex-end; flex-wrap: wrap; }
.pw-pauschal-form .pw-antrag-form-zeile > * { flex: 1 1 10rem; }
/* Löschen-Ecke: oben rechts an der Card, damit die Felder darunter Platz behalten. */
.pw-pauschal-form .pw-loeschen-ecke { position: absolute; inset-block-start: 0.25rem; inset-inline-end: 0.25rem; }
.pw-pauschal-form .pw-antrag-form-zeile:first-of-type { padding-inline-end: 2.5rem; }
</style>
