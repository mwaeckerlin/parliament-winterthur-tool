<template>
  <div class="pw-notizen-liste">
    <div v-if="aktiveNotizen.length === 0 && !editorOffen" class="pw-hinweis">
      Noch keine Notizen vorhanden.
    </div>
    <div
      v-for="n in sichtbareNotizen"
      :key="n.id"
      class="pw-notiz-eintrag"
    >
      <div class="pw-notiz-kopf">
        <span class="pw-notiz-autor">{{ n.autorName || n.autorUid }}</span>
        <span class="pw-notiz-datum">{{ formatieredatum(n.erstelltAm) }} {{ formatiereUhrzeit(n.erstelltAm) }}</span>
        <PwLoeschen
          v-if="!readonly && istEigeneAktion(n) && aktiveNotizId !== n.id"
          class="pw-notiz-loeschen"
          label="Notiz löschen"
          @click="notizLoeschen(n)"
        />
      </div>
      <div v-if="editorOffen && editorModus === 'edit' && aktiveNotizId === n.id" class="pw-notiz-bearbeiten-zeile">
        <PwWysiwyg
          :model-value="aktiveNotizText"
          :revisionen="aktiveNotizRevisionen"
          placeholder="Notiz bearbeiten…"
          @update:model-value="notizEingabe"
          @version-angezeigt="v => angezeigteVersion = v"
        />
        <div class="pw-notiz-bearbeiten-aktionen">
          <button type="button" class="button pw-btn-mini" title="Speichern" @mousedown.prevent @click="notizBestaetigen">✓</button>
          <button type="button" class="button pw-btn-mini" title="Abbrechen" @mousedown.prevent @click="notizVerwerfen">✕</button>
        </div>
      </div>
      <div
        v-else
        class="pw-notiz-inhalt"
        :class="{ 'pw-notiz-text-klickbar': darfBearbeiten(n) }"
        :role="darfBearbeiten(n) ? 'button' : null"
        :tabindex="darfBearbeiten(n) ? 0 : null"
        :title="darfBearbeiten(n) ? 'Klicken zum Bearbeiten' : ''"
        @click="darfBearbeiten(n) && notizBearbeitenStarten(n, $event)"
        @keydown.enter.prevent="darfBearbeiten(n) && notizBearbeitenStarten(n)"
        v-html="markdownZuHtml(n.text)"
      />
    </div>

    <!-- Gelöschte Notizen erscheinen NICHT hier, sondern als Lösch-Vermerk mit
         Wiederherstellen in der Aktionszeitleiste (des Geschäfts/Vorstosses). -->

    <div v-if="editorOffen && editorModus === 'neu'" class="pw-notiz-bearbeiten-zeile">
      <PwWysiwyg
        :model-value="aktiveNotizText"
        placeholder="Kommentar, Beobachtung, Hinweis"
        @update:model-value="notizEingabe"
      />
      <div class="pw-notiz-bearbeiten-aktionen">
        <button type="button" class="button pw-btn-mini" title="Speichern" @mousedown.prevent @click="notizBestaetigen">✓</button>
        <button type="button" class="button pw-btn-mini" title="Abbrechen" @mousedown.prevent @click="notizVerwerfen">✕</button>
      </div>
    </div>
    <button
      v-if="!readonly"
      type="button"
      class="button pw-btn-neue-notiz"
      title="Neue Notiz"
      @click="notizNeuOeffnen"
    >+ Neue Notiz</button>
  </div>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import { showSuccess, showError } from '@nextcloud/dialogs'
import { markdownZuHtml } from '../utils'
import axios from '@nextcloud/axios'
import PwWysiwyg from './PwWysiwyg.vue'
import PwLoeschen from './PwLoeschen.vue'

/**
 * Geteilte Notizen-Liste für Geschäfte UND Vorstösse — 100 % derselbe Code und
 * dieselbe Funktionalität an beiden Orten (eine einzige Implementierung).
 *
 * Sie kapselt die komplette Notiz-Behandlung: die Liste der Notizen, den
 * Inline-Editor mit Versions-Blättern (PwWysiwyg mit :revisionen), den
 * «+ Neue Notiz»-Knopf und das Speichern ausschliesslich über ✓ (Häkchen, legt
 * bei einer Änderung eine Revision an) bzw. ✕ (verwirft) — kein Autosave, kein
 * Speichern beim Fokus-Verlust; bei ungespeicherten Änderungen wird beim
 * Verlassen gewarnt. Löschen ist ein Soft-Delete; gelöschte Notizen erscheinen
 * NICHT hier, sondern als «… hat seine Notiz gelöscht» mit Wiederherstellen
 * (nur der Autor) in der Aktionszeitleiste.
 *
 * Persistiert wird über REST unter `basisUrl` (z.B. `geschaefte/123` oder
 * `vorstoesse/45`). Die Komponente hält eine lokale Arbeitskopie der Notizen
 * (aus der prop synchronisiert) und meldet nach jeder Änderung die vollständige
 * neue Liste über `geaendert` an die Elternansicht.
 */
