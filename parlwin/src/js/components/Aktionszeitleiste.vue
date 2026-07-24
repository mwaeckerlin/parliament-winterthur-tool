<template>
  <div class="pw-detail-abschnitt">
    <h4>Aktionszeitleiste</h4>
    <div v-if="zeitleisteEintraege.length === 0" class="pw-hinweis">Noch keine Aktionen vorhanden.</div>
    <div
      v-for="(e, idx) in zeitleisteEintraege"
      :key="e._key"
      class="pw-timeline-eintrag"
      :class="{ 'pw-timeline-drag-over': dragZeitleisteUeberIdx === idx }"
      @dragstart="tlDragStart($event, idx)"
      @dragover.prevent="tlDragOver($event, idx)"
      @dragleave="tlDragLeave"
      @drop.prevent="tlDrop($event, idx)"
      @dragend="tlDragEnd"
    >
      <span class="pw-notiz-griff" draggable="true" title="Verschieben" aria-hidden="true">⠿</span>
      <div class="pw-timeline-datum">
        <span class="pw-timeline-datum-tag">{{ formatieredatum(e.erstelltAm) }}</span>
        <span class="pw-timeline-datum-uhrzeit">{{ formatiereUhrzeit(e.erstelltAm) }}</span>
        <small v-if="e._sitzungInfo" class="pw-traktandum-kontext-meta">{{ e._sitzungInfo }}</small>
      </div>
      <span class="pw-timeline-autor">{{ e.autorName || e.autorUid || 'unbekannt' }}</span>
      <div class="pw-timeline-inhalt">
        <template v-if="e._type === 'traktandumNotiz'">
          <span
            v-if="e._sitzungId"
            class="pw-timeline-text pw-notiz-text-klickbar"
            role="button"
            tabindex="0"
            title="Zur Sitzung springen"
            @click="$emit('oeffne-traktandum', e._sitzungId)"
            @keydown.enter.prevent="$emit('oeffne-traktandum', e._sitzungId)"
            v-html="markdownZuHtml(e.text)"
          />
          <span v-else class="pw-timeline-text" v-html="markdownZuHtml(e.text)" />
        </template>
        <div v-else-if="e.text && e.aktionTyp === 'votum'" class="pw-timeline-text pw-timeline-html" v-html="e.text" />
        <template v-else-if="e.titel && e.aktionTyp !== 'notiz'">
          <span class="pw-timeline-text">{{ e.titel }}</span>
          <span v-if="e.text" class="pw-timeline-detail">{{ e.text }}</span>
        </template>
        <span v-else-if="e.text" class="pw-timeline-text">{{ e.text }}</span>
      </div>
    </div>
  </div>
</template>

<script>
import { markdownZuHtml } from '../utils'

// Geteilte Aktionszeitleiste für Geschäft UND Vorstoss — eine Komponente, keine
// Duplikation. Die Umsortierung per Drag-and-Drop ist rein lokal (kein Backend).
export default {
  name: 'Aktionszeitleiste',
  props: {
    // Aktionen des Objekts (Geschäft: geschaeft.aktionen, Vorstoss: bearbeitung.aktionen).
    aktionen: { type: Array, required: true },
    // Nur das Geschäft übergibt einen Traktandum-Kontext; der Vorstoss lässt ihn weg.
    traktandumKontext: { type: Object, default: null },
  },
  emits: ['oeffne-traktandum'],
  data() {
    return {
      zeitleisteReihenfolge: [],
      dragZeitleisteVonIdx: -1,
      dragZeitleisteUeberIdx: -1,
    }
  },
  computed: {
    zeitleisteAktionen() {
      const alle = this.aktionen || []
      return alle.filter(a => {
        if (a.aktionTyp === 'votum' && a.entscheidGueltig) return false
        // Notizen und Sitzungsnotizen (aktive wie gelöschte) leben in ihren
        // eigenen Listen (NotizenListe), nicht in der Zeitleiste.
        if (a.aktionTyp === 'notiz' || a.aktionTyp === 'sitzungsnotiz') return false
        return true
      })
    },
    zeitleisteEintraege() {
      const aktionen = this.zeitleisteAktionen.map(a => ({
        ...a,
        _key: String(a.id),
        _type: 'aktion',
        _sitzungInfo: null,
        _sitzungId: null,
      }))
      const tk = this.traktandumKontext
      const traktandumNotizen = (tk?.notizen || []).map((n, i) => {
        const parts = []
        if (tk.traktandumNummer) parts.push(`Trakt. ${tk.traktandumNummer}`)
        if (tk.sitzungDatum) parts.push(this.formatieredatum(tk.sitzungDatum))
        if (tk.sitzungTitel) parts.push(tk.sitzungTitel)
        return {
          id: null,
          _key: `tk_${i}`,
          _type: 'traktandumNotiz',
          _sitzungId: tk.sitzungId || null,
          aktionTyp: 'notiz',
          titel: '',
          text: n.text,
          autorName: n.displayName || n.uid,
          autorUid: n.uid,
          erstelltAm: n.datum,
          aktionCode: '',
          entscheidGueltig: false,
          _sitzungInfo: parts.join(', '),
        }
      })
      const kombiniert = [...traktandumNotizen, ...aktionen]
      if (this.zeitleisteReihenfolge.length > 0) {
        const indexMap = {}
        this.zeitleisteReihenfolge.forEach((key, i) => { indexMap[key] = i })
        return [...kombiniert].sort((a, b) => {
          const ia = indexMap[a._key] ?? Number.MAX_SAFE_INTEGER
          const ib = indexMap[b._key] ?? Number.MAX_SAFE_INTEGER
          return ia - ib
        })
      }
      return kombiniert
    },
  },
  methods: {
    markdownZuHtml,
    tlDragStart(event, idx) {
      this.dragZeitleisteVonIdx = idx
      event.dataTransfer.effectAllowed = 'move'
    },
    tlDragOver(event, idx) {
      event.dataTransfer.dropEffect = 'move'
      this.dragZeitleisteUeberIdx = idx
    },
    tlDragLeave() {
      this.dragZeitleisteUeberIdx = -1
    },
    tlDrop(event, zuIdx) {
      const vonIdx = this.dragZeitleisteVonIdx
      this.dragZeitleisteUeberIdx = -1
      this.dragZeitleisteVonIdx = -1
      if (vonIdx < 0 || vonIdx === zuIdx) return
      const eintraege = [...this.zeitleisteEintraege]
      const [verschoben] = eintraege.splice(vonIdx, 1)
      eintraege.splice(zuIdx, 0, verschoben)
      this.zeitleisteReihenfolge = eintraege.map(e => e._key)
    },
    tlDragEnd() {
      this.dragZeitleisteVonIdx = -1
      this.dragZeitleisteUeberIdx = -1
    },
    formatieredatum(datum) {
      if (!datum) return ''
      try {
        return new Date(datum).toLocaleDateString('de-CH')
      } catch {
        return datum
      }
    },
    formatiereUhrzeit(wert) {
      if (!wert) return ''
      try {
        return new Date(wert).toLocaleTimeString('de-CH', { hour: '2-digit', minute: '2-digit' })
      } catch {
        return ''
      }
    },
  },
}
</script>
