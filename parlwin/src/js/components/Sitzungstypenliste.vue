<template>
  <Teleport v-if="filterReady" to="#pw-search-slot">
    <NcTextField
      v-model="suche"
      label="Suche"
      placeholder="Name oder Zweck"
      trailing-button-icon="close"
      :show-trailing-button="!!suche"
      @trailing-button-click="suche = ''"
    />
  </Teleport>

  <section class="pw-view-content pw-sitzungstypen">
    <header class="pw-view-header">
      <h2 class="pw-view-title">Sitzungstypen</h2>
      <span class="pw-view-count">{{ gefiltert.length }}</span>
      <NcButton type="primary" @click="neuerTyp">+ Neuer Typ</NcButton>
    </header>

    <div v-if="laden" class="pw-laden"><NcLoadingIcon :size="32" /></div>

    <div v-else>
      <div v-if="!gefiltert.length" class="pw-hinweis">
        Keine Sitzungstypen vorhanden. Erstellen Sie einen neuen Typ.
      </div>
      <div
        v-for="typ in gefiltert"
        :key="typ.id"
        class="pw-sitzungstyp-karte"
      >
        <div class="pw-sitzungstyp-kopf">
          <div>
            <h3>{{ typ.name }}</h3>
            <p v-if="typ.zweck" class="pw-sitzungstyp-zweck">{{ typ.zweck }}</p>
          </div>
          <div class="pw-sitzungstyp-aktionen">
            <NcButton type="secondary" @click="bearbeiten(typ)">Bearbeiten</NcButton>
            <NcButton type="error" @click="loeschen(typ)">Löschen</NcButton>
          </div>
        </div>
        <div class="pw-sitzungstyp-meta">
          <span v-if="typ.standardOrt">📍 {{ typ.standardOrt }}</span>
          <span v-if="typ.standardZeitVon">🕒 {{ typ.standardZeitVon }}<template v-if="typ.standardZeitBis"> – {{ typ.standardZeitBis }}</template></span>
          <span>{{ (typ.traktanden || []).length }} Vorlage-Traktanden</span>
          <span>{{ (typ.teilnehmer || []).length }} Teilnehmer</span>
        </div>
      </div>
    </div>

    <!-- Bearbeitungs-Dialog -->
    <Teleport to="body">
    <div v-if="bearbeitung" class="pw-modal-overlay" @click.self="abbrechen">
      <div class="pw-modal">
        <header class="pw-modal-header">
          <h3>{{ bearbeitung.id ? 'Sitzungstyp bearbeiten' : 'Neuer Sitzungstyp' }}</h3>
          <button type="button" class="pw-modal-close" @click="abbrechen">×</button>
        </header>
        <div class="pw-modal-body">
          <PwField label="Name *">
            <input v-model="bearbeitung.name" type="text" class="pw-input" />
          </PwField>
          <PwField label="Zweck">
            <textarea v-model="bearbeitung.zweck" class="pw-textarea" rows="2" />
          </PwField>
          <PwField label="Standard-Ort">
            <input v-model="bearbeitung.standardOrt" type="text" class="pw-input" />
          </PwField>
          <div class="pw-von-bis">
            <PwField label="Von">
              <input v-model="bearbeitung.standardZeitVon" type="time" class="pw-input" />
            </PwField>
            <PwField label="Bis">
              <input v-model="bearbeitung.standardZeitBis" type="time" class="pw-input" />
            </PwField>
          </div>
          <fieldset class="pw-fieldset">
            <legend>Vorlage-Traktanden</legend>
            <div
              v-for="(t, i) in bearbeitung.traktanden"
              :key="i"
              class="pw-zeile"
              :class="{ 'pw-drag-over': typDragOverIdx === i }"
              @dragover.prevent="typDragOverIdx = i"
              @dragleave="typDragOverIdx = null"
              @drop.prevent="typDragDrop(i)"
              @dragend="typDragOverIdx = null"
            >
              <span
                class="pw-drag-handle"
                aria-hidden="true"
                draggable="true"
                @dragstart="typDragSrc = i"
              >⠿</span>
              <input v-model="t.titel" placeholder="Titel" class="pw-input" />
              <input v-model="t.beschreibung" placeholder="Beschreibung" class="pw-input" />
              <button type="button" class="pw-btn-klein" @click="bearbeitung.traktanden.splice(i, 1)">✕</button>
            </div>
            <NcButton type="secondary" @click="bearbeitung.traktanden.push({ titel: '', beschreibung: '' })">+ Traktandum</NcButton>
          </fieldset>

          <fieldset class="pw-fieldset">
            <legend>Teilnehmer</legend>
            <NcCheckboxRadioSwitch
              :checked="hatEigeneFraktion"
              @update:checked="toggleEigeneFraktion"
            >Eigene Fraktion<span v-if="konfigurierteGruppe" class="pw-hinweis pw-hinweis-inline"> → {{ konfigurierteGruppe }}</span></NcCheckboxRadioSwitch>
            <PwField label="Einzelne Mitglieder">
              <PwMultiSelect
                :model-value="ausgewaehlteMitglieder"
                :options="mitgliederOptionen"
                placeholder="Mitglieder hinzufügen…"
                label="label"
                @update:model-value="updateMitglieder"
              />
            </PwField>
          </fieldset>
        </div>
        <footer class="pw-modal-footer">
          <NcButton type="tertiary" @click="abbrechen">Abbrechen</NcButton>
          <NcButton type="primary" :disabled="!bearbeitung.name || speichernLaeuft" @click="speichern">Speichern</NcButton>
        </footer>
      </div>
    </div>
    </Teleport>
  </section>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import PwMultiSelect from './PwMultiSelect.vue'
