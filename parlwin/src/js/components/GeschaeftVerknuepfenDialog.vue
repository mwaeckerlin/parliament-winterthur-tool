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

/**
 * Geteilter «Mit Geschäft verknüpfen»-Dialog — EIN Ort für die Auswahl eines
 * Geschäfts per Titel-Ähnlichkeit, genutzt von Vorstoss→Geschäft UND von
 * eigenes→offizielles Geschäft (kein Copy-Paste). Lädt die Geschäfte selbst,
 * sortiert die ähnlichsten zum übergebenen Titel zuoberst (sonst neueste) und
 * meldet die Auswahl über `verknuepfen`.
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
  emits: ['verknuepfen', 'schliessen'],
  data() {
    return { suche: '', geschaefte: [] }
  },
  async mounted() {
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
      return [...liste].sort((a, b) => {
        const d = titelAehnlichkeit(this.titel, b.titel || '') - titelAehnlichkeit(this.titel, a.titel || '')
        if (d !== 0) return d
        return String(b.datum || '').localeCompare(String(a.datum || ''))
      })
    },
  },
}
</script>

<style scoped>
.pw-verknuepfen-liste { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 0.25rem; }
.pw-verknuepfen-eintrag { inline-size: 100%; text-align: start; justify-content: flex-start; gap: 0.5rem; }
</style>
