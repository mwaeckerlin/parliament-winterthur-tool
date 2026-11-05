import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { reactive } from 'vue'
import { ereignisseBuendeln, listeAbgleichen } from './nachladen.js'

/**
 * Die Liste der Geschäfte, die «Kommissionen», «Geschäfte» und «Sitzungen»
 * teilen (F150). Die App lädt sie einmal, und ein Ereignis lädt nur die
 * Geschäfte nach, die es nennt. Ohne geteilte Liste, in einer Ansicht für sich,
 * legt die Ansicht ihre eigene an; das Verhalten ist dasselbe.
 */

/** Der Schlüssel, unter dem App.vue die geteilte Liste bereitstellt. */
export const BESTAND_SCHLUESSEL = 'parlwinGeschaefteBestand'

/** So viele Geschäfte holt eine Anfrage der ganzen Liste. */
export const SEITE = 1000

/** So viele IDs fragt eine Anfrage nach einem Ereignis nach; der Server nimmt bis zu 500. */
export const IDS_JE_ANFRAGE = 200

/**
 * Die Ereignisse, mit denen ein Abgleich endet: abgeschlossen, abgebrochen oder
 * gescheitert. Ihr Feld `geschaeftIds` nennt die Geschäfte, die er bis dahin
 * geändert hat.
 */
export const ABGLEICH_ENDE = ['sync.completed', 'sync.cancelled', 'sync.failed']

/** Grenze gegen eine endlose Schleife: 50 Seiten sind 50'000 Geschäfte, weit jenseits des Bestands. */
const HOECHSTENS_SEITEN = 50

const URL_GESCHAEFTE = () => generateUrl('/apps/parlwin/geschaefte')

/**
 * Erledigt ist ein Geschäft nach derselben Regel wie auf dem Server
 * (GeschaeftMapper::findAll): Sein Status enthält «erledigt», «abgeschlossen»
 * oder «aufgehoben».
 */
export function istErledigt(geschaeft) {
  const status = String(geschaeft?.status || '').toLowerCase()
  return status.includes('erledigt') || status.includes('abgeschlossen') || status.includes('aufgehoben')
}

/** Neueste zuerst, wie die Liste des Servers. */
function nachDatum(a, b) {
  return String(b.datum || '').localeCompare(String(a.datum || ''))
}

