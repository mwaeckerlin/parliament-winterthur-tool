<template>
  <Teleport v-if="filterReady" to="#pw-search-slot">
    <NcTextField
      v-model="suche"
      label="Suche"
      placeholder="Nr. oder Titel"
      trailing-button-icon="close"
      :show-trailing-button="!!suche"
      @trailing-button-click="suche = ''"
    />
  </Teleport>
  <Teleport v-if="filterReady" to="#pw-filter-slot">
    <div class="pw-filter-body">
      <NcCheckboxRadioSwitch v-model="nurKuenftige" type="switch">
        Nur zukünftige Sitzungen
      </NcCheckboxRadioSwitch>
    </div>
  </Teleport>

  <section class="pw-view-content pw-sitzungen">
    <header class="pw-view-header">
      <h2 class="pw-view-title">Sitzungen</h2>
      <span class="pw-view-count">{{ gefilterteSitzungen.length }}</span>
      <NcActions :menu-name="verfuegbareTypen.length ? '+ Neue Sitzung' : '+ Neue Sitzung'" type="primary">
        <NcActionButton v-if="!verfuegbareTypen.length" :disabled="true">
          Keine Sitzungstypen vorhanden
        </NcActionButton>
        <NcActionButton
          v-for="typ in verfuegbareTypen"
          :key="typ.id"
          @click="oeffneNeuDialogMitTyp(typ)"
        >
          {{ typ.name }}
        </NcActionButton>
      </NcActions>
    </header>
    <div v-if="laden" class="pw-laden"><NcLoadingIcon :size="32" /></div>

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

        <!-- Aufklappbarer Bereich mit Traktanden -->
        <div v-if="offeneSitzungen.includes(sitzung.id)" class="pw-sitzung-details">
          <!-- Notizen zur Sitzung (ersetzt frühere „Bemerkungen zur Sitzung“). -->
          <div class="pw-sitzung-notizen">
            <h4>Notizen zur Sitzung</h4>
            <NotizenListe
              :model-value="sitzungNotizen[sitzung.id] || []"
              placeholder="Notiz zur Sitzung hinzufügen…"
              @update:model-value="speichereSitzungNotizen(sitzung, $event)"
            />
          </div>

          <!-- Traktanden – Darstellung wie Geschäftsliste-Hauptseite -->
          <div class="pw-traktanden">
            <h4>Traktanden</h4>
            <div v-if="ladenTraktanden[sitzung.id]" class="pw-laden">Traktanden laden...</div>
            <template v-else>
              <div class="pw-table-wrap pw-table-desktop">
                <table class="pw-tabelle pw-tabelle-geschaefte pw-tabelle-traktanden" lang="de">
                  <thead>
                    <tr>
                      <th class="pw-col-nr">Tr.</th>
                      <th class="pw-col-nr">Nr.</th>
                      <th class="pw-col-titel">Titel</th>
                      <th class="pw-col-typ">Typ</th>
                      <th class="pw-col-status">Status</th>
                      <th class="pw-col-datum">Datum</th>
                      <th class="pw-col-zustaendig">Zuständig</th>
                      <th class="pw-col-beschluss">Beschluss</th>
                    </tr>
                  </thead>
                  <tbody>
                    <template v-for="t in gefilterteTraktanden(sitzung.id)" :key="t.id">
                      <tr
                        :class="['pw-table-row-clickable', { 'pw-geloescht': t.geschaeft?.geloescht }]"
                        tabindex="0"
                        role="button"
                        :aria-label="`Traktandum ${t.nummer} öffnen`"
                        @click="oeffneGeschaeft(t)"
                        @keydown.enter.prevent="oeffneGeschaeft(t)"
                        @keydown.space.prevent="oeffneGeschaeft(t)"
                      >
                        <td data-label="Tr." class="pw-col-nr"><strong>{{ t.nummer }}</strong></td>
                        <td data-label="Nr." class="pw-col-nr">{{ t.geschaeft?.nummer || '' }}</td>
                        <td class="pw-titel pw-col-titel" data-label="Titel">
                          <a
                            v-if="t.geschaeft?.url"
                            :href="t.geschaeft.url"
                            target="_blank"
                            @click.stop
                            class="pw-inline-link"
                            title="Extern öffnen"
                          >↗</a>
                          {{ t.geschaeft?.titel || t.titel }}
                        </td>
                        <td data-label="Typ" class="pw-col-typ">{{ t.geschaeft?.typ || '' }}</td>
                        <td data-label="Status" class="pw-col-status">
                          <span
                            v-if="t.geschaeft?.status"
                            :class="['pw-status-' + statusKlasse(t.geschaeft.status), 'pw-status-text']"
                            :title="t.geschaeft.status"
                          >{{ t.geschaeft.status }}</span>
                        </td>
                        <td data-label="Datum" class="pw-col-datum">{{ formatieredatum(t.geschaeft?.datum) }}</td>
                        <td v-if="t.geschaeft" data-label="Zuständig" class="pw-col-inline-edit pw-col-zustaendig" @click.stop>
                          <PwMultiSelect
                            class="pw-inline-select"
                            :model-value="zustaendigOptionenFuer(t.geschaeft)"
                            :options="zustaendigeOptionenFuerSelect"
                            :clearable="true"
                            placeholder="—"
                            label="label"
                            @update:model-value="aenderungZustaendig(t.geschaeft, sitzung.id, $event || [])"
                          />
                        </td>
                        <td v-else data-label="Zuständig" class="pw-col-zustaendig">—</td>
                        <td v-if="t.geschaeft" data-label="Beschluss" class="pw-col-inline-edit pw-col-beschluss" @click.stop>
                          <NcSelect
                            class="pw-inline-select"
                            :model-value="beschlussOptionFuer(t.geschaeft)"
                            :options="beschlussOptionenFuer(t.geschaeft)"
                            :clearable="true"
                            placeholder="—"
                            label="label"
                            @update:model-value="aenderungBeschluss(t.geschaeft, sitzung.id, $event)"
                          />
                        </td>
                        <td v-else data-label="Beschluss" class="pw-col-beschluss">—</td>
                      </tr>
                      <tr class="pw-traktandum-notizen-zeile" @click.stop>
                        <td></td>
                        <td colspan="7">
                          <NotizenListe
                            :model-value="parseTraktandumNotizen(t.id)"
                            placeholder="Notiz zum Traktandum hinzufügen…"
                            @update:model-value="speichereTraktandumNotizen(t, $event)"
                          />
                        </td>
                      </tr>
                    </template>
                  </tbody>
                </table>
              </div>
              <p v-if="gefilterteTraktanden(sitzung.id).length === 0">
                Keine Traktanden gefunden.
              </p>
            </template>
          </div>
        </div>
      </div>
      <p v-if="gefilterteSitzungen.length === 0" class="pw-leer">Keine Sitzungen gefunden.</p>
    </div>
  </section>

  <Teleport to="body">
    <div v-if="neuDialogOffen" class="pw-modal-overlay" @click.self="schliesseNeuDialog">
      <div class="pw-modal pw-modal-neu">
        <div class="pw-modal-kopf">
          <h3>Neue Sitzung: {{ gewaehlterTypName }}</h3>
          <button type="button" class="button pw-btn-schliessen" aria-label="Dialog schliessen" @click="schliesseNeuDialog">✕</button>
        </div>
        <div class="pw-modal-body">
          <label>Datum *
            <input v-model="neueSitzung.datum" type="date" class="pw-input" />
          </label>
          <div class="pw-grid-2">
            <label>Zeit von
              <input v-model="neueSitzung.zeitVon" type="time" class="pw-input" />
            </label>
            <label>Zeit bis
              <input v-model="neueSitzung.zeitBis" type="time" class="pw-input" />
            </label>
          </div>
          <label>Ort
            <input v-model="neueSitzung.ort" type="text" class="pw-input" />
          </label>
          <label>Titel
            <input v-model="neueSitzung.titel" type="text" class="pw-input" />
          </label>
        </div>
        <div class="pw-modal-footer">
          <button type="button" class="button" @click="schliesseNeuDialog">Abbrechen</button>
          <button
            type="button"
            class="button primary"
            :disabled="!neueSitzung.typId || !neueSitzung.datum || neueSitzungLaeuft"
            @click="erstelleNeueSitzung"
          >Sitzung anlegen</button>
        </div>
      </div>
    </div>
  </Teleport>

  <Teleport to="body">
    <div v-if="ausgewaehlteGeschaeftId" class="pw-modal-overlay" @click.self="schliesseGeschaeft">
      <div class="pw-modal">
        <div class="pw-modal-kopf pw-modal-kopf-leer">
          <button type="button" class="button pw-btn-schliessen" aria-label="Dialog schliessen" @click="schliesseGeschaeft">✕</button>
        </div>
        <GeschaeftDetail
          :geschaeft-id="ausgewaehlteGeschaeftId"
          :mitglieder="mitglieder"
          @gespeichert="schliesseGeschaeft"
        />
      </div>
    </div>
  </Teleport>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { subscribeRealtime } from '../realtime'
