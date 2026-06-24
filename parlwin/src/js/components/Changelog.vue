<template>
  <div class="pw-changelog">
    <div class="pw-changelog-inhalt" v-html="html" />
  </div>
</template>

<script>
import changelogText from '@changelog'
import { markdownZuHtml } from '../utils'

export default {
  name: 'Changelog',
  computed: {
    // Der Changelog wird zur Build-Zeit als Rohtext eingebunden und hier als
    // (gesäubertes) HTML gerendert.
    html() {
      // Die führende «# Changelog»-Überschrift entfernen – die anderen Ansichten
      // haben keinen eigenen Seitentitel im Inhalt (Konsistenz; der Tab-Name genügt).
      const ohneTitel = String(changelogText).replace(/^#\s+Changelog\s*\n+/i, '')
      return markdownZuHtml(ohneTitel)
    },
  },
}
</script>
