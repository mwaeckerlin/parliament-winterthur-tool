<template>
  <div class="pw-notizen-liste">
    <div v-if="aktiveNotizen.length === 0 && geloeschteNotizen.length === 0 && !editorOffen" class="pw-hinweis">
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
        <button
          v-if="istEigeneAktion(n) && aktiveNotizId !== n.id"
          type="button"
          class="button pw-btn-mini pw-btn-loeschen"
          title="Notiz löschen"
          @click="notizLoeschen(n)"
        ><svg class="pw-btn-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path :d="mdiDelete" fill="currentColor" /></svg></button>
      </div>
      <div v-if="editorOffen && editorModus === 'edit' && aktiveNotizId === n.id" class="pw-notiz-bearbeiten-zeile">
        <PwWysiwyg
          :model-value="aktiveNotizText"
          :revisionen="aktiveNotizRevisionen"
          placeholder="Notiz bearbeiten…"
          @update:model-value="notizEingabe"
          @version-angezeigt="v => angezeigteVersion = v"
          @blur="notizAbschliessen"
        />
        <div class="pw-notiz-bearbeiten-aktionen">
          <button type="button" class="button pw-btn-mini" title="Speichern" @mousedown.prevent @click="notizBestaetigen">✓</button>
          <button type="button" class="button pw-btn-mini" title="Abbrechen" @mousedown.prevent @click="notizVerwerfen">✕</button>
        </div>
      </div>
      <div
        v-else
        class="pw-notiz-inhalt"
        :class="{ 'pw-notiz-text-klickbar': istEigeneAktion(n) }"
        :role="istEigeneAktion(n) ? 'button' : null"
        :tabindex="istEigeneAktion(n) ? 0 : null"
        :title="istEigeneAktion(n) ? 'Klicken zum Bearbeiten' : ''"
        @click="istEigeneAktion(n) && notizBearbeitenStarten(n, $event)"
        @keydown.enter.prevent="istEigeneAktion(n) && notizBearbeitenStarten(n)"
        v-html="markdownZuHtml(n.text)"
      />
    </div>

    <!-- Gelöschte Notizen: nur ein Vermerk (der Text bleibt verborgen), der Autor
         kann das Löschen rückgängig machen. Früher lebte dieser Teil in der
         Aktionszeitleiste des Geschäfts — jetzt bei beiden (Geschäft + Vorstoss)
         hier in der geteilten Notizen-Liste. -->
    <div
      v-for="n in geloeschteNotizen"
      :key="'geloescht-' + n.id"
      class="pw-notiz-eintrag pw-notiz-geloescht"
    >
      <div class="pw-notiz-kopf">
        <span class="pw-notiz-autor">{{ n.autorName || n.autorUid }}</span>
        <span class="pw-notiz-datum">{{ formatieredatum(n.erstelltAm) }} {{ formatiereUhrzeit(n.erstelltAm) }}</span>
      </div>
      <div class="pw-notiz-geloescht-zeile">
        <span class="pw-notiz-geloescht-text">{{ (n.autorName || n.autorUid || 'Jemand') }} hat seine Notiz gelöscht</span>
        <button
          v-if="istEigeneAktion(n)"
          type="button"
          class="button pw-btn-mini"
          title="Löschen rückgängig machen"
          @click="notizWiederherstellen(n)"
        >↺</button>
      </div>
    </div>

    <div v-if="editorOffen && editorModus === 'neu'" class="pw-notiz-bearbeiten-zeile">
      <PwWysiwyg
        :model-value="aktiveNotizText"
        placeholder="Kommentar, Beobachtung, Hinweis"
        @update:model-value="notizEingabe"
        @blur="notizAbschliessen"
      />
      <div class="pw-notiz-bearbeiten-aktionen">
        <button type="button" class="button pw-btn-mini" title="Speichern" @mousedown.prevent @click="notizBestaetigen">✓</button>
        <button type="button" class="button pw-btn-mini" title="Abbrechen" @mousedown.prevent @click="notizVerwerfen">✕</button>
      </div>
    </div>
    <button
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
import { mdiDelete } from '@mdi/js'
import PwWysiwyg from './PwWysiwyg.vue'

