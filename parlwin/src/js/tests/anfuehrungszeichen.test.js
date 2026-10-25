import { describe, it, expect } from 'vitest'
import { readdirSync, readFileSync, statSync } from 'node:fs'

/**
 * Die Anwendung setzt die Schweizer Anführungszeichen «» und ‹›.
 *
 * Befund aus dem Design-Test vom 16.10.2026: In der Kachel einer Kommission
 * stand «Keine Geschäfte mit Status „Bei Kommission … pendent“», während der
 * Titel darüber «Spezialkommission «Richtplan»» schreibt. Zwei Formen auf
 * einem Schirm, und die deutsche ist die falsche: Die Anwendung richtet sich
 * an Leser in Winterthur.
 *
 * Zwei Abfragen setzten zusätzlich „…" — unten die deutsche Form, oben das
 * gerade Zoll-Zeichen.
 *
 * vitest läuft im Projekt-Root.
 */
const WURZEL = 'parlwin/src/js'
const VERBOTEN = /[„“”]/

function dateien(verzeichnis) {
  return readdirSync(verzeichnis).flatMap((eintrag) => {
    const pfad = `${verzeichnis}/${eintrag}`
    if (statSync(pfad).isDirectory()) return dateien(pfad)
    return /\.(js|vue)$/.test(eintrag) ? [pfad] : []
  })
}

describe('Anführungszeichen', () => {
  it('keine deutschen Anführungszeichen im Quelltext', () => {
    // Diese Datei selbst nennt die verbotenen Formen und wird darum
    // übergangen.
    const treffer = dateien(WURZEL)
      .filter((pfad) => !pfad.endsWith('anfuehrungszeichen.test.js'))
      .flatMap((pfad) =>
      readFileSync(pfad, 'utf8')
        .split('\n')
        .map((zeile, i) => [i + 1, zeile])
        .filter(([, zeile]) => VERBOTEN.test(zeile))
        .map(([nr, zeile]) => `${pfad}:${nr}: ${zeile.trim()}`),
    )
    expect(treffer, 'die Schweizer Form ist «…», innen ‹…›').toEqual([])
  })
})
