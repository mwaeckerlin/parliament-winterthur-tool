<template>
  <Teleport v-if="filterReady" to="#pw-search-slot">
    <NcTextField
      v-model="suche"
      label="Suche"
      placeholder="Titel, Art oder Zuständigkeit"
      trailing-button-icon="close"
      :show-trailing-button="!!suche"
      @trailing-button-click="suche = ''"
    />
  </Teleport>
  <Teleport v-if="filterReady" to="#pw-filter-slot">
    <FilterPanel @reset="filterZuruecksetzen">
      <NcSelect v-model="herkunftOption" :options="herkunftOptionen" :clearable="false" input-label="Herkunft" />
      <NcSelect v-model="statusOption" :options="statusOptionen" :clearable="false" input-label="Status" />
    </FilterPanel>
  </Teleport>

  <section class="pw-view-content pw-vorstoesse">
    <header class="pw-view-header">
      <h2 class="pw-view-title">Vorstösse</h2>
      <span class="pw-view-count">{{ gefiltert.length }}</span>
      <NcButton type="primary" @click="neuerVorstoss">+ Neuer Vorstoss</NcButton>
    </header>

    <div v-if="laden" class="pw-laden"><NcLoadingIcon :size="32" /></div>

    <template v-else>
      <NcEmptyContent v-if="!gefiltert.length" name="Keine Vorstösse vorhanden" />
      <!-- F119: Die Übersicht ist gebaut wie die der Geschäfte — breit eine
           Tabelle, schmal Karten. In der Zeile stehen die Angaben, nach denen
           man sucht und entscheidet; alles Weitere zeigt die geöffnete Maske. -->
      <div v-else class="pw-table-wrap pw-table-desktop">
        <!-- Dieselbe Tabelle wie bei den Geschäften und darum auch deren Klasse:
             Spaltenbreiten, Zeilenhöhen und die eingebetteten Auswahllisten sind
             an EINER Stelle definiert. «pw-tabelle-vorstoesse» benennt nur diese
             Ausprägung, für Tests und allfällige Eigenheiten. -->
        <table class="pw-tabelle pw-tabelle-geschaefte pw-tabelle-vorstoesse" lang="de">
          <thead>
            <tr>
              <th class="pw-col-nr">Art</th>
              <th class="pw-col-titel">Titel</th>
              <th class="pw-col-prio">Prio</th>
              <th class="pw-col-status">Status</th>
              <th class="pw-col-zustaendig">Zuständig</th>
              <th class="pw-col-beschluss">Beschluss</th>
              <!-- Ohne sichtbare Beschriftung: die Spalte trägt nur den
                   Löschknopf, der seine Benennung für Hilfstechnologien selbst
                   mitbringt. -->
              <th class="pw-col-loeschen" aria-label="Löschen"></th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="vorstoss in gefiltert"
              :key="vorstoss.id"
              :class="['pw-table-row-clickable', { 'pw-prio-hoch': prioritaetEffektiv(vorstoss) === 'hoch', 'pw-prio-tief': prioritaetEffektiv(vorstoss) === 'tief' }]"
              tabindex="0"
              role="button"
              :aria-label="`Vorstoss ${vorstoss.titel || 'ohne Titel'} bearbeiten`"
              @click="zeilenKlick(vorstoss, $event)"
              @keydown.enter.prevent="bearbeiten(vorstoss)"
              @keydown.space.prevent="bearbeiten(vorstoss)"
            >
              <td data-label="Art" class="pw-col-nr">
                <strong>{{ vorstoss.art || '—' }}</strong>
                <span class="pw-col-nr-typ">{{ herkunftLabel(vorstoss.herkunft) }}</span>
                <span class="pw-col-nr-datum">{{ formatieredatumKurz(vorstoss.erstelltAm) }}</span>
              </td>
              <td class="pw-titel pw-col-titel" data-label="Titel">
                {{ vorstoss.titel || 'Ohne Titel' }}
                <span v-if="vorstoss.herkunft === 'fremde' && vorstoss.herkunftFraktion" class="pw-col-einreicher pw-col-herkunftsfraktion">{{ kuerze(vorstoss.herkunftFraktion) }}</span>
              </td>
              <td data-label="Prio" class="pw-col-inline-edit pw-col-prio" @click.stop>
                <PwPrioritaetSelect
                  class="pw-inline-select"
                  :model-value="vorstoss.prioritaet"
                  @update:model-value="feldDirektSpeichern(vorstoss, 'prioritaet', $event)"
                />
              </td>
              <td data-label="Status" class="pw-col-status">
                <span :class="['pw-status-' + vorstossStatusKlasse(vorstoss.status), 'pw-status-text']">{{ statusLabel(vorstoss.status) }}</span>
              </td>
              <td data-label="Zuständig" class="pw-col-inline-edit pw-col-zustaendig" @click.stop>
                <PwMultiSelect
                  class="pw-inline-select"
                  :model-value="zustaendigOptionenFuer(vorstoss)"
                  :options="mitgliederOptionen"
                  :clearable="true"
                  placeholder="—"
                  label="label"
                  @update:model-value="zustaendigkeitInZeile(vorstoss, $event || [])"
                />
              </td>
              <td data-label="Beschluss" class="pw-col-inline-edit pw-col-beschluss" @click.stop>
                <BeschlussWidget
                  class="pw-inline-beschluss"
                  :model-value="beschlussWertFuer(vorstoss)"
                  :options="beschlussOptionen"
                  placeholder="—"
                  @update:model-value="feldDirektSpeichern(vorstoss, 'beschluss', $event ? $event.label : '')"
                />
              </td>
              <td data-label="Löschen" class="pw-col-loeschen" @click.stop>
                <PwLoeschen label="Vorstoss löschen" @click="loeschen(vorstoss)" />
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="!laden" class="pw-card-grid pw-card-mobile">
        <article
          v-for="vorstoss in gefiltert"
          :key="`card-${vorstoss.id}`"
          class="pw-data-card"
          :class="{ 'pw-prio-hoch': prioritaetEffektiv(vorstoss) === 'hoch', 'pw-prio-tief': prioritaetEffektiv(vorstoss) === 'tief' }"
          tabindex="0"
          role="button"
          :aria-label="`Vorstoss ${vorstoss.titel || 'ohne Titel'} bearbeiten`"
          @click="bearbeiten(vorstoss)"
          @keydown.enter.prevent="bearbeiten(vorstoss)"
          @keydown.space.prevent="bearbeiten(vorstoss)"
        >
          <div class="pw-data-card-header">
            <div>
              <p class="pw-data-card-kicker">{{ kartenKicker(vorstoss) }}</p>
              <h3>{{ vorstoss.titel || 'Ohne Titel' }}</h3>
            </div>
            <span :class="'pw-status-' + vorstossStatusKlasse(vorstoss.status)">{{ statusLabel(vorstoss.status) }}</span>
          </div>
          <div class="pw-data-card-grid">
            <div class="pw-data-pair">
              <span>Zuständigkeit</span>
              <strong>{{ zustaendigkeitText(vorstoss) || '—' }}</strong>
            </div>
            <div v-if="vorstoss.herkunft === 'fremde'" class="pw-data-pair">
              <span>Herkunftsfraktion</span>
              <strong>{{ kuerze(vorstoss.herkunftFraktion) || '—' }}</strong>
            </div>
            <div v-if="vorstoss.herkunft === 'fremde' && vorstoss.beschluss" class="pw-data-pair">
              <span>Beschluss</span>
              <strong>{{ vorstoss.beschluss }}</strong>
            </div>
          </div>
          <div class="pw-data-card-aktionen">
            <PwLoeschen label="Vorstoss löschen" @click="loeschen(vorstoss)" />
          </div>
        </article>
      </div>
    </template>

    <!-- EIN Formular für Erstellen UND Bearbeiten (geteilter Code). Beim
         Bearbeiten speichert jede Eingabe sofort — darum keine Knöpfe, ✕
         schliesst nur. Beim Erstellen sammelt die Maske die Eingaben und legt
         erst «Speichern» sie an; verworfen wird nur über «Abbrechen». Ein Klick
         neben die Maske tut beim Erstellen nichts, damit keine Eingabe
         ungewollt verloren geht. Notizen, Dokumente und Zeitleiste brauchen
         eine bestehende ID und erscheinen deshalb erst nach dem Speichern. -->
    <Teleport to="body">
    <div v-if="bearbeitung" class="pw-modal-overlay" @click.self="overlayKlick">
      <div class="pw-modal">
        <div class="pw-modal-kopf">
          <h3>{{ istEntwurf ? 'Neuer Vorstoss' : 'Vorstoss bearbeiten' }}</h3>
          <button v-if="!istEntwurf" type="button" class="button pw-btn-schliessen" aria-label="Dialog schliessen" @click.stop="schliessen">✕</button>
        </div>
        <div class="pw-modal-body">
          <PwField label="Titel *">
            <input v-model="bearbeitung.titel" type="text" class="pw-input" placeholder="Titel des Vorstosses" @change="feldSpeichern" @keyup.enter="feldSpeichern" />
          </PwField>
          <PwField label="Art">
            <BeschlussWidget
              :model-value="artWert"
              :options="artOptionen"
              placeholder="Motion, Postulat, Interpellation …"
              @update:model-value="artGewaehlt"
            />
          </PwField>
          <div class="pw-von-bis">
            <PwField label="Herkunft">
              <NcSelect :model-value="herkunftWahl" :options="herkunftWahlOptionen" :clearable="false" @update:model-value="herkunftGewaehlt" />
            </PwField>
            <PwField label="Status">
              <NcSelect :model-value="statusWahl" :options="statusWahlOptionen" :clearable="false" @update:model-value="statusGewaehlt" />
            </PwField>
            <PwField label="Priorität">
              <PwPrioritaetSelect :model-value="bearbeitung.prioritaet" @update:model-value="prioritaetGewaehlt" />
            </PwField>
          </div>
          <PwField label="Zuständigkeit">
            <PwMultiSelect
              :model-value="zustaendigkeitWert"
              :options="mitgliederOptionen"
              :clearable="true"
              placeholder="Personen wählen"
              @update:model-value="zustaendigkeitGewaehlt"
            />
          </PwField>

          <template v-if="bearbeitung.herkunft === 'fremde'">
            <PwField label="Beschluss (Haltung zum fremden Vorstoss)">
              <BeschlussWidget
                :model-value="fremdBeschlussWert"
                :options="fremdBeschlussOptionen"
                placeholder="Miteinreichen, Unterstützen, Ablehnen …"
                @update:model-value="fremdBeschlussGewaehlt"
              />
            </PwField>
            <div class="pw-von-bis">
              <PwField label="Herkunft (fremde Fraktion)">
                <NcSelect
                  :model-value="herkunftFraktionOption"
                  :options="fremdeFraktionsOptionen"
                  :clearable="true"
                  placeholder="Fraktion wählen"
                  @update:model-value="herkunftFraktionGewaehlt"
                />
              </PwField>
              <PwField label="Ansprechpartner">
                <PwMultiSelect
                  :model-value="ansprechpartnerWert"
                  :options="ansprechpartnerOptionen"
                  :clearable="true"
                  :disabled="!bearbeitung.herkunftFraktion"
                  placeholder="Mitglieder der Fraktion"
                  @update:model-value="ansprechpartnerGewaehlt"
                />
              </PwField>
            </div>
          </template>

          <PwField label="Inhalt">
            <PwWysiwyg
              :model-value="bearbeitung.inhalt"
              placeholder="Inhalt des Vorstosses"
              @update:model-value="v => bearbeitung.inhalt = v"
              @blur="inhaltAbschliessen"
            />
          </PwField>
          <!-- Diese Bereiche hängen an einer bestehenden ID und stehen deshalb
               erst nach dem Speichern zur Verfügung. -->
          <template v-if="!istEntwurf">
            <PwField label="Dokument">
              <GeschaeftDokumente
                :api-basis="`/apps/parlwin/vorstoesse/${bearbeitung.id}`"
                :ordner-hinweis="dokumentOrdnerHinweis"
                :titel="bearbeitung.titel"
                objekt-typ="vorstoss"
                :objekt-id="bearbeitung.id"
                :start-pfad="dokumentStartPfad"
              />
            </PwField>
            <PwField label="Notizen">
              <NotizenListe
                ref="notizenListe"
                :basis-url="'vorstoesse/' + bearbeitung.id"
                :notizen="bearbeitung.aktionen || []"
                :aktuelle-uid="aktuelleUid"
                @geaendert="v => bearbeitung.aktionen = v"
              />
            </PwField>
            <PwField label="Aktionszeitleiste">
              <Aktionszeitleiste
                :aktionen="bearbeitung.aktionen || []"
                :basis-url="'vorstoesse/' + bearbeitung.id"
                :aktuelle-uid="aktuelleUid"
                @notiz-wiederhergestellt="onNotizWiederhergestellt"
              />
            </PwField>
            <PwField label="Geschäft">
              <div v-if="bearbeitung.geschaeftId" class="pw-hinweis">Mit einem Geschäft verknüpft und abgeschlossen.</div>
              <NcButton v-else type="secondary" @click="verknuepfenOeffnen">Mit Geschäft verknüpfen</NcButton>
            </PwField>
          </template>
          <small v-else class="pw-hinweis">
            Dokumente, Notizen und der Verlauf stehen bereit, sobald der Vorstoss gespeichert ist.
          </small>
        </div>
        <div v-if="istEntwurf" class="pw-modal-footer">
          <NcButton type="primary" :disabled="!bearbeitung.titel.trim() || erstellenLaeuft" @click="speichern">Speichern</NcButton>
          <NcButton @click="abbrechen">Abbrechen</NcButton>
        </div>
      </div>
    </div>
    </Teleport>

    <!-- Verknüpfung mit einem Geschäft (geteilter Dialog) -->
    <GeschaeftVerknuepfenDialog
      v-if="verknuepfenDialog"
      :titel="bearbeitung?.titel || ''"
      @verknuepfen="verknuepfen"
      @schliessen="verknuepfenSchliessen"
    />
  </section>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { showError, showSuccess } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/style.css'