/**
 * Geteilte Notizen-Liste für Geschäfte UND Vorstösse — 100 % derselbe Code und
 * dieselbe Funktionalität an beiden Orten (eine einzige Implementierung).
 *
 * Sie kapselt die komplette Notiz-Behandlung: die Liste der Notizen, den
 * Inline-Editor mit Versions-Blättern (PwWysiwyg mit :revisionen), den
 * «+ Neue Notiz»-Knopf, den 5-Sekunden-Autosave (ohne Revision), den Abschluss
 * über Fokus-Verlust/Häkchen (mit Revision), Löschen (Soft-Delete) und die
 * gelöschten Notizen als «… hat seine Notiz gelöscht» mit Wiederherstellen
 * (nur der Autor).
 *
 * Persistiert wird über REST unter `basisUrl` (z.B. `geschaefte/123` oder
 * `vorstoesse/45`). Die Komponente hält eine lokale Arbeitskopie der Notizen
 * (aus der prop synchronisiert) und meldet nach jeder Änderung die vollständige
 * neue Liste über `geaendert` an die Elternansicht.
 */
export default {
  name: 'NotizenListe',
  components: { PwWysiwyg },
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
  },
  emits: ['geaendert'],
  data() {
    return {
      mdiDelete,
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
      aktiveNotizRevisionen: [],
      // Text der aktuell im Verlauf angezeigten älteren Fassung (null = neueste/
      // Arbeitsstand). Nur relevant fürs explizite Ok (Restore) beim Blättern.
      angezeigteVersion: null,
      notizTimer: null,
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
  beforeUnmount() {
    if (this.editorOffen) {
      // Letzten Stand beim Verlassen sichern (final ⇒ Revision wird archiviert).
      if (this.notizTimer) { clearTimeout(this.notizTimer); this.notizTimer = null }
      this.notizPersistieren(true)
    }
  },
  computed: {
    /** Aktive (nicht gelöschte) Notizen. */
    aktiveNotizen() {
      return this.arbeitsNotizen.filter(n => !n.geloescht)
    },
    /** Gelöschte Notizen — erscheinen nur als Lösch-Vermerk (mit Undo für den Autor). */
    geloeschteNotizen() {
      return this.arbeitsNotizen.filter(n => !!n.geloescht)
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
    /**
     * Öffnet einen leeren Editor für eine neue Notiz. Die Notiz wird erst in der
     * DB angelegt, wenn sie nicht leer ist (siehe notizPersistieren). Ein bereits
     * offener Editor wird vorher abgeschlossen.
     */
    async notizNeuOeffnen() {
      if (this.editorOffen) await this.notizAbschliessen()
      this.editorModus = 'neu'
      this.aktiveNotizId = null
      this.aktiveNotizText = ''
      this.aktiveNotizRevisionen = []
      this.editorOffen = true
    },
    /** Übernimmt Editor-Eingaben und stösst den verzögerten Zwischenspeicher an. */
    notizEingabe(val) {
      this.aktiveNotizText = val
      this.notizAutosave()
    },
    /** Macht das Löschen einer Notiz rückgängig — Notiz und History kommen zurück. */
    async notizWiederherstellen(a) {
      try {
        const { data } = await axios.post(
          generateUrl(`/apps/parlwin/${this.basisUrl}/notizen/${a.id}/wiederherstellen`) + this._katParam
        )
        this._lokalAktualisieren(data)
        showSuccess('Notiz wiederhergestellt')
      } catch (e) {
        showError('Notiz konnte nicht wiederhergestellt werden')
      }
    },
    /** Öffnet denselben Editor für eine bestehende Notiz (inline an ihrer Stelle). */
    async notizBearbeitenStarten(a) {
      if (this.editorOffen && this.aktiveNotizId !== a.id) await this.notizAbschliessen()
      this.editorModus = 'edit'
      this.aktiveNotizId = a.id
      this.aktiveNotizText = a.text || ''
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
    /** Verzögerter Zwischenspeicher (5 s) — legt KEINE Revision an. */
    notizAutosave() {
      if (this.notizTimer) clearTimeout(this.notizTimer)
      this.notizTimer = setTimeout(() => { this.notizTimer = null; this.notizPersistieren(false) }, 5000)
    },
    /**
     * Abschluss über echten Fokus-Verlust (Blur): speichert IMMER nur den Arbeitsstand
     * (auch während des Blätterns – nie die angezeigte alte Fassung) und räumt den
     * Editor weg. Ein leerer neuer Editor wird verworfen, ohne etwas zu erzeugen.
     */
    async notizAbschliessen() {
      if (!this.editorOffen) return
      if (this.notizTimer) { clearTimeout(this.notizTimer); this.notizTimer = null }
      await this.notizPersistieren(true)
      this.notizEditorSchliessen()
    },
    /**
     * Explizites Ok (Häkchen). Während des Blätterns hat es die Sonderfunktion
     * «Restore»: Der Arbeitsstand wird regulär abgeschlossen (erzeugt bei Änderung
     * eine Version), danach wird die angezeigte alte Fassung als Kopie als neue
     * aktuelle Version angelegt. Ausserhalb des Blätterns = normaler Abschluss.
     */
    async notizBestaetigen() {
      if (!this.editorOffen) return
      if (this.angezeigteVersion === null) {
        await this.notizAbschliessen()
        return
      }
      const alteFassung = this.angezeigteVersion
      if (this.notizTimer) { clearTimeout(this.notizTimer); this.notizTimer = null }
      // 1. Arbeitsstand final sichern (archiviert die bisherige Fassung, falls geändert).
      await this.notizPersistieren(true)
      // 2. Die angezeigte alte Fassung als Kopie als neue aktuelle Version anlegen.
      this.aktiveNotizText = alteFassung
      await this.notizPersistieren(true)
      this.notizEditorSchliessen()
    },
    /** Bricht die Bearbeitung ab und räumt den Editor weg (ohne weiteren Speicher). */
    notizVerwerfen() {
      if (this.notizTimer) { clearTimeout(this.notizTimer); this.notizTimer = null }
      this.notizEditorSchliessen()
    },
    notizEditorSchliessen() {
      this.editorOffen = false
      this.editorModus = null
      this.aktiveNotizId = null
      this.aktiveNotizText = ''
      this.aktiveNotizRevisionen = []
      this.angezeigteVersion = null
    },
    notizPersistieren(final) {
      // Serialisierung: paralleler Autosave + Abschluss laufen nacheinander, sonst
      // sähen beide aktiveNotizId=null und legten zwei Notizen an.
      this._notizKette = (this._notizKette || Promise.resolve()).then(() => this._notizPersistierenIntern(final))
      return this._notizKette
    },
    async _notizPersistierenIntern(final) {
      const text = (this.aktiveNotizText || '').trim()
      if (this.aktiveNotizId === null) {
        // Neue Notiz: erst anlegen, wenn Text vorhanden ist (leer ⇒ nichts erzeugen).
        if (!text) return
        try {
          const { data } = await axios.post(
            generateUrl(`/apps/parlwin/${this.basisUrl}/notizen`),
            { text, kategorie: this.kategorie }
          )
          this.aktiveNotizId = data.id
          this._lokalHinzufuegen(data)
          if (final) showSuccess('Notiz gespeichert')
        } catch (e) {
          showError('Fehler beim Speichern der Notiz')
        }
        return
      }
      // Bestehende (oder gerade erzeugte) Notiz aktualisieren. Leerer Text löscht
      // nicht — dafür gibt es den Lösch-Knopf; so geht kein Inhalt verloren.
      // zwischenspeichern=true beim Autosave verhindert das Anlegen einer Revision;
      // nur der finale Abschluss (Ok/Fokus-Verlust) archiviert eine Revision.
      if (!text) return
      try {
        const { data } = await axios.put(
          generateUrl(`/apps/parlwin/${this.basisUrl}/notizen/${this.aktiveNotizId}`),
          { text, zwischenspeichern: !final, kategorie: this.kategorie }
        )
        this._lokalAktualisieren(data)
        if (final) showSuccess('Notiz gespeichert')
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
/* Alle übrigen Notiz-Stile (pw-notizen-liste, pw-notiz-eintrag, …) kommen aus der
   globalen style.scss — gleiche Elemente wie bisher beim Geschäft. Eigenständig
   ist nur der Lösch-Vermerk einer gelöschten Notiz. */
.pw-notiz-geloescht-zeile {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}
.pw-notiz-geloescht-text {
  color: var(--pw-muted);
  font-style: italic;
}
</style>
