<template>
  <div class="pw-uebernahme">
    <h4 class="pw-uebernahme-titel">Aus einer Tabelle übernehmen</h4>
    <p class="pw-uebernahme-hinweis">
      Spalten: {{ spalten.join(', ') }}. Die Position ist die Nummer der
      Produktegruppe oder ihr Name; ein Betrag ohne Vorzeichen gilt als Kürzung,
      solange die Spalte «Richtung» nichts anderes sagt.
    </p>
    <div class="pw-uebernahme-aktionen">
      <NcButton type="tertiary" @click="vorlageHerunterladen">Vorlage herunterladen</NcButton>
      <input ref="datei" type="file" accept=".csv,.xlsx" class="pw-uebernahme-feld" @change="dateiGewaehlt">
    </div>

    <table v-if="geprueft.length" class="pw-uebernahme-vorschau">
      <thead>
        <tr><th>Zeile</th><th>Position</th><th>Betrag</th><th>Begründung</th><th>Befund</th></tr>
      </thead>
      <tbody>
        <tr v-for="z in geprueft" :key="z.zeile" :class="{ 'pw-uebernahme-fehler': z.fehler }">
          <td data-label="Zeile">{{ z.zeile }}</td>
          <td data-label="Position">{{ z.position }}</td>
          <td data-label="Betrag">{{ z.betrag }}</td>
          <td data-label="Begründung">{{ z.begruendung }}</td>
          <td data-label="Befund">{{ z.fehler || 'übernehmbar' }}</td>
        </tr>
      </tbody>
    </table>

    <NcButton v-if="uebernehmbare.length" type="primary" @click="uebernehmen">
      {{ uebernehmbare.length }} Anträge übernehmen
    </NcButton>
    <p v-if="meldung" class="pw-uebernahme-meldung">{{ meldung }}</p>
  </div>
</template>

<script>
import NcButton from '@nextcloud/vue/components/NcButton'
import { csvLesen, xlsxLesen, zeilenPruefen, vorlageCsv, VORLAGE_SPALTEN } from '../tabellen-datei'

/**
 * Anträge aus einer Tabellendatei übernehmen (F127).
 *
 * Marc am 24.10.2026: «was vielleicht gut wäre, wäre so etwas wie ein
 * Excel-Upload oder so». Die Datei wird gelesen, jede Zeile geprüft und in
 * einer Vorschau gezeigt; erst «Übernehmen» legt die Anträge an.
 */
export default {
  name: 'BudgetAntraegeUebernahme',
  components: { NcButton },
  props: {
    positionOptionen: { type: Array, default: () => [] },
  },
  emits: ['anlegen'],
  data() {
    return { geprueft: [], meldung: '', spalten: VORLAGE_SPALTEN }
  },
  computed: {
    uebernehmbare() {
      return this.geprueft.filter(z => z.antrag)
    },
  },
  methods: {
    vorlageHerunterladen() {
      const blob = new Blob([vorlageCsv()], { type: 'text/csv;charset=utf-8' })
      const url = URL.createObjectURL(blob)
      const a = document.createElement('a')
      a.href = url
      a.download = 'budgetantraege-vorlage.csv'
      a.click()
      URL.revokeObjectURL(url)
    },
    async dateiGewaehlt(ereignis) {
      const datei = ereignis && ereignis.target && ereignis.target.files && ereignis.target.files[0]
      if (!datei) { return }
      this.meldung = ''
      try {
        const zeilen = datei.name.toLowerCase().endsWith('.xlsx')
          ? xlsxLesen(await this.archivLesen(datei))
          : csvLesen(await datei.text())
        this.geprueft = zeilenPruefen(zeilen, this.positionOptionen)
        if (this.geprueft.length === 0) { this.meldung = 'Die Datei enthält keine Zeilen.' }
      } catch (e) {
        this.geprueft = []
        this.meldung = 'Die Datei konnte nicht gelesen werden.'
      }
    },
    /**
     * Die beiden XML-Dateien einer xlsx-Datei, die Werte tragen. Ein ZIP ohne
     * Verdichtung trägt sie im Klartext; verdichtete Einträge entpackt der
     * Browser mit seiner eigenen Dekomprimierung.
     */
    async archivLesen(datei) {
      const puffer = new Uint8Array(await datei.arrayBuffer())
      const eintraege = {}
      const sicht = new DataView(puffer.buffer)
      for (let i = 0; i < puffer.length - 4; i++) {
        if (sicht.getUint32(i, true) !== 0x04034b50) { continue }
        const verfahren = sicht.getUint16(i + 8, true)
        const groesse = sicht.getUint32(i + 18, true)
        const namenLaenge = sicht.getUint16(i + 26, true)
        const extraLaenge = sicht.getUint16(i + 28, true)
        const anfang = i + 30
        const name = new TextDecoder().decode(puffer.slice(anfang, anfang + namenLaenge))
        const datenAnfang = anfang + namenLaenge + extraLaenge
        const daten = puffer.slice(datenAnfang, datenAnfang + groesse)
        if (!name.endsWith('.xml')) { continue }
        eintraege[name] = verfahren === 0
          ? new TextDecoder().decode(daten)
          : await this.entpacken(daten)
      }
      return eintraege
    },
    async entpacken(daten) {
      const strom = new Blob([daten]).stream().pipeThrough(new DecompressionStream('deflate-raw'))
      return await new Response(strom).text()
    },
    uebernehmen() {
      const antraege = this.uebernehmbare.map(z => z.antrag)
      this.$emit('anlegen', antraege)
      this.meldung = antraege.length + ' Anträge übernommen.'
      this.geprueft = []
      if (this.$refs.datei) { this.$refs.datei.value = '' }
    },
  },
}
</script>

<style scoped lang="scss">
.pw-uebernahme { display: flex; flex-direction: column; gap: var(--pw-gap-1); margin-block-start: var(--pw-gap-2); }
.pw-uebernahme-titel { margin: 0; font-size: var(--pw-font-lg); }
.pw-uebernahme-hinweis { margin: 0; color: var(--pw-muted); font-size: var(--pw-font-sm); }
.pw-uebernahme-aktionen { display: flex; gap: var(--pw-gap-1); align-items: center; flex-wrap: wrap; }
.pw-uebernahme-vorschau { inline-size: 100%; border-collapse: collapse; }
.pw-uebernahme-vorschau th { text-align: start; font-weight: 400; font-size: var(--pw-font-sm); color: var(--pw-muted); }
.pw-uebernahme-vorschau td { padding-block: var(--pw-space-2xs); padding-inline-end: var(--pw-space-xs); }
.pw-uebernahme-fehler td { color: var(--color-error, #c0392b); }

@media (max-width: 54rem) {
  .pw-uebernahme-vorschau thead { display: none; }
  .pw-uebernahme-vorschau tr { display: block; margin-block-end: var(--pw-gap-1); }
  .pw-uebernahme-vorschau td { display: block; }
  .pw-uebernahme-vorschau td::before { content: attr(data-label) ': '; color: var(--pw-muted); }
}
</style>