import { getCurrentUser } from '@nextcloud/auth'
import { vollerName, personKey, kuerze } from '../utils'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import FilterPanel from './FilterPanel.vue'
import PwField from './PwField.vue'
import BeschlussWidget from './BeschlussWidget.vue'
import PwMultiSelect from './PwMultiSelect.vue'
import PwPrioritaetSelect from './PwPrioritaetSelect.vue'
import PwWysiwyg from './PwWysiwyg.vue'
import GeschaeftDokumente from './GeschaeftDokumente.vue'
import NotizenListe from './NotizenListe.vue'
import Aktionszeitleiste from './Aktionszeitleiste.vue'
import GeschaeftVerknuepfenDialog from './GeschaeftVerknuepfenDialog.vue'
import PwLoeschen from './PwLoeschen.vue'

const HERKUENFTE = [
  { code: 'eigene', label: 'Eigene' },
  { code: 'fremde', label: 'Fremde' },
]
const STATUS = [
  { code: 'neu', label: 'Neu' },
  { code: 'entwurf', label: 'Entwurf' },
  { code: 'bereit', label: 'Bereit' },
  { code: 'eingereicht', label: 'Eingereicht' },
  { code: 'erledigt', label: 'Erledigt' },
  { code: 'pausiert', label: 'Pausiert' },
]
// Bekannte Vorstoss-Arten; im BeschlussWidget frei überschreibbar.
const VORSTOSS_ARTEN = [
  'Motion', 'Postulat', 'Interpellation', 'Schriftliche Anfrage',
  'Beschlussantrag',
  'Dringliche Motion', 'Dringliches Postulat', 'Budgetmotion',
  'Fragestunde', 'Einzelinitiative', 'Parlamentarische Initiative',
]
// Haltung zu einem fremden Vorstoss; im BeschlussWidget frei überschreibbar.
const FREMD_BESCHLUESSE = [
  'Miteinreichen', 'Unterstützen', 'Stimmfreigabe', 'Ablehnen', 'Ablehnungsantrag stellen',
]

