<template>
  <div class="pw-geschaeft-detail">
    <div v-if="laden" class="pw-laden">Lade Geschäft...</div>
    <div v-else-if="!geschaeft" class="pw-fehler">Geschäft konnte nicht geladen werden.</div>

    <template v-else>
      <header class="pw-detail-header">
        <div>
          <p class="pw-detail-kicker">{{ geschaeft.nummer || 'Eigenes Geschäft' }}</p>
          <!-- Selbst angelegte Geschäfte sind vollständig bearbeitbar; bei
               Parlamentsgeschäften stammt der Titel von der Webseite. -->
          <input
            v-if="istEigenes"
            v-model="geschaeft.titel"
            type="text"
            class="pw-detail-titel-input"
            placeholder="Titel des Geschäfts"
            aria-label="Titel"
            @change="stammdatenSpeichern"
          />
          <h3 v-else>{{ geschaeft.titel }}</h3>
        </div>
        <span :class="'pw-status-' + fraktionsstatusKlasse(geschaeft.fraktionsstatus)">
          {{ fraktionsstatusLabel(geschaeft.fraktionsstatus) }}
        </span>
      </header>

      <!-- Der Beschreibungstext steht direkt unter dem Titel: er sagt, worum es
           geht, und gehört damit vor alle Detailangaben. Bei Geschäften von der
           Parlamentswebseite steht der Text in der Quelle — dort entfällt er. -->
      <div v-if="istEigenes" class="pw-detail-inhalt">
        <PwWysiwyg
          :model-value="geschaeft.inhalt || ''"
          placeholder="Worum geht es?"
          @update:model-value="inhaltGeaendert"
          @blur="stammdatenSpeichern"
        />
      </div>

      <div class="pw-detail-abschnitt pw-oeffentlich">
        <h4>Öffentliche Informationen</h4>
        <table class="pw-info-tabelle">
          <tbody>
            <tr><th>Nummer</th><td>{{ geschaeft.nummer }}</td></tr>
            <tr>
              <th>Typ</th>
              <td>
                <!-- Die Typen für eigene Geschäfte pflegt die Administration. -->
                <PwTypSelect
                  v-if="istEigenes"
                  :model-value="geschaeft.typ"
                  :options="typOptionen"
                  aria-label="Typ"
                  @update:model-value="typGewaehlt"
                />
                <template v-else>{{ geschaeft.typ }}</template>
              </td>
            </tr>
            <tr>
              <th>Status</th>
              <td>
                <!-- Vorgeschlagen wird, was es bereits gibt; ein eigener Status
                     lässt sich jederzeit eintippen (gleiches Widget wie beim
                     Beschluss). -->
                <BeschlussWidget
                  v-if="istEigenes"
                  :model-value="{ label: geschaeft.status || '', value: geschaeft.status || '' }"
                  :options="statusWidgetOptionen"
                  input-class="pw-status-input"
                  aria-label="Status"
                  placeholder="Status wählen oder eingeben …"
                  @update:model-value="statusGeaendert($event && $event.label)"
                />
                <template v-else>{{ kuerze(geschaeft.status) }}</template>
              </td>
            </tr>
            <tr><th>Fraktionsstatus</th><td>{{ fraktionsstatusLabel(geschaeft.fraktionsstatus) }}</td></tr>
            <tr>
              <th>Datum</th>
              <td>
                <PwDatumInput
                  v-if="istEigenes"
                  :model-value="geschaeft.datum"
                  class="pw-input"
                  aria-label="Datum"
                  @update:model-value="datumGeaendert"
                />
                <template v-else>{{ formatieredatum(geschaeft.datum) }}</template>
              </td>
            </tr>
            <tr v-if="istEigenes">
              <th>Kommission</th>
              <td>
                <!-- Höchstens eine Kommission; keine ist ebenfalls gültig.
                     Gekürzte Anzeige, voller Name gespeichert (PwKommissionSelect). -->
                <PwKommissionSelect
                  :model-value="geschaeft.kommission"
                  :options="kommissionsOptionen"
                  aria-label="Kommission"
                  @update:model-value="kommissionGewaehlt"
                />
              </td>
            </tr>
            <tr v-else-if="geschaeft.kommission">
              <th>Kommission</th>
              <td>{{ kuerze(geschaeft.kommission) }}</td>
            </tr>
            <tr v-if="geschaeft.einreicher && geschaeft.einreicher.length">
              <th>Einreicher</th>
              <td>
                <span
                  v-for="(p, i) in geschaeft.einreicher"
                  :key="i"
                  class="pw-einreicher-person"
                >{{ p.name }}<span class="pw-einreicher-rolle"> ({{ p.rolle }})</span><span v-if="i < geschaeft.einreicher.length - 1">, </span></span>
              </td>
            </tr>
            <tr><th>Letzte externe Änderung</th><td>{{ formatiereZeitpunkt(geschaeft.letzteExterneAenderungAm) }}</td></tr>
            <tr><th>Letzte Fraktionsentscheidung</th><td>{{ formatiereZeitpunkt(geschaeft.letzteFraktionsentscheidungAm) }}</td></tr>
            <tr v-if="geschaeft.url">
              <th>Link</th>
              <td><a :href="geschaeft.url" target="_blank">Auf Parlamentswebseite öffnen ↗</a></td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="pw-detail-abschnitt pw-fraktion">
        <h4>Fraktionsinterne Bearbeitung</h4>

        <div class="pw-form-zeile">
          <label>Priorität</label>
          <PwPrioritaetSelect
            :model-value="geschaeft.prioritaet"
            @update:model-value="prioritaetGewaehlt"
          />
        </div>

        <div class="pw-form-zeile">
          <label>Zuständigkeit</label>
          <PwMultiSelect
            class="pw-zustaendigkeit-select"
            :model-value="zustaendigOptionenAusgewaehlt"
            :options="zustaendigOptionen"
            :clearable="true"
            placeholder="—"
            label="label"
            @update:model-value="aenderungZustaendig($event || [])"
          />
          <small class="pw-hinweis">
            Falls mehrere Personen ausgewählt sind, wird die erste Auswahl intern als Hauptzuständigkeit geführt.
          </small>
        </div>

        <!-- Ein eigenes Geschäft lässt sich mit dem offiziellen Parlaments-
             geschäft verknüpfen (wie Vorstoss→Geschäft): es wird dadurch als
             erledigt abgeschlossen, die Priorität wandert ins offizielle Geschäft. -->
        <div v-if="istEigenes && !istNeu" class="pw-form-zeile">
          <label>Offizielles Geschäft</label>
          <div v-if="geschaeft.verknuepftGeschaeftId && geschaeft.verknuepftGeschaeft" class="pw-hinweis">
            Mit dem offiziellen Geschäft
            <button type="button" class="pw-verweis-knopf" @click="$emit('oeffneGeschaeft', geschaeft.verknuepftGeschaeft.id)"><strong>{{ geschaeft.verknuepftGeschaeft.nummer || '—' }}</strong> «{{ geschaeft.verknuepftGeschaeft.titel }}»</button>
            verknüpft und abgeschlossen.
          </div>
          <div v-else-if="geschaeft.verknuepftGeschaeftId" class="pw-hinweis">Mit einem offiziellen Geschäft verknüpft und abgeschlossen.</div>
          <NcButton v-else type="secondary" @click="verknuepfenOeffnen">Mit offiziellem Geschäft verknüpfen</NcButton>
        </div>

        <!-- Beschluss, Votum, Notizen, Dokumente und Verlauf hängen an einer
             bestehenden ID und erscheinen deshalb erst nach dem Speichern. -->
        <template v-if="!istNeu">
        <div class="pw-form-zeile">
          <label>Beschluss erfassen</label>
          <BeschlussWidget
            :model-value="beschlussWert"
            :options="beschlussOptionen"
            :disabled="!geschaeft.fraktionssitzung?.beschlussSchreibbar"
            @update:model-value="beschlussNachWahl"
            @blur="beschlussSessionBeenden"
          />
          <small v-if="!geschaeft.fraktionssitzung?.beschlussSchreibbar" class="pw-hinweis">
            Im Fraktionssitzungsmodus darf nur der Protokollführer Beschlüsse erfassen.
          </small>
        </div>

        <!-- Votum im Rat: die zuständige Person erfasst den Wortlaut; das PDF
             steht bereit, sobald etwas erfasst ist. Für Nicht-Zuständige ist das
             Feld nur sichtbar, wenn ein Votum vorliegt (kein leerer Kasten). -->
        <div v-if="votumSchreibbar || votumHatInhalt" class="pw-form-zeile pw-votum">
          <label>Votum im Rat</label>
          <PwWysiwyg
            :model-value="votumHtml"
            :editable="votumSchreibbar"
            placeholder="Wortlaut des Votums"
            :status="votumStatus"
            :pdf-href="votumPdfUrl"
            pdf-title="Votum als PDF drucken"
            @update:model-value="votumGeaendert"
            @blur="votumSofortSpeichern"
          />
          <small v-if="!votumSchreibbar" class="pw-hinweis">
            Das Votum erfasst die für dieses Geschäft zuständige Person.
          </small>
          <NcButton
            v-if="votumSchreibbar && votumHatInhalt"
            type="secondary"
            class="pw-votum-archivieren"
            @click="votumArchivieren"
          >
            Votum archivieren
          </NcButton>
        </div>

        <div class="pw-form-zeile">
          <label>Notizen</label>
          <NotizenListe
            ref="notizenListe"
            :basis-url="'geschaefte/' + geschaeftId"
            :notizen="notizAktionen"
            :aktuelle-uid="aktuelleUid"
            @geaendert="onNotizenGeaendert"
          />
          <!-- Sitzungsnotizen: in Sitzungen erfasste, am Geschäft haftende Notizen.
               Separat und ausklappbar, standardmässig eingeklappt. -->
          <details class="pw-sitzungsnotizen-details">
            <summary>Sitzungsnotizen<span v-if="sitzungsnotizAktive.length" class="pw-sitzungsnotizen-zahl">{{ sitzungsnotizAktive.length }}</span></summary>
            <NotizenListe
              ref="sitzungsnotizenListe"
              :basis-url="'geschaefte/' + geschaeftId"
              :notizen="sitzungsnotizAktionen"
              :aktuelle-uid="aktuelleUid"
              kategorie="sitzungsnotiz"
              @geaendert="onSitzungsnotizenGeaendert"
            />
          </details>
        </div>

        <div class="pw-form-zeile">
          <label>Dokumente zum Geschäft</label>
          <GeschaeftDokumente
            :geschaeft-id="geschaeftId"
            :geschaeft-nummer="geschaeft.nummer || ''"
            :titel="geschaeft.titel || ''"
          />
        </div>
        </template>
        <small v-else class="pw-hinweis">
          Beschluss, Votum, Notizen, Dokumente und der Verlauf stehen bereit, sobald das Geschäft gespeichert ist.
        </small>
      </div>

      <div v-if="istNeu" class="pw-modal-footer">
        <NcButton type="primary" :disabled="!(geschaeft.titel || '').trim() || speichernLaeuft" @click="neuesGeschaeftSpeichern">Speichern</NcButton>
        <NcButton @click="$emit('abbrechen')">Abbrechen</NcButton>
      </div>

      <Aktionszeitleiste
        v-if="!istNeu"
        :aktionen="geschaeft.aktionen || []"
        :traktandum-kontext="traktandumKontext"
        :basis-url="'geschaefte/' + geschaeftId"
        :aktuelle-uid="aktuelleUid"
        @oeffne-traktandum="id => $emit('oeffneTraktandum', id)"
        @notiz-wiederhergestellt="onNotizWiederhergestellt"
      />

      <GeschaeftVerknuepfenDialog
        v-if="verknuepfenDialog"
        :titel="geschaeft.titel || ''"
        :ausschluss-id="geschaeftId"
        :nur-offizielle="true"
        :inklusive-erledigt="true"
        kopf="Mit offiziellem Geschäft verknüpfen"
        @verknuepfen="verknuepfen"
        @schliessen="verknuepfenDialog = false"
      />

      <div v-if="verknuepfteVorstoesse.length" class="pw-detail-abschnitt">
        <h4>Verknüpfte Vorstösse</h4>
        <div v-for="v in verknuepfteVorstoesse" :key="v.id" class="pw-verknuepfter-eintrag">
          <h5>{{ v.titel }}<span v-if="v.art"> · {{ v.art }}</span></h5>
          <div class="pw-data-card-grid">
            <div v-if="v.beschluss" class="pw-data-pair"><span>Haltung</span><strong>{{ v.beschluss }}</strong></div>
            <div v-if="vorstossZustaendigkeit(v)" class="pw-data-pair"><span>Zuständigkeit</span><strong>{{ vorstossZustaendigkeit(v) }}</strong></div>
          </div>
          <div v-for="n in vorstossNotizen(v)" :key="n.id" class="pw-notiz-eintrag">
            <div class="pw-notiz-kopf"><span class="pw-notiz-autor">{{ n.autorName || n.autorUid }}</span></div>
            <div class="pw-notiz-inhalt" v-html="markdownZuHtml(n.text)" />
          </div>
        </div>
      </div>

      <!-- Gegenstück zu «Verknüpfte Vorstösse»: die eigenen Geschäfte, die auf
           dieses offizielle Geschäft verlinkt wurden. Ihre Notizen und Angaben sind
           beim Verknüpfen hierher gewandert; der Titel ist anklickbar (hin und her). -->
      <div v-if="verknuepfteEigene.length" class="pw-detail-abschnitt">
        <h4>Verknüpfte eigene Geschäfte</h4>
        <div v-for="e in verknuepfteEigene" :key="e.id" class="pw-verknuepfter-eintrag">
          <h5><button type="button" class="pw-verweis-knopf" @click="$emit('oeffneGeschaeft', e.id)">{{ e.titel }}</button></h5>
          <div class="pw-data-card-grid">
            <div v-if="prioritaetLabel(e.prioritaet)" class="pw-data-pair"><span>Priorität</span><strong>{{ prioritaetLabel(e.prioritaet) }}</strong></div>
          </div>
        </div>
      </div>

    </template>
  </div>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import { getCurrentUser } from '@nextcloud/auth'
