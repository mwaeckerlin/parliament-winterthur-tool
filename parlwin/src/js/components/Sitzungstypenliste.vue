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
          <span>{{ typ.kalenderAnlegen ? '📅 Kalendereintrag' : '— kein Kalender' }}</span>
          <span>{{ typ.einladungVersenden ? '✉️ Einladung' : '— keine Einladung' }}</span>
          <span>{{ (typ.traktanden || []).length }} Vorlage-Traktanden</span>
          <span>{{ (typ.teilnehmer || []).length }} Teilnehmer-Regeln</span>
        </div>
      </div>
    </div>

    <!-- Bearbeitungs-Dialog -->
    <div v-if="bearbeitung" class="pw-modal-overlay" @click.self="abbrechen">
      <div class="pw-modal">
        <header class="pw-modal-header">
          <h3>{{ bearbeitung.id ? 'Sitzungstyp bearbeiten' : 'Neuer Sitzungstyp' }}</h3>
          <button type="button" class="pw-modal-close" @click="abbrechen">×</button>
        </header>
        <div class="pw-modal-body">
          <label>Name *
            <input v-model="bearbeitung.name" type="text" class="pw-input" />
          </label>
          <label>Zweck
            <textarea v-model="bearbeitung.zweck" class="pw-textarea" rows="2"></textarea>
          </label>
          <div class="pw-grid-2">
            <label>Standard-Ort
              <input v-model="bearbeitung.standardOrt" type="text" class="pw-input" />
            </label>
            <label>Standard-Zeit von
              <input v-model="bearbeitung.standardZeitVon" type="time" class="pw-input" />
            </label>
            <label>Standard-Zeit bis
              <input v-model="bearbeitung.standardZeitBis" type="time" class="pw-input" />
            </label>
          </div>
          <NcCheckboxRadioSwitch v-model="bearbeitung.kalenderAnlegen" type="switch">
            Kalendereintrag automatisch erstellen
          </NcCheckboxRadioSwitch>
          <NcCheckboxRadioSwitch v-model="bearbeitung.einladungVersenden" type="switch">
            Einladung an Teilnehmer versenden
          </NcCheckboxRadioSwitch>

          <fieldset class="pw-fieldset">
            <legend>Vorlage-Traktanden</legend>
            <div v-for="(t, i) in bearbeitung.traktanden" :key="i" class="pw-zeile">
              <input v-model="t.titel" placeholder="Titel" class="pw-input" />
              <input v-model="t.beschreibung" placeholder="Beschreibung" class="pw-input" />
              <button type="button" class="pw-btn-klein" @click="bearbeitung.traktanden.splice(i, 1)">✕</button>
            </div>
            <NcButton type="secondary" @click="bearbeitung.traktanden.push({ titel: '', beschreibung: '' })">+ Traktandum</NcButton>
          </fieldset>

          <fieldset class="pw-fieldset">
            <legend>Teilnehmer-Regeln</legend>
            <div v-for="(p, i) in bearbeitung.teilnehmer" :key="i" class="pw-zeile">
              <select v-model="p.art" class="pw-input" @change="onArtChange(p)">
                <option value="mitglied">Einzelnes Mitglied</option>
                <option value="fraktion">Ganze Fraktion</option>
                <option value="eigeneFraktion">Eigene Fraktion</option>
                <option value="kommission">Ganze Kommission</option>
                <option value="rolle">Fraktions-Rolle</option>
                <option value="ncGruppe">Nextcloud-Gruppe</option>
                <option value="ncUser">Nextcloud-Benutzer</option>
              </select>
              <select v-if="p.art === 'mitglied'" v-model.number="p.referenzId" class="pw-input">
                <option :value="0">— Mitglied wählen —</option>
                <option v-for="m in aktiveMitglieder" :key="m.id" :value="m.id">{{ mitgliedLabel(m) }}</option>
              </select>
              <select v-else-if="p.art === 'fraktion'" v-model="p.referenzName" class="pw-input">
                <option value="">— Fraktion wählen —</option>
                <option v-for="f in aktiveFraktionen" :key="f.kuerzel || f.name" :value="f.name">{{ f.name }}</option>
              </select>
              <span v-else-if="p.art === 'eigeneFraktion'" class="pw-hinweis">→ wird beim Anlegen der Sitzung anhand des angemeldeten Users aufgelöst.</span>
              <select v-else-if="p.art === 'kommission'" v-model.number="p.referenzId" class="pw-input">
                <option :value="0">{{ aktiveKommissionen.length ? '— Kommission wählen —' : '— Keine Kommissionen vorhanden —' }}</option>
                <option v-for="k in aktiveKommissionen" :key="k.id" :value="k.id">{{ k.name }}</option>
              </select>
              <select v-else-if="p.art === 'ncGruppe'" v-model="p.referenzName" class="pw-input">
                <option value="">{{ ncGruppenLaden ? '— Lade Gruppen … —' : (ncGruppen.length ? '— Nextcloud-Gruppe wählen —' : '— Keine Gruppen verfügbar —') }}</option>
                <option v-for="g in ncGruppen" :key="g.gid" :value="g.gid">{{ g.displayName || g.gid }}</option>
              </select>
              <select v-else-if="p.art === 'ncUser'" v-model="p.referenzName" class="pw-input">
                <option value="">{{ ncUserLaden ? '— Lade Benutzer … —' : (ncUser.length ? '— Nextcloud-Benutzer wählen —' : '— Keine Benutzer verfügbar —') }}</option>
                <option v-for="u in ncUser" :key="u.uid" :value="u.uid">{{ u.displayName || u.uid }} ({{ u.uid }})</option>
              </select>
              <select v-else-if="p.art === 'rolle'" v-model="p.referenzName" class="pw-input">
                <option value="">— Fraktions-Rolle wählen —</option>
                <option v-for="r in verfuegbareRollen" :key="r.code" :value="r.code">{{ r.bezeichnung }}</option>
              </select>
              <input v-else v-model="p.referenzName" placeholder="Bezeichnung" class="pw-input" />
              <button type="button" class="pw-btn-klein" @click="bearbeitung.teilnehmer.splice(i, 1)">✕</button>
            </div>
            <NcButton type="secondary" @click="bearbeitung.teilnehmer.push({ art: 'mitglied', referenzId: 0, referenzName: '' })">+ Regel</NcButton>
          </fieldset>
        </div>
        <footer class="pw-modal-footer">
          <NcButton type="tertiary" @click="abbrechen">Abbrechen</NcButton>
          <NcButton type="primary" :disabled="!bearbeitung.name || speichernLaeuft" @click="speichern">Speichern</NcButton>
        </footer>
      </div>
    </div>
  </section>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'

