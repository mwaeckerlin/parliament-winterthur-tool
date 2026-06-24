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
        <ul v-if="offen.includes(v.version)">
          <li v-for="(e, i) in v.eintraege" :key="i">
            {{ e.text }}
            <ul v-if="e.kinder.length">
              <li v-for="(k, j) in e.kinder" :key="j">
                {{ k.text }}
                <ul v-if="k.kinder.length">
                  <li v-for="(s, l) in k.kinder" :key="l">{{ s.text }}</li>
                </ul>
              </li>
            </ul>
          </li>
        </ul>
      </div>
    </div>
  </section>
</template>

<script>
import changelogText from '@changelog'

// Parst die CHANGELOG.md (verschachtelte Markdown-Liste) in eine Baumstruktur.
// Bewusst KEIN Markdown→HTML-Rendering: die Ansicht nutzt exakt dieselbe
// HTML-/CSS-Struktur (pw-view-content, pw-card-grid, pw-data-card, pw-toggle)
// und dasselbe Handorgel-Verhalten wie die übrigen Ansichten.
function parseChangelog(text) {
  const versionen = []
  let aktuelle = null
  let stack = []
  for (const zeile of String(text).split('\n')) {
    const vMatch = zeile.match(/^-\s+(\d{4}-\d{2}-\d{2})\s+\*\*(.+?)\*\*/)
    if (vMatch) {
      aktuelle = { datum: vMatch[1], version: vMatch[2], eintraege: [] }
      versionen.push(aktuelle)
      stack = [{ level: -1, kinder: aktuelle.eintraege }]
      continue
    }
    if (!aktuelle) continue
    const eMatch = zeile.match(/^(\s*)-\s+(.+)$/)
    if (!eMatch) continue
    const level = eMatch[1].length
    const knoten = { text: eMatch[2].replace(/\*\*(.+?)\*\*/g, '$1'), kinder: [] }
    while (stack.length > 1 && stack[stack.length - 1].level >= level) stack.pop()
    stack[stack.length - 1].kinder.push(knoten)
    stack.push({ level, kinder: knoten.kinder })
  }
  return versionen
}

export default {
  name: 'Changelog',
  data() {
    const versionen = parseChangelog(changelogText)
    return {
      versionen,
      // Neueste Version standardmässig aufgeklappt.
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