export default {
  name: 'Vorstoesseliste',
  components: { NcTextField, NcButton, NcSelect, NcLoadingIcon, NcEmptyContent, FilterPanel, PwField, BeschlussWidget, PwMultiSelect, PwPrioritaetSelect, PwWysiwyg, GeschaeftDokumente, NotizenListe, Aktionszeitleiste, GeschaeftVerknuepfenDialog, PwLoeschen },
  props: {
    mitglieder: { type: Array, default: () => [] },
    fraktionen: { type: Array, default: () => [] },
  },
  data() {
    // Das laufende Speichern ist kein Anzeigezustand und steht deshalb ausserhalb
    // der Reaktivität; Schliessen und Wechseln warten darauf.
    this.laufendesSpeichern = null
    return {
      vorstoesse: [],
      laden: true,
      filterReady: false,
      suche: '',
      herkunftOption: { label: 'Alle', value: '' },
      statusOption: { label: 'Alle', value: '' },
      bearbeitung: null,
      // Ein frisch angelegter, noch nicht benannter Vorstoss: der Dialog heisst
      // dann «Neuer Vorstoss» und der Entwurf wird beim Schliessen verworfen,
      // falls kein Titel eingegeben wurde.
      istEntwurf: false,
      erstellenLaeuft: false,
      eigeneFraktion: String(window.PARLWIN_CONFIG?.fraktion || ''),
      verknuepfenDialog: false,
      HERKUENFTE,
      STATUS,
    }
  },
  computed: {
    // UID des angemeldeten Nutzers — für «nur der Autor darf» in der Notizen-Liste.
    aktuelleUid() {
      return getCurrentUser()?.uid || ''
    },
    herkunftOptionen() {
      // Grundprinzip: nur die Herkünfte anbieten, die in den Vorstössen wirklich
      // vorkommen (feste Reihenfolge aus HERKUENFTE bleibt erhalten). «Alle» ist
      // kein Datenwert, sondern «kein Filter».
      const vorhanden = new Set(this.vorstoesse.map(v => v.herkunft).filter(Boolean))
      return [{ label: 'Alle', value: '' }, ...HERKUENFTE.filter(h => vorhanden.has(h.code)).map(h => ({ label: h.label, value: h.code }))]
    },
    statusOptionen() {
      // Grundprinzip: nur die Status anbieten, die in den Vorstössen wirklich vorkommen.
      const vorhanden = new Set(this.vorstoesse.map(v => v.status).filter(Boolean))
      return [{ label: 'Alle', value: '' }, ...STATUS.filter(s => vorhanden.has(s.code)).map(s => ({ label: s.label, value: s.code }))]
    },
    herkunftWahlOptionen() {
      return HERKUENFTE.map(h => ({ label: h.label, value: h.code }))
    },
    statusWahlOptionen() {
      return STATUS.map(s => ({ label: s.label, value: s.code }))
    },
    herkunftWahl() {
      return this.herkunftWahlOptionen.find(o => o.value === this.bearbeitung?.herkunft) || null
    },
    statusWahl() {
      return this.statusWahlOptionen.find(o => o.value === this.bearbeitung?.status) || null
    },
    artOptionen() {
      return VORSTOSS_ARTEN.map(a => ({ label: a, value: a }))
    },
    artWert() {
      const art = this.bearbeitung?.art || ''
      return art ? { label: art, value: art } : null
    },
    fremdBeschlussOptionen() {
      return FREMD_BESCHLUESSE.map(b => ({ label: b, value: b }))
    },
    fremdBeschlussWert() {
      const b = this.bearbeitung?.beschluss || ''
      return b ? { label: b, value: b } : null
    },
    // Für die Tabelle der Übersicht (F119): dieselben Werte, die auch die
    // geöffnete Maske anbietet — dieselbe Auswahl an beiden Orten.
    beschlussOptionen() {
      return FREMD_BESCHLUESSE.map(b => ({ label: b, value: b }))
    },
    // Wählbare Personen: aktive Fraktionsmitglieder mit Nextcloud-Benutzer.
    mitgliederOptionen() {
      return this.mitglieder
        .filter(m => m.aktiv !== false && !!(m.nextcloudUid || m.nextcloud_uid))
        .map(m => ({ label: vollerName(m), value: personKey(m) }))
        .filter(o => !!o.label)
        .sort((a, b) => a.label.localeCompare(b.label))
    },
    zustaendigkeitWert() {
      return (this.bearbeitung?.zustaendigkeit || []).map(z =>
        this.mitgliederOptionen.find(o => o.value === z.key) || { label: z.name, value: z.key })
    },
    // Nur aktive fremde Fraktionen (ohne die eigene), mit ihrer eingetragenen
    // Bezeichnung/Abkürzung (case-insensitiv verglichen). Angezeigt wird der
    // gekürzte Name, gespeichert der volle.
    fremdeFraktionsOptionen() {
      const eigen = (this.eigeneFraktion || '').toLowerCase()
      return this.fraktionen
        .filter(f => f.aktiv !== false && (f.name || '').toLowerCase() !== eigen)
        .map(f => ({ label: kuerze(f.name), value: f.name }))
        .filter(o => !!o.label)
        .sort((a, b) => a.label.localeCompare(b.label))
    },
    herkunftFraktionOption() {
      const f = this.bearbeitung?.herkunftFraktion || ''
      return f ? (this.fremdeFraktionsOptionen.find(o => o.value === f) || { label: kuerze(f), value: f }) : null
    },
    // Nur aktive Mitglieder der gewählten fremden Fraktion.
    ansprechpartnerOptionen() {
      const fraktion = (this.bearbeitung?.herkunftFraktion || '').toLowerCase()
      if (!fraktion) return []
      return this.mitglieder
        .filter(m => m.aktiv !== false && (m.fraktion || '').toLowerCase() === fraktion)
        .map(m => ({ label: vollerName(m), value: personKey(m) }))
        .filter(o => !!o.label)
        .sort((a, b) => a.label.localeCompare(b.label))
    },
    ansprechpartnerWert() {
      return (this.bearbeitung?.ansprechpartner || []).map(a =>
        this.ansprechpartnerOptionen.find(o => o.value === a.key) || { label: a.name, value: a.key })
    },
    dokumentOrdnerHinweis() {
      if (!this.bearbeitung?.id) return ''
      const unter = this.bearbeitung.herkunft === 'fremde' ? '20_Fremde' : '10_Eigene'
      const jahr = (this.bearbeitung.erstelltAm || '').slice(0, 4) || String(new Date().getFullYear())
      return `Fraktion/40_Vorstösse/${unter}/${jahr}/*`
    },
    // Startordner des Verknüpfen-Filepickers: Jahres-Ordner (navigierbar).
    dokumentStartPfad() {
      if (!this.bearbeitung?.id) return ''
      const unter = this.bearbeitung.herkunft === 'fremde' ? '20_Fremde' : '10_Eigene'
      const jahr = (this.bearbeitung.erstelltAm || '').slice(0, 4) || String(new Date().getFullYear())
      return `Fraktion/40_Vorstösse/${unter}/${jahr}`
    },
    gefiltert() {
      const q = (this.suche || '').toLowerCase().trim()
      const herkunft = this.herkunftOption?.value || ''
      const status = this.statusOption?.value || ''
      return this.vorstoesse.filter(v => {
        if (herkunft && v.herkunft !== herkunft) return false
        if (status && v.status !== status) return false
        if (!q) return true
        return (v.titel || '').toLowerCase().includes(q) ||
          (v.art || '').toLowerCase().includes(q) ||
          this.zustaendigkeitText(v).toLowerCase().includes(q)
      })
    },
  },
  mounted() {
    this.$nextTick(() => { this.filterReady = true })
    this.lade()
  },
  methods: {
    filterZuruecksetzen() {
      this.suche = ''
      this.herkunftOption = { label: 'Alle', value: '' }
      this.statusOption = { label: 'Alle', value: '' }
    },
    vollerName,
    personKey,
    kuerze,
    verknuepfenOeffnen() {
      this.verknuepfenDialog = true
    },
    verknuepfenSchliessen() {
      this.verknuepfenDialog = false
    },
    async verknuepfen(geschaeft) {
      if (!this.bearbeitung?.id || !geschaeft?.id) return
      try {
        const { data } = await axios.post(
          generateUrl(`/apps/parlwin/vorstoesse/${this.bearbeitung.id}/verknuepfen`),
          { geschaeftId: geschaeft.id }
        )
        this.bearbeitung.geschaeftId = data.geschaeftId || geschaeft.id
        this.bearbeitung.status = data.status || 'erledigt'
        this.verknuepfenDialog = false
        await this.lade()
      } catch (e) {
        showError('Verknüpfung fehlgeschlagen: ' + (e?.response?.data?.fehler || e?.message || ''))
      }
    },
    herkunftLabel(code) {
      return (HERKUENFTE.find(h => h.code === code) || {}).label || code
    },
    statusLabel(code) {
      return (STATUS.find(s => s.code === code) || {}).label || code
    },
    zustaendigkeitText(vorstoss) {
      const liste = Array.isArray(vorstoss.zustaendigkeit) ? vorstoss.zustaendigkeit : []
      return liste.map(z => z.name).filter(Boolean).join(', ')
    },
    kartenKicker(vorstoss) {
      const teile = [this.herkunftLabel(vorstoss.herkunft)]
      if (vorstoss.art) teile.push(vorstoss.art)
      return teile.join(' · ')
    },
    vorstossStatusKlasse(status) {
      if (status === 'erledigt') return 'erledigt'
      if (status === 'neu' || status === 'entwurf') return 'offen'
      return 'neutral'
    },
    prioritaetEffektiv(vorstoss) {
      return vorstoss.prioritaet || 'mittel'
    },
    // Jede Auswahl speichert SOFORT — wie überall in der App (Konsistenz).
    prioritaetGewaehlt(wert) {
      this.bearbeitung.prioritaet = wert || ''
      this.feldSpeichern()
    },
    artGewaehlt(option) {
      this.bearbeitung.art = option ? option.label : ''
      this.feldSpeichern()
    },
    herkunftGewaehlt(option) {
      this.bearbeitung.herkunft = option ? option.value : 'eigene'
      this.feldSpeichern()
    },
    statusGewaehlt(option) {
      this.bearbeitung.status = option ? option.value : 'neu'
      this.feldSpeichern()
    },
    zustaendigkeitGewaehlt(optionen) {
      this.bearbeitung.zustaendigkeit = (optionen || []).map(o => ({ key: o.value, name: o.label }))
      this.feldSpeichern()
    },
    fremdBeschlussGewaehlt(option) {
      this.bearbeitung.beschluss = option ? option.label : ''
      this.feldSpeichern()
    },
    herkunftFraktionGewaehlt(option) {
      const neu = option ? option.value : ''
      if (neu !== this.bearbeitung.herkunftFraktion) {
        // Fraktionswechsel: die alten Ansprechpartner gehören zu einer anderen Fraktion.
        this.bearbeitung.ansprechpartner = []
      }
      this.bearbeitung.herkunftFraktion = neu
      this.feldSpeichern()
    },
    ansprechpartnerGewaehlt(optionen) {
      this.bearbeitung.ansprechpartner = (optionen || []).map(o => ({ key: o.value, name: o.label }))
      this.feldSpeichern()
    },
    inhaltAbschliessen() {
      return this.feldSpeichern()
    },
    async lade() {
      this.laden = true
      try {
        const { data } = await axios.get(generateUrl('/apps/parlwin/vorstoesse'))
        this.vorstoesse = Array.isArray(data) ? data : []
      } catch (e) {
        console.error('Fehler beim Laden der Vorstösse:', e)
      } finally {
        this.laden = false
      }
    },
    leereBearbeitung(vorstoss) {
      return {
        id: vorstoss?.id || 0,
        titel: vorstoss?.titel || '',
        art: vorstoss?.art || '',
        herkunft: vorstoss?.herkunft || 'eigene',
        status: vorstoss?.status || 'neu',
        prioritaet: vorstoss?.prioritaet || '',
        beschluss: vorstoss?.beschluss || '',
        zustaendigkeit: Array.isArray(vorstoss?.zustaendigkeit) ? vorstoss.zustaendigkeit.map(z => ({ ...z })) : [],
        herkunftFraktion: vorstoss?.herkunftFraktion || '',
        ansprechpartner: Array.isArray(vorstoss?.ansprechpartner) ? vorstoss.ansprechpartner.map(a => ({ ...a })) : [],
        inhalt: vorstoss?.inhalt || '',
        dokument: vorstoss?.dokument || '',
        // Notizen als Aktions-Liste (aktive + gelöschte) für die geteilte NotizenListe.
        aktionen: Array.isArray(vorstoss?.aktionen) ? vorstoss.aktionen.map(n => ({ ...n })) : [],
        geschaeftId: vorstoss?.geschaeftId || 0,
      }
    },
    // Der angemeldete Benutzer als Person (falls er ein Fraktionsmitglied ist).
    standardZustaendigkeit() {
      const uid = (getCurrentUser()?.uid || '').toLowerCase()
      if (!uid) return []
      const m = this.mitglieder.find(x => ((x.nextcloudUid || x.nextcloud_uid || '').toLowerCase()) === uid)
      return m ? [{ key: personKey(m), name: vollerName(m) }] : []
    },
    // «+ Neuer Vorstoss» öffnet dieselbe Maske wie das Bearbeiten (geteilter
    // Code). Es wird noch NICHTS angelegt: die Eingaben sammelt die Maske, erst
    // «Speichern» erzeugt den Vorstoss.
    neuerVorstoss() {
      this.bearbeitung = this.leereBearbeitung(null)
      // Standard-Zuständigkeit: der angemeldete Benutzer.
      this.bearbeitung.zustaendigkeit = this.standardZustaendigkeit()
      this.istEntwurf = true
    },
    // Legt den in der Maske erfassten Vorstoss an und geht unmittelbar in die
    // Bearbeitung über — dort lassen sich sofort Dokumente und Notizen ergänzen.
    async speichern() {
      const titel = (this.bearbeitung?.titel || '').trim()
      if (!titel || this.erstellenLaeuft) return
      this.erstellenLaeuft = true
      try {
        const { data } = await axios.post(generateUrl('/apps/parlwin/vorstoesse'), {
          ...this.bearbeitung,
          titel,
        })
        await this.lade()
        this.bearbeiten(data)
        showSuccess('Gespeichert')
      } catch (e) {
        showError('Vorstoss konnte nicht erstellt werden: ' + (e?.response?.data?.fehler || e?.message || ''))
      } finally {
        this.erstellenLaeuft = false
      }
    },
    // Nur der ausdrückliche Abbruch verwirft die Eingaben.
    abbrechen() {
      this.bearbeitung = null
      this.istEntwurf = false
    },
    // Ein Klick neben die Maske darf beim Erfassen nichts verwerfen; beim
    // Bearbeiten ist ohnehin alles gespeichert, dort schliesst er.
    overlayKlick() {
      if (!this.istEntwurf) this.schliessen()
    },
    bearbeiten(vorstoss) {
      // Die Maske öffnet sofort und nimmt den Stand aus der Liste: Das Speichern
      // schreibt seine Antwort dorthin zurück, und das Schliessen wartet darauf.
      const frisch = this.vorstoesse.find(v => v.id === vorstoss?.id) || vorstoss
      this.bearbeitung = this.leereBearbeitung(frisch)
      this.istEntwurf = false
    },
    async loeschen(vorstoss) {
      if (!confirm(`Vorstoss „${vorstoss.titel || 'Ohne Titel'}" wirklich löschen?`)) return
      try {
        await axios.delete(generateUrl(`/apps/parlwin/vorstoesse/${vorstoss.id}`))
        await this.lade()
      } catch (e) {
        showError('Vorstoss konnte nicht gelöscht werden: ' + (e?.response?.data?.fehler || e?.message || ''))
      }
    },
    async schliessen() {
      // Warnung, wenn im Notiz-Editor ungespeicherte Änderungen offen sind.
      if (this.$refs.notizenListe?.hatUngespeicherteAenderungen?.()) {
        // eslint-disable-next-line no-alert
        if (!window.confirm('Die Notiz ist noch nicht gespeichert. Trotzdem schliessen?')) return
      }
      // Ein Feld, das beim Verlassen gerade speichert, wird abgewartet: sonst
      // schliesst die Maske über die laufende Anfrage hinweg und die Liste behält
      // den Stand von davor.
      await this.laufendesSpeichern
      this.bearbeitung = null
      this.istEntwurf = false
    },
    /**
     * Eine in der Aktionszeitleiste wiederhergestellte Notiz zurück in die
     * Aktionsliste übernehmen (geloescht=false).
     */
    onNotizWiederhergestellt(data) {
      if (!this.bearbeitung || !data?.id) return
      const idx = (this.bearbeitung.aktionen || []).findIndex(a => a.id === data.id)
      if (idx < 0) return
      const kopie = [...this.bearbeitung.aktionen]
      kopie[idx] = data
      this.bearbeitung.aktionen = kopie
    },
    // Speichert den aktuellen Bearbeitungsstand SOFORT (bei jeder Eingabe).
    // Ein leerer Titel wird nie weggespeichert.
    /**
     * Speichert und merkt sich den laufenden Vorgang, damit das Schliessen und der
     * Wechsel auf einen anderen Vorstoss ihn abwarten können. Ohne dieses Warten
     * ging der zuletzt getippte Inhalt verloren: Der Editor speichert beim
     * Verlassen, und wer gleich darauf schliesst, bekam beim nächsten Öffnen den
     * Stand aus der Liste — den von vor dem Speichern.
     */
    feldSpeichern() {
      this.laufendesSpeichern = this.feldSpeichernJetzt()
      return this.laufendesSpeichern
    },
    // Die Zuständigkeit als Optionen der Auswahlliste (F119) — in derselben
    // Form wie in der Geschäfteliste, damit dieselbe Komponente dieselben Daten
    // bekommt und gleich aussieht.
    zustaendigOptionenFuer(vorstoss) {
      const liste = Array.isArray(vorstoss.zustaendigkeit) ? vorstoss.zustaendigkeit : []
      return liste.map(z =>
        this.mitgliederOptionen.find(o => o.value === z.key) || { label: z.name || z.key, value: z.key })
    },
    zustaendigkeitInZeile(vorstoss, optionen) {
      const zustaendigkeit = (optionen || []).map(o => ({ key: o.value, name: o.label }))
      return this.feldDirektSpeichern(vorstoss, 'zustaendigkeit', zustaendigkeit)
    },
    beschlussWertFuer(vorstoss) {
      const b = vorstoss.beschluss || ''
      return b ? { label: b, value: b } : null
    },
    // Datum kurz für die Tabellenzeile (F119) — wie in der Geschäfteliste.
    formatieredatumKurz(wert) {
      const roh = String(wert || '').slice(0, 10)
      if (!/^\d{4}-\d{2}-\d{2}$/.test(roh)) return ''
      const [jahr, monat, tag] = roh.split('-')
      return `${tag}.${monat}.${jahr.slice(2)}`
    },
    /**
     * Ein Klick in die Zeile öffnet den Vorstoss. Ein Klick auf ein Bedienelement
     * gehört diesem Element — geprüft am Ziel des Klicks, weil fremde Komponenten
     * @click.stop auf ihrem Wurzelelement setzen und ein Klick auf ein Kindelement
     * darin die Zeile trotzdem erreicht.
     */
    zeilenKlick(vorstoss, event) {
      if (event?.target?.closest('button, input, select, textarea, a, label, .pw-inline-select, .pw-inline-beschluss')) return
      this.bearbeiten(vorstoss)
    },
    /**
     * Ein in der Übersicht geändertes Feld wird sofort gespeichert — wie in der
     * Geschäfteliste, ohne die Maske zu öffnen.
     */
    async feldDirektSpeichern(vorstoss, feld, wert) {
      const geaendert = { ...vorstoss, [feld]: wert }
      try {
        const { data } = await axios.put(
          generateUrl(`/apps/parlwin/vorstoesse/${vorstoss.id}`),
          geaendert
        )
        const i = this.vorstoesse.findIndex(v => v.id === (data?.id ?? vorstoss.id))
        if (i >= 0) this.vorstoesse.splice(i, 1, data || geaendert)
      } catch (e) {
        showError('Vorstoss konnte nicht gespeichert werden: ' + (e?.response?.data?.fehler || e?.message || ''))
      }
    },
    async feldSpeichernJetzt() {
      if (!this.bearbeitung?.id) return
      if (!(this.bearbeitung.titel || '').trim()) return
      try {
        const { data } = await axios.put(
          generateUrl(`/apps/parlwin/vorstoesse/${this.bearbeitung.id}`),
          { ...this.bearbeitung }
        )
        // Karte in der Übersicht direkt aktualisieren — kein Neuladen der Liste.
        const i = this.vorstoesse.findIndex(v => v.id === data?.id)
        if (i >= 0) this.vorstoesse.splice(i, 1, data)
        // Ein benannter Vorstoss ist kein Entwurf mehr: der Dialog heisst ab
        // jetzt «Vorstoss bearbeiten» und wird beim Schliessen nicht verworfen.
        this.istEntwurf = false
        showSuccess('Gespeichert')
      } catch (e) {
        showError('Vorstoss konnte nicht gespeichert werden: ' + (e?.response?.data?.fehler || e?.message || ''))
      }
    },
  },
}
</script>

<!-- Übersicht (Karten), Modal, Inputs, Badges, Status, das Nebeneinander im
     Dialog und die Aktionen der Karte kommen aus der globalen style.scss —
     dieselben Elemente wie bei den Geschäften und in der Fragestunde. -->