export function erzeugeBestand({ client = axios } = {}) {
  const zustand = reactive({ geschaefte: [], geladen: false, laden: false, fehler: '', version: 0 })
  let ladevorgang = null
  // Verlangt jemand während einer Ladung den neuen Stand, folgt ihr eine weitere.
  let nochEinmal = false
  // Geschäfte, die ein Ereignis während der ersten Ladung nennt; sie kommen danach.
  const ausstehend = new Set()

  /**
   * Jede Anfrage zieht eine Nummer, in der Reihenfolge, in der sie abgeht. Eine
   * Antwort gilt für ein Geschäft nur, wenn keine spätere Anfrage es schon
   * gesetzt hat: `angewandt` nennt die Nummer der letzten übernommenen Antwort
   * je Geschäft, `basis` die der letzten übernommenen ganzen Liste.
   */
  let zaehler = 0
  let basis = -1
  const angewandt = new Map()
  const stand = (id) => Math.max(basis, angewandt.get(id) ?? -1)

  const geaendert = () => { zustand.version++ }

  /** Eine Runde über alle Seiten, erledigte eingeschlossen. */
  async function ganzeListe() {
    const nummer = zaehler++
    const alle = []
    for (let seite = 0; seite < HOECHSTENS_SEITEN; seite++) {
      const { data } = await client.get(URL_GESCHAEFTE(), {
        params: { show_erledigt: 1, limit: SEITE, offset: seite * SEITE },
      })
      const teil = Array.isArray(data) ? data : []
      alle.push(...teil)
      if (teil.length < SEITE) { break }
    }
    // Was seit dem Beginn dieser Runde nachgeladen wurde, ist neuer als ihre
    // Antwort und bleibt, wie es ist: geändert, neu oder entfernt.
    const neuer = (id) => stand(id) > nummer
    const jetzt = new Map(zustand.geschaefte.map((g) => [g.id, g]))
    const ziel = alle.flatMap((g) => {
      if (!neuer(g.id)) { return [g] }
      return jetzt.has(g.id) ? [jetzt.get(g.id)] : []
    })
    const imZiel = new Set(ziel.map((g) => g.id))
    for (const g of zustand.geschaefte) {
      if (!neuer(g.id) || imZiel.has(g.id)) { continue }
      const vor = ziel.findIndex((x) => nachDatum(g, x) < 0)
      ziel.splice(vor < 0 ? ziel.length : vor, 0, g)
    }
    listeAbgleichen(zustand.geschaefte, ziel)
    basis = nummer
  }

  /**
   * Lädt die ganze Liste, erledigte eingeschlossen, in Seiten zu 1000. Ist sie
   * schon da, kommt sie ohne Anfrage zurück, ausser `neu` verlangt den Stand
   * des Servers. Bricht das Laden ab, bleibt die bisherige Liste stehen.
   */
  async function laden({ neu = false } = {}) {
    if (ladevorgang) {
      if (neu) { nochEinmal = true }
      return ladevorgang
    }
    if (zustand.geladen && !neu) { return zustand.geschaefte }
    ladevorgang = (async () => {
      zustand.laden = true
      try {
        do {
          nochEinmal = false
          await ganzeListe()
        } while (nochEinmal)
        zustand.geladen = true
        zustand.fehler = ''
      } catch (e) {
        console.error('Fehler beim Laden der Geschäfte:', e)
        zustand.fehler = 'Die Geschäfte liessen sich nicht laden.'
      } finally {
        zustand.laden = false
        ladevorgang = null
        geaendert()
      }
      const danach = [...ausstehend]
      ausstehend.clear()
      if (zustand.geladen) { await nachladen(danach) }
      return zustand.geschaefte
    })()
    return ladevorgang
  }

  /**
   * Lädt nur diese Geschäfte nach und ersetzt sie an Ort und Stelle. Ein
   * Geschäft, das der Server nicht mehr liefert, verschwindet aus der Liste;
   * ein neues kommt nach seinem Datum hinein. Eine Antwort, die eine spätere
   * Anfrage überholt hat, fällt weg.
   */
  async function nachladen(ids) {
    const liste = [...new Set((ids || []).map(Number).filter((id) => Number.isInteger(id) && id > 0))]
    if (!liste.length) { return }
    // Läuft die erste Ladung, kommen diese Geschäfte nach ihr; ohne Ladung bringt
    // das erste Laden auch sie.
    if (!zustand.geladen) {
      if (ladevorgang) { liste.forEach((id) => ausstehend.add(id)) }
      return ladevorgang
    }
    for (let i = 0; i < liste.length; i += IDS_JE_ANFRAGE) {
      const block = liste.slice(i, i + IDS_JE_ANFRAGE)
      const nummer = zaehler++
      try {
        const { data } = await client.get(URL_GESCHAEFTE(), { params: { ids: block.join(',') } })
        const frisch = new Map((Array.isArray(data) ? data : []).map((g) => [g.id, g]))
        for (const id of block) {
          if (nummer < stand(id)) { continue }
          angewandt.set(id, nummer)
          const index = zustand.geschaefte.findIndex((g) => g.id === id)
          const neu = frisch.get(id)
          if (neu && index >= 0) {
            Object.assign(zustand.geschaefte[index], neu)
          } else if (neu) {
            const vor = zustand.geschaefte.findIndex((g) => nachDatum(neu, g) < 0)
            zustand.geschaefte.splice(vor < 0 ? zustand.geschaefte.length : vor, 0, neu)
          } else if (index >= 0) {
            zustand.geschaefte.splice(index, 1)
          }
        }
      } catch (e) {
        console.error('Fehler beim Nachladen der Geschäfte:', e)
      }
    }
    geaendert()
  }

  /**
   * Eine Runde gebündelter Ereignisse: Ein Ereignis mit der ID eines
   * Geschäfts lädt dieses nach, das Ende eines Abgleichs die Geschäfte, die er
   * nennt. Nennt ein Ereignis keine Geschäfte, die es betrifft, kommt die ganze
   * Liste neu.
   */
  function verarbeiten(ereignisse) {
    let alles = false
    const ids = new Set()
    for (const ereignis of ereignisse) {
      const typ = ereignis?.type || ''
      const nutzlast = ereignis?.payload || {}
      if (ABGLEICH_ENDE.includes(typ)) {
        if (Array.isArray(nutzlast.geschaeftIds)) {
          nutzlast.geschaeftIds.forEach((id) => ids.add(Number(id)))
        } else {
          alles = true
        }
      } else if (typ.startsWith('geschaefte.')) {
        const id = Number(nutzlast.id)
        if (id > 0) { ids.add(id) } else { alles = true }
      } else if (typ === 'fraktionssitzung.updated') {
        alles = true
      }
    }
    // Ohne frühere und ohne laufende Ladung bringt das erste Laden alles.
    if (!zustand.geladen && !ladevorgang) { return Promise.resolve() }
    return alles ? laden({ neu: true }) : nachladen([...ids])
  }

  const annehmen = ereignisseBuendeln(verarbeiten)
  const ereignis = (e) => {
    const typ = e?.type || ''
    if (ABGLEICH_ENDE.includes(typ) || typ.startsWith('geschaefte.') || typ === 'fraktionssitzung.updated') {
      annehmen(e)
    }
  }

  return { zustand, laden, nachladen, ereignis, abbrechen: () => annehmen.abbrechen() }
}

/**
 * Bindet eine Ansicht an die geteilte Liste aus App.vue, sonst an eine eigene,
 * und ruft `uebernehmen` nach jeder Änderung der Liste. Die Ansicht gibt ihre
 * Ereignisse nur an eine eigene Liste weiter (`eigener`); die geteilte bekommt
 * sie von App.vue.
 */
export function bestandAnbinden(vm, geteilt, uebernehmen) {
  const bestand = geteilt || erzeugeBestand()
  vm.$watch(() => bestand.zustand.version, uebernehmen)
  return { bestand, eigener: !geteilt }
}