import PwField from './PwField.vue'

export default {
  name: 'Sitzungstypenliste',
  components: { NcTextField, NcButton, NcCheckboxRadioSwitch, NcLoadingIcon, PwMultiSelect, PwField },
  props: {
    mitglieder: { type: Array, default: () => [] },
    fraktionen: { type: Array, default: () => [] },
    kommissionen: { type: Array, default: () => [] },
  },
  data() {
    return {
      typen: [],
      laden: true,
      filterReady: false,
      suche: '',
      bearbeitung: null,
      speichernLaeuft: false,
      ncGruppen: [],
      ncUser: [],
      ncGruppenLaden: false,
      ncUserLaden: false,
      typDragSrc: null,
      typDragOverIdx: null,
      verfuegbareRollen: [
        { code: 'kommissionsmitglied', bezeichnung: 'Kommissionsmitglied' },
        { code: 'fraktionspraesident', bezeichnung: 'Fraktionspräsident' },
        { code: 'fraktionspraesident_stellvertretung', bezeichnung: 'Fraktionspräsident Stellvertretung' },
        { code: 'protokollfuehrer', bezeichnung: 'Protokollführer' },
        { code: 'protokollfuehrer_stellvertretung', bezeichnung: 'Protokollführer Stellvertretung' },
      ],
    }
  },
  computed: {
    gefiltert() {
      const q = (this.suche || '').toLowerCase().trim()
      if (!q) return this.typen
      return this.typen.filter(t =>
        (t.name || '').toLowerCase().includes(q) ||
        (t.zweck || '').toLowerCase().includes(q)
      )
    },
    aktiveMitglieder() {
      return (this.mitglieder || []).filter(m => m.aktiv !== false)
    },
    aktiveFraktionen() {
      return (this.fraktionen || []).filter(f => f.aktiv !== false)
    },
    aktiveKommissionen() {
      return (this.kommissionen || []).filter(k => k.aktiv !== false && !k.geloescht)
    },
    konfigurierteGruppe() {
      return (typeof window !== 'undefined' && window.PARLWIN_CONFIG && window.PARLWIN_CONFIG.nextcloudGruppe) || ''
    },
    hatEigeneFraktion() {
      return !!(this.bearbeitung?.teilnehmer || []).some(p => p.art === 'eigeneFraktion')
    },
    mitgliederOptionen() {
      return this.aktiveMitglieder.map(m => ({ value: m.id, label: this.mitgliedLabel(m) }))
    },
    ausgewaehlteMitglieder() {
      return (this.bearbeitung?.teilnehmer || [])
        .filter(p => p.art === 'mitglied')
        .map(p => ({ value: p.referenzId, label: p.referenzName || this.mitgliedLabel(this.aktiveMitglieder.find(m => m.id === p.referenzId) || {}) }))
    },
  },
  mounted() {
    this.$nextTick(() => { this.filterReady = true })
    this.laden = true
    this.lade()
    this.ladeNcGruppen()
    this.ladeNcUser()
  },
  methods: {
    async lade() {
      this.laden = true
      try {
        const { data } = await axios.get(generateUrl('/apps/parlwin/sitzungstypen'))
        this.typen = Array.isArray(data) ? data : []
      } catch (e) {
        console.error('Fehler beim Laden der Sitzungstypen:', e)
      } finally {
        this.laden = false
      }
    },
    mitgliedLabel(m) {
      const v = m.vorname || ''
      const n = m.name || ''
      return `${v} ${n}`.trim() + (m.fraktion ? ` (${m.fraktion})` : '')
    },
    toggleEigeneFraktion(checked) {
      if (!this.bearbeitung) return
      if (checked) {
        if (!this.bearbeitung.teilnehmer.some(p => p.art === 'eigeneFraktion')) {
          this.bearbeitung.teilnehmer.push({ art: 'eigeneFraktion', referenzId: 0, referenzName: '' })
        }
      } else {
        this.bearbeitung.teilnehmer = this.bearbeitung.teilnehmer.filter(p => p.art !== 'eigeneFraktion')
      }
    },
    updateMitglieder(optionen) {
      if (!this.bearbeitung) return
      const andere = this.bearbeitung.teilnehmer.filter(p => p.art !== 'mitglied')
      const mitglieder = (optionen || []).map(o => {
        const m = this.aktiveMitglieder.find(x => x.id === o.value)
        return { art: 'mitglied', referenzId: o.value, referenzName: m ? this.mitgliedLabel(m) : (o.label || '') }
      })
      this.bearbeitung.teilnehmer = [...andere, ...mitglieder]
    },
    async ladeNcGruppen(search = '') {
      this.ncGruppenLaden = true
      try {
        const { data } = await axios.get(generateUrl('/apps/parlwin/sitzungstypen/nc/groups'), { params: { search, limit: 100 } })
        this.ncGruppen = Array.isArray(data) ? data : []
      } catch (e) {
        console.error('Fehler beim Laden der Nextcloud-Gruppen:', e?.response?.status, e?.response?.data || e.message)
        this.ncGruppen = []
      } finally {
        this.ncGruppenLaden = false
      }
    },
    async ladeNcUser(search = '') {
      this.ncUserLaden = true
      try {
        const { data } = await axios.get(generateUrl('/apps/parlwin/sitzungstypen/nc/users'), { params: { search, limit: 100 } })
        this.ncUser = Array.isArray(data) ? data : []
      } catch (e) {
        console.error('Fehler beim Laden der Nextcloud-Benutzer:', e?.response?.status, e?.response?.data || e.message)
        this.ncUser = []
      } finally {
        this.ncUserLaden = false
      }
    },
    typDragDrop(targetIdx) {
      if (this.typDragSrc === null || this.typDragSrc === targetIdx) return
      const arr = this.bearbeitung.traktanden
      const moved = arr.splice(this.typDragSrc, 1)[0]
      arr.splice(targetIdx, 0, moved)
      this.typDragSrc = null
      this.typDragOverIdx = null
    },
    neuerTyp() {
      this.bearbeitung = {
        id: 0,
        name: '',
        zweck: '',
        kalenderAnlegen: true,
        einladungVersenden: true,
        standardOrt: '',
        standardZeitVon: '',
        standardZeitBis: '',
        traktanden: [],
        teilnehmer: [],
      }
    },
    bearbeiten(typ) {
      this.bearbeitung = JSON.parse(JSON.stringify({
        id: typ.id || 0,
        name: typ.name || '',
        zweck: typ.zweck || '',
        kalenderAnlegen: typ.kalenderAnlegen !== false,
        einladungVersenden: typ.einladungVersenden !== false,
        standardOrt: typ.standardOrt || '',
        standardZeitVon: typ.standardZeitVon || '',
        standardZeitBis: typ.standardZeitBis || '',
        traktanden: (typ.traktanden || []).map(t => ({
          titel: t.titel || '',
          beschreibung: t.beschreibung || '',
        })),
        teilnehmer: (typ.teilnehmer || []).map(p => ({
          art: p.art || 'mitglied',
          referenzId: p.referenzId || 0,
          referenzName: p.referenzName || '',
        })),
      }))
    },
    abbrechen() {
      this.bearbeitung = null
    },
    async speichern() {
      if (!this.bearbeitung || !this.bearbeitung.name) return
      // Unvollstaendige Teilnehmer-Regeln (z.B. "— wählen —" stehen geblieben)
      // werden stillschweigend verworfen, damit der Benutzer nicht durch eine
      // Validierungs-Meldung blockiert wird.
      const istVollstaendig = (p) => {
        if (p.art === 'eigeneFraktion') return true
        if (['mitglied', 'kommission'].includes(p.art)) return !!p.referenzId
        if (['fraktion', 'ncGruppe', 'ncUser', 'rolle'].includes(p.art)) return !!p.referenzName
        return !!p.referenzName
      }
      const teilnehmerSauber = (this.bearbeitung.teilnehmer || []).filter(istVollstaendig)
      this.speichernLaeuft = true
      try {
        const payload = { ...this.bearbeitung, teilnehmer: teilnehmerSauber }
        if (payload.id) {
          await axios.put(generateUrl(`/apps/parlwin/sitzungstypen/${payload.id}`), payload)
        } else {
          await axios.post(generateUrl('/apps/parlwin/sitzungstypen'), payload)
        }
        this.bearbeitung = null
        await this.lade()
      } catch (e) {
        const status = e?.response?.status
        const serverMsg = e?.response?.data?.fehler || e?.response?.data?.message || ''
        console.error('Fehler beim Speichern des Sitzungstyps:', status, e?.response?.data || e.message)
        alert(`Fehler beim Speichern (HTTP ${status || '?'}): ${serverMsg || e.message}`)
      } finally {
        this.speichernLaeuft = false
      }
    },
    async loeschen(typ) {
      if (!confirm(`Sitzungstyp „${typ.name}" wirklich löschen?`)) return
      try {
        await axios.delete(generateUrl(`/apps/parlwin/sitzungstypen/${typ.id}`))
        await this.lade()
      } catch (e) {
        console.error('Fehler beim Löschen:', e)
      }
    },
  },
}
</script>

