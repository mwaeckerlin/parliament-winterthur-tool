<template>
  <div class="pw-changelog">
    <h2 class="pw-changelog-titel">Änderungsverlauf</h2>
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
      return markdownZuHtml(changelogText)
    },
  },
}
</script>
