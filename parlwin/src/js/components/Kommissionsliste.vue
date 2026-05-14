<template>
  <Teleport v-if="filterReady" to="#pw-search-slot">
    <NcTextField :value="suche" label="Suche" placeholder="Name oder Beschreibung" trailing-button-icon="close" :show-trailing-button="!!suche" @update:value="suche = $event" @trailing-button-click="suche = ''" />
  </Teleport>
  <Teleport v-if="filterReady" to="#pw-filter-slot">
    <div class="pw-filter-body">
      <NcCheckboxRadioSwitch v-model="nurAktive" type="switch">
        Nur aktive Kommissionen
      </NcCheckboxRadioSwitch>
    </div>
  </Teleport>

  <section class="pw-view-content pw-kommissionen">
      <header class="pw-view-header">
        <h2 class="pw-view-title">Kommissionen</h2>
        <span class="pw-view-count">{{ gefilterteKommissionen.length }}</span>
      </header>
      <div v-if="laden" class="pw-laden"><NcLoadingIcon :size="32" /></div>

      <div v-else class="pw-card-grid">
        <div
          v-for="k in gefilterteKommissionen"
          :key="k.id"
          class="pw-kommission-karte"
          :class="{ 'ist-inaktiv': !istAktiv(k) }"
        >
        <div class="pw-kommission-kopf" @click="toggleKommission(k.id)">
          <div>
            <h3>{{ k.name }}</h3>
            <p class="pw-kommission-status">{{ istAktiv(k) ? 'Aktiv' : 'Aufgelöst oder inaktiv' }}</p>
          </div>
          <span class="pw-toggle">{{ offene.includes(k.id) ? '▲' : '▼' }}</span>
        </div>
        <div v-if="offene.includes(k.id)" class="pw-kommission-details">
          <p v-if="k.beschreibung">{{ k.beschreibung }}</p>
          <div v-if="geschaefteFuer(k).length" class="pw-kommission-geschaefte">
            <strong>Pendente Geschäfte ({{ geschaefteFuer(k).length }}):</strong>
            <ul class="pw-kommission-geschaeftsliste">
              <li v-for="g in geschaefteFuer(k)" :key="g.id">
                <span class="pw-nr">{{ g.nummer }}</span>
                <a v-if="g.url" :href="g.url" target="_blank" class="pw-inline-link" title="Extern öffnen">↗</a>
                <span class="pw-titel">{{ g.titel }}</span>
                <span class="pw-status">{{ g.status }}</span>
              </li>
            </ul>
          </div>
          <div v-if="k.mitgliederArray && k.mitgliederArray.length" class="pw-kommission-mitglieder">
            <strong>Mitglieder:</strong>
            <div class="pw-chip-list">
              <span v-for="mitglied in k.mitgliederArray" :key="mitglied.externId || mitglied.label" class="pw-chip">
                {{ mitglied.label }}
              </span>
            </div>
          </div>
          <p v-else class="pw-hinweis">Keine Mitglieder synchronisiert.</p>
          <p v-if="!geschaefteFuer(k).length" class="pw-hinweis">
            Keine Geschäfte mit Status „Bei Kommission {{ k.name }} pendent“.
          </p>
          <div v-if="!istAktiv(k)" class="pw-inline-note">
            Diese Kommission erscheint nur, weil historische Daten vorhanden sind.
          </div>
        </div>
        </div>
        <NcEmptyContent v-if="gefilterteKommissionen.length === 0" name="Keine Kommissionen gefunden" />
      </div>
    </section>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { subscribeRealtime } from '../realtime'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'

