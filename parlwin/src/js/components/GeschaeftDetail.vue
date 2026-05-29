<template>
  <div class="pw-geschaeft-detail">
    <div v-if="laden" class="pw-laden">Lade Geschäft...</div>
    <div v-else-if="!geschaeft" class="pw-fehler">Geschäft konnte nicht geladen werden.</div>

    <template v-else>
      <header class="pw-detail-header">
        <div>
          <p class="pw-detail-kicker">{{ geschaeft.nummer || 'Geschäft' }}</p>
          <h3>{{ geschaeft.titel }}</h3>
        </div>
        <span :class="'pw-status-' + fraktionsstatusKlasse(geschaeft.fraktionsstatus)">
          {{ fraktionsstatusLabel(geschaeft.fraktionsstatus) }}
        </span>
      </header>

      <div class="pw-detail-abschnitt pw-oeffentlich">
        <h4>Öffentliche Informationen</h4>
        <table class="pw-info-tabelle">
          <tbody>
            <tr><th>Nummer</th><td>{{ geschaeft.nummer }}</td></tr>
            <tr><th>Typ</th><td>{{ geschaeft.typ }}</td></tr>
            <tr><th>Status</th><td>{{ geschaeft.status }}</td></tr>
            <tr><th>Fraktionsstatus</th><td>{{ fraktionsstatusLabel(geschaeft.fraktionsstatus) }}</td></tr>
            <tr><th>Datum</th><td>{{ formatieredatum(geschaeft.datum) }}</td></tr>
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

        <div class="pw-form-zeile">
          <label>Notiz hinzufügen</label>
          <textarea
            v-model="neueNotiz"
            class="pw-textarea"
            rows="2"
            placeholder="Kommentar, Beobachtung, Hinweis"
            @input="notizDebounce"
            @blur="notizSpeichernBeiBlur"
          />
        </div>

        <div class="pw-form-zeile">
          <label>Beschluss erfassen</label>
          <BeschlussWidget
            :model-value="beschlussWert"
            :options="beschlussOptionen"
            :disabled="!geschaeft.fraktionssitzung?.beschlussSchreibbar"
            @update:model-value="beschlussNachWahl"
          />
          <small v-if="!geschaeft.fraktionssitzung?.beschlussSchreibbar" class="pw-hinweis">
            Im Fraktionssitzungsmodus darf nur der Protokollführer Beschlüsse erfassen.
          </small>
        </div>

        <div class="pw-form-zeile">
          <label>Dokumente zum Geschäft</label>
          <GeschaeftDokumente
            :geschaeft-id="geschaeftId"
            :geschaeft-nummer="geschaeft.nummer || ''"
          />
        </div>
      </div>

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
                @click="$emit('oeffneTraktandum', e._sitzungId)"
                @keydown.enter.prevent="$emit('oeffneTraktandum', e._sitzungId)"
              >{{ e.text }}</span>
              <span v-else class="pw-timeline-text">{{ e.text }}</span>
            </template>
            <template v-else-if="e.aktionTyp === 'notiz' && istEigeneAktion(e)">
              <div v-if="bearbeitenNotizId === e.id" class="pw-notiz-bearbeiten-zeile">
                <textarea
                  ref="notizBearbeitenInput"
                  v-model="bearbeitenNotizText"
                  class="pw-textarea"
                  rows="2"
                  @keydown.escape="notizBearbeitenAbbrechen"
                />
                <div class="pw-notiz-bearbeiten-aktionen">
                  <button type="button" class="button pw-btn-mini" @click="notizBearbeitenSpeichern(e)">✓</button>
                  <button type="button" class="button pw-btn-mini" @click="notizBearbeitenAbbrechen">✕</button>
                </div>
              </div>
              <span
                v-else-if="e.text"
                class="pw-timeline-text pw-notiz-text-klickbar"
                role="button"
                tabindex="0"
                title="Klicken zum Bearbeiten"
                @click="notizBearbeitenStarten(e, $event)"
                @keydown.enter.prevent="notizBearbeitenStarten(e)"
              >{{ e.text }}</span>
            </template>
            <div v-else-if="e.text && e.aktionTyp === 'votum'" class="pw-timeline-text pw-timeline-html" v-html="e.text" />
            <template v-else-if="e.titel && e.aktionTyp !== 'notiz'">
              <span class="pw-timeline-text">{{ e.titel }}</span>
              <span v-if="e.text" class="pw-timeline-detail">{{ e.text }}</span>
            </template>
            <span v-else-if="e.text" class="pw-timeline-text">{{ e.text }}</span>
          </div>
          <div class="pw-timeline-aktionen">
            <button
              v-if="e._type === 'aktion' && e.aktionTyp === 'notiz' && istEigeneAktion(e) && bearbeitenNotizId !== e.id"
              type="button"
              class="button pw-btn-mini"
              title="Notiz löschen"
              @click="notizLoeschen(e)"
            >✕</button>
          </div>
        </div>
      </div>

      <div class="pw-detail-aktionen">
        <span v-if="meldung" class="pw-meldung" :class="fehler ? 'fehler' : 'erfolg'">{{ meldung }}</span>
      </div>
    </template>
  </div>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import { getCurrentUser } from '@nextcloud/auth'
