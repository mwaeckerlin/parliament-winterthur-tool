<template>
  <section class="pw-view-content pw-fragestunde">
    <header class="pw-view-header">
      <h2 class="pw-view-title">Fragestunde</h2>
      <span class="pw-view-count">{{ fragen.length }}</span>
      <NcButton type="primary" @click="neueFrage">+ Neue Frage</NcButton>
      <NcButton type="secondary" @click="neueFragestunde">+ Neue Fragestunde</NcButton>
    </header>

    <div v-if="laden" class="pw-laden"><NcLoadingIcon :size="32" /></div>

    <template v-else>
      <!-- Die geplanten Fragestunden stehen oben, jede mit ihrer Frist und dem,
           was ihr zugeteilt ist. Fragen gibt es auch ohne sie. -->
      <article v-for="fs in fragestunden" :key="fs.id" class="pw-data-card pw-fragestunde-karte">
        <div class="pw-data-card-header">
          <div>
            <p class="pw-data-card-kicker">{{ datumLang(fs.datum) }}</p>
            <h3>{{ fs.titel }}</h3>
          </div>
          <span class="pw-status-neutral">{{ fs.fragen.length }} zugeteilt</span>
        </div>

        <p class="pw-hinweis pw-fragestunde-frist">
          Einzureichen bis {{ datumLang(fs.frist) }} beim Parlamentsdienst; höchstens {{ maxZeichen }} Zeichen je Frage
          (Art. 103 Abs. 2 der Organisationsverordnung).
          <a
            v-if="fs.geschaeft"
            :href="fs.geschaeft.url"
            target="_blank"
            rel="noopener noreferrer"
            class="pw-extern-link"
          >Geschäft {{ fs.geschaeft.nummer }}</a>
        </p>

        <p v-if="fs.mehrfachZugeteilt.length" class="pw-meldung warnung" data-mehrfach>
          {{ mehrfachText(fs) }} — jedes Mitglied reicht nur eine Frage ein.
        </p>
      </article>

      <NcEmptyContent
        v-if="!fragen.length"
        name="Noch keine Frage eingetragen"
        description="Fragen lassen sich jederzeit sammeln, auch ohne angesetzte Fragestunde. Die Fraktion bespricht sie in ihrer Sitzung und teilt sie später einer Fragestunde zu."
      />

      <ol v-else class="pw-fragen-liste">
          <li
            v-for="frage in fragen"
            :key="frage.id"
            class="pw-data-card pw-frage"
            tabindex="0"
            role="button"
            :aria-label="`Frage von ${frage.urheber.name || 'unbekannt'} bearbeiten`"
            @click="bearbeiten(frage)"
            @keydown.enter.prevent="bearbeiten(frage)"
            @keydown.space.prevent="bearbeiten(frage)"
          >
            <!-- Derselbe Kartenkopf wie bei Geschäften, Vorstössen und Sitzungen:
                 Kicker, Titel, Status-Pille. Die Frage IST der Titel. -->
            <div class="pw-data-card-header">
              <div>
                <p class="pw-data-card-kicker">{{ frage.urheber.name || 'Ohne Angabe' }}</p>
                <h3 class="pw-frage-text">{{ frage.frage || 'Noch kein Text' }}</h3>
              </div>
              <span :class="'pw-status-' + statusKlasse(frage.status)">{{ statusLabel(frage.status) }}</span>
            </div>
            <div class="pw-data-card-grid">
              <div class="pw-data-pair">
                <span>Fragestunde</span>
                <strong>{{ frage.fragestunde ? datumLang(frage.fragestunde.datum) : 'noch keiner zugeteilt' }}</strong>
              </div>
              <div class="pw-data-pair">
                <span>Einzureichen von</span>
                <strong>{{ frage.einreicher.name || 'noch nicht zugeteilt' }}</strong>
              </div>
              <div class="pw-data-pair">
                <span>Zeichen</span>
                <strong :class="{ 'pw-negativ': frage.zeichen > maxZeichen }">{{ frage.zeichen }} / {{ maxZeichen }}</strong>
              </div>
              <div class="pw-data-pair">
                <span>Eingetragen</span>
                <strong>{{ datumLang((frage.erstelltAm || '').slice(0, 10)) }}</strong>
              </div>
              <div v-if="frage.kommentar" class="pw-data-pair">
                <span>Kommentar</span>
                <strong>{{ frage.kommentar }}</strong>
              </div>
            </div>
            <div class="pw-data-card-aktionen">
              <PwLoeschen label="Frage löschen" @click="loeschen(frage)" />
            </div>
          </li>
      </ol>
    </template>

    <!-- EIN Formular für Erstellen UND Bearbeiten (geteilter Code): Beim
         Bearbeiten speichert jedes Feld sofort, deshalb gibt es keine Knöpfe und
         ✕ schliesst nur. Beim Erstellen sammelt die Maske die Eingaben, und erst
         «Speichern» legt die Frage an. Notizen hängen an einer bestehenden ID und
         erscheinen deshalb erst danach. -->
    <Teleport to="body">
      <div v-if="bearbeitung" class="pw-modal-overlay" @click.self="overlayKlick">
        <div class="pw-modal">
          <div class="pw-modal-kopf">
            <h3>{{ istEntwurf ? 'Neue Frage' : 'Frage bearbeiten' }}</h3>
            <button v-if="!istEntwurf" type="button" class="button pw-btn-schliessen" aria-label="Dialog schliessen" @click.stop="schliessen">✕</button>
          </div>
          <div class="pw-modal-body">
            <PwField label="Eingebracht von">
              <PwSelect
                :model-value="bearbeitung.urheber.key"
                :options="mitgliederOptionen"
                :clearable="true"
                placeholder="Mitglied wählen"
                @update:model-value="urheberGewaehlt"
              />
            </PwField>
            <PwField :label="`Frage (höchstens ${maxZeichen} Zeichen)`">
              <textarea
                v-model="bearbeitung.frage"
                class="pw-input pw-frage-eingabe"
                rows="6"
                placeholder="Die Frage, wie sie eingereicht wird"
                @change="feldSpeichern"
              ></textarea>
              <small class="pw-hinweis" :class="{ 'pw-negativ': zuLang }">
                {{ zeichen }} / {{ maxZeichen }} Zeichen<template v-if="zuLang"> — bitte kürzen</template>
              </small>
            </PwField>
            <PwField label="Kommentar">
              <textarea
                v-model="bearbeitung.kommentar"
                class="pw-input"
                rows="3"
                placeholder="Anmerkung für die Fraktionssitzung"
                @change="feldSpeichern"
              ></textarea>
            </PwField>
            <div class="pw-von-bis">
              <PwField label="Fragestunde">
                <PwSelect
                  :model-value="bearbeitung.fragestundeId || ''"
                  :options="fragestundenOptionen"
                  :clearable="true"
                  placeholder="Noch keiner zugeteilt"
                  @update:model-value="fragestundeGewaehlt"
                />
              </PwField>
              <PwField label="Einzureichen von">
                <PwSelect
                  :model-value="bearbeitung.einreicher.key"
                  :options="mitgliederOptionen"
                  :clearable="true"
                  placeholder="Meist die Person, die sie eingebracht hat"
                  @update:model-value="einreicherGewaehlt"
                />
              </PwField>
            </div>
            <div class="pw-von-bis">
              <PwField label="Status">
                <PwSelect
                  :model-value="bearbeitung.status"
                  :options="statusOptionen"
                  :clearable="false"
                  @update:model-value="statusGewaehlt"
                />
              </PwField>
            </div>
            <PwField v-if="!istEntwurf" label="Notizen">
              <NotizenListe
                :basis-url="'fragen/' + bearbeitung.id"
                :notizen="bearbeitung.notizen || []"
                :aktuelle-uid="aktuelleUid"
                @geaendert="v => bearbeitung.notizen = v"
              />
            </PwField>
            <small v-else class="pw-hinweis">Notizen stehen bereit, sobald die Frage gespeichert ist.</small>
          </div>
          <div v-if="istEntwurf" class="pw-modal-footer">
            <NcButton type="primary" :disabled="!bearbeitung.frage.trim() || zuLang || speichernLaeuft" @click="anlegen">Speichern</NcButton>
            <NcButton @click="abbrechen">Abbrechen</NcButton>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Neue Fragestunde: dieselbe Maske wie beim Bearbeiten ihres Datums. -->
    <Teleport to="body">
      <div v-if="neueStunde" class="pw-modal-overlay">
        <div class="pw-modal">
          <div class="pw-modal-kopf">
            <h3>Neue Fragestunde</h3>
          </div>
          <div class="pw-modal-body">
            <PwField label="Datum der Fragestunde *">
              <PwDatumInput v-model="neueStunde.datum" class="pw-input" />
            </PwField>
            <PwField label="Titel">
              <input v-model="neueStunde.titel" type="text" class="pw-input" :placeholder="titelVorschlag" />
            </PwField>
            <small class="pw-hinweis">
              Die Frist ergibt sich aus dem Datum: der Donnerstag davor
              (Art. 103 Abs. 2 der Organisationsverordnung).
            </small>
          </div>
          <div class="pw-modal-footer">
            <NcButton type="primary" :disabled="!neueStunde.datum || speichernLaeuft" @click="fragestundeAnlegen">Speichern</NcButton>
            <NcButton @click="neueStunde = null">Abbrechen</NcButton>
          </div>
        </div>
      </div>
    </Teleport>
  </section>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/style.css'
