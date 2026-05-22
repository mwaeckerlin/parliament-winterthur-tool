<template>
  <Teleport v-if="filterReady" to="#pw-search-slot">
    <NcTextField v-model="suche" label="Suche" placeholder="Name, Partei oder E-Mail" trailing-button-icon="close" :show-trailing-button="!!suche" @trailing-button-click="suche = ''" />
  </Teleport>
  <Teleport v-if="filterReady" to="#pw-filter-slot">
    <div class="pw-filter-body">
      <NcSelect v-model="filterFraktionOption" :options="fraktionOptions" :clearable="false" input-label="Fraktion" />
      <NcSelect v-model="filterParteiOption" :options="parteiOptions" :clearable="false" input-label="Partei" />
      <NcCheckboxRadioSwitch v-model="nurAktive" type="switch">
        Nur aktive Mitglieder
      </NcCheckboxRadioSwitch>
    </div>
  </Teleport>

  <section class="pw-view-content pw-mitglieder">
      <header class="pw-view-header">
        <h2 class="pw-view-title">Mitglieder</h2>
        <span class="pw-view-count">{{ gefilterteMitglieder.length }}</span>
      </header>
      <div class="pw-mitglieder-grid">
      <div
        v-for="m in gefilterteMitglieder"
        :key="m.id"
        class="pw-mitglied-karte"
        :class="{ inaktiv: !m.aktiv }"
      >
        <div class="pw-mitglied-kopf">
          <div class="pw-mitglied-kopftext">
            <strong>{{ m.vorname }} {{ m.name }}</strong>
            <span class="pw-mitglied-partei">{{ m.partei || 'Ohne Partei' }}</span>
            <span v-if="fraktionsRolle(m)" class="pw-mitglied-fraktionsrolle">{{ fraktionsRolle(m) }}</span>
          </div>
        </div>
        <div class="pw-mitglied-info">
          <div class="pw-data-pair">
            <span>Fraktion</span>
            <strong>{{ m.fraktion || '—' }}</strong>
          </div>
          <div v-if="kommissionenVon(m).length" class="pw-data-pair pw-mitglied-kommissionen">
            <span>Kommission</span>
            <strong>
              <span
                v-for="(k, idx) in kommissionenVon(m)"
                :key="idx"
                class="pw-mitglied-kommission-eintrag"
              >
                <span class="pw-mitglied-kommission-name">{{ k.name }}</span>
                <span v-if="k.rolle" class="pw-mitglied-kommission-rolle">{{ k.rolle }}</span>
              </span>
            </strong>
          </div>
          <a v-if="m.email" :href="'mailto:' + m.email" class="pw-mitglied-email">{{ m.email }}</a>
          <span v-if="!m.aktiv" class="pw-badge inaktiv">Ehemaliges Mitglied</span>
        </div>
      </div>
      </div>

      <NcEmptyContent v-if="gefilterteMitglieder.length === 0" name="Keine Mitglieder gefunden" />
    </section>
</template>

<script>
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'