export default {
  name: 'Kommissionsliste',
  components: { NcTextField, NcCheckboxRadioSwitch, NcLoadingIcon, NcEmptyContent },
  props: {
    mitglieder: { type: Array, default: () => [] },
  },
  data() {
    return {
      filterReady: false,
      kommissionen: [],
      geschaefte: [],
      laden: true,
      suche: '',
      nurAktive: true,
      offene: [],
      unsubRealtime: null,
    }
  },
  computed: {
    gefilterteKommissionen() {
      let liste = [...this.kommissionen]
      if (this.nurAktive) {
        liste = liste.filter((kommission) => this.istAktiv(kommission))
      }
      if (!this.suche) return liste
      const s = this.suche.toLowerCase()
      return liste.filter(k =>
        (k.name || '').toLowerCase().includes(s) ||
        (k.beschreibung || '').toLowerCase().includes(s)
      )
    },
  },
  mounted() {
    this.$nextTick(() => { this.filterReady = true })
    this.ladeKommissionen()
    this.ladeGeschaefte()
    this.unsubRealtime = subscribeRealtime(this.handleRealtimeEvent)
  },
  beforeUnmount() {
    if (this.unsubRealtime) {
      this.unsubRealtime()
      this.unsubRealtime = null
    }
  },
  methods: {
    async ladeKommissionen() {
      this.laden = true
      try {
        const { data } = await axios.get(generateUrl('/apps/parlwin/kommissionen'))
        this.kommissionen = data.map(k => ({
          ...k,
          mitgliederArray: this.parseMitglieder(k.mitglieder),
        }))
      } catch (e) {
        console.error('Fehler beim Laden der Kommissionen:', e)
      } finally {
        this.laden = false
      }
    },
    async ladeGeschaefte() {
      try {
        const { data } = await axios.get(generateUrl('/apps/parlwin/geschaefte'), { params: { show_erledigt: 1, limit: 1000 } })
        this.geschaefte = Array.isArray(data) ? data : []
      } catch (e) {
        console.error('Fehler beim Laden der Geschäfte:', e)
        this.geschaefte = []
      }
    },
    parseMitglieder(raw) {
      if (!raw) return []
      try {
        const arr = typeof raw === 'string' ? JSON.parse(raw) : raw
        if (!Array.isArray(arr)) {
          return []
        }
        return arr.map((externId) => {
          const treffer = this.mitglieder.find((mitglied) => String(mitglied.externId || mitglied.extern_id || '') === String(externId))
          return {
            externId: String(externId),
            label: treffer ? `${treffer.vorname || ''} ${treffer.name || ''}`.trim() : String(externId),
          }
        })
      } catch {
        return []
      }
    },
    istAktiv(kommission) {
      if (!kommission || kommission.geloescht === true) return false
      // Primär: explizites aktiv-Flag aus dem Scraper (vom Parlamentsserver)
      if (kommission.aktiv === false) return false
      // Sekundär: datumBis in der Vergangenheit ⇒ aufgelöst
      const bis = kommission.datumBis || ''
      if (bis) {
        const heute = new Date().toISOString().slice(0, 10)
        if (bis < heute) return false
      }
      return true
    },
    /**
     * Liefert alle Geschäfte, deren Status auf diese Kommission verweist
     * (z.B. "Bei Kommission Geschäftsprüfungskommission pendent").
     */
    geschaefteFuer(kommission) {
      if (!kommission?.name) return []
      const kname = kommission.name.toLowerCase()
      // Tokens des Kommissionsnamens (Stopwörter raus), z.B. "geschäftsprüfungskommission"
      const tokens = kname.split(/\s+/).filter(t => t.length >= 4 && t !== 'kommission')
      return this.geschaefte.filter(g => {
        const s = (g.status || '').toLowerCase()
        if (!s.includes('kommission')) return false
        if (s.includes(kname)) return true
        // Fallback: alle markanten Tokens müssen vorkommen
        return tokens.length > 0 && tokens.every(t => s.includes(t))
      })
    },
    handleRealtimeEvent(event) {
      const type = event?.type || ''
      if (type === 'sync.completed') {
        this.ladeKommissionen()
        this.ladeGeschaefte()
      }
    },
    toggleKommission(id) {
      if (this.offene.includes(id)) {
        this.offene = this.offene.filter(i => i !== id)
      } else {
        this.offene.push(id)
      }
    },
  },
}
</script>
