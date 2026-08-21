<template>
  <div class="pw-dokumente">
    <div class="pw-dokumente-kopf">
      <h4>Dokumente</h4>
      <small class="pw-hinweis">Pfad: <code>{{ ordnerHinweis || ('Fraktion/20_Geschäfte/' + jahr + '/' + praefixWert + '-*') }}</code></small>
    </div>

    <div v-if="laden" class="pw-laden">Lädt…</div>
    <div v-else-if="!dokumente.length" class="pw-hinweis">
      Noch keine Dokumente vorhanden.
    </div>
    <ul v-else class="pw-dokumente-liste">
      <li v-for="d in dokumente" :key="d.fileId" class="pw-dokument-eintrag">
        <a :href="dateiUrl(d)" target="_blank" rel="noopener">
          <span class="pw-dokument-name">{{ d.name }}</span>
        </a>
        <a :href="downloadUrl(d)" class="button pw-btn-mini" :download="d.name" title="Herunterladen">⤓</a>
        <button v-if="d.verknuepft" type="button" class="button pw-btn-mini" title="Verknüpfung lösen" @click="linkLoesen(d)">✕</button>
      </li>
    </ul>

    <div v-if="bereit || hatLinks" class="pw-dokument-aktionen">
      <NcActions v-if="bereit" v-model:open="menuOffen" :menu-name="'+ Neues Dokument'" type="primary">
        <NcActionButton
          v-for="t in vorlagen"
          :key="t.label + t.extension"
          @click="vorlageGewaehlt(t)"
        >
          {{ t.label }}
        </NcActionButton>
      </NcActions>
      <button v-if="bereit" type="button" class="button" @click="uploadKlick">⤒ Hochladen</button>
      <button v-if="hatLinks" type="button" class="button" @click="verknuepfenKlick">🔗 Verknüpfen</button>
      <input
        ref="uploadInput"
        type="file"
        class="pw-upload-input"
        @change="uploadDatei"
      />
    </div>

    <Teleport to="body">
      <div v-if="dialogOffen" class="pw-modal-overlay" @click.self="dialogSchliessen">
        <div class="pw-modal pw-modal-dokument">
          <div class="pw-modal-kopf">
            <h3>Neues Dokument: {{ aktiveVorlage?.label }}</h3>
            <button type="button" class="button pw-btn-schliessen" @click.stop="dialogSchliessen">✕</button>
          </div>
          <div class="pw-modal-body">
            <label>
              {{ praefixWert ? 'Dateiname (ohne Präfix und Endung)' : 'Dateiname (ohne Endung)' }}
              <div class="pw-dokument-name-vorschau">
                <span v-if="praefixWert" class="pw-dokument-praefix">{{ praefixWert }}-</span>
                <input ref="nameInput" v-model="neuerName" type="text" class="pw-input" placeholder="z. B. Überweisung Rede" @keyup.enter="dokumentErstellen" />
                <span class="pw-dokument-suffix">.{{ aktiveVorlage?.extension }}</span>
              </div>
            </label>
            <small class="pw-hinweis">Leerzeichen werden zu Unterstrichen.</small>
          </div>
          <div class="pw-modal-footer">
            <button
              type="button"
              class="button primary"
              :disabled="!neuerName.trim() || laeuft"
              @click="dokumentErstellen"
            >Erstellen</button>
            <button type="button" class="button" @click="dialogOffen = false">Abbrechen</button>
          </div>
        </div>
      </div>
    </Teleport>

    <div v-if="meldung" class="pw-meldung">{{ meldung }}</div>
  </div>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { getFilePickerBuilder } from '@nextcloud/dialogs'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'

// Standard-Vorlagentypen analog Files-App "+ Neu".
const STANDARD_VORLAGEN = [
  { label: 'Word-Dokument (docx)', extension: 'docx' },
  { label: 'Excel-Tabelle (xlsx)', extension: 'xlsx' },
  { label: 'PowerPoint (pptx)', extension: 'pptx' },
  { label: 'OpenDocument-Text (odt)', extension: 'odt' },
  { label: 'OpenDocument-Tabelle (ods)', extension: 'ods' },
  { label: 'OpenDocument-Präsentation (odp)', extension: 'odp' },
  { label: 'Markdown (md)', extension: 'md' },
  { label: 'Textdatei (txt)', extension: 'txt' },
]

