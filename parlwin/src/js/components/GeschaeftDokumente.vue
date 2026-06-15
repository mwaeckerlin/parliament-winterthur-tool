<template>
  <div class="pw-dokumente">
    <div class="pw-dokumente-kopf">
      <h4>Dokumente</h4>
      <small class="pw-hinweis">Pfad: <code>Fraktion/20_Geschäfte/{{ jahr }}/{{ geschaeftNummer }}-*</code></small>
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
      </li>
    </ul>

    <div v-if="geschaeftNummer" class="pw-dokument-aktionen">
      <NcActions v-model:open="menuOffen" :menu-name="'+ Neues Dokument'" type="primary">
        <NcActionButton
          v-for="t in vorlagen"
          :key="t.label + t.extension"
          @click="vorlageGewaehlt(t)"
        >
          {{ t.label }}
        </NcActionButton>
      </NcActions>
      <button type="button" class="button" @click="uploadKlick">⤒ Hochladen</button>
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
            <button type="button" class="button pw-btn-schliessen" @click="dialogSchliessen">✕</button>
          </div>
          <div class="pw-modal-body">
            <label>
              Dateiname (ohne Präfix und Endung)
              <div class="pw-dokument-name-vorschau">
                <span class="pw-dokument-praefix">{{ geschaeftNummer }}-</span>
                <input v-model="neuerName" type="text" class="pw-input" placeholder="z. B. Überweisung Rede" @keyup.enter="dokumentErstellen" />
                <span class="pw-dokument-suffix">.{{ aktiveVorlage?.extension }}</span>
              </div>
            </label>
            <small class="pw-hinweis">Leerzeichen werden zu Unterstrichen.</small>
          </div>
          <div class="pw-modal-footer">
            <button type="button" class="button" @click="dialogSchliessen">Abbrechen</button>
            <button
              type="button"
              class="button primary"
              :disabled="!neuerName.trim() || laeuft"
              @click="dokumentErstellen"
            >Erstellen</button>
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
    geschaeftId: { type: Number, required: true },
    geschaeftNummer: { type: String, required: true },
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
    jahr() {
      const m = (this.geschaeftNummer || '').match(/^(\d{4})\./)
      return m ? m[1] : ''
    },
  },
  watch: {
    geschaeftId() { this.laden_() },
    geschaeftNummer() { this.laden_() },
  },
  mounted() { this.laden_() },
  methods: {
    async laden_() {
      if (!this.geschaeftId || !this.geschaeftNummer) {
        this.dokumente = []
        return
      }
      this.laden = true
      try {
        const { data } = await axios.get(generateUrl(`/apps/parlwin/geschaefte/${this.geschaeftId}/dokumente`))
        this.dokumente = Array.isArray(data) ? data : []
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
      return generateUrl(`/apps/files/ajax/download.php?dir=${encodeURIComponent('/' + d.pfad.replace(/\/[^/]+$/, ''))}&files=${encodeURIComponent(d.name)}`)
    },
    vorlageGewaehlt(t) {
      // NcActions schliesst sich auf NcActionButton-Klicks nicht immer
      // automatisch – explizit zu, damit das Templates-Menü nach der Auswahl
      // verschwindet und der Dialog frei steht.
      this.menuOffen = false
      this.aktiveVorlage = t
      this.neuerName = ''
      this.dialogOffen = true
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
          generateUrl(`/apps/parlwin/geschaefte/${this.geschaeftId}/dokumente/upload`),
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
          generateUrl(`/apps/parlwin/geschaefte/${this.geschaeftId}/dokumente`),
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