import GeschaeftDetail from './GeschaeftDetail.vue'
import NotizenListe from './NotizenListe.vue'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import PwMultiSelect from './PwMultiSelect.vue'

export default {
  name: 'Sitzungsliste',
  components: { GeschaeftDetail, NotizenListe, NcCheckboxRadioSwitch, NcLoadingIcon, NcSelect, NcTextField, NcButton, NcActions, NcActionButton, PwMultiSelect },
  props: {
    mitglieder: { type: Array, default: () => [] },
  },
  data() {
    return {
      filterReady: false,
      sitzungen: [],
      laden: true,
      suche: '',
      nurKuenftige: true,
      offeneSitzungen: [],
      traktanden: {},
      ladenTraktanden: {},
      sitzungNotizen: {},
      traktandumNotizen: {},
      ausgewaehlteGeschaeftId: null,
      unsubRealtime: null,
      neuDialogOffen: false,
      verfuegbareTypen: [],
      typenLaden: false,
      neueSitzung: { typId: 0, datum: '', zeitVon: '', zeitBis: '', ort: '', titel: '' },
      neueSitzungLaeuft: false,
    }
  },
  computed: {
    gefilterteSitzungen() {
      let liste = this.sitzungen
      if (this.nurKuenftige) {
        const heute = new Date().toISOString().slice(0, 10)
        liste = liste.filter(s => (s.datum || '') >= heute)
      }
      const s = (this.suche || '').trim().toLowerCase()
      if (s) {
        liste = liste.filter((sit) => {
          // Sitzung selbst (Titel/Ort) auch durchsuchen.
          const titel = (sit.titel || '').toLowerCase()
          const ort = (sit.ort || '').toLowerCase()
          if (titel.includes(s) || ort.includes(s)) return true
          // Wenn Traktanden noch nicht geladen sind: optimistisch anzeigen –
          // der Watcher unten lädt sie nach und reduziert die Liste dann weiter.
          const traks = this.traktanden[sit.id]
          if (!traks) return true
          return traks.some((t) => {
            const tt = (t.geschaeft?.titel || t.titel || '').toLowerCase()
            const tn = (t.geschaeft?.nummer || '').toLowerCase()
            return tt.includes(s) || tn.includes(s)
          })
        })
      }
      return liste
    },
    zustaendigeOptionenFuerSelect() {
      return this.mitglieder
        .filter((m) => m.aktiv !== false && !!(m.nextcloudUid || m.nextcloud_uid))
        .map((member) => ({
          label: this.vollerName(member),
          value: this.personKey(member),
          mitglied: member,
        }))
        .filter((o) => !!o.label)
        .sort((a, b) => a.label.localeCompare(b.label))
    },
    gewaehlterTypName() {
      const t = this.verfuegbareTypen.find(x => x.id === Number(this.neueSitzung.typId))
      return t ? t.name : ''
    },
  },
  watch: {
    suche(neu) {
      const term = (neu || '').trim()
      if (!term) return
      // Beim Tippen: Traktanden aller (gefilterten) Sitzungen nachladen, damit
      // die Suche tatsächlich greift. Trefferliste klappt sich automatisch auf.
      this.sitzungen.forEach((sit) => {
        if (this.nurKuenftige) {
          const heute = new Date().toISOString().slice(0, 10)
          if ((sit.datum || '') < heute) return
        }
        if (!this.traktanden[sit.id] && !this.ladenTraktanden[sit.id]) {
          this.ladeTraktandenFuerSitzung(sit.id)
        }
      })
    },
  },
  mounted() {
    this.$nextTick(() => { this.filterReady = true })
    this.ladeSitzungen()
    this.ladeSitzungstypen()
    this.unsubRealtime = subscribeRealtime(this.handleRealtimeEvent)
  },
  beforeUnmount() {
    if (this.unsubRealtime) {
      this.unsubRealtime()
      this.unsubRealtime = null
    }
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
    async ladeSitzungen() {
      this.laden = true
      try {
        const { data } = await axios.get(generateUrl('/apps/parlwin/sitzungen'), {
          params: { limit: 100 },
        })
        this.sitzungen = data
        data.forEach(s => {
          this.sitzungNotizen[s.id] = this.parseNotizen(s.notizen)
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
        const notizen = { ...this.traktandumNotizen }
        data.forEach(t => {
          notizen[t.id] = this.parseNotizen(t.notizen)
        })
        this.traktandumNotizen = notizen
        // Wenn aktive Suche Treffer ergibt, Sitzung automatisch aufklappen.
        const term = (this.suche || '').trim().toLowerCase()
        if (term && !this.offeneSitzungen.includes(sitzungId)) {
          const hit = data.some((t) => {
            const tt = (t.geschaeft?.titel || t.titel || '').toLowerCase()
            const tn = (t.geschaeft?.nummer || '').toLowerCase()
            return tt.includes(term) || tn.includes(term)
          })
          if (hit) this.offeneSitzungen.push(sitzungId)
        }
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
      if (type === 'geschaefte.updated' || type === 'geschaefte.action') {
        // Geschäftsdaten geändert – betroffene offene Sitzungen neu laden.
        this.offeneSitzungen.forEach((sid) => this.ladeTraktandenFuerSitzung(sid, true))
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
    gefilterteTraktanden(sitzungId) {
      const liste = this.traktanden[sitzungId] || []
      const s = (this.suche || '').trim().toLowerCase()
      if (!s) return liste
      return liste.filter((t) => {
        const titel = (t.geschaeft?.titel || t.titel || '').toLowerCase()
        const nummer = (t.geschaeft?.nummer || '').toLowerCase()
        return titel.includes(s) || nummer.includes(s)
      })
    },
    async speichereTraktandumNotizen(traktandum, notizen) {
      const tId = traktandum.id
      const liste = Array.isArray(notizen) ? notizen : []
      this.traktandumNotizen = { ...this.traktandumNotizen, [tId]: liste }
      const sitzungId = traktandum.sitzungId
      try {
        await axios.put(
          generateUrl(`/apps/parlwin/sitzungen/${sitzungId}/traktanden/${traktandum.id}`),
          { notizen: JSON.stringify(liste) }
        )
      } catch (e) {
        console.error('Fehler beim Speichern der Traktandum-Notizen:', e)
      }
    },
    async speichereSitzungNotizen(sitzung, notizen) {
      const liste = Array.isArray(notizen) ? notizen : []
      this.sitzungNotizen = { ...this.sitzungNotizen, [sitzung.id]: liste }
      try {
        await axios.put(
          generateUrl(`/apps/parlwin/sitzungen/${sitzung.id}`),
          { notizen: JSON.stringify(liste) }
        )
      } catch (e) {
        console.error('Fehler beim Speichern der Sitzungs-Notizen:', e)
      }
    },
    zustaendigOptionenFuer(geschaeft) {
      const zust = Array.isArray(geschaeft.zustaendigkeiten) ? geschaeft.zustaendigkeiten : []
      return zust.map((z) => {
        const treffer = this.zustaendigeOptionenFuerSelect.find((o) => o.value === z.personKey)
        return treffer || { label: z.personName || z.personKey, value: z.personKey, mitglied: null }
      })
    },
    beschlussOptionenFuer(geschaeft) {
      const erlaubt = Array.isArray(geschaeft.erlaubteBeschluesse) ? geschaeft.erlaubteBeschluesse : []
      return erlaubt.map((b) => ({ label: b.label || b.code, value: b.code }))
    },
    beschlussOptionFuer(geschaeft) {
      const code = geschaeft.letzterBeschluss?.aktionCode || ''
      if (!code) return null
      const optionen = this.beschlussOptionenFuer(geschaeft)
      return optionen.find((o) => o.value === code) || { label: geschaeft.letzterBeschluss?.titel || code, value: code }
    },
    async aenderungZustaendig(geschaeft, sitzungId, optionen) {
      const optList = Array.isArray(optionen) ? optionen : (optionen ? [optionen] : [])
      const keys = optList.map((o) => o.value).filter(Boolean)
      const vorhandeneHaupt = (geschaeft.zustaendigkeiten || []).find((z) => z.istHaupt)?.personKey || ''
      const haupt = keys.includes(vorhandeneHaupt) ? vorhandeneHaupt : (keys[0] || '')
      const payload = keys.map((key) => {
        const member = this.mitglieder.find((m) => this.personKey(m) === key)
        const fallback = optList.find((o) => o.value === key)
        return {
          mitgliedExternId: member?.externId || member?.extern_id || '',
          personName: member ? this.vollerName(member) : (fallback?.label || ''),
        }
      })
      try {
        await axios.put(generateUrl(`/apps/parlwin/geschaefte/${geschaeft.id}`), {
          zustaendigkeiten: payload,
          haupt_person_key: haupt,
        })
        await this.ladeTraktandenFuerSitzung(sitzungId, true)
      } catch (fehler) {
        console.error('Fehler beim Speichern der Zuständigkeit:', fehler)
      }
    },
    async aenderungBeschluss(geschaeft, sitzungId, option) {
      const code = option?.value || ''
      try {
        if (code) {
          await axios.post(generateUrl(`/apps/parlwin/geschaefte/${geschaeft.id}/beschluesse`), { code, text: '' })
        } else {
          await axios.delete(generateUrl(`/apps/parlwin/geschaefte/${geschaeft.id}/beschluesse`))
        }
        await this.ladeTraktandenFuerSitzung(sitzungId, true)
      } catch (fehler) {
        console.error('Fehler beim Speichern des Beschlusses:', fehler)
      }
    },
    oeffneGeschaeft(traktandum) {
      const id = Number(traktandum?.geschaeftId || traktandum?.geschaeft?.id || 0)
      if (id > 0) {
        this.ausgewaehlteGeschaeftId = id
      }
    },
    schliesseGeschaeft() {
      this.ausgewaehlteGeschaeftId = null
    },
    async oeffneNeuDialog() {
      this.neueSitzung = { typId: 0, datum: '', zeitVon: '', zeitBis: '', ort: '', titel: '' }
      this.neuDialogOffen = true
      this.typenLaden = true
      try {
        const { data } = await axios.get(generateUrl('/apps/parlwin/sitzungstypen'))
        this.verfuegbareTypen = Array.isArray(data) ? data : []
      } catch (e) {
        console.error('Fehler beim Laden der Sitzungstypen:', e)
        this.verfuegbareTypen = []
      } finally {
        this.typenLaden = false
      }
    },
    schliesseNeuDialog() {
      this.neuDialogOffen = false
    },
    async ladeSitzungstypen() {
      try {
        const { data } = await axios.get(generateUrl('/apps/parlwin/sitzungstypen'))
        this.verfuegbareTypen = Array.isArray(data) ? data : []
      } catch (e) {
        console.error('Fehler beim Laden der Sitzungstypen:', e)
        this.verfuegbareTypen = []
      }
    },
    oeffneNeuDialogMitTyp(typ) {
      this.neueSitzung = {
        typId: typ.id,
        datum: '',
        zeitVon: typ.standardZeitVon || '',
        zeitBis: typ.standardZeitBis || '',
        ort: typ.standardOrt || '',
        titel: typ.name || '',
      }
      this.neuDialogOffen = true
    },
    typAusgewaehlt(typId) {
      const typ = this.verfuegbareTypen.find(t => t.id === Number(typId))
      if (!typ) return
      this.neueSitzung.typId = typ.id
      if (!this.neueSitzung.ort) this.neueSitzung.ort = typ.standardOrt || ''
      if (!this.neueSitzung.zeitVon) this.neueSitzung.zeitVon = typ.standardZeitVon || ''
      if (!this.neueSitzung.zeitBis) this.neueSitzung.zeitBis = typ.standardZeitBis || ''
      if (!this.neueSitzung.titel) this.neueSitzung.titel = typ.name || ''
    },
    async erstelleNeueSitzung() {
      if (!this.neueSitzung.typId || !this.neueSitzung.datum) return
      this.neueSitzungLaeuft = true
      try {
        await axios.post(
          generateUrl(`/apps/parlwin/sitzungstypen/${this.neueSitzung.typId}/sitzung`),
          {
            datum: this.neueSitzung.datum,
            zeitVon: this.neueSitzung.zeitVon,
            zeitBis: this.neueSitzung.zeitBis,
            ort: this.neueSitzung.ort,
            titel: this.neueSitzung.titel,
          }
        )
        this.neuDialogOffen = false
        await this.ladeSitzungen()
      } catch (e) {
        console.error('Fehler beim Erstellen der Sitzung:', e)
        alert('Fehler: ' + (e?.response?.data?.fehler || e.message))
      } finally {
        this.neueSitzungLaeuft = false
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
    statusKlasse(status) {
      if (!status) return ''
      const s = status.toLowerCase()
      if (s.includes('pendent') || s.includes('offen') || s.includes('laufend')) return 'offen'
      if (s.includes('erledigt') || s.includes('abgeschlossen') || s.includes('aufgehoben')) return 'erledigt'
      if (s.includes('abgelehnt') || s.includes('zurückgezogen')) return 'abgelehnt'
      return 'neutral'
    },
  },
}
</script>
