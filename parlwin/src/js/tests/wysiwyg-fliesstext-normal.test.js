import { describe, it, expect } from 'vitest'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, resolve } from 'node:path'

const stylePath = resolve(dirname(fileURLToPath(import.meta.url)), '../../css/style.scss')

// Bug: Der Inhalt-Editor steht (via PwField) in einem <label>. Nextcloud stellt
// Formular-Labels fett dar; font-weight ist vererbbar, also erscheint der ganze
// Editor-Fliesstext fett («Bold by default»). Der Editor-Body muss seine
// Schriftstärke explizit auf normal setzen — fett bleibt strong/Überschriften.
describe('WYSIWYG-Inhalt: Fliesstext ist nicht fett', () => {
  it('setzt für die Editor-Eingabefläche font-weight normal', () => {
    const css = readFileSync(stylePath, 'utf8')
    const block = css.match(/\.pw-wysiwyg__editor \.ProseMirror \{[^}]*\}/)
    expect(block, 'Editor-Eingabeflächen-Regel fehlt').toBeTruthy()
    expect(block[0]).toMatch(/font-weight:\s*(normal|400)\b/)
  })
})
