<template>
  <div class="pw-sitzungen">
    <div class="pw-toolbar">
      <h2>Sitzungen</h2>
      <div class="pw-filter">
        <label class="pw-filter-checkbox">
          <input v-model="nurKuenftige" type="checkbox" />
          Nur zukünftige Sitzungen
        </label>
      </div>
    </div>

    <div v-if="laden" class="pw-laden">Daten werden geladen...</div>

    <div v-else>
      <div
        v-for="sitzung in gefilterteSitzungen"
        :key="sitzung.id"
        class="pw-sitzung-karte"
        :class="{ 'pw-vergangen': istVergangen(sitzung.datum) }"
      >
        <div class="pw-sitzung-kopf" @click="toggleSitzung(sitzung.id)">
          <div class="pw-sitzung-datum">
            <strong>{{ formatieredatum(sitzung.datum) }}</strong>
            <span v-if="sitzung.zeitVon">{{ sitzung.zeitVon }}{{ sitzung.zeitBis ? ' – ' + sitzung.zeitBis : '' }}</span>
          </div>
          <div class="pw-sitzung-titel">{{ sitzung.titel }}</div>
          <div class="pw-sitzung-ort">{{ sitzung.ort }}</div>
          <a v-if="sitzung.url" :href="sitzung.url" target="_blank" @click.stop class="pw-extern-link">Extern</a>
          <span class="pw-toggle">{{ offeneSitzungen.includes(sitzung.id) ? '▲' : '▼' }}</span>
        </div>

        <!-- Aufklappbarer Bereich mit Traktanden und Bearbeitung -->
        <div v-if="offeneSitzungen.includes(sitzung.id)" class="pw-sitzung-details">
          <!-- Fraktionsbemerkungen zur Sitzung -->
          <div class="pw-sitzung-bemerkungen">
            <label>Bemerkungen zur Sitzung</label>
            <textarea
              v-model="sitzungBemerkungen[sitzung.id]"
              class="pw-textarea"
              rows="2"
              placeholder="Interne Bemerkungen zur Sitzung..."
            />
            <button type="button" class="button pw-btn-klein" @click="speichereSitzungBemerkungen(sitzung)">Speichern</button>
          </div>

          <!-- Traktanden -->
          <div class="pw-traktanden">
            <h4>Traktanden</h4>
            <div v-if="ladenTraktanden[sitzung.id]" class="pw-laden">Traktanden laden...</div>
            <div
              v-else
              v-for="t in traktanden[sitzung.id] || []"
              :key="t.id"
              class="pw-traktandum"
            >
              <div class="pw-traktandum-kopf">
                <span class="pw-traktandum-nr">{{ t.nummer }}.</span>
                <span class="pw-traktandum-titel">{{ t.titel }}</span>
                <span v-if="t.beschreibung" class="pw-traktandum-beschr">{{ t.beschreibung }}</span>
              </div>
              <div class="pw-traktandum-felder">
                <textarea
                  v-model="traktandumBemerkungen[t.id]"
                  class="pw-textarea-klein"
                  rows="2"
                  placeholder="Bemerkungen zum Traktandum..."
                />
                <!-- Notizen zum Traktandum -->
                <div class="pw-traktandum-notizen">
                  <div v-for="(n, idx) in parseTraktandumNotizen(t.id)" :key="idx" class="pw-notiz-klein">
                    <span class="pw-notiz-datum">{{ n.datum }}</span>
                    {{ n.text }}
                    <button type="button" class="button pw-btn-mini" @click="loesche_traktandum_notiz(t.id, idx)">✕</button>
                  </div>
                  <div class="pw-neue-notiz-klein">
                    <input
                      v-model="neueNotizen[t.id]"
                      type="text"
                      placeholder="Notiz hinzufügen..."
                      class="pw-input-klein"
                      @keyup.enter="fuegeNotizHinzu(t.id)"
                    />
                    <button type="button" class="button pw-btn-mini" @click="fuegeNotizHinzu(t.id)">+</button>
                  </div>
                </div>
                <button type="button" class="button pw-btn-klein" @click="speichereTraktandumFelder(t)">Speichern</button>
              </div>
            </div>
            <p v-if="(traktanden[sitzung.id] || []).length === 0 && !ladenTraktanden[sitzung.id]">
              Keine Traktanden vorhanden.
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { subscribeRealtime } from '../realtime'

