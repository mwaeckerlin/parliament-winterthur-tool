<template>
  <Teleport v-if="filterReady" to="#pw-search-slot">
    <NcTextField
      v-model="suche"
      label="Suche"
      placeholder="Titel, Art oder Zuständigkeit"
      trailing-button-icon="close"
      :show-trailing-button="!!suche"
      @trailing-button-click="suche = ''"
    />
  </Teleport>

  <section class="pw-view-content pw-vorstoesse">
    <header class="pw-view-header">
      <h2 class="pw-view-title">Vorstösse</h2>
      <span class="pw-view-count">{{ gefiltert.length }}</span>
      <NcButton type="primary" @click="neuerVorstoss">+ Neuer Vorstoss</NcButton>
    </header>

    <div class="pw-vorstoss-filter">
      <PwField label="Herkunft">
        <select v-model="herkunftFilter" class="pw-input">
          <option value="">Alle</option>
          <option v-for="h in HERKUENFTE" :key="h.code" :value="h.code">{{ h.label }}</option>
        </select>
      </PwField>
      <PwField label="Status">
        <select v-model="statusFilter" class="pw-input">
          <option value="">Alle</option>
          <option v-for="s in STATUS" :key="s.code" :value="s.code">{{ s.label }}</option>
        </select>
      </PwField>
    </div>

    <div v-if="laden" class="pw-laden"><NcLoadingIcon :size="32" /></div>

    <div v-else>
      <div v-if="!gefiltert.length" class="pw-hinweis">
        Keine Vorstösse vorhanden. Erstellen Sie einen neuen Vorstoss.
      </div>
      <div
        v-for="vorstoss in gefiltert"
        :key="vorstoss.id"
        class="pw-vorstoss-karte"
      >
        <div class="pw-vorstoss-kopf">
          <div>
            <h3>{{ vorstoss.titel }}</h3>
            <p v-if="vorstoss.art" class="pw-vorstoss-art">{{ vorstoss.art }}</p>
          </div>
          <div class="pw-vorstoss-aktionen">
            <NcButton type="secondary" @click="bearbeiten(vorstoss)">Bearbeiten</NcButton>
            <NcButton type="error" @click="loeschen(vorstoss)">Löschen</NcButton>
          </div>
        </div>
        <div class="pw-vorstoss-meta">
          <span class="pw-badge" :class="`pw-badge-herkunft-${vorstoss.herkunft}`">{{ herkunftLabel(vorstoss.herkunft) }}</span>
          <span class="pw-badge" :class="`pw-badge-status-${vorstoss.status}`">{{ statusLabel(vorstoss.status) }}</span>
          <span v-if="vorstoss.zustaendigkeit">👤 {{ vorstoss.zustaendigkeit }}</span>
          <span v-if="vorstoss.herkunft === 'fremde' && vorstoss.beschluss">⚖️ {{ vorstoss.beschluss }}</span>
          <span v-if="vorstoss.dokument">📄 {{ vorstoss.dokument }}</span>
        </div>
      </div>
    </div>

    <!-- Bearbeitungs-Dialog -->
    <Teleport to="body">
    <div v-if="bearbeitung" class="pw-modal-overlay" @click.self="abbrechen">
      <div class="pw-modal">
        <header class="pw-modal-header">
          <h3>{{ bearbeitung.id ? 'Vorstoss bearbeiten' : 'Neuer Vorstoss' }}</h3>
          <button type="button" class="pw-modal-close" @click="abbrechen">×</button>
        </header>
        <div class="pw-modal-body">
          <PwField label="Titel *">
            <input v-model="bearbeitung.titel" type="text" class="pw-input" />
          </PwField>
          <PwField label="Art">
            <input v-model="bearbeitung.art" type="text" class="pw-input" placeholder="z.B. Motion, Postulat, Interpellation" />
          </PwField>
          <div class="pw-von-bis">
            <PwField label="Herkunft">
              <select v-model="bearbeitung.herkunft" class="pw-input">
                <option v-for="h in HERKUENFTE" :key="h.code" :value="h.code">{{ h.label }}</option>
              </select>
            </PwField>
            <PwField label="Status">
              <select v-model="bearbeitung.status" class="pw-input">
                <option v-for="s in STATUS" :key="s.code" :value="s.code">{{ s.label }}</option>
              </select>
            </PwField>
          </div>
          <PwField v-if="bearbeitung.herkunft === 'fremde'" label="Beschluss (Haltung zum fremden Vorstoss)">
            <input v-model="bearbeitung.beschluss" type="text" class="pw-input" />
          </PwField>
          <PwField label="Zuständigkeit">
            <input v-model="bearbeitung.zustaendigkeit" type="text" class="pw-input" />
          </PwField>
          <PwField label="Inhalt">
            <textarea v-model="bearbeitung.inhalt" class="pw-textarea" rows="4" />
          </PwField>
          <PwField label="Dokument (Pfad)">
            <input v-model="bearbeitung.dokument" type="text" class="pw-input" />
          </PwField>
        </div>
        <footer class="pw-modal-footer">
          <NcButton type="tertiary" @click="abbrechen">Abbrechen</NcButton>
          <NcButton type="primary" :disabled="!bearbeitung.titel || speichernLaeuft" @click="speichern">Speichern</NcButton>
        </footer>
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
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import PwField from './PwField.vue'

const HERKUENFTE = [
  { code: 'eigene', label: 'Eigene' },
  { code: 'fremde', label: 'Fremde' },
]
const STATUS = [
  { code: 'neu', label: 'Neu' },
  { code: 'entwurf', label: 'Entwurf' },
  { code: 'bereit', label: 'Bereit' },
  { code: 'eingereicht', label: 'Eingereicht' },
  { code: 'erledigt', label: 'Erledigt' },
  { code: 'pausiert', label: 'Pausiert' },
]

