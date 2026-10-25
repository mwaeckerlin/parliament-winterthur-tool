<template>
  <div class="pw-antrag-form">
    <div class="pw-antrag-form-zeile">
      <NcSelect
        :model-value="herkunftOption"
        :options="herkunftOptionen"
        :clearable="false"
        input-label="Herkunft"
        @update:model-value="herkunftGewaehlt"
      />
      <NcSelect
        v-model="antragstellerOption"
        :options="antragstellerOptionen"
        input-label="Antragsteller"
        :placeholder="form.herkunft === 'fremde' ? 'Fraktion / Person' : 'Person'"
      />
    </div>
    <div v-if="mitStellen" class="pw-antrag-form-zeile">
      <NcTextField :model-value="form.stellen" type="number" label="Stellen (− kürzen)" @update:model-value="form.stellen = $event" />
      <NcTextField :model-value="form.betrag" type="number" label="Betrag CHF (optional)" @update:model-value="betragGeaendert" />
    </div>
    <div v-else class="pw-antrag-form-zeile">
      <NcCheckboxRadioSwitch :model-value="form.mehrausgabe" type="switch" @update:model-value="form.mehrausgabe = $event">
        {{ form.mehrausgabe ? 'Mehrausgabe' : 'Reduktion' }}
      </NcCheckboxRadioSwitch>
      <NcTextField :model-value="chfMagnitude" type="number" label="Betrag CHF" @update:model-value="betragGeaendert" />
      <NcTextField :model-value="prozentMagnitude" type="number" label="Betrag %" @update:model-value="prozentGeaendert" />
    </div>
    <!-- F137: Ein Antrag, der statt eines Betrags einen neuen Wert setzt — das
         Bewilligungsorgan eines Verpflichtungskredits. Bleibt das Feld leer,
         gilt der Betrag darüber. -->
    <div v-if="mitNeuemWert" class="pw-antrag-form-zeile">
      <NcTextField :model-value="form.neuerWert || ''" :label="neuerWertLabel" @update:model-value="form.neuerWert = $event" />
    </div>
    <div class="pw-antrag-form-zeile">
      <!-- «Unsere Haltung» (einreichen/unterstützen) wird nach dem Anlegen direkt an
           der Antragszeile per Toggle gesetzt, nicht mehr hier im Formular. -->
      <PwMultiSelect
        :model-value="unterstuetzerOptionen"
        :options="fraktionOptionen"
        input-label="Unterstützende Fraktionen"
        @update:model-value="unterstuetzerGewaehlt"
      />
    </div>
    <!-- Zielvorgaben ändern (F109, WoV): je Messgrösse ein neuer Soll-Wert. Leer
         lassen = keine Änderung. Ein Antrag kann Budget UND/ODER Zielvorgaben ändern;
         nur Zielvorgaben (ohne Budget) ist zulässig. -->
    <details v-if="zielvorgaben.length" class="pw-antrag-ziele">
      <summary>Zielvorgaben ändern ({{ gesetzteZiele }})</summary>
      <div v-for="(zv, i) in zielvorgaben" :key="i" class="pw-antrag-ziel-zeile">
        <span class="pw-antrag-ziel-label">{{ zielName(zv) }}</span>
        <div class="pw-antrag-ziel-eingabe">
          <span class="pw-antrag-ziel-soll">Soll aktuell: {{ zv.soll }}</span>
          <NcTextField :model-value="zielWert(zv)" label="neuer Soll" @update:model-value="zielWertGesetzt(zv, $event)" />
        </div>
      </div>
    </details>
    <!-- Einsparungsverteilung (F109, WoV): wo innerhalb der Produktegruppe der Betrag
         eingespart wird. Ohne Betrag oben ergibt sich der PG-Betrag aus der Summe hier;
         mit Betrag oben dient die Verteilung nur der Begründung. -->
    <details v-if="kostenzeilen.length || produkte.length" class="pw-antrag-aufteilung">
      <summary>Einsparung verteilen ({{ (form.aufteilung || []).length }})</summary>
      <div v-for="(kz, i) in kostenzeilen" :key="'k' + i" class="pw-antrag-aufteil-zeile">
        <span class="pw-antrag-aufteil-label">
          {{ kz.label }}
          <!-- F128: Wie weit diese Zeile beeinflussbar ist. Eine Orientierung;
               beantragen lässt sich auch eine gebundene Position. -->
          <span v-if="stufenText(kz)" class="pw-aufteil-stufe">{{ stufenText(kz) }}</span>
        </span>
        <NcTextField :model-value="aufteilungWert('pg-kosten', kz.label)" type="number" label="Betrag CHF" @update:model-value="aufteilungGesetzt('pg-kosten', kz.label, $event)" />
      </div>
      <div v-for="(p, i) in produkte" :key="'p' + i" class="pw-antrag-aufteil-zeile">
        <span class="pw-antrag-aufteil-label">Produkt {{ p.nummer }} {{ p.name }}</span>
        <NcTextField :model-value="aufteilungWert('produkt', String(p.nummer))" type="number" label="Betrag CHF" @update:model-value="aufteilungGesetzt('produkt', String(p.nummer), $event)" />
      </div>
    </details>
    <!-- Die Begründung ist der Text, den der Rat liest: mehrzeilig und
         formatierbar, mit derselben Komponente wie Notizen und der Inhalt eines
         Vorstosses (Auftrag von Marc am 24.10.2026). -->
    <div class="pw-antrag-begruendung-feld">
      <label class="pw-antrag-begruendung-label">Begründung</label>
      <PwWysiwyg
        :model-value="form.begruendung"
        aria-label="Begründung"
        placeholder="Warum dieser Antrag?"
        @update:model-value="form.begruendung = $event"
      />
    </div>
    <div class="pw-antrag-form-zeile">
      <NcButton type="secondary" @click="$emit('save')">Antrag</NcButton>
      <NcButton type="tertiary" @click="$emit('abbrechen')">Abbrechen</NcButton>
    </div>
  </div>