export default {
  name: 'Sitzungsliste',
  data() {
    return {
      sitzungen: [],
      laden: true,
      nurKuenftige: true,
      offeneSitzungen: [],
      traktanden: {},
      ladenTraktanden: {},
      sitzungBemerkungen: {},
      traktandumBemerkungen: {},
      traktandumNotizen: {},
      neueNotizen: {},
      unsubRealtime: null,
    }
  },
  computed: {
    gefilterteSitzungen() {
      if (!this.nurKuenftige) return this.sitzungen
      const heute = new Date().toISOString().slice(0, 10)
      return this.sitzungen.filter(s => (s.datum || '') >= heute)
    },
  },
  mounted() {
    this.ladeSitzungen()
    this.unsubRealtime = subscribeRealtime(this.handleRealtimeEvent)
  },
  beforeUnmount() {
    if (this.unsubRealtime) {
      this.unsubRealtime()
      this.unsubRealtime = null
    }
  },
  methods: {
    async ladeSitzungen() {
      this.laden = true
      try {
        const { data } = await axios.get(generateUrl('/apps/parlwin/sitzungen'), {
          params: { limit: 100 },
        })
        this.sitzungen = data
        data.forEach(s => {
          this.sitzungBemerkungen[s.id] = s.bemerkungen || ''
        })
      } catch (e) {
        console.error('Fehler beim Laden der Sitzungen:', e)
      } finally {
        this.laden = false
      }
    },
    async toggleSitzung(id) {
      if (this.offeneSitzungen.includes(id)) {
        this.offeneSitzungen = this.offeneSitzungen.filter(i => i !== id)
      } else {
        this.offeneSitzungen.push(id)
        await this.ladeTraktandenFuerSitzung(id)
      }
    },
    async ladeTraktandenFuerSitzung(sitzungId, force = false) {
      if (this.traktanden[sitzungId] && !force) return
      this.ladenTraktanden = { ...this.ladenTraktanden, [sitzungId]: true }
      try {
        const { data } = await axios.get(
          generateUrl(`/apps/parlwin/sitzungen/${sitzungId}/traktanden`)
        )
        this.traktanden = { ...this.traktanden, [sitzungId]: data }
        data.forEach(t => {
          this.traktandumBemerkungen[t.id] = t.bemerkungen || ''
          this.traktandumNotizen[t.id] = this.parseNotizen(t.notizen)
        })
      } catch (e) {
        console.error('Fehler beim Laden der Traktanden:', e)
      } finally {
        this.ladenTraktanden = { ...this.ladenTraktanden, [sitzungId]: false }
      }
    },
    handleRealtimeEvent(event) {
      const type = event?.type || ''
      if (type === 'sync.completed' || type === 'sitzungen.updated') {
        this.ladeSitzungen()
      }

      if (type === 'traktanden.updated') {
        const sitzungId = Number(event?.payload?.sitzungId || 0)
        if (sitzungId > 0 && this.offeneSitzungen.includes(sitzungId)) {
          this.ladeTraktandenFuerSitzung(sitzungId, true)
        }
      }
    },
    parseNotizen(raw) {
      if (!raw) return []
      try {
        const arr = typeof raw === 'string' ? JSON.parse(raw) : raw
        return Array.isArray(arr) ? arr : []
      } catch {
        return []
      }
    },
    parseTraktandumNotizen(tId) {
      return this.traktandumNotizen[tId] || []
    },
    fuegeNotizHinzu(tId) {
      const text = (this.neueNotizen[tId] || '').trim()
      if (!text) return
      if (!this.traktandumNotizen[tId]) {
        this.traktandumNotizen[tId] = []
      }
      this.traktandumNotizen[tId].push({
        text,
        datum: new Date().toLocaleString('de-CH'),
      })
      this.neueNotizen = { ...this.neueNotizen, [tId]: '' }
    },
    loesche_traktandum_notiz(tId, idx) {
      if (this.traktandumNotizen[tId]) {
        this.traktandumNotizen[tId].splice(idx, 1)
      }
    },
    async speichereSitzungBemerkungen(sitzung) {
      try {
        await axios.put(
          generateUrl(`/apps/parlwin/sitzungen/${sitzung.id}`),
          { bemerkungen: this.sitzungBemerkungen[sitzung.id] || '' }
        )
      } catch (e) {
        console.error('Fehler beim Speichern der Sitzungsbemerkungen:', e)
      }
    },
    async speichereTraktandumFelder(traktandum) {
      try {
        const sitzungId = traktandum.sitzungId
        await axios.put(
          generateUrl(`/apps/parlwin/sitzungen/${sitzungId}/traktanden/${traktandum.id}`),
          {
            bemerkungen: this.traktandumBemerkungen[traktandum.id] || '',
            notizen: JSON.stringify(this.traktandumNotizen[traktandum.id] || []),
          }
        )
      } catch (e) {
        console.error('Fehler beim Speichern des Traktandums:', e)
      }
    },
    istVergangen(datum) {
      if (!datum) return false
      return datum < new Date().toISOString().slice(0, 10)
    },
    formatieredatum(datum) {
      if (!datum) return ''
      try {
        return new Date(datum).toLocaleDateString('de-CH', {
          weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
        })
      } catch {
        return datum
      }
    },
  },
}
</script>