export default {
  name: 'GeschaeftDokumente',
  components: { NcActions, NcActionButton },
  props: {
    // Geschäfts-Modus (abwärtskompatibel)
    geschaeftId: { type: Number, default: 0 },
    geschaeftNummer: { type: String, default: '' },
    // Generischer Modus (z.B. für Sitzungen): API-Basis + Datei-Präfix + Hinweistext
    apiBasis: { type: String, default: '' },
    praefix: { type: String, default: '' },
    jahrText: { type: String, default: '' },
    ordnerHinweis: { type: String, default: '' },
    // Vorschlag für den Dateinamen (Geschäfts-/Vorstoss-Titel); wird beim
    // Erstellen normalisiert vorbelegt.
    titel: { type: String, default: '' },
    // Explizite Verknüpfung bestehender Dateien (überall gleich): Objekttyp
    // (vorstoss|geschaeft|sitzung) + Objekt-ID. Nur gesetzt → «Verknüpfen»-Knopf.
    objektTyp: { type: String, default: '' },
    objektId: { type: Number, default: 0 },
    // Startordner des Filepickers (Default: aktuelles Jahr, aber frei navigierbar).
    startPfad: { type: String, default: '' },
  },
  data() {
    return {
      dokumente: [],
      laden: false,
      vorlagen: STANDARD_VORLAGEN,
      menuOffen: false,
      dialogOffen: false,
      aktiveVorlage: null,
      neuerName: '',
      laeuft: false,
      meldung: '',
      uploadLaeuft: false,
    }
  },
  computed: {
    // API-Basis: generischer apiBasis-Prop hat Vorrang, sonst Geschäfts-URL.
    basis() {
      return this.apiBasis || `/apps/parlwin/geschaefte/${this.geschaeftId}`
    },
    // Datei-Präfix (vor «-name.ext»): generischer praefix-Prop oder Geschäftsnummer.
    praefixWert() {
      return this.praefix || this.geschaeftNummer
    },
    jahr() {
      if (this.jahrText) return this.jahrText
      const m = (this.geschaeftNummer || '').match(/^(\d{4})\./)
      return m ? m[1] : ''
    },
    bereit() {
      // apiBasis-Modus (Vorstoss/Sitzung): der Ablageordner ergibt sich aus der
      // Basis-URL; ein Datei-Präfix ist NICHT mehr nötig (jahr-basierte Ablage).
      return !!(this.apiBasis ? this.apiBasis : (this.geschaeftId && this.geschaeftNummer))
    },
    // Titel als Dateiname-Vorschlag: Leerzeichen und Pfadtrenner → «_».
    standardName() {
      return (this.titel || '').trim().replace(/[ /\\]+/g, '_')
    },
    // «Verknüpfen» steht bereit, sobald Objekttyp und -ID gesetzt sind.
    hatLinks() {
      return !!(this.objektTyp && this.objektId)
    },
  },
  watch: {
    geschaeftId() { this.laden_() },
    geschaeftNummer() { this.laden_() },
    apiBasis() { this.laden_() },
    praefix() { this.laden_() },
    objektTyp() { this.laden_() },
    objektId() { this.laden_() },
  },
  mounted() { this.laden_() },
  methods: {
    async laden_() {
      if (!this.bereit && !this.hatLinks) {
        this.dokumente = []
        return
      }
      this.laden = true
      try {
        const eintraege = []
        const gesehen = new Set()
        if (this.bereit) {
          const { data } = await axios.get(generateUrl(`${this.basis}/dokumente`))
          ;(Array.isArray(data) ? data : []).forEach(d => { eintraege.push(d); gesehen.add(d.fileId) })
        }
        if (this.hatLinks) {
          const { data } = await axios.get(generateUrl(`/apps/parlwin/dokument-links/${this.objektTyp}/${this.objektId}`))
          ;(Array.isArray(data) ? data : []).forEach(d => {
            if (!gesehen.has(d.fileId)) { eintraege.push({ ...d, verknuepft: true }); gesehen.add(d.fileId) }
          })
        }
        this.dokumente = eintraege
      } catch (e) {
        console.error('parlwin: Dokumente laden fehlgeschlagen', e)
        this.dokumente = []
      } finally {
        this.laden = false
      }
    },
    dateiUrl(d) {
      // Öffnen via Files-App-Route (lädt Datei im Default-Viewer/Editor).
      return generateUrl(`/f/${d.fileId}`)
    },
    downloadUrl(d) {
      // Direkter Download via WebDAV-kompatiblem Files-Endpunkt.
      const pfad = String(d.pfad || '')
      return generateUrl(`/apps/files/ajax/download.php?dir=${encodeURIComponent('/' + pfad.replace(/\/[^/]+$/, ''))}&files=${encodeURIComponent(d.name)}`)
    },
    vorlageGewaehlt(t) {
      // NcActions schliesst sich auf NcActionButton-Klicks nicht immer
      // automatisch – explizit zu, damit das Templates-Menü nach der Auswahl
      // verschwindet und der Dialog frei steht.
      this.menuOffen = false
      this.aktiveVorlage = t
      this.neuerName = this.standardName
      this.dialogOffen = true
      // Fokus + Cursor ans Ende, damit der Nutzer den vorbelegten Titel nur
      // noch ergänzen muss (z.B. «-rede»).
      this.$nextTick(() => {
        const el = this.$refs.nameInput
        if (el) {
          el.focus()
          const len = el.value.length
          el.setSelectionRange(len, len)
        }
      })
    },
    dialogSchliessen() {
      this.dialogOffen = false
      this.aktiveVorlage = null
      this.neuerName = ''
    },
    uploadKlick() {
      this.$refs.uploadInput.value = ''
      this.$refs.uploadInput.click()
    },
    async uploadDatei(event) {
      const datei = event.target.files?.[0]
      if (!datei) return
      this.uploadLaeuft = true
      this.meldung = ''
      const form = new FormData()
      form.append('datei', datei)
      try {
        await axios.post(
          generateUrl(`${this.basis}/dokumente/upload`),
          form,
          { headers: { 'Content-Type': 'multipart/form-data' } }
        )
        this.meldung = 'Datei hochgeladen'
        setTimeout(() => { this.meldung = '' }, 2500)
        await this.laden_()
      } catch (e) {
        console.error('parlwin: Upload fehlgeschlagen', e)
        this.meldung = 'Upload fehlgeschlagen: ' + (e?.response?.data?.fehler || e.message)
      } finally {
        this.uploadLaeuft = false
      }
    },
    async dokumentErstellen() {
      const name = (this.neuerName || '').trim()
      if (!name || !this.aktiveVorlage) return
      this.laeuft = true
      // Tab synchron im Click-Kontext öffnen, damit Browser-Popup-Blocker
      // den window.open() spaeter nicht abfaengt. URL setzen wir, sobald
      // die fileId vom Server zurückkommt; bis dahin läuft der neue Tab im
      // „about:blank“-Zustand und die Collabora-/Office-Lade­zeit beginnt
      // direkt nach dem Server-Roundtrip (vermeidet die zusätzlichen
      // dutzend Sekunden, die ein nachgeschalteter manueller Klick kostet).
      let neuerTab = null
      try { neuerTab = window.open('about:blank', '_blank') } catch (e) { neuerTab = null }
      try {
        const { data } = await axios.post(
          generateUrl(`${this.basis}/dokumente`),
          { name, extension: this.aktiveVorlage.extension }
        )
        const fileId = data && data.fileId
        if (fileId && neuerTab) {
          // Direkt zur Files-Route mit fileid-Hash navigieren – öffnet den
          // Default-Viewer (Collabora für Office-Dateien, Text-Editor sonst).
          neuerTab.location.href = generateUrl(`/f/${fileId}`)
        } else if (neuerTab) {
          neuerTab.close()
        }
        this.dialogSchliessen()
        this.meldung = 'Dokument erstellt'
        setTimeout(() => { this.meldung = '' }, 2500)
        await this.laden_()
      } catch (e) {
        if (neuerTab) {
          try { neuerTab.close() } catch (_e) { /* ignore */ }
        }
        console.error('parlwin: Dokument erstellen fehlgeschlagen', e)
        this.meldung = 'Fehler: ' + (e?.response?.data?.fehler || e.message)
      } finally {
        this.laeuft = false
      }
    },
    // «Verknüpfen»: NC-Standard-Filepicker (öffnet im Startordner, frei
    // navigierbar); die gewählte Datei wird unabhängig vom Namen verknüpft.
    async verknuepfenKlick() {
      let pfad = ''
      try {
        const picker = getFilePickerBuilder('Bestehende Datei verknüpfen')
          .setMultiSelect(false)
          .allowDirectories(false)
          .startAt(this.startPfad || '/')
          .build()
        pfad = await picker.pick()
      } catch (e) {
        return // im Filepicker abgebrochen
      }
      if (!pfad) return
      try {
        await axios.post(generateUrl(`/apps/parlwin/dokument-links/${this.objektTyp}/${this.objektId}`), { pfad })
        this.meldung = 'Datei verknüpft'
        setTimeout(() => { this.meldung = '' }, 2500)
        await this.laden_()
      } catch (e) {
        this.meldung = 'Verknüpfen fehlgeschlagen: ' + (e?.response?.data?.fehler || e?.message || '')
      }
    },
    async linkLoesen(d) {
      try {
        await axios.delete(generateUrl(`/apps/parlwin/dokument-links/${this.objektTyp}/${this.objektId}/${d.fileId}`))
        await this.laden_()
      } catch (e) {
        this.meldung = 'Lösen fehlgeschlagen: ' + (e?.response?.data?.fehler || e?.message || '')
      }
    },
  },
}
</script>

<style scoped>
.pw-dokumente { display: flex; flex-direction: column; gap: 0.5rem; }
.pw-dokumente-kopf { display: flex; flex-direction: column; gap: 0.2rem; }
.pw-dokumente-liste { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 0.25rem; }
.pw-dokument-eintrag { display: flex; align-items: center; gap: 0.5rem; }
.pw-dokument-eintrag a { text-decoration: none; }
.pw-dokument-name-vorschau { display: flex; align-items: center; gap: 0.25rem; }
.pw-dokument-praefix, .pw-dokument-suffix { color: var(--pw-muted, #888); font-family: monospace; }
.pw-dokument-aktionen { display: flex; gap: 0.5rem; flex-wrap: wrap; margin-top: 0.4rem; }
.pw-upload-input { display: none; }
</style>