</template>

<script>
import NcButton from '@nextcloud/vue/components/NcButton'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import PwMultiSelect from './PwMultiSelect.vue'
import PwWysiwyg from './PwWysiwyg.vue'
import { zielName, gleicheZielvorgabe } from '../utils'

const HERKUNFT = [
  { value: 'eigene', label: 'Eigen' },
  { value: 'fremde', label: 'Fremd' },
]

/**
 * Antragsformular (F94/F95/F97/F98). Bindet an ein Formularobjekt der
 * Elternkomponente (betrag/prozent/mehrausgabe/herkunft/antragsteller/haltung/
 * unterstuetzer/begruendung). Die Richtung ist das Vorzeichen (Reduktion −,
 * Mehrausgabe +); CHF und Prozent werden über die Bezugsbasis wechselseitig
 * berechnet (F95).
 */
export default {
  name: 'BudgetAntragForm',
  components: { NcButton, NcSelect, NcTextField, NcCheckboxRadioSwitch, PwMultiSelect, PwWysiwyg },
  props: {
    form: { type: Object, required: true },
    antragstellerOptionen: { type: Array, default: () => [] },
    fraktionOptionen: { type: Array, default: () => [] },
    mitStellen: { type: Boolean, default: false },
    // Budgetwert der Position als Basis für die %↔CHF-Rechnung (F95).
    basis: { type: Number, default: 0 },
    // F137: zeigt das Feld für einen neuen Wert statt eines Betrags.
    mitNeuemWert: { type: Boolean, default: false },
    neuerWertLabel: { type: String, default: 'Neuer Wert' },
    // Parlamentarische Zielvorgaben der Produktegruppe (F109), zum Ändern des Soll.
    zielvorgaben: { type: Array, default: () => [] },
    // Kostenzeilen und Produkte der Produktegruppe (F109) für die Einsparungsverteilung.
    kostenzeilen: { type: Array, default: () => [] },
    produkte: { type: Array, default: () => [] },
  },
  emits: ['save', 'herkunft', 'abbrechen'],
  data() {
    return { herkunftOptionen: HERKUNFT }
  },
  computed: {
    gesetzteZiele() {
      return (this.form.zielAenderungen || []).length
    },
    herkunftOption() {
      return HERKUNFT.find(h => h.value === this.form.herkunft) || HERKUNFT[0]
    },
    antragstellerOption: {
      get() {
        return this.antragstellerOptionen.find(o => o.value === this.form.antragsteller)
          || (this.form.antragsteller ? { value: this.form.antragsteller, label: this.form.antragsteller } : null)
      },
      set(opt) { this.form.antragsteller = opt ? opt.value : '' },
    },
    unterstuetzerOptionen() {
      return (this.form.unterstuetzer || []).map(u => (u && u.value)
        ? u
        : { value: u, label: u })
    },
    chfMagnitude() {
      return this.form.betrag === '' || this.form.betrag === null ? '' : String(Math.abs(Number(this.form.betrag)))
    },
    prozentMagnitude() {
      return this.form.prozent === '' || this.form.prozent === null ? '' : String(Math.abs(Number(this.form.prozent)))
    },
  },
  methods: {
    herkunftGewaehlt(opt) {
      this.$emit('herkunft', opt ? opt.value : 'eigene')
    },
    unterstuetzerGewaehlt(liste) {
      this.form.unterstuetzer = Array.isArray(liste) ? liste : []
    },
    // Eingabe im CHF-Feld → Prozent nachrechnen (F95).
    betragGeaendert(wert) {
      this.form.betrag = wert
      if (this.basis > 0 && wert !== '' && wert !== null) {
        this.form.prozent = String(Math.round(Math.abs(Number(wert)) / this.basis * 10000) / 100)
      } else if (wert === '' || wert === null) {
        this.form.prozent = ''
      }
    },
    // Eingabe im Prozent-Feld → CHF nachrechnen (F95).
    prozentGeaendert(wert) {
      this.form.prozent = wert
      if (this.basis > 0 && wert !== '' && wert !== null) {
        this.form.betrag = String(Math.round(this.basis * Math.abs(Number(wert)) / 100))
      } else if (wert === '' || wert === null) {
        this.form.betrag = ''
      }
    },
    // F128: der Hinweis an einer Kostenzeile, wie weit sie beeinflussbar ist.
    // Die beeinflussbare Zeile trägt keinen Hinweis — sie ist der Normalfall.
    stufenText(kz) {
      const stufe = kz && kz.steuerbarkeit
      if (stufe === 'gebunden') { return 'gebunden' }
      if (stufe === 'neutral') { return 'intern verrechnet' }
      return ''
    },
    // Ziel, Unterabschnitt und Messgrösse als ein Name (F109).
    zielName,
    // Aktueller neuer Soll-Wert einer Zielvorgabe im Formular (F109), leer = keine Änderung.
    zielWert(zv) {
      const e = (this.form.zielAenderungen || []).find(x => gleicheZielvorgabe(x, zv))
      return e ? e.neuerWert : ''
    },
    // Setzt oder entfernt die Zielvorgaben-Änderung; leerer Wert entfernt sie (F109).
    zielWertGesetzt(zv, wert) {
      const liste = (this.form.zielAenderungen || []).filter(x => !gleicheZielvorgabe(x, zv))
      const w = String(wert == null ? '' : wert).trim()
      if (w !== '') {
        liste.push({
          zielNummer: zv.zielNummer,
          unterTitel: zv.unterTitel || '',
          messgroesse: zv.messgroesse,
          neuerWert: w,
        })
      }
      this.form.zielAenderungen = liste
    },
    // Aktueller Aufteilungs-Betrag (Magnitude) einer Ebene/Position (F109).
    aufteilungWert(ebene, ref) {
      const e = (this.form.aufteilung || []).find(x => x.ebene === ebene && x.ref === ref)
      return e && e.betrag != null ? String(Math.abs(e.betrag)) : ''
    },
    // Setzt oder entfernt einen Aufteilungs-Eintrag; das Vorzeichen folgt der Richtung
    // (Reduktion −, Mehrausgabe +) wie beim Hauptbetrag (F109).
    aufteilungGesetzt(ebene, ref, wert) {
      const liste = (this.form.aufteilung || []).filter(x => !(x.ebene === ebene && x.ref === ref))
      const w = String(wert == null ? '' : wert).trim()
      if (w !== '' && !isNaN(Number(w))) {
        const sign = this.form.mehrausgabe ? 1 : -1
        liste.push({ ebene, ref, betrag: sign * Math.abs(Math.round(Number(w))) })
      }
      this.form.aufteilung = liste
    },
  },
}
</script>

