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
        </table>
      </div>

      <div class="pw-detail-abschnitt pw-fraktion">
        <h4>Fraktionsinterne Bearbeitung</h4>

        <div class="pw-form-zeile">
          <label>Zuständigkeit</label>
          <div class="pw-zustaendigkeiten-liste">
            <div class="pw-zustaendigkeiten-gruppe">
              <h5>Aktive Mitglieder</h5>
              <label v-for="m in aktiveMitglieder" :key="`aktiv-${m.id}`" class="pw-checkbox-row">
                <input type="checkbox" :value="personKey(m)" v-model="ausgewaehltePersonKeys" />
                <span>{{ vollerName(m) }}</span>
              </label>
            </div>
            <template v-if="inaktiveMitglieder.length > 0">
              <hr class="pw-zustaendigkeiten-trenner">
              <div class="pw-zustaendigkeiten-gruppe">
                <h5>Inaktive Mitglieder</h5>
                <label v-for="m in inaktiveMitglieder" :key="`inaktiv-${m.id}`" class="pw-checkbox-row">
                  <input type="checkbox" :value="personKey(m)" v-model="ausgewaehltePersonKeys" />
                  <span>{{ vollerName(m) }}</span>
                </label>
              </div>
            </template>
          </div>
          <small class="pw-hinweis">
            Falls mehrere Personen ausgewählt sind, wird die erste Auswahl intern als Hauptzuständigkeit geführt.
          </small>
          <button type="button" class="button pw-btn-klein" @click="speichereZustaendigkeiten">Zuständigkeiten speichern</button>
        </div>

        <div class="pw-form-zeile">
          <label>Notiz hinzufügen</label>
          <textarea v-model="neueNotiz" class="pw-textarea" rows="2" placeholder="Kommentar, Beobachtung, Hinweis" />
          <button type="button" class="button pw-btn-klein" @click="notizSpeichern">Notiz speichern</button>
        </div>

        <div class="pw-form-zeile">
          <label>Beschluss erfassen</label>
          <select v-model="beschlussCode" class="pw-select" :disabled="!geschaeft.fraktionssitzung?.beschlussSchreibbar">
            <option value="">— Beschluss wählen —</option>
            <option v-for="b in geschaeft.erlaubteBeschluesse || []" :key="b.code" :value="b.code">{{ b.label }}</option>
          </select>
          <textarea
            v-model="beschlussText"
            class="pw-textarea"
            rows="2"
            placeholder="Optionale Begründung"
            :disabled="!geschaeft.fraktionssitzung?.beschlussSchreibbar"
          />
          <button
            type="button"
            class="button pw-btn-klein"
            :disabled="!geschaeft.fraktionssitzung?.beschlussSchreibbar"
            @click="beschlussSpeichern"
          >
            Beschluss speichern
          </button>
          <small v-if="!geschaeft.fraktionssitzung?.beschlussSchreibbar" class="pw-hinweis">
            Im Fraktionssitzungsmodus darf nur der Protokollführer Beschlüsse erfassen.
          </small>
        </div>

        <div class="pw-form-zeile">
          <label>Votum im Rat</label>
          <textarea v-model="votumText" class="pw-textarea" rows="2" placeholder="Votum, Sprecher, Kernpunkte" />
          <button type="button" class="button pw-btn-klein" @click="votumSpeichern">Votum speichern</button>
        </div>
      </div>

      <div class="pw-detail-abschnitt">
        <h4>Aktionszeitleiste</h4>
        <div v-if="(geschaeft.aktionen || []).length === 0" class="pw-hinweis">Noch keine Aktionen vorhanden.</div>
        <div v-for="a in geschaeft.aktionen || []" :key="a.id" class="pw-timeline-eintrag">
          <div class="pw-timeline-kopf">
            <strong>{{ a.titel || a.aktionTyp }}</strong>
            <span>{{ a.erstelltAm }}</span>
          </div>
          <div class="pw-timeline-meta">
            <span>{{ a.autorName || a.autorUid || 'unbekannt' }}</span>
            <span v-if="a.aktionCode">· {{ a.aktionCode }}</span>
          </div>
          <div v-if="a.text" class="pw-timeline-text">{{ a.text }}</div>
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
import axios from '@nextcloud/axios'
import { subscribeRealtime } from '../realtime'