export default {
  name: 'Mitgliederliste',
  components: { NcTextField, NcSelect, NcCheckboxRadioSwitch, NcEmptyContent },
  props: {
    mitglieder: { type: Array, default: () => [] },
    fraktionen: { type: Array, default: () => [] },
    kommissionen: { type: Array, default: () => [] },
  },
  data() {
    return {
      filterReady: false,
      suche: '',
      filterFraktion: '',
      filterPartei: '',
      nurAktive: true,
    }
  },
  computed: {
    alleFraktionen() {
      return [...new Set(this.basisMitglieder.map(m => m.fraktion).filter(Boolean))].sort()
    },
    alleParteien() {
      return [...new Set(this.basisMitglieder.map(m => m.partei).filter(Boolean))].sort()
    },
    fraktionOptions() {
      return [{ label: 'Alle Fraktionen', value: '' }, ...this.alleFraktionen.map(f => ({ label: f, value: f }))]
    },
    parteiOptions() {
      return [{ label: 'Alle Parteien', value: '' }, ...this.alleParteien.map(p => ({ label: p, value: p }))]
    },
    filterFraktionOption: {
      get() { return this.fraktionOptions.find(o => o.value === this.filterFraktion) || this.fraktionOptions[0] },
      set(v) { this.filterFraktion = v ? v.value : '' },
    },
    filterParteiOption: {
      get() { return this.parteiOptions.find(o => o.value === this.filterPartei) || this.parteiOptions[0] },
      set(v) { this.filterPartei = v ? v.value : '' },
    },
    basisMitglieder() {
      return this.nurAktive ? this.mitglieder.filter(m => m.aktiv) : this.mitglieder
    },
    gefilterteMitglieder() {
      let liste = [...this.basisMitglieder]
      if (this.suche) {
        const s = this.suche.toLowerCase()
        liste = liste.filter(m =>
          (m.name + ' ' + m.vorname).toLowerCase().includes(s) ||
          (m.partei || '').toLowerCase().includes(s) ||
          (m.fraktion || '').toLowerCase().includes(s) ||
          (m.email || '').toLowerCase().includes(s)
        )
      }
      if (this.filterFraktion) {
        liste = liste.filter(m => m.fraktion === this.filterFraktion)
      }
      if (this.filterPartei) {
        liste = liste.filter(m => m.partei === this.filterPartei)
      }
      return liste.sort((a, b) => `${a.name}${a.vorname}`.localeCompare(`${b.name}${b.vorname}`))
    },
    fraktionsrolleNachExternId() {
      const map = new Map()
      for (const f of this.fraktionen || []) {
        if (f?.aktiv === false) continue
        const eintraege = this.parseBehoerdenMitglieder(f?.mitglieder)
        for (const e of eintraege) {
          if (!e.externId || e.aktiv === false) continue
          const rolle = this.kategorisiereFraktionsfunktion(e.funktion)
          if (rolle) {
            map.set(e.externId, rolle)
          }
        }
      }
      return map
    },
    kommissionenNachExternId() {
      const map = new Map()
      for (const k of this.kommissionen || []) {
        if (k?.aktiv === false) continue
        const name = k?.name || ''
        const eintraege = this.parseBehoerdenMitglieder(k?.mitglieder)
        for (const e of eintraege) {
          if (!e.externId || e.aktiv === false) continue
          const rolle = this.kategorisiereKommissionsfunktion(e.funktion)
          const liste = map.get(e.externId) || []
          liste.push({ name, rolle })
          map.set(e.externId, liste)
        }
      }
      // Sortierung: Präsident zuerst, dann Vize, dann alphabetisch
      const rang = (eintrag) => {
        if (eintrag.rolle === 'Präsident') return 0
        if (eintrag.rolle === 'Vizepräsident') return 1
        return 2
      }
      for (const [k, v] of map) {
        v.sort((a, b) => rang(a) - rang(b) || a.name.localeCompare(b.name, 'de'))
        map.set(k, v)
      }
      return map
    },
  },
  methods: {
    parseBehoerdenMitglieder(raw) {
      if (!raw) return []
      let arr
      try {
        arr = typeof raw === 'string' ? JSON.parse(raw) : raw
      } catch {
        return []
      }
      if (!Array.isArray(arr)) return []
      return arr.map((e) => {
        if (e && typeof e === 'object') {
          return {
            externId: String(e.externId ?? e.extern_id ?? e.id ?? ''),
            funktion: String(e.funktion ?? ''),
            aktiv: e.aktiv !== false,
          }
        }
        return { externId: String(e ?? ''), funktion: '', aktiv: true }
      })
    },
    kategorisiereFraktionsfunktion(funktion) {
      const v = (funktion || '').toLowerCase()
      if (!v) return ''
      const istPraesident = v.includes('präsiden') || v.includes('praesiden')
      if (!istPraesident) return ''
      const istVize = v.includes('vize') || v.includes('stellvert')
      return istVize ? 'Vize Fraktionspräsident' : 'Fraktionspräsident'
    },
    kategorisiereKommissionsfunktion(funktion) {
      const v = (funktion || '').toLowerCase()
      if (!v) return ''
      const istPraesident = v.includes('präsiden') || v.includes('praesiden')
      if (!istPraesident) return ''
      const istVize = v.includes('vize') || v.includes('stellvert')
      return istVize ? 'Vizepräsident' : 'Präsident'
    },
    fraktionsRolle(m) {
      const id = String(m?.externId || m?.extern_id || '')
      return id ? (this.fraktionsrolleNachExternId.get(id) || '') : ''
    },
    kommissionenVon(m) {
      const id = String(m?.externId || m?.extern_id || '')
      return id ? (this.kommissionenNachExternId.get(id) || []) : []
    },
  },
  mounted() {
    this.$nextTick(() => { this.filterReady = true })
  },
}
</script>
