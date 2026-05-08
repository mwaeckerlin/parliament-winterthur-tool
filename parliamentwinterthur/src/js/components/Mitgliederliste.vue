<template>
  <div class="pw-mitglieder">
    <div class="pw-toolbar">
      <h2>Mitglieder</h2>
      <div class="pw-filter">
        <input v-model="suche" type="text" placeholder="Suchen..." class="pw-suche" />
        <select v-model="filterFraktion" class="pw-select">
          <option value="">Alle Fraktionen</option>
          <option v-for="f in alleFraktionen" :key="f" :value="f">{{ f }}</option>
        </select>
        <select v-model="filterPartei" class="pw-select">
          <option value="">Alle Parteien</option>
          <option v-for="p in alleParteien" :key="p" :value="p">{{ p }}</option>
        </select>
        <label>
          <input v-model="nurAktive" type="checkbox" />
          Nur aktive Mitglieder
        </label>
      </div>
    </div>

    <div class="pw-mitglieder-grid">
      <div
        v-for="m in gefilterteMitglieder"
        :key="m.id"
        class="pw-mitglied-karte"
        :class="{ inaktiv: !m.aktiv }"
      >
        <div class="pw-mitglied-foto">
          <img v-if="m.fotoUrl" :src="m.fotoUrl" :alt="m.vorname + ' ' + m.name" loading="lazy" />
          <div v-else class="pw-mitglied-initial">{{ (m.vorname || '?')[0] }}{{ (m.name || '?')[0] }}</div>
        </div>
        <div class="pw-mitglied-info">
          <strong>{{ m.vorname }} {{ m.name }}</strong>
          <span class="pw-mitglied-partei">{{ m.partei }}</span>
          <span class="pw-mitglied-fraktion">{{ m.fraktion }}</span>
          <a v-if="m.email" :href="'mailto:' + m.email" class="pw-mitglied-email">✉️ {{ m.email }}</a>
          <span v-if="!m.aktiv" class="pw-badge inaktiv">Ehemaliges Mitglied</span>
        </div>
      </div>
    </div>

    <p v-if="gefilterteMitglieder.length === 0" class="pw-leer">
      Keine Mitglieder gefunden.
    </p>
  </div>
</template>

<script>
export default {
  name: 'Mitgliederliste',
  props: {
    mitglieder: { type: Array, default: () => [] },
  },
  data() {
    return {
      suche: '',
      filterFraktion: '',
      filterPartei: '',
      nurAktive: true,
    }
  },
  computed: {
    alleFraktionen() {
      return [...new Set(this.mitglieder.map(m => m.fraktion).filter(Boolean))].sort()
    },
    alleParteien() {
      return [...new Set(this.mitglieder.map(m => m.partei).filter(Boolean))].sort()
    },
    gefilterteMitglieder() {
      let liste = [...this.mitglieder]
      if (this.nurAktive) {
        liste = liste.filter(m => m.aktiv)
      }
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
      return liste.sort((a, b) => (a.name + a.vorname).localeCompare(b.name + b.vorname))
    },
  },
}
</script>
