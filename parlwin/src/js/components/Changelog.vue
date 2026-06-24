<template>
  <section class="pw-view-content pw-changelog">
    <header class="pw-view-header">
      <h2 class="pw-view-title">Änderungsverlauf</h2>
      <span class="pw-view-count">{{ versionen.length }}</span>
    </header>
    <div class="pw-card-grid">
      <div v-for="v in versionen" :key="v.version" class="pw-data-card">
        <div
          class="pw-data-card-header"
          role="button"
          tabindex="0"
          @click="toggle(v.version)"
          @keydown.enter.prevent="toggle(v.version)"
        >
          <h3>{{ v.version }}</h3>
          <span class="pw-view-count">{{ v.datum }}</span>
          <span class="pw-toggle">{{ offen.includes(v.version) ? '▲' : '▼' }}</span>
        </div>
        <!-- Card-Inhalt: sauber formatiertes Markdown (fett, Listen, …) -->
        <div v-if="offen.includes(v.version)" class="pw-changelog-eintraege" v-html="v.html" />
      </div>
    </div>
  </section>
</template>

<script>
import changelogText from '@changelog'
import { markdownZuHtml } from '../utils'

// Parst die CHANGELOG.md in Versionen. Die Seiten-Hülle nutzt exakt dieselbe
// Struktur/CSS wie alle Ansichten (pw-view-content, pw-card-grid, pw-data-card,
// pw-toggle, Handorgel); der Card-Inhalt einer Version wird als sauber
// formatiertes Markdown gerendert.
function parseChangelog(text) {
  const versionen = []
  let aktuelle = null
  for (const zeile of String(text).split('\n')) {
    const vMatch = zeile.match(/^-\s+(\d{4}-\d{2}-\d{2})\s+\*\*(.+?)\*\*/)
    if (vMatch) {
      aktuelle = { datum: vMatch[1], version: vMatch[2], mdZeilen: [] }
      versionen.push(aktuelle)
      continue
    }
    if (!aktuelle) continue
    // Änderungs-Zeilen (eingerückt) sammeln, eine Ebene Einrückung entfernen,
    // damit markdown-it sie als eigenständige (verschachtelte) Liste rendert.
    if (/^\s+-\s+/.test(zeile)) {
      aktuelle.mdZeilen.push(zeile.replace(/^ {4}/, ''))
    }
  }
  return versionen.map(v => ({
    version: v.version,
    datum: v.datum,
    html: markdownZuHtml(v.mdZeilen.join('\n')),
  }))
}

export default {
  name: 'Changelog',
  data() {
    const versionen = parseChangelog(changelogText)
    return {
      versionen,
      offen: versionen.length ? [versionen[0].version] : [],
    }
  },
  methods: {
    toggle(version) {
      this.offen = this.offen.includes(version)
        ? this.offen.filter(v => v !== version)
        : [...this.offen, version]
    },
  },
}
</script>
