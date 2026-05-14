<template>
  <Teleport v-if="filterReady" to="#pw-search-slot">
    <NcTextField :value="suche" label="Suche" placeholder="Name, Partei oder E-Mail" trailing-button-icon="close" :show-trailing-button="!!suche" @update:value="suche = $event" @trailing-button-click="suche = ''" />
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
          <div class="pw-mitglied-avatar">
            <img v-if="m.fotoUrl" :src="m.fotoUrl" :alt="m.vorname + ' ' + m.name" loading="lazy" />
            <div v-else class="pw-mitglied-initial">{{ (m.vorname || '?')[0] }}{{ (m.name || '?')[0] }}</div>
          </div>
          <div class="pw-mitglied-kopftext">
            <strong>{{ m.vorname }} {{ m.name }}</strong>
            <span class="pw-mitglied-partei">{{ m.partei || 'Ohne Partei' }}</span>
          </div>
        </div>
        <div class="pw-mitglied-info">
          <div class="pw-data-pair">
            <span>Fraktion</span>
            <strong>{{ m.fraktion || '—' }}</strong>
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
  },
  mounted() {
    this.$nextTick(() => { this.filterReady = true })
  },
}
</script>
