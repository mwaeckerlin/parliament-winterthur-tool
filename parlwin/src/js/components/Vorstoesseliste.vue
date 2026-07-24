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
    <div class="pw-filter-body">
      <NcSelect v-model="herkunftOption" :options="herkunftOptionen" :clearable="false" input-label="Herkunft" />
      <NcSelect v-model="statusOption" :options="statusOptionen" :clearable="false" input-label="Status" />
    </div>
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
      <div v-else class="pw-card-grid">
        <article
          v-for="vorstoss in gefiltert"
          :key="vorstoss.id"
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
            <NcButton type="error" @click.stop="loeschen(vorstoss)">Löschen</NcButton>
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
              <NcSelect :model-value="prioritaetWahl" :options="prioritaetOptionen" :clearable="true" placeholder="Nicht gesetzt" @update:model-value="prioritaetGewaehlt" />
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
                :praefix="`V${bearbeitung.id}`"
                jahr-text=" "
                :ordner-hinweis="dokumentOrdnerHinweis"
                :titel="bearbeitung.titel"
              />
            </PwField>
            <PwField label="Notizen">
              <NotizenListe
                :basis-url="'vorstoesse/' + bearbeitung.id"
                :notizen="bearbeitung.aktionen || []"
                :aktuelle-uid="aktuelleUid"
                @geaendert="v => bearbeitung.aktionen = v"
              />
            </PwField>
            <PwField label="Aktionszeitleiste">
              <Aktionszeitleiste :aktionen="bearbeitung.aktionen || []" />
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

    <!-- Verknüpfung mit einem Geschäft -->
    <Teleport to="body">
    <div v-if="verknuepfenDialog" class="pw-modal-overlay" @click.self="verknuepfenSchliessen">
      <div class="pw-modal">
        <div class="pw-modal-kopf">
          <h3>Mit Geschäft verknüpfen</h3>
          <button type="button" class="button pw-btn-schliessen" aria-label="Dialog schliessen" @click.stop="verknuepfenSchliessen">✕</button>
        </div>
        <div class="pw-modal-body">
          <NcTextField v-model="geschaeftSuche" label="Suche" placeholder="Nr. oder Titel" />
          <small class="pw-hinweis">Ähnlichste zum Vorstoss-Titel zuerst, sonst neueste.</small>
          <ul class="pw-verknuepfen-liste">
            <li v-for="g in aehnlicheGeschaefte" :key="g.id">
              <button type="button" class="button pw-verknuepfen-eintrag" @click="verknuepfen(g)">
                <strong>{{ g.nummer || '—' }}</strong> <span>{{ g.titel }}</span>
              </button>
            </li>
          </ul>
          <div v-if="!aehnlicheGeschaefte.length" class="pw-hinweis">Keine Geschäfte gefunden.</div>
        </div>
      </div>
    </div>
    </Teleport>
  </section>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { showError, showSuccess } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/style.css'
import { getCurrentUser } from '@nextcloud/auth'
import { vollerName, personKey, PRIORITAETEN, kuerze } from '../utils'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import PwField from './PwField.vue'
import BeschlussWidget from './BeschlussWidget.vue'
import PwMultiSelect from './PwMultiSelect.vue'
import PwWysiwyg from './PwWysiwyg.vue'
import GeschaeftDokumente from './GeschaeftDokumente.vue'
import NotizenListe from './NotizenListe.vue'
import Aktionszeitleiste from './Aktionszeitleiste.vue'

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
  'Dringliche Motion', 'Dringliches Postulat', 'Budgetmotion',
  'Fragestunde', 'Einzelinitiative', 'Parlamentarische Initiative',
]
// Haltung zu einem fremden Vorstoss; im BeschlussWidget frei überschreibbar.
const FREMD_BESCHLUESSE = [
  'Miteinreichen', 'Unterstützen', 'Stimmfreigabe', 'Ablehnen', 'Ablehnungsantrag stellen',
]

