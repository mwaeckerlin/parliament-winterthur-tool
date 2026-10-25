/**
 * Wo die Liste eines Filterfeldes steht.
 *
 * Die Filter stehen am Telefon in der Navigation, ganz unten. Tippt man ein
 * Auswahlfeld an, bekommt sein Suchfeld den Fokus und die Bildschirmtastatur
 * fährt hoch. Das Fenster schrumpft dabei nicht — nur der SICHTBARE Teil wird
 * kleiner (`window.visualViewport`). NcSelect setzt die Liste mit floating-ui,
 * das gegen das Fenster misst: Es sah unterhalb des Feldes Platz, wo die
 * Tastatur stand, und liess die Liste dort. Gemessen am 19.10.2026 am iPhone:
 * sichtbar 439 Punkte, erster Eintrag der Liste bei 481.
 *
 * Darum rechnet parlwin die Lage selbst, gegen den sichtbaren Teil.
 */

/** Abstand zwischen Liste und Rand des sichtbaren Bereichs. */
const LUFT = 8
/** Weniger als das lohnt sich nicht: Darunter ist keine Zeile mehr zu treffen. */
const MINDESTHOEHE = 80

/**
 * @param {{top: number, bottom: number}} feld Lage des Feldes im sichtbaren Bereich
 * @param {number} menuHoehe Höhe, die die Liste gerne hätte
 * @param {number} sichtbareHoehe Höhe des sichtbaren Bereichs (ohne Tastatur)
 * @param {number} versatz Oberer Rand des sichtbaren Bereichs im Fenster
 * @return {{top: number, maxHoehe: number, oben: boolean}}
 */
export function dropdownLage(feld, menuHoehe, sichtbareHoehe, versatz = 0) {
  const obenGrenze = versatz + LUFT
  const untenGrenze = versatz + sichtbareHoehe - LUFT
  const untenFrei = Math.max(0, untenGrenze - feld.bottom)
  const obenFrei = Math.max(0, feld.top - obenGrenze)
  // Nach oben nur, wenn unten zu wenig Platz ist UND oben mehr zur Verfügung
  // steht — sonst bliebe die Liste genauso abgeschnitten, nur andersherum.
  const oben = untenFrei < Math.min(menuHoehe, MINDESTHOEHE) && obenFrei > untenFrei
  const maxHoehe = Math.min(menuHoehe, Math.max(MINDESTHOEHE, untenGrenze - obenGrenze))
  // Die Liste gehört in den sichtbaren Bereich, auch wenn das FELD nicht mehr
  // darin steht: Mit hochgefahrener Tastatur lag es gemessen bei 513 bis 547,
  // während nur 439 sichtbar waren — über dem Feld zu öffnen hiess dort immer
  // noch «unter der Tastatur» (gemessen am 19.10.2026).
  const gewuenscht = oben ? feld.top - maxHoehe : feld.bottom
  const top = Math.min(Math.max(gewuenscht, obenGrenze), Math.max(obenGrenze, untenGrenze - maxHoehe))
  return { top, maxHoehe, oben }
}

/**
 * Die Positionierung, die NcSelect als `calculate-position` entgegennimmt.
 * Sie hängt die Liste an den sichtbaren Bereich und folgt ihm, solange sie
 * offen ist; der Rückgabewert räumt die Beobachter wieder ab.
 *
 * @param {HTMLElement} liste die Liste
 * @param {object} komponente die vue-select-Komponente
 * @return {Function} Aufräumen
 */
export function filterDropdownPosition(liste, komponente) {
  liste.classList.add('vs__dropdown-menu--floating')
  const setzen = () => {
    const anker = komponente && komponente.$refs ? komponente.$refs.toggle : null
    if (!anker || !anker.getBoundingClientRect) { return }
    const feld = anker.getBoundingClientRect()
    const sicht = typeof window !== 'undefined' ? window.visualViewport : null
    const sichtbareHoehe = sicht ? sicht.height : window.innerHeight
    const versatz = sicht ? sicht.offsetTop : 0
    const lage = dropdownLage(feld, liste.scrollHeight || MINDESTHOEHE, sichtbareHoehe, versatz)
    Object.assign(liste.style, {
      position: 'fixed',
      left: `${feld.left}px`,
      width: `${feld.width}px`,
      top: `${lage.top}px`,
      maxHeight: `${lage.maxHoehe}px`,
    })
    liste.classList.toggle('vs__dropdown-menu--floating-placement-top', lage.oben)
    if (komponente.$el && komponente.$el.classList) {
      komponente.$el.classList.toggle('select--drop-up', lage.oben)
    }
  }
  setzen()
  const sicht = typeof window !== 'undefined' ? window.visualViewport : null
  window.addEventListener('resize', setzen)
  window.addEventListener('scroll', setzen, true)
  if (sicht) {
    sicht.addEventListener('resize', setzen)
    sicht.addEventListener('scroll', setzen)
  }
  return () => {
    window.removeEventListener('resize', setzen)
    window.removeEventListener('scroll', setzen, true)
    if (sicht) {
      sicht.removeEventListener('resize', setzen)
      sicht.removeEventListener('scroll', setzen)
    }
  }
}
