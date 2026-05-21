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
          <textarea v-model="neueNotiz" class="pw-textarea" rows="2" placeholder="Kommentar, Beobachtung, Hinweis" />
          <button type="button" class="button pw-btn-klein" @click="notizSpeichern">Notiz speichern</button>
        </div>

        <div class="pw-form-zeile">
          <label>Beschluss erfassen</label>
          <NcSelect
            :model-value="beschlussOption"
            :options="beschlussOptionen"
            :clearable="true"
            :disabled="!geschaeft.fraktionssitzung?.beschlussSchreibbar"
            placeholder="—"
            label="label"
            @update:model-value="aenderungBeschluss($event)"
          />
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
          <label>Dokumente zum Geschäft</label>
          <GeschaeftDokumente
            :geschaeft-id="geschaeftId"
            :geschaeft-nummer="geschaeft.nummer || ''"
          />
        </div>
      </div>

      <div class="pw-detail-abschnitt">
        <h4>Aktionszeitleiste</h4>
        <div v-if="zeitleisteAktionen.length === 0" class="pw-hinweis">Noch keine Aktionen vorhanden.</div>
        <div v-for="a in zeitleisteAktionen" :key="a.id" class="pw-timeline-eintrag">
          <div class="pw-timeline-kopf">
            <strong>{{ a.titel || a.aktionTyp }}</strong>
            <span class="pw-timeline-zeit">{{ formatiereZeitpunkt(a.erstelltAm) }}</span>
          </div>
          <div class="pw-timeline-meta">
            <span class="pw-timeline-autor">{{ a.autorName || a.autorUid || 'unbekannt' }}</span>
            <span v-if="a.aktionCode"> · {{ a.aktionCode }}</span>
          </div>
          <div v-if="a.text && a.aktionTyp === 'votum'" class="pw-timeline-text pw-timeline-html" v-html="a.text" />
          <div v-else-if="a.text" class="pw-timeline-text">{{ a.text }}</div>
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
import NcSelect from '@nextcloud/vue/components/NcSelect'
import PwMultiSelect from './PwMultiSelect.vue'
import PwWysiwyg from './PwWysiwyg.vue'
import GeschaeftDokumente from './GeschaeftDokumente.vue'
import { subscribeRealtime } from '../realtime'

export default {
  name: 'GeschaeftDetail',
  components: { NcSelect, PwMultiSelect, PwWysiwyg, GeschaeftDokumente },
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
      votumHtml: '',
      votumAktionId: null,
      votumSpeicherTimer: null,
      votumStatus: '',
      votumDirty: false,
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
    beschlussOption() {
      if (!this.beschlussCode) return null
      const treffer = this.beschlussOptionen.find((o) => o.value === this.beschlussCode)
      if (treffer) return treffer
      const lb = this.geschaeft?.letzterBeschluss
      return { label: lb?.titel || this.beschlussCode, value: this.beschlussCode }
    },
    zeitleisteAktionen() {
      // Das aktuell aktive Votum (entscheidGueltig=true) wird im Editor
      // angezeigt und soll nicht zusätzlich als Timeline-Eintrag
      // erscheinen – erst nach dem Archivieren.
      const alle = this.geschaeft?.aktionen || []
      return alle.filter(a => !(a.aktionTyp === 'votum' && a.entscheidGueltig))
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
        this.beschlussCode = lb?.aktionCode || ''
        this.beschlussText = lb?.text || ''
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
    async aenderungBeschluss(option) {
      const code = option?.value || ''
      try {
        if (code) {
          await axios.post(generateUrl(`/apps/parlwin/geschaefte/${this.geschaeftId}/beschluesse`), {
            code,
            text: this.beschlussText,
          })
          this.meldung = 'Beschluss gespeichert'
        } else {
          await axios.delete(generateUrl(`/apps/parlwin/geschaefte/${this.geschaeftId}/beschluesse`))
          this.beschlussText = ''
          this.meldung = 'Beschluss zurückgenommen'
        }
        this.fehler = false
        await this.ladeDetail()
        this.$emit('gespeichert')
      } catch (e) {
        this.meldung = 'Fehler beim Speichern des Beschlusses'
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
