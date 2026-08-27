<template>
  <section class="pw-view-content pw-anleitung">
    <header class="pw-view-header">
      <h2 class="pw-view-title">Bedienungsanleitung</h2>
    </header>
    <!-- Das README des Projekts, als formatiertes Markdown gerendert — damit die
         Anleitung direkt im Tool auffindbar ist. -->
    <div class="pw-data-card pw-anleitung-inhalt" v-html="html" />
  </section>
</template>

<script>
import readmeText from '@readme'
import { markdownZuHtml } from '../utils'

export default {
  name: 'Bedienungsanleitung',
  data() {
    return { html: markdownZuHtml(String(readmeText)) }
  },
}
</script>

<style scoped lang="scss">
.pw-anleitung-inhalt { padding: 1rem 1.25rem; line-height: 1.6; max-inline-size: 60rem; }
/* Titelregel: über einer Überschrift viel Abstand, darunter wenig. */
.pw-anleitung-inhalt :deep(h1) { font-size: 1.5rem; margin-block: 0 0.5rem; }
.pw-anleitung-inhalt :deep(h2) { font-size: 1.25rem; margin-block: 1.75rem 0.4rem; }
.pw-anleitung-inhalt :deep(h3) { font-size: 1.1rem; margin-block: 1.25rem 0.3rem; }
.pw-anleitung-inhalt :deep(h4) { font-size: 1rem; margin-block: 1rem 0.25rem; }
.pw-anleitung-inhalt :deep(p) { margin-block: 0.5rem 0; }
.pw-anleitung-inhalt :deep(ul), .pw-anleitung-inhalt :deep(ol) { margin-block: 0.4rem; padding-inline-start: 1.5rem; }
.pw-anleitung-inhalt :deep(a) { color: var(--color-primary-element); }
.pw-anleitung-inhalt :deep(code) { background: var(--pw-surface-soft); padding: 0.05rem 0.3rem; border-radius: var(--pw-radius-small); }
.pw-anleitung-inhalt :deep(pre) { overflow-x: auto; background: var(--pw-surface-soft); padding: 0.6rem 0.8rem; border-radius: var(--pw-radius-small); }
.pw-anleitung-inhalt :deep(pre) code { background: none; padding: 0; }
</style>
