import { describe, it, expect } from 'vitest'
import { readdirSync, readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, resolve, join } from 'node:path'

const komponentenDir = resolve(dirname(fileURLToPath(import.meta.url)), '../components')

/**
 * Jede in einer Vorlage verwendete Komponente muss auch registriert sein.
 *
 * Eine nicht registrierte Komponente rendert im Browser einfach NICHTS — das
 * Bedienelement fehlt dann still. In Modultests fällt das nicht auf, weil dort
 * unbekannte Elemente als Platzhalter durchgehen. Genau so verschwanden ein
 * «Speichern»-Knopf und der Knopf zum Archivieren eines Votums unbemerkt.
 */
const dateien = readdirSync(komponentenDir).filter((f) => f.endsWith('.vue'))

/** Alle in der Vorlage verwendeten Komponenten mit Grossbuchstaben-Namen. */
function verwendet(quelle) {
  const template = quelle.slice(0, quelle.indexOf('<script'))
  const namen = new Set()
  for (const treffer of template.matchAll(/<([A-Z][A-Za-z0-9]*)[\s/>]/g)) {
    namen.add(treffer[1])
  }
  // Von Vue selbst bereitgestellte Elemente brauchen keine Registrierung.
  for (const eingebaut of ['Teleport', 'Transition', 'TransitionGroup', 'KeepAlive', 'Suspense', 'Component']) {
    namen.delete(eingebaut)
  }
  return [...namen]
}

/** Alle registrierten bzw. importierten Namen aus dem Skriptteil. */
function registriert(quelle) {
  const namen = new Set()
  const block = quelle.match(/components:\s*\{([^}]*)\}/s)
  if (block) {
    for (const teil of block[1].split(',')) {
      const name = teil.split(':')[0].trim()
      if (name) namen.add(name)
    }
  }
  // `<script setup>` gibt es hier nicht; Vollständigkeit halber zählen auch
  // direkte Importe, falls eine Komponente einmal so eingebunden wird.
  for (const treffer of quelle.matchAll(/^import\s+([A-Z][A-Za-z0-9]*)\s+from/gm)) {
    namen.add(treffer[1])
  }
  return namen
}

describe('Alle verwendeten Komponenten sind registriert', () => {
  it.each(dateien)('%s verwendet nur registrierte Komponenten', (datei) => {
    const quelle = readFileSync(join(komponentenDir, datei), 'utf8')
    const bekannt = registriert(quelle)
    const fehlend = verwendet(quelle).filter((name) => !bekannt.has(name))
    expect(
      fehlend,
      `In ${datei} werden Komponenten verwendet, die weder importiert noch registriert sind — `
      + 'sie rendern im Browser NICHTS',
    ).toEqual([])
  })
})