import { showSuccess, showError } from '@nextcloud/dialogs'
import { vollerName, personKey, markdownZuHtml, kuerze, prioritaetLabel } from '../utils'
import axios from '@nextcloud/axios'
import NcButton from '@nextcloud/vue/components/NcButton'
import PwPrioritaetSelect from './PwPrioritaetSelect.vue'
import PwKommissionSelect from './PwKommissionSelect.vue'
import PwTypSelect from './PwTypSelect.vue'
import PwDatumInput from './PwDatumInput.vue'
import PwWysiwyg from './PwWysiwyg.vue'
import PwMultiSelect from './PwMultiSelect.vue'
import GeschaeftDokumente from './GeschaeftDokumente.vue'
import BeschlussWidget from './BeschlussWidget.vue'
import NotizenListe from './NotizenListe.vue'
import Aktionszeitleiste from './Aktionszeitleiste.vue'
import GeschaeftVerknuepfenDialog from './GeschaeftVerknuepfenDialog.vue'
import { subscribeRealtime } from '../realtime'

export default {
  name: 'GeschaeftDetail',
  components: { NcButton, PwWysiwyg, PwMultiSelect, PwPrioritaetSelect, PwKommissionSelect, PwTypSelect, PwDatumInput, GeschaeftDokumente, BeschlussWidget, NotizenListe, Aktionszeitleiste, GeschaeftVerknuepfenDialog },
  props: {
    geschaeftId: { type: Number, required: true },
    mitglieder: { type: Array, default: () => [] },
    traktandumKontext: { type: Object, default: null },
  },
  emits: ['gespeichert', 'oeffneTraktandum', 'erstellt', 'abbrechen', 'oeffneGeschaeft'],
  data() {
    return {
      laden: false,
      geschaeft: null,
      beschlussWert: null,
      beschlussAktionId: null,
      // Läuft, während ein neu erfasstes Geschäft angelegt wird.
      speichernLaeuft: false,
      // Dialog «Mit offiziellem Geschäft verknüpfen» (nur eigene Geschäfte).
      verknuepfenDialog: false,
      beschlussZuletztGespeichert: null,
      votumHtml: '',
      votumAktionId: null,
      votumSpeicherTimer: null,
      votumStatus: '',
      votumDirty: false,
      ausgewaehltePersonKeys: [],
      hauptPersonKey: '',
      unsubRealtime: null,
      verknuepfteVorstoesse: [],
      verknuepfteEigene: [],
      // Auswahlwerte der Stammdaten: Typen pflegt der Administrator, die
      // Status-Werte stammen aus den vorhandenen Geschäften, die Kommissionen
      // aus der Synchronisation.
      typOptionen: [],
      statusOptionen: [],
      kommissionen: [],
    }
  },
  created() {
    this.unsubRealtime = subscribeRealtime(this.handleRealtimeEvent)
    this.ladeAuswahllisten()
  },
  beforeUnmount() {
    if (this.unsubRealtime) {
      this.unsubRealtime()
      this.unsubRealtime = null
    }
    if (this.votumSpeicherTimer) {
      clearTimeout(this.votumSpeicherTimer)
      this.votumSpeicherTimer = null
      // Letzten Stand sofort sichern.
      this.votumSpeichernJetzt()
    }
  },
  computed: {
    // Neu-Modus: dieselbe Maske, aber es existiert noch kein Geschäft.
    istNeu() {
      return !this.geschaeftId
    },
    // Selbst angelegte Geschäfte tragen die externe ID «eigen:…». Sie stammen
    // nicht von der Parlamentswebseite, werden vom Abgleich nicht überschrieben
    // und sind deshalb in allen Stammdaten bearbeitbar.
    istEigenes() {
      return String(this.geschaeft?.externId || '').startsWith('eigen:')
    },
    // Zur Auswahl stehen nur aktive Kommissionen — eine aufgelöste Kommission
    // bekommt kein neues Geschäft mehr.
    kommissionsOptionen() {
      return (this.kommissionen || []).filter(k => k.aktiv !== false).map(k => k.name)
    },
    // Das Beschluss-Widget erwartet Paare aus Beschriftung und Wert.
    statusWidgetOptionen() {
      return (this.statusOptionen || []).map(s => ({ label: s, value: s }))
    },
    aktiveMitglieder() {
      // Nur Fraktionsmitglieder, die auch als Nextcloud-User registriert sind,
      // können als zuständig gewählt werden.
      return this.mitglieder
        .filter((mitglied) => mitglied.aktiv !== false)
        .filter((mitglied) => !!(mitglied.nextcloudUid || mitglied.nextcloud_uid))
        .sort((a, b) => this.vollerName(a).localeCompare(this.vollerName(b)))
    },
    inaktiveMitglieder() {
      // Auch inaktive werden nur dann angeboten, wenn sie einen Nextcloud-User
      // haben (z.B. ehemalige Mitglieder, die noch zuständig sein können).
      return this.mitglieder
        .filter((mitglied) => mitglied.aktiv === false)
        .filter((mitglied) => !!(mitglied.nextcloudUid || mitglied.nextcloud_uid))
        .sort((a, b) => this.vollerName(a).localeCompare(this.vollerName(b)))
    },
    zustaendigOptionen() {
      const mk = (m, aktiv) => ({
        key: this.personKey(m),
        label: aktiv ? this.vollerName(m) : `${this.vollerName(m)} (inaktiv)`,
      })
      return [
        ...this.aktiveMitglieder.map((m) => mk(m, true)),
        ...this.inaktiveMitglieder.map((m) => mk(m, false)),
      ]
    },
    zustaendigOptionenAusgewaehlt() {
      return this.ausgewaehltePersonKeys
        .map((key) => this.zustaendigOptionen.find((o) => o.key === key))
        .filter(Boolean)
    },
    beschlussOptionen() {
      const erlaubt = Array.isArray(this.geschaeft?.erlaubteBeschluesse) ? this.geschaeft.erlaubteBeschluesse : []
      return erlaubt.map((b) => ({ label: b.label || b.code, value: b.code }))
    },
    /** UID des angemeldeten Nutzers — für «nur der Autor darf» in der Notizen-Liste. */
    aktuelleUid() {
      return getCurrentUser()?.uid || ''
    },
    /** Alle Notiz-Aktionen (AKTIVE und GELÖSCHTE) — die geteilte NotizenListe verwaltet sie. */
    notizAktionen() {
      const alle = this.geschaeft?.aktionen || []
      return alle.filter(a => a.aktionTyp === 'notiz')
    },
    /** Sitzungsnotizen (in Sitzungen erfasst, am Geschäft haftend) — AKTIVE und GELÖSCHTE. */
    sitzungsnotizAktionen() {
      const alle = this.geschaeft?.aktionen || []
      return alle.filter(a => a.aktionTyp === 'sitzungsnotiz')
    },
    /** Aktive (nicht gelöschte) Sitzungsnotizen — für die Zahl im aufklappbaren Titel. */
    sitzungsnotizAktive() {
      return this.sitzungsnotizAktionen.filter(a => !a.geloescht)
    },
    // Das Votum vertritt die Fraktion im Rat — erfassen darf es nur, wer für
    // dieses Geschäft zuständig ist. Der Server weist andere ohnehin ab; hier
    // bleibt das Feld für sie sichtbar, aber schreibgeschützt.
    votumSchreibbar() {
      const key = this.eigenerPersonKey
      return !!key && this.ausgewaehltePersonKeys.includes(key)
    },
    // Der personKey des angemeldeten Nutzers, sofern er ein Fraktionsmitglied mit
    // Nextcloud-Konto ist. Eine Stelle für «der Erzeuger ist per Default zuständig»
    // UND für das Votum-Schreibrecht (keine Doppel-Logik).
    eigenerPersonKey() {
      const uid = (getCurrentUser()?.uid || '').toLowerCase()
      if (!uid) return ''
      const ich = this.mitglieder.find(
        (m) => ((m.nextcloudUid || m.nextcloud_uid || '').toLowerCase()) === uid,
      )
      return ich ? personKey(ich) : ''
    },
    votumHatInhalt() {
      const t = (this.votumHtml || '').replace(/<[^>]*>/g, '').trim()
      return t.length > 0 || !!this.votumAktionId
    },
    votumPdfUrl() {
      if (!this.geschaeftId) return ''
      if (!this.votumHatInhalt) return ''
      return generateUrl(`/apps/parlwin/geschaefte/${this.geschaeftId}/votum/pdf`)
    },
  },
  watch: {
    geschaeftId: {
      immediate: true,
      handler() {
        this.ladeDetail()
      },
    },
    ausgewaehltePersonKeys() {
      this.synchronisiereHauptPersonKey()
    },
  },
  methods: {
    markdownZuHtml,
    vollerName,
    personKey,
    kuerze,
    personLabelByKey(key) {
      const member = this.mitglieder.find(m => this.personKey(m) === key)
      return member ? this.vollerName(member) : key
    },
    /** Heutiges Datum als JJJJ-MM-TT — Vorbelegung eines neuen Geschäfts. */
    heute() {
      return new Date().toISOString().slice(0, 10)
    },
    // Die Auswahlwerte der Stammdaten. Der Typ kommt aus der Administration,
    // der Status aus den tatsächlich vorkommenden Werten (bleibt frei
    // überschreibbar), die Kommissionen aus der Synchronisation.
    async ladeAuswahllisten() {
      const [typen, status, kommissionen] = await Promise.all([
        axios.get(generateUrl('/apps/parlwin/settings/eigene-typen')).catch(() => ({ data: [] })),
        axios.get(generateUrl('/apps/parlwin/geschaefte/statuswerte')).catch(() => ({ data: [] })),
        axios.get(generateUrl('/apps/parlwin/kommissionen')).catch(() => ({ data: [] })),
      ])
      this.typOptionen = Array.isArray(typen.data) ? typen.data : []
      this.statusOptionen = Array.isArray(status.data) ? status.data : []
      this.kommissionen = Array.isArray(kommissionen.data) ? kommissionen.data : []
    },
    // Eine Kommission je Geschäft; keine zu wählen ist zulässig.
    kommissionGewaehlt(wahl) {
      if (!this.geschaeft) return
      this.geschaeft.kommission = wahl || ''
      this.stammdatenSpeichern()
    },
    datumGeaendert(wert) {
      if (!this.geschaeft) return
      this.geschaeft.datum = wert || ''
      this.stammdatenSpeichern()
    },
    verknuepfenOeffnen() {
      this.verknuepfenDialog = true
    },
    // Verknüpft das eigene Geschäft mit einem offiziellen (wie Vorstoss→Geschäft):
    // es wird als erledigt abgeschlossen, die Priorität wandert ins offizielle.
    async verknuepfen(ziel) {
      if (!this.geschaeftId || !ziel?.id) return
      try {
        const { data } = await axios.post(
          generateUrl(`/apps/parlwin/geschaefte/${this.geschaeftId}/verknuepfen`),
          { zielGeschaeftId: ziel.id }
        )
        if (this.geschaeft) {
          this.geschaeft.verknuepftGeschaeftId = data.verknuepftGeschaeftId || ziel.id
          this.geschaeft.verknuepftGeschaeft = { id: ziel.id, nummer: ziel.nummer || '', titel: ziel.titel || '' }
          this.geschaeft.status = data.status || 'erledigt'
        }
        this.verknuepfenDialog = false
        showSuccess('Mit dem offiziellen Geschäft verknüpft')
        this.$emit('gespeichert')
      } catch (e) {
        showError('Verknüpfung fehlgeschlagen: ' + (e?.response?.data?.fehler || e?.message || ''))
      }
    },
    statusGeaendert(wert) {
      if (!this.geschaeft) return
      this.geschaeft.status = wert || ''
      this.stammdatenSpeichern()
    },
    // Der Beschreibungstext speichert wie jede Eingabe beim Verlassen; während
    // des Tippens wird nur der Stand gehalten.
    inhaltGeaendert(html) {
      if (!this.geschaeft) return
      this.geschaeft.inhalt = html || ''
    },
    typGewaehlt(wahl) {
      if (!this.geschaeft) return
      this.geschaeft.typ = wahl || ''
      this.stammdatenSpeichern()
    },
    // Legt das in der Maske erfasste eigene Geschäft an. Danach übernimmt die
    // Liste die neue Nummer und öffnet dieselbe Maske als Bearbeitung, sodass
    // sich Dokumente und Notizen direkt anschliessen lassen.
    async neuesGeschaeftSpeichern() {
      const titel = (this.geschaeft?.titel || '').trim()
      if (!titel || this.speichernLaeuft) return
      this.speichernLaeuft = true
      try {
        const { data } = await axios.post(generateUrl('/apps/parlwin/geschaefte'), {
          titel,
          typ: this.geschaeft.typ || 'Eigenes Geschäft',
          status: this.geschaeft.status || 'Pendent',
          inhalt: this.geschaeft.inhalt || '',
          kommission: this.geschaeft.kommission || '',
          datum: this.geschaeft.datum || '',
        })
        const neueId = data?.id || 0
        // Die vorausgewählte Zuständigkeit (per Default der Erzeuger) am neu
        // angelegten Geschäft festhalten — gleiche Payload-Bildung wie sonst.
        if (neueId && this.ausgewaehltePersonKeys.length) {
          try {
            await axios.put(generateUrl(`/apps/parlwin/geschaefte/${neueId}`), this._zustaendigkeitenBody())
          } catch (e) { /* Zuständigkeit ist nachträglich änderbar — nicht blockieren */ }
        }
        showSuccess('Gespeichert')
        this.$emit('erstellt', neueId)
      } catch (e) {
        showError('Geschäft konnte nicht erstellt werden: ' + (e?.response?.data?.fehler || e?.message || ''))
      } finally {
        this.speichernLaeuft = false
      }
    },
    // Titel, Typ, Status und Datum eines selbst angelegten Geschäfts speichern
    // sofort — wie jede andere Eingabe in dieser Maske.
    async stammdatenSpeichern() {
      if (!this.istEigenes || !this.geschaeft?.id) return
      try {
        await axios.put(generateUrl(`/apps/parlwin/geschaefte/${this.geschaeft.id}/stammdaten`), {
          titel: this.geschaeft.titel || '',
          typ: this.geschaeft.typ || '',
          status: this.geschaeft.status || '',
          datum: this.geschaeft.datum || '',
          inhalt: this.geschaeft.inhalt || '',
          kommission: this.geschaeft.kommission || '',
        })
        showSuccess('Gespeichert')
        this.$emit('gespeichert')
      } catch (e) {
        showError('Geschäft konnte nicht gespeichert werden: ' + (e?.response?.data?.fehler || e?.message || ''))
      }
    },
    aenderungZustaendig(options) {
      const keys = (Array.isArray(options) ? options : [])
        .map((o) => (o && typeof o === 'object' ? o.key : ''))
        .filter(Boolean)
      // Wenn unverändert: nicht speichern (verhindert Loop nach ladeDetail).
      const gleich = keys.length === this.ausgewaehltePersonKeys.length
        && keys.every((k, i) => k === this.ausgewaehltePersonKeys[i])
      if (gleich) return
      this.ausgewaehltePersonKeys = keys
      this.synchronisiereHauptPersonKey()
      this.speichereZustaendigkeiten()
    },
    async ladeDetail() {
      // Neu-Modus: es gibt noch nichts zu laden. Dieselbe Maske sammelt die
      // Eingaben, angelegt wird erst beim Speichern.
      if (!this.geschaeftId) {
        this.geschaeft = {
          id: 0,
          externId: 'eigen:neu',
          titel: '',
          nummer: '',
          typ: 'Eigenes Geschäft',
          status: 'Pendent',
          // Ein neu erfasstes Geschäft entsteht heute — das ist in aller Regel
          // das gesuchte Datum und bleibt änderbar.
          datum: this.heute(),
          inhalt: '',
          kommission: '',
          aktionen: [],
          zustaendigkeiten: [],
        }
        // Der Erzeuger ist per Default zuständig: als Vorauswahl sichtbar und
        // beim Speichern übernommen; vor dem Speichern noch änderbar.
        const eigen = this.eigenerPersonKey
        this.ausgewaehltePersonKeys = eigen ? [eigen] : []
        this.hauptPersonKey = eigen || ''
        this.laden = false
        return
      }
      this.laden = true
      try {
        const { data } = await axios.get(generateUrl(`/apps/parlwin/geschaefte/${this.geschaeftId}`))
        this.geschaeft = data
        this.ladeVerknuepfteVorstoesse()
        this.ladeVerknuepfteEigene()

        const zustaendigkeiten = data.zustaendigkeiten || []
        this.ausgewaehltePersonKeys = zustaendigkeiten.map(z => z.personKey)
        const haupt = zustaendigkeiten.find(z => z.istHaupt)
        this.hauptPersonKey = haupt?.personKey || ''
        this.synchronisiereHauptPersonKey()
        const lb = data.letzterBeschluss || null
        this.beschlussAktionId = null
        if (!lb) {
          this.beschlussWert = null
          this.beschlussZuletztGespeichert = null
        } else if (lb.aktionCode) {
          this.beschlussWert = { label: lb.titel || lb.aktionCode, value: lb.aktionCode }
          this.beschlussZuletztGespeichert = lb.aktionCode + '\n'
        } else {
          this.beschlussWert = { label: lb.text || '', value: '', freitext: true }
          this.beschlussZuletztGespeichert = '\n' + (lb.text || '')
        }
        const av = data.aktuellesVotum || null
        this.votumHtml = av?.text || ''
        this.votumAktionId = av?.id || null
        this.votumDirty = false
      } catch (e) {
        this.geschaeft = null
        console.error(e)
      } finally {
        this.laden = false
      }
    },
    async ladeVerknuepfteVorstoesse() {
      try {
        const { data } = await axios.get(generateUrl(`/apps/parlwin/vorstoesse/geschaeft/${this.geschaeftId}`))
        this.verknuepfteVorstoesse = Array.isArray(data) ? data : []
      } catch (e) {
        this.verknuepfteVorstoesse = []
      }
    },
    async ladeVerknuepfteEigene() {
      try {
        const { data } = await axios.get(generateUrl(`/apps/parlwin/geschaefte/${this.geschaeftId}/verknuepfte-eigene`))
        this.verknuepfteEigene = Array.isArray(data) ? data : []
      } catch (e) {
        this.verknuepfteEigene = []
      }
    },
    prioritaetLabel,
    vorstossZustaendigkeit(vorstoss) {
      const liste = Array.isArray(vorstoss.zustaendigkeit) ? vorstoss.zustaendigkeit : []
      return liste.map(z => z.name).filter(Boolean).join(', ')
    },
    /** Aktive (nicht gelöschte) Notizen eines verknüpften Vorstosses (geteilter Notiz-Speicher: aktionen). */
    vorstossNotizen(vorstoss) {
      const liste = Array.isArray(vorstoss.aktionen) ? vorstoss.aktionen : []
      return liste.filter(n => n.aktionTyp === 'notiz' && !n.geloescht)
    },
    handleRealtimeEvent(event) {
      const type = event?.type || ''
      const changedId = Number(event?.payload?.id || 0)
      if (type === 'fraktionssitzung.updated' || type === 'fraktion.roles.updated') {
        // Seltene globale Konfigurationsänderung – Full-Reload nötig
        this.ladeDetail()
        return
      }
      if (type === 'sync.completed') {
        // Sync: nur Aktionen und öffentliche Felder aktualisieren
        this._ladeAktionenNur()
        return
      }
      if (type === 'geschaefte.action' && (changedId === 0 || changedId === this.geschaeftId)) {
        // Notiz/Beschluss/Votum: nur Aktionen-Liste aktualisieren
        this._ladeAktionenNur()
        return
      }
      if (type === 'geschaefte.updated' && (changedId === 0 || changedId === this.geschaeftId)) {
        // Zuständigkeiten, Status: Aktionen + Zuständigkeiten aktualisieren
        this._ladeZustaendigkeitenUndAktionen()
        return
      }
    },
    // Lädt nur aktionen nach – ohne beschlussWert oder andere UI-State zu berühren
    async _ladeAktionenNur() {
      try {
        const { data } = await axios.get(generateUrl(`/apps/parlwin/geschaefte/${this.geschaeftId}`))
        if (this.geschaeft && Array.isArray(data?.aktionen)) {
          this.geschaeft.aktionen = data.aktionen
        }
      } catch (e) {
        console.error('Aktionen-Reload fehlgeschlagen:', e)
      }
    },
    // Lädt Zuständigkeiten + Aktionen nach – ohne beschlussWert zu ändern
    async _ladeZustaendigkeitenUndAktionen() {
      try {
        const { data } = await axios.get(generateUrl(`/apps/parlwin/geschaefte/${this.geschaeftId}`))
        if (!this.geschaeft) return
        if (Array.isArray(data?.aktionen)) {
          this.geschaeft.aktionen = data.aktionen
        }
        if (Array.isArray(data?.zustaendigkeiten)) {
          this.geschaeft.zustaendigkeiten = data.zustaendigkeiten
          this.ausgewaehltePersonKeys = data.zustaendigkeiten.map(z => z.personKey)
          const haupt = data.zustaendigkeiten.find(z => z.istHaupt)
          this.hauptPersonKey = haupt?.personKey || ''
        }
      } catch (e) {
        console.error('Reload fehlgeschlagen:', e)
      }
    },
    // Fügt eine Aktion zur lokalen Liste hinzu
    _aktionHinzufuegen(aktion) {
      if (!this.geschaeft || !aktion) return
      if (!Array.isArray(this.geschaeft.aktionen)) this.geschaeft.aktionen = []
      this.geschaeft.aktionen.push(aktion)
    },
    // Aktualisiert eine bestehende Aktion in der lokalen Liste (in-place)
    _aktionAktualisieren(aktion) {
      if (!this.geschaeft || !aktion?.id) return
      const aktionen = this.geschaeft.aktionen || []
      const idx = aktionen.findIndex(a => a.id === aktion.id)
      if (idx >= 0) aktionen[idx] = aktion
    },
    // Entfernt eine Aktion aus der lokalen Liste (in-place)
    _aktionEntfernen(aktionId) {
      if (!this.geschaeft) return
      const aktionen = this.geschaeft.aktionen || []
      const idx = aktionen.findIndex(a => a.id === aktionId)
      if (idx >= 0) aktionen.splice(idx, 1)
    },
    // Priorität speichert wie alle Eingaben sofort; leer = nicht gesetzt.
    async prioritaetGewaehlt(wert) {
      const prioritaet = wert || ''
      try {
        await axios.put(generateUrl(`/apps/parlwin/geschaefte/${this.geschaeftId}/prioritaet`), { prioritaet })
        if (this.geschaeft) this.geschaeft.prioritaet = prioritaet
        showSuccess('Priorität gespeichert')
        this.$emit('gespeichert')
      } catch (e) {
        showError('Priorität konnte nicht gespeichert werden: ' + (e?.response?.data?.fehler || e?.message || ''))
      }
    },
    // Baut den Zuständigkeits-Payload aus der aktuellen Auswahl. Eine Stelle,
    // gemeinsam genutzt von der Sofort-Speicherung und der Neuanlage.
    _zustaendigkeitenBody() {
      this.synchronisiereHauptPersonKey()
      const zustaendigkeiten = this.ausgewaehltePersonKeys.map(key => {
        const member = this.mitglieder.find(m => this.personKey(m) === key)
        return {
          mitgliedExternId: member?.externId || member?.extern_id || '',
          personName: member ? this.vollerName(member) : this.personLabelByKey(key),
        }
      })
      return { zustaendigkeiten, haupt_person_key: this.hauptPersonKey }
    },
    async speichereZustaendigkeiten() {
      try {
        const { data } = await axios.put(generateUrl(`/apps/parlwin/geschaefte/${this.geschaeftId}`), this._zustaendigkeitenBody())
        if (this.geschaeft && Array.isArray(data?.zustaendigkeiten)) {
          this.geschaeft.zustaendigkeiten = data.zustaendigkeiten
        }
        // Nur Aktionen neu laden (Audit-Trail-Eintrag) – kein Full-Reload
        await this._ladeAktionenNur()
        showSuccess('Zuständigkeiten gespeichert')
        this.$emit('gespeichert')
      } catch (e) {
        showError('Fehler beim Speichern der Zuständigkeiten')
      }
    },
    /**
     * Übernimmt die von der geteilten NotizenListe gemeldete, vollständige
     * Notizen-Liste: ersetzt die Notiz-Aktionen in der lokalen Aktionsliste
     * (Nicht-Notiz-Aktionen bleiben), damit Zeitleiste und Realtime konsistent bleiben.
     */
    onNotizenGeaendert(neu) {
      if (!this.geschaeft) return
      const andere = (this.geschaeft.aktionen || []).filter(a => a.aktionTyp !== 'notiz')
      this.geschaeft.aktionen = [...andere, ...(Array.isArray(neu) ? neu : [])]
    },
    /** Wie onNotizenGeaendert, aber für die Sitzungsnotizen-Kategorie. */
    onSitzungsnotizenGeaendert(neu) {
      if (!this.geschaeft) return
      const andere = (this.geschaeft.aktionen || []).filter(a => a.aktionTyp !== 'sitzungsnotiz')
      this.geschaeft.aktionen = [...andere, ...(Array.isArray(neu) ? neu : [])]
    },
    /**
     * Eine in der Aktionszeitleiste wiederhergestellte Notiz zurück in die
     * Aktionsliste übernehmen (geloescht=false) — sie wandert damit von der
     * Zeitleiste zurück in ihre Notizen-Liste.
     */
    onNotizWiederhergestellt(data) {
      if (!this.geschaeft || !data?.id) return
      const idx = (this.geschaeft.aktionen || []).findIndex(a => a.id === data.id)
      if (idx < 0) return
      const kopie = [...this.geschaeft.aktionen]
      kopie[idx] = data
      this.geschaeft.aktionen = kopie
    },
    /**
     * Ob eine der Notizen-Listen einen offenen Editor mit ungespeicherten
     * Änderungen hat. Die Elternansicht prüft das, bevor sie den Dialog schliesst.
     */
    hatUngespeicherteNotizen() {
      return [this.$refs.notizenListe, this.$refs.sitzungsnotizenListe]
        .filter(Boolean)
        .some(r => typeof r.hatUngespeicherteAenderungen === 'function' && r.hatUngespeicherteAenderungen())
    },
    async beschlussNachWahl(val) {
      const hatteWert = !!this.beschlussWert
      this.beschlussWert = val
      if (!val) {
        if (hatteWert) await this.beschlussZuruecknehmen()
      } else {
        await this.beschlussSpeichern()
      }
    },
    beschlussSpeichern() {
      // Wert JETZT capturen (nicht erst in der Kette), sonst kann ein
      // dazwischen laufendes ladeDetail() den Wert zurücksetzen.
      const wert = this.beschlussWert
      // Serialisierung wie bei Notizen: verhindert doppelte POSTs bei
      // Blur während laufendem Debounce-Save.
      this._beschlussKette = (this._beschlussKette || Promise.resolve()).then(() => this._beschlussSpeichernIntern(wert))
      return this._beschlussKette
    },
    async _beschlussSpeichernIntern(wert) {
      if (!wert) return
      const code = wert.freitext ? '' : (wert.value || '')
      const text = wert.freitext ? (wert.label || '') : ''
      const schluessel = code + '\n' + text
      if (schluessel === this.beschlussZuletztGespeichert) return
      try {
        let data
        if (this.beschlussAktionId) {
          // Gleiche Eingabe-Session → bestehende Aktion aktualisieren
          const antwort = await axios.put(
            generateUrl(`/apps/parlwin/geschaefte/${this.geschaeftId}/beschluesse/${this.beschlussAktionId}`),
            { code, text }
          )
          data = antwort.data
          this._aktionAktualisieren(data)
        } else {
          const antwort = await axios.post(
            generateUrl(`/apps/parlwin/geschaefte/${this.geschaeftId}/beschluesse`),
            { code, text }
          )
          data = antwort.data
          this.beschlussAktionId = data?.id || null
          this._aktionHinzufuegen(data)
        }
        this.beschlussZuletztGespeichert = schluessel
        if (this.geschaeft) {
          this.geschaeft.letzterBeschluss = data
            ? { aktionCode: code, titel: data.titel || wert.label || code, text }
            : null
          this.geschaeft.fraktionsstatus = 'entschieden'
        }
        showSuccess('Beschluss gespeichert')
        this.$emit('gespeichert')
      } catch (e) {
        showError('Fehler beim Speichern des Beschlusses')
      }
    },
    async beschlussSessionBeenden() {
      // Fokus-Verlust = Eingabe-Session zu Ende: laufende Speicherung abwarten,
      // danach führt eine erneute Änderung zu einer NEUEN Aktion (History).
      await this._beschlussKette
      this.beschlussAktionId = null
    },
    async beschlussZuruecknehmen() {
      try {
        const { data } = await axios.delete(
          generateUrl(`/apps/parlwin/geschaefte/${this.geschaeftId}/beschluesse`)
        )
        this.beschlussWert = null
        this.beschlussAktionId = null
        this.beschlussZuletztGespeichert = null
        if (data) this._aktionHinzufuegen(data)
        if (this.geschaeft) {
          this.geschaeft.letzterBeschluss = null
          this.geschaeft.fraktionsstatus = null
        }
        showSuccess('Beschluss zurückgenommen')
        this.$emit('gespeichert')
      } catch (e) {
        showError('Fehler beim Zurücknehmen des Beschlusses')
      }
    },
    async votumSpeichern() {
      // Legacy: nicht mehr genutzt (Autosave). Wird beibehalten, falls etwas
      // ausserhalb dieser Komponente noch darauf verweisen sollte.
      await this.votumSofortSpeichern()
    },
    votumGeaendert(neuerHtml) {
      // Wird von PwWysiwyg via @update:model-value ausgelöst.
      // v-model setzt votumHtml bereits; hier nur Dirty-Flag + Autosave-Timer.
      if (typeof neuerHtml === 'string') {
        this.votumHtml = neuerHtml
      }
      this.votumDirty = true
      this.votumStatus = ''
      if (this.votumSpeicherTimer) clearTimeout(this.votumSpeicherTimer)
      this.votumSpeicherTimer = setTimeout(() => this.votumSpeichernJetzt(), 800)
    },
    async votumSofortSpeichern() {
      if (!this.votumDirty) return
      if (this.votumSpeicherTimer) {
        clearTimeout(this.votumSpeicherTimer)
        this.votumSpeicherTimer = null
      }
      await this.votumSpeichernJetzt()
    },
    async votumSpeichernJetzt() {
      this.votumSpeicherTimer = null
      if (!this.votumDirty) return
      this.votumDirty = false
      try {
        const { data } = await axios.put(generateUrl(`/apps/parlwin/geschaefte/${this.geschaeftId}/votum`), {
          text: this.votumHtml,
        })
        this.votumAktionId = data?.id || this.votumAktionId
        this.votumStatus = 'Gespeichert'
        this.$emit('gespeichert')
      } catch (e) {
        this.votumStatus = 'Fehler beim Speichern'
        this.votumDirty = true
        showError('Fehler beim Speichern des Votums')
      }
    },
    async votumArchivieren() {
      await this.votumSofortSpeichern()
      try {
        await axios.post(generateUrl(`/apps/parlwin/geschaefte/${this.geschaeftId}/votum/archivieren`))
        this.votumHtml = ''
        this.votumAktionId = null
        this.votumDirty = false
        this.votumStatus = 'Votum archiviert'
        showSuccess('Votum archiviert')
        await this.ladeDetail()
        this.$emit('gespeichert')
      } catch (e) {
        showError('Fehler beim Archivieren des Votums')
      }
    },
    _scrollContainer() {
      let el = this.$el?.parentElement
      while (el && el !== document.documentElement) {
        const { overflowY } = window.getComputedStyle(el)
        if (overflowY === 'auto' || overflowY === 'scroll') return el
        el = el.parentElement
      }
      return null
    },
    async _mitScrollSchutz(fn) {
      const c = this._scrollContainer()
      const top = c ? c.scrollTop : window.scrollY
      await fn()
      await this.$nextTick()
      if (c) c.scrollTop = top
      else window.scrollTo({ top, behavior: 'instant' })
    },
    formatieredatum(datum) {
      if (!datum) return ''
      try {
        return new Date(datum).toLocaleDateString('de-CH')
      } catch {
        return datum
      }
    },
    formatiereZeitpunkt(wert) {
      if (!wert) return '—'
      try {
        return new Date(wert).toLocaleString('de-CH')
      } catch {
        return wert
      }
    },
    fraktionsstatusLabel(status) {
      if (status === 'neu_zu_entscheiden') return 'Neu zu entscheiden'
      if (status === 'entschieden') return 'Entschieden'
      return 'Offen'
    },
    fraktionsstatusKlasse(status) {
      if (status === 'neu_zu_entscheiden') return 'offen'
      if (status === 'entschieden') return 'erledigt'
      return 'neutral'
    },
    synchronisiereHauptPersonKey() {
      if (this.ausgewaehltePersonKeys.includes(this.hauptPersonKey)) {
        return
      }
      this.hauptPersonKey = this.ausgewaehltePersonKeys[0] || ''
    },
  },
}
</script>