import { vollerName, personKey } from '../utils'
import axios from '@nextcloud/axios'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import PwMultiSelect from './PwMultiSelect.vue'
import PwWysiwyg from './PwWysiwyg.vue'
import GeschaeftDokumente from './GeschaeftDokumente.vue'
import BeschlussWidget from './BeschlussWidget.vue'
import { subscribeRealtime } from '../realtime'

export default {
  name: 'GeschaeftDetail',
  components: { NcSelect, PwMultiSelect, PwWysiwyg, GeschaeftDokumente, BeschlussWidget },
  props: {
    geschaeftId: { type: Number, required: true },
    mitglieder: { type: Array, default: () => [] },
    traktandumKontext: { type: Object, default: null },
  },
  emits: ['gespeichert', 'oeffneTraktandum'],
  data() {
    return {
      laden: false,
      geschaeft: null,
      neueNotiz: '',
      beschlussWert: null,
      bearbeitenNotizId: null,
      bearbeitenNotizText: '',
      zeitleisteReihenfolge: [],
      dragZeitleisteVonIdx: -1,
      dragZeitleisteUeberIdx: -1,
      votumHtml: '',
      votumAktionId: null,
      votumSpeicherTimer: null,
      votumStatus: '',
      votumDirty: false,
      notizTimer: null,
      notizAktionId: null,
      ausgewaehltePersonKeys: [],
      hauptPersonKey: '',
      meldung: '',
      fehler: false,
      unsubRealtime: null,
    }
  },
  created() {
    this.unsubRealtime = subscribeRealtime(this.handleRealtimeEvent)
  },
  beforeUnmount() {
    if (this.unsubRealtime) {
      this.unsubRealtime()
      this.unsubRealtime = null
    }
    if (this.notizTimer) {
      clearTimeout(this.notizTimer)
      this.notizTimer = null
      this.notizSpeichern()
    }
    if (this.votumSpeicherTimer) {
      clearTimeout(this.votumSpeicherTimer)
      this.votumSpeicherTimer = null
      // Letzten Stand sofort sichern.
      this.votumSpeichernJetzt()
    }
  },
  computed: {
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
    zeitleisteAktionen() {
      const alle = this.geschaeft?.aktionen || []
      return alle.filter(a => !(a.aktionTyp === 'votum' && a.entscheidGueltig))
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
    vollerName,
    personKey,
    personLabelByKey(key) {
      const member = this.mitglieder.find(m => this.personKey(m) === key)
      return member ? this.vollerName(member) : key
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
      this.laden = true
      try {
        const { data } = await axios.get(generateUrl(`/apps/parlwin/geschaefte/${this.geschaeftId}`))
        this.geschaeft = data

        const zustaendigkeiten = data.zustaendigkeiten || []
        this.ausgewaehltePersonKeys = zustaendigkeiten.map(z => z.personKey)
        const haupt = zustaendigkeiten.find(z => z.istHaupt)
        this.hauptPersonKey = haupt?.personKey || ''
        this.synchronisiereHauptPersonKey()
        const lb = data.letzterBeschluss || null
        if (!lb) {
          this.beschlussWert = null
        } else if (lb.aktionCode) {
          this.beschlussWert = { label: lb.titel || lb.aktionCode, value: lb.aktionCode }
        } else {
          this.beschlussWert = { label: lb.text || '', value: '', freitext: true }
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
    async speichereZustaendigkeiten() {
      try {
        this.synchronisiereHauptPersonKey()
        const zustaendigkeiten = this.ausgewaehltePersonKeys.map(key => {
          const member = this.mitglieder.find(m => this.personKey(m) === key)
          return {
            mitgliedExternId: member?.externId || member?.extern_id || '',
            personName: member ? this.vollerName(member) : this.personLabelByKey(key),
          }
        })
        const { data } = await axios.put(generateUrl(`/apps/parlwin/geschaefte/${this.geschaeftId}`), {
          zustaendigkeiten,
          haupt_person_key: this.hauptPersonKey,
        })
        if (this.geschaeft && Array.isArray(data?.zustaendigkeiten)) {
          this.geschaeft.zustaendigkeiten = data.zustaendigkeiten
        }
        // Nur Aktionen neu laden (Audit-Trail-Eintrag) – kein Full-Reload
        await this._ladeAktionenNur()
        this.meldung = 'Zuständigkeiten gespeichert'
        this.fehler = false
        this.$emit('gespeichert')
      } catch (e) {
        this.meldung = 'Fehler beim Speichern der Zuständigkeiten'
        this.fehler = true
        console.error(e)
      }
    },
    istEigeneAktion(a) {
      const uid = (getCurrentUser()?.uid || '').toLowerCase()
      return !!uid && (a.autorUid || '').toLowerCase() === uid
    },
    notizDebounce() {
      if (this.notizTimer) clearTimeout(this.notizTimer)
      this.notizTimer = setTimeout(() => { this.notizSpeichern(); this.notizTimer = null }, 5000)
    },
    async notizSpeichernBeiBlur() {
      if (this.notizTimer) { clearTimeout(this.notizTimer); this.notizTimer = null }
      await this.notizSpeichern()
      // Feld erst nach blur leeren und Session zurücksetzen
      this.neueNotiz = ''
      this.notizAktionId = null
    },
    async beschlussNachWahl(val) {
      const hatteWert = !!this.beschlussWert
      this.beschlussWert = val
      if (!val) {
        if (hatteWert) await this.beschlussZuruecknehmen()
      } else if (!val.freitext) {
        await this.beschlussSpeichern()
      }
      // freitext: wait for blur via beschlussAutoSpeichern
    },
    beschlussFreitextInput() {
      if (!this.beschlussWert?.label) {
        this.beschlussWert = null
      }
    },
    async beschlussAutoSpeichern() {
      if (this.beschlussWert?.freitext && this.beschlussWert.label) {
        await this.beschlussSpeichern()
      }
    },
    notizBearbeitenStarten(a, clickEvent) {
      this.bearbeitenNotizId = a.id
      this.bearbeitenNotizText = a.text || ''
      let caretOffset = (a.text || '').length
      if (clickEvent) {
        const pos = document.caretPositionFromPoint?.(clickEvent.clientX, clickEvent.clientY)
        if (pos) {
          caretOffset = pos.offset
        } else {
          const range = document.caretRangeFromPoint?.(clickEvent.clientX, clickEvent.clientY)
          if (range) caretOffset = range.startOffset
        }
      }
      this.$nextTick(() => {
        const el = Array.isArray(this.$refs.notizBearbeitenInput)
          ? this.$refs.notizBearbeitenInput[0]
          : this.$refs.notizBearbeitenInput
        if (el) {
          el.focus()
          el.setSelectionRange(caretOffset, caretOffset)
        }
      })
    },
    notizBearbeitenAbbrechen() {
      this.bearbeitenNotizId = null
      this.bearbeitenNotizText = ''
    },
    async notizBearbeitenSpeichern(a) {
      const text = (this.bearbeitenNotizText || '').trim()
      if (!text) return
      try {
        const { data } = await axios.put(generateUrl(`/apps/parlwin/geschaefte/${this.geschaeftId}/notizen/${a.id}`), { text })
        this.notizBearbeitenAbbrechen()
        this._aktionAktualisieren(data)
      } catch (e) {
        this.meldung = 'Fehler beim Bearbeiten der Notiz'
        this.fehler = true
        console.error(e)
      }
    },
    async notizLoeschen(a) {
      try {
        await axios.delete(generateUrl(`/apps/parlwin/geschaefte/${this.geschaeftId}/notizen/${a.id}`))
        this._aktionEntfernen(a.id)
      } catch (e) {
        this.meldung = 'Fehler beim Löschen der Notiz'
        this.fehler = true
        console.error(e)
      }
    },
    async notizSpeichern() {
      const text = (this.neueNotiz || '').trim()
      if (!text) return
      try {
        if (this.notizAktionId) {
          // Gleiche Notiz in derselben Eingabe-Session aktualisieren
          const { data } = await axios.put(
            generateUrl(`/apps/parlwin/geschaefte/${this.geschaeftId}/notizen/${this.notizAktionId}`),
            { text }
          )
          this._aktionAktualisieren(data)
        } else {
          // Erste Speicherung → neue Aktion anlegen
          const { data } = await axios.post(
            generateUrl(`/apps/parlwin/geschaefte/${this.geschaeftId}/notizen`),
            { text }
          )
          this.notizAktionId = data.id
          this._aktionHinzufuegen(data)
        }
        this.meldung = 'Notiz gespeichert'
        this.fehler = false
        this.$emit('gespeichert')
        // Kein ladeDetail() / Feld bleibt – erst blur löscht es
      } catch (e) {
        this.meldung = 'Fehler beim Speichern der Notiz'
        this.fehler = true
        console.error(e)
      }
    },
    async beschlussSpeichern() {
      if (!this.beschlussWert) return
      const code = this.beschlussWert.freitext ? '' : (this.beschlussWert.value || '')
      const text = this.beschlussWert.freitext ? (this.beschlussWert.label || '') : ''
      try {
        const { data } = await axios.post(
          generateUrl(`/apps/parlwin/geschaefte/${this.geschaeftId}/beschluesse`),
          { code, text }
        )
        this._aktionHinzufuegen(data)
        if (this.geschaeft) {
          this.geschaeft.letzterBeschluss = data
            ? { aktionCode: code, titel: data.titel || this.beschlussWert.label || code, text }
            : null
          this.geschaeft.fraktionsstatus = 'entschieden'
        }
        this.meldung = 'Beschluss gespeichert'
        this.fehler = false
        this.$emit('gespeichert')
      } catch (e) {
        this.meldung = 'Fehler beim Speichern des Beschlusses'
        this.fehler = true
        console.error(e)
      }
    },
    async beschlussZuruecknehmen() {
      try {
        const { data } = await axios.delete(
          generateUrl(`/apps/parlwin/geschaefte/${this.geschaeftId}/beschluesse`)
        )
        this.beschlussWert = null
        if (data) this._aktionHinzufuegen(data)
        if (this.geschaeft) {
          this.geschaeft.letzterBeschluss = null
          this.geschaeft.fraktionsstatus = null
        }
        this.meldung = 'Beschluss zurückgenommen'
        this.fehler = false
        this.$emit('gespeichert')
      } catch (e) {
        this.meldung = 'Fehler beim Zurücknehmen des Beschlusses'
        this.fehler = true
        console.error(e)
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
        this.fehler = false
        this.$emit('gespeichert')
      } catch (e) {
        this.votumStatus = 'Fehler beim Speichern'
        this.meldung = 'Fehler beim Speichern des Votums'
        this.fehler = true
        this.votumDirty = true
        console.error(e)
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
        this.meldung = 'Votum archiviert'
        this.fehler = false
        await this.ladeDetail()
        this.$emit('gespeichert')
      } catch (e) {
        this.meldung = 'Fehler beim Archivieren des Votums'
        this.fehler = true
        console.error(e)
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
    formatiereZeitpunkt(wert) {
      if (!wert) return '—'
      try {
        return new Date(wert).toLocaleString('de-CH')
      } catch {
        return wert
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
