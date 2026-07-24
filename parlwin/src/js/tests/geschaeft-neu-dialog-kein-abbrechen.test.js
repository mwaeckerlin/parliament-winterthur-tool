import { describe, it, expect } from 'vitest'
import { readdirSync, readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, resolve, join } from 'node:path'

const komponentenDir = resolve(dirname(fileURLToPath(import.meta.url)), '../components')

const vueDateien = () => readdirSync(komponentenDir).filter(f => f.endsWith('.vue'))
const inhalt = (datei) => readFileSync(join(komponentenDir, datei), 'utf8')

/**
 * Ein Formular, das erst auf Knopfdruck absendet, braucht auch einen Weg, ohne
 * Wirkung wieder herauszukommen. Wo Eingaben dagegen sofort speichern, ist ein
 * «Abbrechen» sinnlos (es gäbe nichts zu verwerfen) — dort schliesst ✕.
 *
 * Der frühere Test forderte pauschal, dass NIRGENDS ein «Abbrechen» steht. Das
 * war falsch und hat das ersatzlose Entfernen mehrerer Abbrechen-Knöpfe als
 * Soll-Zustand festgeschrieben.
 */
describe('Formulare mit Absende-Knopf bieten auch einen Abbruch', () => {
  it('jedes Formular mit «Erstellen»-Knopf hat einen Abbruchweg', () => {
    const ohneAbbruch = vueDateien().filter((f) => {
      const q = inhalt(f)
      if (!/>\s*(Erstellen|\{\{ .*? '?Erstellen'? \}\})\s*</.test(q)) return false
      // Entweder ein echter «Abbrechen»-Knopf oder das Schliesskreuz.
      return !/>\s*Abbrechen\s*</.test(q) && !/pw-btn-schliessen/.test(q)
    })
    expect(ohneAbbruch, 'Formular ohne jeden Abbruchweg').toEqual([])
  })

  it('das Formular für eine neue Sitzung bietet «Erstellen» und «Abbrechen»', () => {
    const q = inhalt('Sitzungsliste.vue')
    expect(q, '«Erstellen» fehlt im Sitzungsformular').toMatch(/>\s*\{\{ neuerSitzungLaden \? 'Erstellt …' : 'Erstellen' \}\}\s*</)
    expect(q, '«Abbrechen» fehlt im Sitzungsformular').toMatch(/>\s*Abbrechen\s*</)
  })

  it('der Dialog für ein neues Dokument bietet «Erstellen» und «Abbrechen»', () => {
    const q = inhalt('GeschaeftDokumente.vue')
    expect(q, '«Erstellen» fehlt im Dokument-Dialog').toMatch(/>\s*Erstellen\s*</)
    expect(q, '«Abbrechen» fehlt im Dokument-Dialog').toMatch(/>\s*Abbrechen\s*</)
  })
})