export default {
  name: 'Sitzungstypenliste',
  components: { NcTextField, NcButton, NcCheckboxRadioSwitch, NcLoadingIcon },
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
      verfuegbareRollen: [
        { code: 'kommissionsmitglied', bezeichnung: 'Kommissionsmitglied' },
        { code: 'fraktionspraesident', bezeichnung: 'Fraktionspräsident*in' },
        { code: 'fraktionspraesident_stellvertretung', bezeichnung: 'Fraktionspräsident*in Stellvertretung' },
        { code: 'protokollfuehrer', bezeichnung: 'Protokollführer*in' },
        { code: 'protokollfuehrer_stellvertretung', bezeichnung: 'Protokollführer*in Stellvertretung' },
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
    onArtChange(p) {
      // Bei Wechsel der Art: Referenzen zurücksetzen, damit nicht versehentlich
      // ein Wert aus einem anderen Auswahlfeld übernommen wird.
      p.referenzId = 0
      p.referenzName = ''
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
    neuerTyp() {
      this.bearbeitung = {
        id: 0,
        name: '',
        zweck: '',
        kalenderAnlegen: true,
        einladungVersenden: false,
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
        einladungVersenden: !!typ.einladungVersenden,
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
      // Validierung: leere Teilnehmer-Regeln (z.B. „— wählen —“ stehen geblieben)
      // herausfiltern, damit das Backend keine ungueltigen Einträge speichert.
      const unvollstaendig = (this.bearbeitung.teilnehmer || []).filter(p => {
        if (p.art === 'eigeneFraktion') return false
        if (['mitglied', 'kommission'].includes(p.art)) return !p.referenzId
        if (['fraktion', 'ncGruppe', 'ncUser', 'rolle'].includes(p.art)) return !p.referenzName
        return !p.referenzName
      })
      if (unvollstaendig.length) {
        alert('Bitte alle Teilnehmer-Regeln vollständig ausfüllen oder leere Zeilen mit ✕ entfernen.')
        return
      }
      this.speichernLaeuft = true
      try {
        const payload = { ...this.bearbeitung }
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
/* .pw-modal-overlay wird aus dem globalen style.scss übernommen (z-index 100000,
   damit das Modal über der Nextcloud-App-Navigation liegt). Hier NICHT scoped
   überschreiben – das lokale Style mit z-index 10000 hatte zur Folge, dass das
   Modal in eingeklapptem Layout teilweise unter der Sidebar lag. */
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
.pw-modal-body label { display: flex; flex-direction: column; gap: 4px; font-weight: 500; }
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
.pw-grid-2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; }
.pw-fieldset {
  border: 1px solid var(--color-border, #ddd);
  border-radius: 4px; padding: 8px 12px;
}
.pw-fieldset legend { font-weight: 600; padding: 0 6px; }
.pw-zeile {
  display: flex; gap: 6px; margin-bottom: 6px; align-items: center;
}
.pw-zeile .pw-input { flex: 1; }
.pw-btn-klein {
  padding: 4px 8px; cursor: pointer;
  background: var(--color-error, #c00); color: #fff;
  border: none; border-radius: 4px;
}
.pw-hinweis { color: var(--color-text-maxcontrast, #888); padding: 12px 0; }
</style>