export default {
  name: 'Vorstoesseliste',
  components: { NcTextField, NcButton, NcSelect, NcLoadingIcon, NcEmptyContent, PwField, BeschlussWidget, PwMultiSelect, PwWysiwyg, GeschaeftDokumente, NotizenListe, Aktionszeitleiste },
  props: {
    mitglieder: { type: Array, default: () => [] },
    fraktionen: { type: Array, default: () => [] },
  },
  data() {
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
      geschaefteListe: [],
      geschaeftSuche: '',
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
      return [{ label: 'Alle', value: '' }, ...HERKUENFTE.map(h => ({ label: h.label, value: h.code }))]
    },
    statusOptionen() {
      return [{ label: 'Alle', value: '' }, ...STATUS.map(s => ({ label: s.label, value: s.code }))]
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
    prioritaetOptionen() {
      return PRIORITAETEN
    },
    prioritaetWahl() {
      const p = this.bearbeitung?.prioritaet || ''
      return PRIORITAETEN.find(o => o.value === p) || null
    },
    fremdBeschlussWert() {
      const b = this.bearbeitung?.beschluss || ''
      return b ? { label: b, value: b } : null
    },
    // Wählbare Personen: aktive Fraktionsmitglieder mit Nextcloud-User.
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
      return `Fraktion/40_Vorstösse/${unter}/V${this.bearbeitung.id}-*`
    },
    // Geschäfte zur Verknüpfung: nach Titel-Ähnlichkeit zum Vorstoss und Datum
    // (neueste zuerst) sortiert; optional per Suche eingegrenzt.
    aehnlicheGeschaefte() {
      const vorstossTitel = this.bearbeitung?.titel || ''
      const suche = (this.geschaeftSuche || '').toLowerCase().trim()
      let liste = this.geschaefteListe.filter(g => !g.geloescht)
      if (suche) {
        liste = liste.filter(g =>
          (g.titel || '').toLowerCase().includes(suche) ||
          (g.nummer || '').toLowerCase().includes(suche))
      }
      return [...liste].sort((a, b) => {
        const sb = this.titelAehnlichkeit(vorstossTitel, b.titel || '')
        const sa = this.titelAehnlichkeit(vorstossTitel, a.titel || '')
        if (sb !== sa) return sb - sa
        return String(b.datum || '').localeCompare(String(a.datum || ''))
      })
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
    vollerName,
    personKey,
    kuerze,
    // Zahl gemeinsamer (längerer) Titelwörter als einfaches Ähnlichkeitsmass.
    titelAehnlichkeit(a, b) {
      const worte = t => new Set((t || '').toLowerCase().split(/\W+/).filter(w => w.length > 2))
      const wa = worte(a)
      const wb = worte(b)
      let gemeinsam = 0
      wa.forEach(w => { if (wb.has(w)) gemeinsam++ })
      return gemeinsam
    },
    async verknuepfenOeffnen() {
      this.geschaeftSuche = ''
      try {
        const { data } = await axios.get(generateUrl('/apps/parlwin/geschaefte'), { params: { limit: 500 } })
        this.geschaefteListe = Array.isArray(data) ? data : []
      } catch (e) {
        this.geschaefteListe = []
      }
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
    prioritaetGewaehlt(option) {
      this.bearbeitung.prioritaet = option ? option.value : ''
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
      this.bearbeitung = this.leereBearbeitung(vorstoss)
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
    schliessen() {
      this.bearbeitung = null
      this.istEntwurf = false
    },
    // Speichert den aktuellen Bearbeitungsstand SOFORT (bei jeder Eingabe).
    // Ein leerer Titel wird nie weggespeichert.
    async feldSpeichern() {
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

<style scoped>
/* Übersicht (Karten), Modal, Inputs, Badges und Status kommen aus der globalen
   style.scss – gleiche Elemente wie bei den Geschäften. Nur das
   Nebeneinander-Layout im Dialog ist vorstoss-spezifisch. */
.pw-data-card-aktionen { display: flex; justify-content: flex-end; margin-block-start: 0.5rem; }
.pw-von-bis { display: flex; gap: 0.75rem; flex-wrap: wrap; }
.pw-von-bis > * { flex: 1 1 14rem; }
.pw-verknuepfen-liste { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 0.25rem; }
.pw-verknuepfen-eintrag { inline-size: 100%; text-align: start; justify-content: flex-start; gap: 0.5rem; }
</style>
