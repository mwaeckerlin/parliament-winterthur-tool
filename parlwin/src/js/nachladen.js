import axios from '@nextcloud/axios'
import { getCurrentUser } from '@nextcloud/auth'

/**
 * Nachladen nach einem Echtzeit-Ereignis, für jede Ansicht gleich.
 *
 * Ein Ereignis eines anderen Fensters lädt im Hintergrund nach, die Ansicht
 * bleibt dabei stehen und wird danach an Ort und Stelle angeglichen. Mehrere
 * Ereignisse kurz hintereinander lösen eine einzige Runde aus, und das Fenster,
 * das gespeichert hat, lädt nichts nach: Es zeigt seine Änderung schon.
 */

/** Der Header, mit dem jede Anfrage ihr Fenster nennt; der Server gibt ihn im Ereignis weiter. */
export const FENSTER_HEADER = 'X-Parlwin-Fenster'

/**
 * So lange sammelt eine Ansicht Ereignisse, bevor sie nachlädt. Der Wert ist
 * gesetzt und nicht gemessen: kurz genug, dass Mitlesende keine Verzögerung
 * bemerken, und lang genug für die Ereignisse, die ein Speichern auslöst.
 */
export const BUENDEL_MS = 300

/** Die Kennung dieses Browserfensters, einmal je geladener Seite. */
export const FENSTER = (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function')
  ? crypto.randomUUID()
  : `${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 12)}`

/** Hängt die Kennung des Fensters an jede Anfrage des geteilten Axios-Clients. */
export function fensterKennungAnhaengen(client = axios) {
  client.interceptors.request.use((config) => {
    config.headers = config.headers || {}
    config.headers[FENSTER_HEADER] = FENSTER
    return config
  })
}

/**
 * Ob ein Ereignis aus diesem Fenster stammt: Die Kennung des Fensters und die
 * Benutzer-ID, die der Server aus der Sitzung einträgt, passen beide.
 */
export function eigenesEreignis(ereignis) {
  const uid = getCurrentUser()?.uid || ''
  return uid !== ''
    && String(ereignis?.payload?._uid || '') === uid
    && String(ereignis?.payload?._fenster || '') === FENSTER
}

/**
 * Sammelt Ereignisse und gibt sie gebündelt weiter: Das erste Ereignis startet
 * die Frist, alle bis zu ihrem Ende kommen in dieselbe Runde. Die Frist wird
 * durch weitere Ereignisse nicht verlängert, damit ein steter Strom die
 * Anzeige nicht aufhält. Eigene Ereignisse fallen weg, ausser eine Ansicht
 * zeigt ihre eigene Änderung erst über das Ereignis (`eigeneUeberspringen`).
 */
export function ereignisseBuendeln(verarbeite, { frist = BUENDEL_MS, eigeneUeberspringen = true } = {}) {
  let gesammelt = []
  let zeitgeber = null
  const annehmen = (ereignis) => {
    if (eigeneUeberspringen && eigenesEreignis(ereignis)) { return }
    gesammelt.push(ereignis)
    if (zeitgeber !== null) { return }
    zeitgeber = setTimeout(() => {
      const runde = gesammelt
      gesammelt = []
      zeitgeber = null
      verarbeite(runde)
    }, frist)
  }
  annehmen.abbrechen = () => {
    if (zeitgeber !== null) { clearTimeout(zeitgeber) }
    zeitgeber = null
    gesammelt = []
  }
  return annehmen
}

/**
 * Gleicht eine angezeigte Liste an Ort und Stelle an eine neu geladene an:
 * Bestehende Einträge behalten ihr Objekt und bekommen die neuen Werte, neue
 * kommen dazu, fehlende gehen, und die Reihenfolge folgt der neuen Liste. So
 * bleiben Scrollposition, Fokus und ein offener Editor stehen, weil Vue die
 * Zeilen nicht neu aufbaut.
 */
export function listeAbgleichen(alt, neu, schluessel = (eintrag) => eintrag.id) {
  const neuNach = new Map(neu.map((eintrag) => [schluessel(eintrag), eintrag]))
  for (const eintrag of alt) {
    const frisch = neuNach.get(schluessel(eintrag))
    if (frisch) { Object.assign(eintrag, frisch) }
  }
  const vorhanden = new Set(alt.map(schluessel))
  for (const frisch of neu) {
    if (!vorhanden.has(schluessel(frisch))) { alt.push(frisch) }
  }
  for (let i = alt.length - 1; i >= 0; i--) {
    if (!neuNach.has(schluessel(alt[i]))) { alt.splice(i, 1) }
  }
  const reihenfolge = new Map(neu.map((eintrag, i) => [schluessel(eintrag), i]))
  alt.sort((a, b) => (reihenfolge.get(schluessel(a)) ?? 0) - (reihenfolge.get(schluessel(b)) ?? 0))
  return alt
}
