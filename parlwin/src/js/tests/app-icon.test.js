import { describe, it, expect } from 'vitest'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, resolve } from 'node:path'

// parlwin/img relativ zu dieser Testdatei (parlwin/src/js/tests).
const imgDir = resolve(dirname(fileURLToPath(import.meta.url)), '../../../img')

// Das App-Icon muss exakt dem Nextcloud-Standardformat entsprechen (wie die
// Files-App): height/width 20px, genau EIN <path>, keine eigenen Formen
// (kein <rect>/<mask>), keine Marken-/Hintergrundfarbe. app.svg ist weiss
// (fill #fff, fürs farbige Header), app-dark.svg ohne fill (heller Hintergrund).
function svg(datei) {
  return readFileSync(resolve(imgDir, datei), 'utf8')
}

function pruefeNcFormat(inhalt) {
  expect(inhalt, 'height="20px" fehlt').toMatch(/height="20px"/)
  expect(inhalt, 'width="20px" fehlt').toMatch(/width="20px"/)
  expect((inhalt.match(/<path/g) || []).length, 'genau ein <path> erwartet').toBe(1)
  expect(inhalt, 'eigene Formen (rect/mask) sind nicht NC-Standard').not.toMatch(/<rect|<mask|<g[ >]/)
  // Keine Marken-/Hintergrundfarbe (nur weiss/kein fill erlaubt).
  const fills = [...inhalt.matchAll(/fill="([^"]+)"/g)].map((m) => m[1].toLowerCase())
  expect(fills.filter((f) => !['#fff', '#ffffff', 'currentcolor', 'none'].includes(f))).toEqual([])
}

describe('App-Icon entspricht dem Nextcloud-Standardformat (wie Files)', () => {
  it('app.svg ist NC-konform und weiss (fill #fff)', () => {
    const inhalt = svg('app.svg')
    pruefeNcFormat(inhalt)
    expect(inhalt.toLowerCase(), 'app.svg muss fill #fff haben (wie Files-Header-Icon)').toMatch(/fill="#fff(fff)?"/)
  })

  it('app-dark.svg ist NC-konform', () => {
    pruefeNcFormat(svg('app-dark.svg'))
  })
})
