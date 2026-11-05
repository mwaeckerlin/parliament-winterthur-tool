/**
 * Ein Testlauf lässt keinen Container zurück, auch wenn er abgebrochen wird.
 *
 * Die Maschinen-Warteschlange beendet einen Lauf, der seine angemeldete Zeit
 * überschreitet, und ein Lauf mit höherer Priorität beendet den laufenden
 * sofort. Das Skript erreicht dann seine letzte Zeile nie. Gemessen am
 * 29.10.2026: vier Container parlwin-php-test-php-pdf-* standen mit Exit 143
 * bis zu zwei Tage auf dem Rechner und zählten zu den 18 Containern, die den
 * Speicher des Arbeitsplatzes füllten.
 *
 * Drei Dinge müssen zusammenkommen: ein Aufräumschritt für jedes Signal, ein
 * Ende mit dem Code des Signals (ein Handler, der nur zurückkehrt, führt bash
 * in die nächste Zeile, und dann meldete der beendete Lauf den Code von PHPUnit
 * und sähe grün aus), und der trap VOR dem Start des Containers.
 */
import { describe, it, expect } from 'vitest'
import { readFileSync } from 'node:fs'
import { resolve, dirname } from 'node:path'
import { fileURLToPath } from 'node:url'

const WURZEL = resolve(dirname(fileURLToPath(import.meta.url)), '../../../..')
const skript = readFileSync(resolve(WURZEL, 'tests/php-suite.sh'), 'utf8')
const zeilen = skript.split('\n')

/** Die Zeilennummer der ersten Zeile, auf die das Muster passt, oder -1. */
const zeileMit = (muster) => zeilen.findIndex((z) => muster.test(z))

describe('Testläufe lassen keine Container zurück', () => {
  it('räumt bei jedem Ausgang auf: EXIT, INT, TERM und HUP', () => {
    const signale = new Set()
    for (const zeile of zeilen) {
      const treffer = zeile.match(/^trap\s+(?:'[^']*'|\S+)\s+(.+)$/)
      if (treffer) {
        for (const signal of treffer[1].trim().split(/\s+/)) { signale.add(signal) }
      }
    }
    for (const signal of ['EXIT', 'INT', 'TERM', 'HUP']) {
      expect(signale, `kein trap für ${signal}`).toContain(signal)
    }
  })

  it('entfernt den Container im Aufräumschritt', () => {
    const rumpf = skript.match(/^aufraeumen\(\)\s*\{([\s\S]*?)^\}/m)
    expect(rumpf, 'die Funktion aufraeumen fehlt').not.toBeNull()
    expect(rumpf[1]).toMatch(/docker rm -f "\$CONTAINER"/)
  })

  it('endet bei einem Signal mit dessen Code statt weiterzulaufen', () => {
    // 130 ist der übliche Code für INT, 143 für TERM.
    expect(skript, 'INT lässt das Skript weiterlaufen').toMatch(/trap\s+'aufraeumen; exit 130'\s+INT/)
    expect(skript, 'TERM lässt das Skript weiterlaufen').toMatch(/trap\s+'aufraeumen; exit 143'\s+TERM/)
  })

  it('setzt den trap, bevor der Container startet', () => {
    const trap = zeileMit(/^trap\s/)
    const start = zeileMit(/^docker run /)
    expect(trap, 'es gibt keinen trap').toBeGreaterThan(-1)
    expect(start, 'es gibt keinen docker run').toBeGreaterThan(-1)
    expect(trap, 'der trap steht nach dem Start: ein Abbruch im Fenster dazwischen lässt den Container stehen')
      .toBeLessThan(start)
  })

  it('der Container läuft mit --init und einem eindeutigen Namen', () => {
    expect(skript).toMatch(/docker run --init --name "\$CONTAINER"/)
    expect(skript).toMatch(/^CONTAINER="parlwin-php-test-\$\{NAME\}-\$\$"$/m)
  })
})