<style scoped>
.pw-sitzungstyp-karte {
  border: 1px solid var(--color-border, #ddd);
  border-radius: 6px;
  padding: 12px 16px;
  margin-bottom: 12px;
  background: var(--color-main-background, #fff);
}
.pw-sitzungstyp-kopf {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 12px;
}
.pw-sitzungstyp-kopf h3 { margin: 0 0 4px 0; }
.pw-sitzungstyp-zweck { margin: 0; color: var(--color-text-maxcontrast, #888); }
.pw-sitzungstyp-aktionen { display: flex; gap: 8px; }
.pw-sitzungstyp-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  margin-top: 8px;
  font-size: 0.9em;
  color: var(--color-text-maxcontrast, #666);
}
/* .pw-modal-overlay wird aus dem globalen style.scss übernommen.
   Das Modal wird im Template via <Teleport to="body"> aus dem
   NcAppContent-Stacking-Context herausgezogen, damit es nicht von
   NcAppNavigation (z-index 1800) überdeckt wird. */
.pw-modal {
  background: var(--color-main-background, #fff);
  border-radius: 8px;
  width: min(720px, 95vw);
  max-height: 90vh;
  display: flex; flex-direction: column;
}
.pw-modal-header {
  display: flex; justify-content: space-between; align-items: center;
  padding: 12px 16px; border-bottom: 1px solid var(--color-border, #ddd);
}
.pw-modal-header h3 { margin: 0; }
.pw-modal-close {
  background: none; border: none; font-size: 1.6em; cursor: pointer;
  color: var(--color-text-maxcontrast, #666);
}
.pw-modal-body {
  padding: 16px; overflow-y: auto; flex: 1;
  display: flex; flex-direction: column; gap: 12px;
}
.pw-hinweis-inline { font-weight: 400; margin-left: 4px; }
.pw-modal-footer {
  display: flex; justify-content: flex-end; gap: 8px;
  padding: 12px 16px; border-top: 1px solid var(--color-border, #ddd);
}
.pw-input, .pw-textarea {
  width: 100%; padding: 6px 8px;
  border: 1px solid var(--color-border, #ccc);
  border-radius: 4px;
  background: var(--color-main-background, #fff);
  color: var(--color-main-text, #000);
}
.pw-von-bis { display: flex; gap: 12px; }
.pw-von-bis label { flex: 1; }
.pw-fieldset {
  border: 1px solid var(--color-border, #ddd);
  border-radius: 4px; padding: 8px 12px;
}
.pw-fieldset legend { font-weight: 600; padding: 0 6px; }
.pw-zeile {
  display: flex; gap: 6px; margin-bottom: 6px; align-items: center;
}
.pw-zeile.pw-drag-over {
  outline: 2px solid var(--color-primary);
  border-radius: var(--border-radius);
}
.pw-drag-handle {
  cursor: grab;
  color: var(--color-text-lighter);
  user-select: none;
  flex-shrink: 0;
}
.pw-zeile .pw-input { flex: 1; }
.pw-btn-klein {
  padding: 4px 8px; cursor: pointer;
  background: var(--color-error, #c00); color: #fff;
  border: none; border-radius: 4px;
}
.pw-hinweis { color: var(--color-text-maxcontrast, #888); padding: 12px 0; }
</style>
