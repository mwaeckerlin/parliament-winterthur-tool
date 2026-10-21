<template>
  <!-- Die amtlichen Dokumente eines Geschäfts (F121): eingeklappt steht der
       Titel, ein Klick zeigt den gelesenen Inhalt — dieselbe Bedienung wie bei
       den Mitgliedern einer Kommission. EINE Komponente für die Übersicht und
       für das geöffnete Geschäft. -->
  <div v-if="dokumente && dokumente.length" class="pw-amtliche-dokumente">
    <div
      v-for="dokument in dokumente"
      :key="dokument.id"
      class="pw-dokument"
    >
      <div
        class="pw-dokument-kopf"
        role="button"
        tabindex="0"
        :aria-expanded="String(istOffen(dokument.id))"
        @click="umschalten(dokument)"
        @keydown.enter.prevent="umschalten(dokument)"
        @keydown.space.prevent="umschalten(dokument)"
      >
        <span class="pw-toggle">{{ istOffen(dokument.id) ? '▲' : '▼' }}</span>
        <strong class="pw-dokument-titel">{{ dokument.titel }}</strong>
        <span v-if="dokument.kategorie" class="pw-dokument-kategorie">{{ dokument.kategorie }}</span>
        <span v-if="dokument.datum" class="pw-dokument-datum">{{ formatiereDatum(dokument.datum) }}</span>
        <span v-if="dokument.seiten" class="pw-dokument-seiten">{{ dokument.seiten }}&nbsp;S.</span>
        <a
          v-if="dokument.url"
          :href="dokument.url"
          target="_blank"
          class="pw-inline-link"
          title="Das PDF beim Parlament öffnen"
          @click.stop
        >↗</a>
      </div>
      <div v-if="istOffen(dokument.id)" class="pw-dokument-inhalt">
        <div v-if="laden.includes(dokument.id)" class="pw-laden">
          <NcLoadingIcon :size="24" />
        </div>
        <div v-else-if="inhalt[dokument.id]" class="pw-markdown" v-html="inhalt[dokument.id]" />
        <p v-else class="pw-hinweis">
          {{ dokument.fehler || 'Der Inhalt dieses Dokuments liegt nicht gelesen vor.' }}
          <a v-if="dokument.url" :href="dokument.url" target="_blank">{{ dokument.url }}</a>
        </p>
      </div>
    </div>
  </div>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import { markdownZuHtml } from '../utils.js'

export default {
  name: 'PwAmtlicheDokumente',
  components: { NcLoadingIcon },
  props: {
    geschaeftId: { type: Number, required: true },
    // Die Köpfe der Dokumente, wie sie am Geschäft stehen. Der Inhalt kommt
    // erst beim Aufklappen, weil ein Budgetantrag mehrere hundert Kilobyte misst.
    dokumente: { type: Array, default: () => [] },
  },
  data() {
    return {
      offene: [],
      laden: [],
      inhalt: {},
    }
  },
  methods: {
    istOffen(id) {
      return this.offene.includes(id)
    },
    async umschalten(dokument) {
      if (this.istOffen(dokument.id)) {
        this.offene = this.offene.filter(id => id !== dokument.id)
        return
      }
      this.offene = [...this.offene, dokument.id]
      // Der Inhalt eines Dokuments ändert sich nur beim Abgleich, also wird er
      // einmal geholt und bleibt danach stehen.
      if (this.inhalt[dokument.id] !== undefined) return
      // Nur wer einen Grund trägt, wird nicht geholt: Ein Dokument, das beim
      // Abgleich nur verzeichnet wurde, hat der Hintergrundauftrag inzwischen
      // gelesen, auch wenn die Liste noch das Gegenteil sagt.
      if (dokument.fehler) return
      if (dokument.markdown) {
        this.inhalt = { ...this.inhalt, [dokument.id]: markdownZuHtml(dokument.markdown) }
        return
      }
      await this.holeInhalt(dokument.id)
    },
    async holeInhalt(dokumentId) {
      this.laden = [...this.laden, dokumentId]
      try {
        const antwort = await axios.get(
          generateUrl(`/apps/parlwin/geschaefte/${this.geschaeftId}/amtliche-dokumente`),
        )
        const geholt = { ...this.inhalt }
        for (const eintrag of antwort.data || []) {
          geholt[eintrag.id] = markdownZuHtml(eintrag.markdown || '')
        }
        this.inhalt = geholt
      } catch (e) {
        // Ohne Inhalt sagt die Fläche, was fehlt, statt leer zu bleiben.
        this.inhalt = { ...this.inhalt, [dokumentId]: '' }
      } finally {
        this.laden = this.laden.filter(id => id !== dokumentId)
      }
    },
    formatiereDatum(datum) {
      if (!datum) return ''
      try {
        return new Date(datum).toLocaleDateString('de-CH')
      } catch {
        return datum
      }
    },
  },
}
</script>

<style scoped lang="scss">
.pw-dokument-kopf {
  display: flex;
  align-items: baseline;
  gap: 0.5rem;
  cursor: pointer;
  padding: 0.25rem 0;
}

/* Titel und Datum stehen links beieinander: Der Titel trägt die Fettschrift,
   die Angaben daneben nicht. */
.pw-dokument-titel {
  font-weight: bold;
}

.pw-dokument-kategorie,
.pw-dokument-datum,
.pw-dokument-seiten {
  color: var(--color-text-maxcontrast);
  font-size: 0.9em;
  font-weight: normal;
}

.pw-dokument-kopf .pw-inline-link {
  margin-inline-start: auto;
}

.pw-dokument-inhalt {
  padding: 0 0 1rem 1.5rem;
}
</style>