export default {
  name: 'Vorstoesseliste',
  components: { NcTextField, NcButton, NcLoadingIcon, PwField },
  data() {
    return {
      vorstoesse: [],
      laden: true,
      filterReady: false,
      suche: '',
      herkunftFilter: '',
      statusFilter: '',
      bearbeitung: null,
      speichernLaeuft: false,
      HERKUENFTE,
      STATUS,
    }
  },
  computed: {
    gefiltert() {
      const q = (this.suche || '').toLowerCase().trim()
      return this.vorstoesse.filter(v => {
        if (this.herkunftFilter && v.herkunft !== this.herkunftFilter) return false
        if (this.statusFilter && v.status !== this.statusFilter) return false
        if (!q) return true
        return (v.titel || '').toLowerCase().includes(q) ||
          (v.art || '').toLowerCase().includes(q) ||
          (v.zustaendigkeit || '').toLowerCase().includes(q)
      })
    },
  },
  mounted() {
    this.$nextTick(() => { this.filterReady = true })
    this.lade()
  },
  methods: {
    herkunftLabel(code) {
      return (HERKUENFTE.find(h => h.code === code) || {}).label || code
    },
    statusLabel(code) {
      return (STATUS.find(s => s.code === code) || {}).label || code
    },
    async lade() {
      this.laden = true
      try {
        const { data } = await axios.get(generateUrl('/apps/parlwin/vorstoesse'))
        this.vorstoesse = Array.isArray(data) ? data : []
      } catch (e) {
        console.error('Fehler beim Laden der Vorstösse:', e)
      } finally {
        this.laden = false
      }
    },
    neuerVorstoss() {
      this.bearbeitung = {
        id: 0,
        titel: '',
        art: '',
        herkunft: 'eigene',
        status: 'neu',
        beschluss: '',
        zustaendigkeit: '',
        inhalt: '',
        dokument: '',
      }
    },
    bearbeiten(vorstoss) {
      this.bearbeitung = JSON.parse(JSON.stringify({
        id: vorstoss.id || 0,
        titel: vorstoss.titel || '',
        art: vorstoss.art || '',
        herkunft: vorstoss.herkunft || 'eigene',
        status: vorstoss.status || 'neu',
        beschluss: vorstoss.beschluss || '',
        zustaendigkeit: vorstoss.zustaendigkeit || '',
        inhalt: vorstoss.inhalt || '',
        dokument: vorstoss.dokument || '',
      }))
    },
    abbrechen() {
      this.bearbeitung = null
    },
    async speichern() {
      if (!this.bearbeitung || !this.bearbeitung.titel) return
      this.speichernLaeuft = true
      try {
        const payload = { ...this.bearbeitung }
        if (payload.id) {
          await axios.put(generateUrl(`/apps/parlwin/vorstoesse/${payload.id}`), payload)
        } else {
          await axios.post(generateUrl('/apps/parlwin/vorstoesse'), payload)
        }
        this.bearbeitung = null
        await this.lade()
      } catch (e) {
        showError('Vorstoss konnte nicht gespeichert werden: ' + (e?.response?.data?.fehler || e?.message || ''))
      } finally {
        this.speichernLaeuft = false
      }
    },
    async loeschen(vorstoss) {
      if (!confirm(`Vorstoss „${vorstoss.titel}" wirklich löschen?`)) return
      try {
        await axios.delete(generateUrl(`/apps/parlwin/vorstoesse/${vorstoss.id}`))
        await this.lade()
      } catch (e) {
        showError('Vorstoss konnte nicht gelöscht werden: ' + (e?.response?.data?.fehler || e?.message || ''))
      }
    },
  },
}
</script>

<style scoped>
.pw-vorstoss-filter {
  display: flex;
  gap: 12px;
  margin-bottom: 12px;
}
.pw-vorstoss-karte {
  border: 1px solid var(--color-border, #ddd);
  border-radius: 6px;
  padding: 12px 16px;
  margin-bottom: 12px;
  background: var(--color-main-background, #fff);
}
.pw-vorstoss-kopf {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 12px;
}
.pw-vorstoss-kopf h3 { margin: 0 0 4px 0; }
.pw-vorstoss-art { margin: 0; color: var(--color-text-maxcontrast, #888); }
.pw-vorstoss-aktionen { display: flex; gap: 8px; }
.pw-vorstoss-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  margin-top: 8px;
  font-size: 0.9em;
  color: var(--color-text-maxcontrast, #666);
  align-items: center;
}
.pw-badge {
  padding: 2px 8px;
  border-radius: 12px;
  font-size: 0.85em;
  background: var(--color-background-dark, #eee);
  color: var(--color-main-text, #000);
}
.pw-badge-herkunft-fremde { background: var(--color-warning, #e9a); color: #fff; }
.pw-badge-herkunft-eigene { background: var(--color-success, #6a6); color: #fff; }
.pw-input, .pw-textarea {
  width: 100%; padding: 6px 8px;
  border: 1px solid var(--color-border, #ccc);
  border-radius: 4px;
  background: var(--color-main-background, #fff);
  color: var(--color-main-text, #000);
}
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
.pw-von-bis { display: flex; gap: 12px; }
.pw-von-bis label { flex: 1; }
.pw-modal-footer {
  display: flex; justify-content: flex-end; gap: 8px;
  padding: 12px 16px; border-top: 1px solid var(--color-border, #ddd);
}
.pw-hinweis { color: var(--color-text-maxcontrast, #888); padding: 12px 0; }
</style>
