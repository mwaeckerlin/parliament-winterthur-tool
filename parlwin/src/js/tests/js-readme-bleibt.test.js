// @vitest-environment node
/**
 * `parlwin/js/README.md` erklärt, dass das Verzeichnis gebaut wird. webpack
 * leert das Verzeichnis vor jedem Bau (`output.clean` in parlwin/webpack.js);
 * `output.clean.keep` lässt die Beschreibung und `.gitkeep` stehen und sonst
 * nichts.
 */
import { describe, it, expect } from 'vitest'
import fs from 'node:fs'
import { createRequire } from 'node:module'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const WURZEL = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../../..')
const konfiguration = createRequire(import.meta.url)(path.join(WURZEL, 'parlwin', 'webpack.js'))

describe('parlwin/js: die Beschreibung des gebauten Verzeichnisses bleibt', () => {
  it('die Datei steht im Verzeichnis', () => {
    expect(fs.existsSync(path.join(WURZEL, 'parlwin', 'js', 'README.md'))).toBe(true)
  })

  it('webpack baut in dieses Verzeichnis und lässt README.md und .gitkeep stehen', () => {
    expect(konfiguration.output.path).toBe(path.join(WURZEL, 'parlwin', 'js'))
    const behalten = konfiguration.output.clean.keep
    for (const datei of ['README.md', '.gitkeep', 'chunks/.gitkeep']) expect(behalten.test(datei), datei).toBe(true)
  })

  it('webpack leert jede gebaute Datei', () => {
    const behalten = konfiguration.output.clean.keep
    for (const datei of ['parlwin-main.js', 'parlwin-main.js.map', 'admin.js', 'chunks/vendors.0123abcd.js', 'README.md.map', 'ALT-README.md.js']) expect(behalten.test(datei), datei).toBe(false)
  })
})
