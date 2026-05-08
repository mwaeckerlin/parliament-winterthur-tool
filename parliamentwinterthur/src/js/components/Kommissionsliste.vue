<template>
  <div class="pw-kommissionen">
    <div class="pw-toolbar">
      <h2>Kommissionen</h2>
      <input v-model="suche" type="text" placeholder="Suchen..." class="pw-suche" />
    </div>

    <div v-if="laden" class="pw-laden">Daten werden geladen...</div>

    <div v-else>
      <div
        v-for="k in gefilterteKommissionen"
        :key="k.id"
        class="pw-kommission-karte"
      >
        <div class="pw-kommission-kopf" @click="toggleKommission(k.id)">
          <h3>{{ k.name }}</h3>
          <span class="pw-toggle">{{ offene.includes(k.id) ? '▲' : '▼' }}</span>
        </div>
        <div v-if="offene.includes(k.id)" class="pw-kommission-details">
          <p v-if="k.beschreibung">{{ k.beschreibung }}</p>
          <div v-if="k.mitgliederArray && k.mitgliederArray.length" class="pw-kommission-mitglieder">
            <strong>Mitglieder:</strong>
            <ul>
              <li v-for="mId in k.mitgliederArray" :key="mId">{{ mId }}</li>
            </ul>
          </div>
        </div>
      </div>
      <p v-if="gefilterteKommissionen.length === 0" class="pw-leer">Keine Kommissionen gefunden.</p>
    </div>
  </div>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'

export default {
  name: 'Kommissionsliste',
  data() {
    return {
      kommissionen: [],
      laden: true,
      suche: '',
      offene: [],
    }
  },
  computed: {
    gefilterteKommissionen() {
      if (!this.suche) return this.kommissionen
      const s = this.suche.toLowerCase()
      return this.kommissionen.filter(k =>
        (k.name || '').toLowerCase().includes(s) ||
        (k.beschreibung || '').toLowerCase().includes(s)
      )
    },
  },
  mounted() {
    this.ladeKommissionen()
  },
  methods: {
    async ladeKommissionen() {
      this.laden = true
      try {
        const { data } = await axios.get(generateUrl('/apps/parliamentwinterthur/kommissionen'))
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
    parseMitglieder(raw) {
      if (!raw) return []
      try {
        const arr = typeof raw === 'string' ? JSON.parse(raw) : raw
        return Array.isArray(arr) ? arr : []
      } catch {
        return []
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