export default {
  name: 'NotizenListe',
  components: { PwWysiwyg, PwLoeschen },
  props: {
    /** API-Basis OHNE führenden Slash, z.B. `geschaefte/123` oder `vorstoesse/45`. */
    basisUrl: { type: String, required: true },
    /** Alle Notiz-Objekte (AKTIVE und GELÖSCHTE). */
    notizen: { type: Array, required: true },
    /** UID des angemeldeten Nutzers (für «nur der Autor darf»). */
    aktuelleUid: { type: String, default: '' },
    /**
     * Notiz-Kategorie: `notiz` (Standard) oder `sitzungsnotiz`. Wird bei jeder
     * Anfrage mitgeschickt, sodass dieselbe Komponente reguläre Notizen ODER
     * Sitzungsnotizen desselben Objekts getrennt verwaltet.
     */
    kategorie: { type: String, default: 'notiz' },
    /**
     * Nur-Lese-Modus: zeigt die Notizen an, blendet aber «+ Neue Notiz»,
     * Löschen und das Bearbeiten aus (z.B. Notizen verknüpfter Sitzungen).
     */
    readonly: { type: Boolean, default: false },
  },
  emits: ['geaendert'],
  data() {
    return {
      // Lokale Arbeitskopie der Notizen (aus der prop synchronisiert via watch).
      arbeitsNotizen: [],
      // Ein einziger Notiz-Editor-Zustand — neue Notiz und Bearbeiten teilen ihn:
      // aktiveNotizId === null ⇒ neue, noch nicht persistierte Notiz. editorModus
      // ('neu' | 'edit') hält die Editor-Position stabil, damit die Editor-Instanz
      // beim ersten Speichern einer neuen Notiz nicht umzieht (Fokus-/Datenverlust).
      editorOffen: false,
      editorModus: null,
      aktiveNotizId: null,
      aktiveNotizText: '',
      // Der Text beim Öffnen des Editors — zum Erkennen ungespeicherter Änderungen.
      aktiveNotizOriginal: '',
      aktiveNotizRevisionen: [],
      // Text der aktuell im Verlauf angezeigten älteren Fassung (null = neueste/
      // Arbeitsstand). Nur relevant fürs explizite Ok (Restore) beim Blättern.
      angezeigteVersion: null,
    }
  },
  watch: {
    notizen: {
      immediate: true,
      handler(neu) {
        this.arbeitsNotizen = Array.isArray(neu) ? neu.map(n => ({ ...n })) : []
      },
    },
  },
  mounted() {
    // Browser-seitige Warnung bei ungespeicherten Notiz-Änderungen (Reload, URL,
    // Tab schliessen). Das Verlassen des Dialogs (X, Klick daneben) prüft die
    // Elternansicht über hatUngespeicherteAenderungen().
    window.addEventListener('beforeunload', this.beforeUnloadWarnung)
  },
  beforeUnmount() {
    window.removeEventListener('beforeunload', this.beforeUnloadWarnung)
  },
  computed: {
    /** Aktive (nicht gelöschte) Notizen. */
    aktiveNotizen() {
      return this.arbeitsNotizen.filter(n => !n.geloescht)
    },
    /** Query-Suffix, der die Kategorie an URL-basierte Anfragen (GET/DELETE/POST-Undo) anhängt. */
    _katParam() {
      return `?kategorie=${encodeURIComponent(this.kategorie)}`
    },
    /**
     * Notizen für die statische Liste. Eine gerade neu erfasste Notiz (Editor unten,
     * aktiveNotizId zunächst null, nach dem ersten Speichern gesetzt) wird hier
     * ausgeblendet, solange ihr Editor unten offen ist — sonst erschiene sie doppelt
     * (einmal als Editor, einmal als fertiger Eintrag). Beim Bearbeiten einer
     * bestehenden Notiz bleibt sie sichtbar; ihr Editor ersetzt dort den Text inline.
     */
    sichtbareNotizen() {
      return this.aktiveNotizen.filter(n =>
        !(this.editorOffen && this.editorModus === 'neu' && n.id === this.aktiveNotizId))
    },
  },
  methods: {
    markdownZuHtml,
    istEigeneAktion(a) {
      const uid = (this.aktuelleUid || '').toLowerCase()
      return !!uid && (a.autorUid || '').toLowerCase() === uid
    },
    /** Der Nutzer darf eine eigene Notiz bearbeiten — ausser im Nur-Lese-Modus. */
    darfBearbeiten(a) {
      return !this.readonly && this.istEigeneAktion(a)
    },
    // Fügt eine Notiz zur lokalen Arbeitskopie hinzu und meldet die neue Liste.
    _lokalHinzufuegen(aktion) {
      if (!aktion) return
      this.arbeitsNotizen = [...this.arbeitsNotizen, aktion]
      this._melde()
    },
    // Aktualisiert eine bestehende Notiz in der lokalen Arbeitskopie.
    _lokalAktualisieren(aktion) {
      if (!aktion?.id) return
      const idx = this.arbeitsNotizen.findIndex(a => a.id === aktion.id)
      if (idx < 0) return
      const kopie = [...this.arbeitsNotizen]
      kopie[idx] = aktion
      this.arbeitsNotizen = kopie
      this._melde()
    },
    // Meldet die vollständige aktuelle Notizen-Liste an die Elternansicht.
    _melde() {
      this.$emit('geaendert', this.arbeitsNotizen.map(n => ({ ...n })))
    },
    /** Meldet dem Browser ungespeicherte Änderungen (beforeunload). */
    beforeUnloadWarnung(e) {
      if (!this.hatUngespeicherteAenderungen()) return
      e.preventDefault()
      e.returnValue = ''
      return ''
    },
    /**
     * Es gibt ungespeicherte Änderungen, wenn der Editor offen ist und der Text
     * vom Stand beim Öffnen abweicht. Die Elternansicht ruft das beim Schliessen
     * des Dialogs (X / Klick daneben) ab, um zu warnen.
     */
    hatUngespeicherteAenderungen() {
      if (!this.editorOffen) return false
      return (this.aktiveNotizText || '').trim() !== (this.aktiveNotizOriginal || '').trim()
    },
    /**
     * Fragt bei ungespeicherten Änderungen nach, bevor der offene Editor
     * verworfen wird. Gibt true zurück, wenn fortgefahren werden darf.
     */
    darfEditorSchliessen() {
      if (!this.hatUngespeicherteAenderungen()) return true
      // eslint-disable-next-line no-alert
      return window.confirm('Die Notiz ist noch nicht gespeichert. Änderungen verwerfen?')
    },
    /**
     * Öffnet einen leeren Editor für eine neue Notiz. Die Notiz wird erst beim
     * Häkchen in der DB angelegt (kein Autosave). Ist bereits ein Editor mit
     * ungespeicherten Änderungen offen, wird zuerst nachgefragt.
     */
    notizNeuOeffnen() {
      if (this.editorOffen && !this.darfEditorSchliessen()) return
      this.editorModus = 'neu'
      this.aktiveNotizId = null
      this.aktiveNotizText = ''
      this.aktiveNotizOriginal = ''
      this.aktiveNotizRevisionen = []
      this.editorOffen = true
    },
    /** Übernimmt Editor-Eingaben in den Arbeitsstand (kein Autosave). */
    notizEingabe(val) {
      this.aktiveNotizText = val
    },
    /** Öffnet denselben Editor für eine bestehende Notiz (inline an ihrer Stelle). */
    async notizBearbeitenStarten(a) {
      if (this.editorOffen && this.aktiveNotizId !== a.id && !this.darfEditorSchliessen()) return
      this.editorModus = 'edit'
      this.aktiveNotizId = a.id
      this.aktiveNotizText = a.text || ''
      this.aktiveNotizOriginal = a.text || ''
      this.aktiveNotizRevisionen = []
      this.editorOffen = true
      // Versions-History nachladen, damit im Editor geblättert werden kann.
      try {
        const { data } = await axios.get(
          generateUrl(`/apps/parlwin/${this.basisUrl}/notizen/${a.id}/revisionen`) + this._katParam
        )
        this.aktiveNotizRevisionen = Array.isArray(data) ? data : []
      } catch (e) {
        this.aktiveNotizRevisionen = []
      }
    },
    async notizLoeschen(a) {
      try {
        await axios.delete(generateUrl(`/apps/parlwin/${this.basisUrl}/notizen/${a.id}`) + this._katParam)
        // Soft-Delete: die Notiz bleibt in der Liste, aber als gelöscht markiert —
        // so erscheint sie als Lösch-Vermerk mit Wiederherstellen (für den Autor).
        const idx = this.arbeitsNotizen.findIndex(n => n.id === a.id)
        if (idx >= 0) {
          const kopie = [...this.arbeitsNotizen]
          kopie[idx] = { ...kopie[idx], geloescht: true }
          this.arbeitsNotizen = kopie
          this._melde()
        }
      } catch (e) {
        showError('Fehler beim Löschen der Notiz')
      }
    },
    /**
     * Explizites Speichern (Häkchen). Legt bei einer Änderung eine Revision an
     * (History). Wird gerade eine ältere Fassung angezeigt (Blättern), gilt das
     * Häkchen als «Restore»: die angezeigte alte Fassung wird als neue aktuelle
     * Version übernommen. Danach schliesst der Editor.
     */
    async notizBestaetigen() {
      if (!this.editorOffen) return
      if (this.angezeigteVersion !== null) {
        // Restore beim Blättern: zuerst den bearbeiteten Arbeitsstand sichern
        // (er wird zur Revision, geht also nicht verloren), dann die angezeigte
        // alte Fassung als neue aktuelle Version übernehmen.
        const alteFassung = this.angezeigteVersion
        await this.notizSpeichern()
        this.aktiveNotizText = alteFassung
        await this.notizSpeichern()
        this.notizEditorSchliessen()
        return
      }
      await this.notizSpeichern()
      this.notizEditorSchliessen()
    },
    /** Bricht die Bearbeitung ab und räumt den Editor weg — ohne zu speichern. */
    notizVerwerfen() {
      this.notizEditorSchliessen()
    },
    notizEditorSchliessen() {
      this.editorOffen = false
      this.editorModus = null
      this.aktiveNotizId = null
      this.aktiveNotizText = ''
      this.aktiveNotizOriginal = ''
      this.aktiveNotizRevisionen = []
      this.angezeigteVersion = null
    },
    /**
     * Persistiert den Arbeitsstand: eine neue Notiz wird angelegt (POST), eine
     * bestehende aktualisiert (PUT, archiviert bei Änderung eine Revision). Ein
     * leerer Text erzeugt/ändert nichts. Serialisiert, damit zwei schnelle
     * Aufrufe nicht zwei Notizen anlegen.
     */
    notizSpeichern() {
      this._notizKette = (this._notizKette || Promise.resolve()).then(() => this._notizSpeichernIntern())
      return this._notizKette
    },
    async _notizSpeichernIntern() {
      const text = (this.aktiveNotizText || '').trim()
      if (!text) return
      if (this.aktiveNotizId === null) {
        try {
          const { data } = await axios.post(
            generateUrl(`/apps/parlwin/${this.basisUrl}/notizen`),
            { text, kategorie: this.kategorie }
          )
          this.aktiveNotizId = data.id
          this._lokalHinzufuegen(data)
          showSuccess('Notiz gespeichert')
        } catch (e) {
          showError('Fehler beim Speichern der Notiz')
        }
        return
      }
      // Bestehende Notiz aktualisieren — der Abschluss archiviert bei einer
      // Änderung eine Revision (History). Leerer Text löscht nicht (dafür gibt es
      // den Lösch-Knopf), damit kein Inhalt verloren geht.
      try {
        const { data } = await axios.put(
          generateUrl(`/apps/parlwin/${this.basisUrl}/notizen/${this.aktiveNotizId}`),
          { text, kategorie: this.kategorie }
        )
        this._lokalAktualisieren(data)
        showSuccess('Notiz gespeichert')
      } catch (e) {
        showError('Fehler beim Bearbeiten der Notiz')
      }
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

<style scoped>
/* Alle Notiz-Stile (pw-notizen-liste, pw-notiz-eintrag, …) kommen aus der
   globalen style.scss — gleiche Elemente wie bisher beim Geschäft. */
</style>
