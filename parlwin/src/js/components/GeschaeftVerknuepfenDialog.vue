<template>
  <Teleport to="body">
    <div class="pw-modal-overlay" @click.self="$emit('schliessen')">
      <div class="pw-modal">
        <div class="pw-modal-kopf">
          <h3>{{ kopf }}</h3>
          <button type="button" class="button pw-btn-schliessen" aria-label="Dialog schliessen" @click.stop="$emit('schliessen')">✕</button>
        </div>
        <div class="pw-modal-body">
          <NcTextField v-model="suche" label="Suche" placeholder="Nr. oder Titel" />
          <small class="pw-hinweis">Ähnlichste zum Titel zuerst, sonst neueste.</small>
          <ul class="pw-verknuepfen-liste">
            <li v-for="g in aehnliche" :key="g.id">
              <button type="button" class="button pw-verknuepfen-eintrag" @click="$emit('verknuepfen', g)">
                <strong>{{ g.nummer || '—' }}</strong> <span>{{ g.titel }}</span>
              </button>
            </li>
          </ul>
          <div v-if="!aehnliche.length" class="pw-hinweis">Keine Geschäfte gefunden.</div>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script>
import NcTextField from '@nextcloud/vue/components/NcTextField'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { titelAehnlichkeit } from '../utils'
import { BESTAND_SCHLUESSEL, istErledigt } from '../geschaefte-bestand.js'

/** So viele Treffer zeigt der Dialog, die neuesten, wie die Tabelle in «Geschäfte». */
const ANZEIGE = 500
const neuesteZuerst = (a, b) => String(b.datum || '').localeCompare(String(a.datum || ''))

/**
 * Geteilter «Mit Geschäft verknüpfen»-Dialog — EIN Ort für die Auswahl eines
 * Geschäfts per Titel-Ähnlichkeit, genutzt von Vorstoss→Geschäft UND von
 * eigenes→offizielles Geschäft (kein Copy-Paste). Nimmt die geteilte Liste der
 * Geschäfte und lädt nur ohne sie selbst, zeigt von den Treffern die neuesten
 * 500, die ähnlichsten zum übergebenen Titel zuoberst, und meldet die Auswahl
 * über `verknuepfen`.
 */
export default {
  name: 'GeschaeftVerknuepfenDialog',
  components: { NcTextField },
  props: {
    // Quell-Titel, nach dessen Ähnlichkeit sortiert wird.
    titel: { type: String, default: '' },
    // Dieses Geschäft nicht anbieten (verhindert die Selbst-Verknüpfung).
    ausschlussId: { type: Number, default: 0 },
    // Nur offizielle Parlamentsgeschäfte anbieten (keine eigenen).
    nurOffizielle: { type: Boolean, default: false },
    // Auch erledigte Geschäfte anbieten (für eigenes→offizielles: das offizielle
    // Geschäft kann bereits erledigt sein und wäre sonst nicht auffindbar).
    inklusiveErledigt: { type: Boolean, default: false },
    kopf: { type: String, default: 'Mit Geschäft verknüpfen' },
  },
  inject: { geteilterBestand: { from: BESTAND_SCHLUESSEL, default: null } },
  emits: ['verknuepfen', 'schliessen'],
  data() {
    return { suche: '', geschaefte: [] }
  },
  async mounted() {
    // In der App steht die Liste der Geschäfte schon bereit (F150); der Dialog
    // nimmt sie und fragt den Server nicht noch einmal.
    if (this.geteilterBestand) {
      const alle = await this.geteilterBestand.laden()
      this.geschaefte = alle.filter(g => this.inklusiveErledigt || !istErledigt(g))
      return
    }
    try {
      const params = { limit: 500 }
      if (this.inklusiveErledigt) params.show_erledigt = 1
      const { data } = await axios.get(generateUrl('/apps/parlwin/geschaefte'), { params })
      this.geschaefte = Array.isArray(data) ? data : []
    } catch (e) {
      this.geschaefte = []
    }
  },
  computed: {
    // Die Ähnlichkeit hängt nur am Titel, also je Geschäft einmal und nicht bei jedem Tastendruck.
    aehnlichkeit() {
      return new Map(this.geschaefte.map(g => [g.id, titelAehnlichkeit(this.titel, g.titel || '')]))
    },
    aehnliche() {
      const suche = (this.suche || '').toLowerCase().trim()
      let liste = this.geschaefte.filter(g => !g.geloescht && g.id !== this.ausschlussId)
      if (this.nurOffizielle) {
        liste = liste.filter(g => !String(g.externId || '').startsWith('eigen:'))
      }
      if (suche) {
        liste = liste.filter(g =>
          (g.titel || '').toLowerCase().includes(suche) ||
          (g.nummer || '').toLowerCase().includes(suche))
      }
      const neueste = [...liste].sort(neuesteZuerst).slice(0, ANZEIGE)
      return neueste.sort((a, b) => (this.aehnlichkeit.get(b.id) - this.aehnlichkeit.get(a.id)) || neuesteZuerst(a, b))
    },
  },
}
</script>

<style scoped>
.pw-verknuepfen-liste { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 0.25rem; }
.pw-verknuepfen-eintrag { inline-size: 100%; text-align: start; justify-content: flex-start; gap: 0.5rem; }
</style>