export default {
  name: 'GeschaeftDetail',
  props: {
    geschaeftId: { type: Number, required: true },
    mitglieder: { type: Array, default: () => [] },
  },
  emits: ['gespeichert'],
  data() {
    return {
      laden: false,
      geschaeft: null,
      neueNotiz: '',
      beschlussCode: '',
      beschlussText: '',
      votumText: '',
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
  },
  computed: {
    aktiveMitglieder() {
      return this.mitglieder
        .filter((mitglied) => mitglied.aktiv !== false)
        .sort((a, b) => this.vollerName(a).localeCompare(this.vollerName(b)))
    },
    inaktiveMitglieder() {
      return this.mitglieder
        .filter((mitglied) => mitglied.aktiv === false)
        .sort((a, b) => this.vollerName(a).localeCompare(this.vollerName(b)))
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
    vollerName(m) {
      return `${m.vorname || ''} ${m.name || ''}`.trim()
    },
    personKey(m) {
      const externId = m.externId || m.extern_id || ''
      if (externId) return `mitglied:${externId}`
      return `name:${this.vollerName(m)}`
    },
    personLabelByKey(key) {
      const member = this.mitglieder.find(m => this.personKey(m) === key)
      return member ? this.vollerName(member) : key
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
      if (type === 'sync.completed' || type === 'fraktionssitzung.updated' || type === 'fraktion.roles.updated') {
        this.ladeDetail()
        return
      }
      if (type.startsWith('geschaefte.') && (changedId === 0 || changedId === this.geschaeftId)) {
        this.ladeDetail()
      }
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

        await axios.put(generateUrl(`/apps/parlwin/geschaefte/${this.geschaeftId}`), {
          zustaendigkeiten,
          haupt_person_key: this.hauptPersonKey,
        })
        this.meldung = 'Zuständigkeiten gespeichert'
        this.fehler = false
        await this.ladeDetail()
        this.$emit('gespeichert')
      } catch (e) {
        this.meldung = 'Fehler beim Speichern der Zuständigkeiten'
        this.fehler = true
        console.error(e)
      }
    },
    async notizSpeichern() {
      if (!this.neueNotiz.trim()) return
      try {
        await axios.post(generateUrl(`/apps/parlwin/geschaefte/${this.geschaeftId}/notizen`), {
          text: this.neueNotiz,
        })
        this.neueNotiz = ''
        this.meldung = 'Notiz gespeichert'
        this.fehler = false
        await this.ladeDetail()
        this.$emit('gespeichert')
      } catch (e) {
        this.meldung = 'Fehler beim Speichern der Notiz'
        this.fehler = true
        console.error(e)
      }
    },
    async beschlussSpeichern() {
      if (!this.beschlussCode) return
      try {
        await axios.post(generateUrl(`/apps/parlwin/geschaefte/${this.geschaeftId}/beschluesse`), {
          code: this.beschlussCode,
          text: this.beschlussText,
        })
        this.beschlussCode = ''
        this.beschlussText = ''
        this.meldung = 'Beschluss gespeichert'
        this.fehler = false
        await this.ladeDetail()
        this.$emit('gespeichert')
      } catch (e) {
        this.meldung = 'Fehler beim Speichern des Beschlusses'
        this.fehler = true
        console.error(e)
      }
    },
    async votumSpeichern() {
      if (!this.votumText.trim()) return
      try {
        await axios.post(generateUrl(`/apps/parlwin/geschaefte/${this.geschaeftId}/voten`), {
          text: this.votumText,
        })
        this.votumText = ''
        this.meldung = 'Votum gespeichert'
        this.fehler = false
        await this.ladeDetail()
        this.$emit('gespeichert')
      } catch (e) {
        this.meldung = 'Fehler beim Speichern des Votums'
        this.fehler = true
        console.error(e)
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