<style scoped lang="scss">
.pw-antrag-form { display: flex; flex-direction: column; gap: 0.4rem; margin-block-start: 0.4rem; }
.pw-antrag-form-zeile { display: flex; gap: 0.5rem; align-items: flex-end; flex-wrap: wrap; }
/* Ein Feld darf schmaler werden als sein Inhalt: Sonst hält die Mindestbreite
   des Eingabefelds die Zeile auseinander, die Zeile die Karte, und die Karte
   steht über ihre Spalte hinaus (Befund von Marc am 24.10.2026). */
.pw-antrag-form-zeile > * { flex: 1 1 10rem; min-inline-size: 0; }
/* Die Bezeichnung einer Zielvorgabe trägt Ziel, Unterabschnitt und Messgrösse
   und wird lang; sie bricht um, statt die Zeile breit zu machen. */
.pw-antrag-ziel-label { min-inline-size: 0; hyphens: auto; }
/* Die Begründung steht über die ganze Breite und trägt ihre Beschriftung
   darüber, wie jedes andere Feld des Formulars. */
.pw-antrag-begruendung-feld { display: flex; flex-direction: column; gap: var(--pw-space-2xs); }
.pw-antrag-begruendung-label { font-size: var(--pw-font-sm); color: var(--pw-muted); }
/* F128: Der Hinweis begleitet die Bezeichnung und steht leiser als sie. */
.pw-aufteil-stufe {
  margin-inline-start: var(--pw-space-xs);
  font-size: var(--pw-font-sm);
  color: var(--pw-muted);
}
/* Auf dem Telefon steht jedes Feld auf einer eigenen Zeile und bekommt die
   ganze Breite: zu zweit nebeneinander blieben vom Namen des Antragstellers
   «D⋯ Review» (Bild vom 24.10.2026, phone-large). In Spaltenrichtung wäre die
   Basis von `flex` eine Höhe, darum steht sie hier auf `auto`. */
@media (max-width: 32rem) {
  .pw-antrag-form-zeile { flex-direction: column; align-items: stretch; }
  .pw-antrag-form-zeile > * { flex-basis: auto; }
}
</style>
