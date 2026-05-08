<template>
  <div class="pw-geschaeft-detail">
    <!-- Öffentliche Informationen (nur Lesezugriff) -->
    <div class="pw-detail-abschnitt pw-oeffentlich">
      <h4>Öffentliche Informationen</h4>
      <table class="pw-info-tabelle">
        <tr><th>Nummer</th><td>{{ geschaeft.nummer }}</td></tr>
        <tr><th>Typ</th><td>{{ geschaeft.typ }}</td></tr>
        <tr><th>Status</th><td>{{ geschaeft.status }}</td></tr>
        <tr><th>Datum</th><td>{{ formatieredatum(geschaeft.datum) }}</td></tr>
        <tr v-if="geschaeft.url">
          <th>Link</th>
          <td><a :href="geschaeft.url" target="_blank">Auf Parlamentswebseite öffnen ↗</a></td>
        </tr>
      </table>
    </div>

    <!-- Fraktionsinterne Felder (bearbeitbar) -->
    <div class="pw-detail-abschnitt pw-fraktion">
      <h4>Fraktionsinterne Bearbeitung</h4>

      <div class="pw-form-zeile">
        <label>Zuständige Person</label>
        <select v-model="felder.zustaendigePerson" class="pw-select">
          <option value="">— keine —</option>
          <option
            v-for="m in mitglieder"
            :key="m.id"
            :value="m.vorname + ' ' + m.name"
          >
            {{ m.vorname }} {{ m.name }}
          </option>
        </select>
      </div>

      <div class="pw-form-zeile">
        <label>Antrag an die Fraktion</label>
        <textarea v-model="felder.antragFraktion" class="pw-textarea" rows="3" />
      </div>

      <div class="pw-form-zeile">
        <label>Entscheid der Fraktion</label>
        <select v-model="felder.entscheidFraktion" class="pw-select">
          <option value="">— kein Entscheid —</option>
          <option value="Zustimmung">Zustimmung</option>
          <option value="Ablehnung">Ablehnung</option>
          <option value="Enthaltung">Enthaltung</option>
          <option value="Offen">Offen</option>
          <option value="Keine Stellungnahme">Keine Stellungnahme</option>
        </select>
      </div>

      <div class="pw-form-zeile">
        <label>Bemerkungen</label>
        <textarea v-model="felder.bemerkungen" class="pw-textarea" rows="4" />
      </div>

      <!-- Notizen -->
      <div class="pw-notizen">
        <h5>Notizen</h5>
        <div v-for="(notiz, idx) in notizen" :key="idx" class="pw-notiz">
          <span class="pw-notiz-datum">{{ notiz.datum }}</span>
          <span class="pw-notiz-text">{{ notiz.text }}</span>
          <button class="pw-btn-klein pw-btn-loeschen" @click="loescheNotiz(idx)">✕</button>
        </div>
        <div class="pw-neue-notiz">
          <input
            v-model="neueNotiz"
            type="text"
            placeholder="Neue Notiz..."
            class="pw-input"
            @keyup.enter="fuegeNotizHinzu"
          />
          <button class="pw-btn-klein" @click="fuegeNotizHinzu">+</button>
        </div>
      </div>
    </div>

    <!-- Aktionen -->
    <div class="pw-detail-aktionen">
      <button class="button primary" :disabled="speichern" @click="speichereFelder">
        {{ speichern ? 'Wird gespeichert...' : 'Speichern' }}
      </button>
      <span v-if="meldung" class="pw-meldung" :class="fehler ? 'fehler' : 'erfolg'">{{ meldung }}</span>
    </div>
  </div>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'

export default {
  name: 'GeschaeftDetail',
  props: {
    geschaeft: { type: Object, required: true },
    mitglieder: { type: Array, default: () => [] },
  },
  emits: ['gespeichert'],
  data() {
    return {
      felder: {
        bemerkungen: this.geschaeft.bemerkungen || '',
        zustaendigePerson: this.geschaeft.zustaendigePerson || '',
        antragFraktion: this.geschaeft.antragFraktion || '',
        entscheidFraktion: this.geschaeft.entscheidFraktion || '',
      },
      notizen: this.parseNotizen(this.geschaeft.notizen),
      neueNotiz: '',
      speichern: false,
      meldung: '',
      fehler: false,
    }
  },
  methods: {
    parseNotizen(raw) {
      if (!raw) return []
      try {
        const arr = typeof raw === 'string' ? JSON.parse(raw) : raw
        return Array.isArray(arr) ? arr : []
      } catch {
        return []
      }
    },
    fuegeNotizHinzu() {
      if (!this.neueNotiz.trim()) return
      this.notizen.push({
        text: this.neueNotiz.trim(),
        datum: new Date().toLocaleString('de-CH'),
      })
      this.neueNotiz = ''
    },
    loescheNotiz(idx) {
      this.notizen.splice(idx, 1)
    },
    async speichereFelder() {
      this.speichern = true
      this.meldung = ''
      try {
        const nutzlast = {
          ...this.felder,
          notizen: JSON.stringify(this.notizen),
        }
        const { data } = await axios.put(
          generateUrl(`/apps/parliamentwinterthur/geschaefte/${this.geschaeft.id}`),
          nutzlast
        )
        this.meldung = 'Gespeichert'
        this.fehler = false
        this.$emit('gespeichert', data)
      } catch (e) {
        this.meldung = 'Fehler beim Speichern'
        this.fehler = true
        console.error(e)
      } finally {
        this.speichern = false
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
  },
}
</script>