import { getCurrentUser } from '@nextcloud/auth'
import { vollerName, personKey } from '../utils'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import PwField from './PwField.vue'
import PwSelect from './PwSelect.vue'
import PwDatumInput from './PwDatumInput.vue'
import PwLoeschen from './PwLoeschen.vue'
import NotizenListe from './NotizenListe.vue'
import { subscribeRealtime } from '../realtime'

/** Höchstlänge einer Frage laut Organisationsverordnung (Art. 103 Abs. 2). */
const MAX_ZEICHEN = 1000

const STATUS = [
  { value: 'neu', label: 'Neu' },
  { value: 'besprochen', label: 'Besprochen' },
  { value: 'eingereicht', label: 'Eingereicht' },
  { value: 'zurueckgezogen', label: 'Zurückgezogen' },
]

const MONATE = [
  'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni',
  'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember',
]

/**
 * Die Fragestunde des Parlaments (F114): Die Fraktionsmitglieder tragen hier ihre
 * Fragen ein, die Fraktion bespricht sie in ihrer Sitzung, passt sie an und teilt
 * sie einander zum Einreichen zu.
 */
export default {
  name: 'Fragestunde',
  components: { NcButton, NcLoadingIcon, NcEmptyContent, PwField, PwSelect, PwDatumInput, PwLoeschen, NotizenListe },
  props: {
    mitglieder: { type: Array, default: () => [] },
  },
  data() {
    return {
      fragestunden: [],
      fragen: [],
      laden: true,
      bearbeitung: null,
      neueStunde: null,
      speichernLaeuft: false,
      maxZeichen: MAX_ZEICHEN,
      statusOptionen: STATUS,
      unsubRealtime: null,
    }
  },
  computed: {
    aktuelleUid() {
      return getCurrentUser()?.uid || ''
    },
    // Die Fragestunden zur Auswahl an einer Frage: freiwillig, deshalb mit
    // «Noch keiner zugeteilt» als Platzhalter statt einer Pflichtoption.
    fragestundenOptionen() {
      return this.fragestunden.map(fs => ({ label: fs.titel || this.datumLang(fs.datum), value: fs.id }))
    },
    istEntwurf() {
      return !!this.bearbeitung && !this.bearbeitung.id
    },
    zeichen() {
      return (this.bearbeitung?.frage || '').length
    },
    zuLang() {
      return this.zeichen > MAX_ZEICHEN
    },
    // Wählbare Personen: aktive Fraktionsmitglieder mit Nextcloud-Benutzer —
    // dieselbe Auswahl wie bei den Vorstössen.
    mitgliederOptionen() {
      return this.mitglieder
        .filter(m => m.aktiv !== false && !!(m.nextcloudUid || m.nextcloud_uid))
        .map(m => ({ label: vollerName(m), value: personKey(m) }))
        .filter(o => !!o.label)
        .sort((a, b) => a.label.localeCompare(b.label))
    },
    // Der angemeldete Benutzer als Person, sofern er ein Fraktionsmitglied ist.
    ichSelbst() {
      const uid = (getCurrentUser()?.uid || '').toLowerCase()
      if (!uid) { return { key: '', name: '' } }
      const m = this.mitglieder.find(x => ((x.nextcloudUid || x.nextcloud_uid || '').toLowerCase()) === uid)
      return m ? { key: personKey(m), name: vollerName(m) } : { key: '', name: '' }
    },
    titelVorschlag() {
      return this.neueStunde?.datum ? 'Fragestunde vom ' + this.datumLang(this.neueStunde.datum) : 'Fragestunde vom …'
    },
  },
  mounted() {
    this.laden = true
    this.ladeFragestunden()
    this.unsubRealtime = subscribeRealtime((e) => {
      if ((e?.type || '') === 'fragestunde.updated') { this.ladeFragestunden() }
    })
  },
  beforeUnmount() {
    if (this.unsubRealtime) { this.unsubRealtime() }
  },
  methods: {
    async ladeFragestunden() {
      try {
        const { data } = await axios.get(generateUrl('/apps/parlwin/fragestunden'))
        this.fragestunden = Array.isArray(data?.fragestunden) ? data.fragestunden : []
        this.fragen = Array.isArray(data?.fragen) ? data.fragen : []
      } catch (fehler) {
        console.error('Fragestunden laden fehlgeschlagen', fehler)
        showError('Die Fragestunden lassen sich gerade nicht laden.')
      } finally {
        this.laden = false
      }
    },
    datumLang(datum) {
      if (!datum) { return '—' }
      const [jahr, monat, tag] = String(datum).split('-').map(Number)
      if (!jahr || !monat || !tag) { return datum }
      return `${tag}. ${MONATE[monat - 1]} ${jahr}`
    },
    statusLabel(code) {
      return (STATUS.find(s => s.value === code) || STATUS[0]).label
    },
    // Dieselben drei Pillen wie bei den Vorstössen: erledigt, offen, abgelehnt,
    // sonst neutral.
    statusKlasse(code) {
      if (code === 'eingereicht') { return 'erledigt' }
      if (code === 'neu') { return 'offen' }
      if (code === 'zurueckgezogen') { return 'abgelehnt' }
      return 'neutral'
    },
    mehrfachText(fs) {
      const namen = fs.fragen
        .filter(f => f.einreicher.key && fs.mehrfachZugeteilt.includes(f.einreicher.key))
        .map(f => f.einreicher.name)
      return [...new Set(namen)].join(', ') + ' hat mehr als eine Frage zugeteilt'
    },
    neueFragestunde() {
      this.neueStunde = { datum: '', titel: '' }
    },
    async fragestundeAnlegen() {
      if (this.speichernLaeuft) { return }
      this.speichernLaeuft = true
      try {
        await axios.post(generateUrl('/apps/parlwin/fragestunden'), {
          datum: this.neueStunde.datum,
          titel: this.neueStunde.titel,
        })
        this.neueStunde = null
        await this.ladeFragestunden()
      } catch (fehler) {
        showError(fehler?.response?.data?.fehler || 'Die Fragestunde lässt sich nicht anlegen.')
      } finally {
        this.speichernLaeuft = false
      }
    },
    // «+ Neue Frage» öffnet dieselbe Maske wie das Bearbeiten (geteilter Code).
    // Angelegt wird noch nichts: Erst «Speichern» erzeugt die Frage. Urheber ist
    // vorbelegt, wer sie einträgt; eine Fragestunde braucht es nicht.
    neueFrage() {
      this.bearbeitung = {
        id: 0,
        fragestundeId: 0,
        frage: '',
        kommentar: '',
        status: 'neu',
        urheber: this.ichSelbst,
        einreicher: { key: '', name: '' },
        notizen: [],
      }
    },
    bearbeiten(frage) {
      this.bearbeitung = JSON.parse(JSON.stringify(frage))
    },
    schliessen() {
      this.bearbeitung = null
    },
    abbrechen() {
      this.bearbeitung = null
    },
    overlayKlick() {
      // Beim Erstellen schliesst ein Klick daneben nicht — sonst gingen Eingaben
      // verloren; beim Bearbeiten ist alles bereits gespeichert.
      if (!this.istEntwurf) { this.schliessen() }
    },
    person(key) {
      const option = this.mitgliederOptionen.find(o => o.value === key)
      return option ? { key: option.value, name: option.label } : { key: '', name: '' }
    },
    urheberGewaehlt(key) {
      this.bearbeitung.urheber = this.person(key)
      this.feldSpeichern()
    },
    einreicherGewaehlt(key) {
      this.bearbeitung.einreicher = this.person(key)
      this.feldSpeichern()
    },
    statusGewaehlt(wert) {
      this.bearbeitung.status = wert || 'neu'
      this.feldSpeichern()
    },
    fragestundeGewaehlt(wert) {
      this.bearbeitung.fragestundeId = Number(wert) || 0
      this.feldSpeichern()
    },
    async anlegen() {
      if (this.speichernLaeuft) { return }
      this.speichernLaeuft = true
      try {
        await axios.post(generateUrl('/apps/parlwin/fragen'), this.nutzdaten())
        this.bearbeitung = null
        await this.ladeFragestunden()
      } catch (fehler) {
        showError(fehler?.response?.data?.fehler || 'Die Frage lässt sich nicht speichern.')
      } finally {
        this.speichernLaeuft = false
      }
    },
    async feldSpeichern() {
      if (this.istEntwurf || !this.bearbeitung) { return }
      try {
        await axios.put(generateUrl('/apps/parlwin/fragen/' + this.bearbeitung.id), this.nutzdaten())
        await this.ladeFragestunden()
      } catch (fehler) {
        showError(fehler?.response?.data?.fehler || 'Die Änderung lässt sich nicht speichern.')
      }
    },
    nutzdaten() {
      return {
        fragestundeId: this.bearbeitung.fragestundeId || 0,
        frage: this.bearbeitung.frage,
        kommentar: this.bearbeitung.kommentar,
        status: this.bearbeitung.status,
        urheber: this.bearbeitung.urheber,
        einreicher: this.bearbeitung.einreicher,
      }
    },
    async loeschen(frage) {
      try {
        await axios.delete(generateUrl('/apps/parlwin/fragen/' + frage.id))
        await this.ladeFragestunden()
      } catch (fehler) {
        showError('Die Frage lässt sich nicht löschen.')
      }
    },
  },
}
</script>
