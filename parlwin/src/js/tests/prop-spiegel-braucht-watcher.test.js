import { describe, it, expect } from 'vitest'
import { readdirSync, readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, resolve, join } from 'node:path'

/**
 * Guard zum Bug vom 2026-08-28 (F101): Eine Komponente, die eine Prop beim
 * Erzeugen in ihren lokalen Zustand kopiert (`data()` liest `this.<prop>`),
 * sieht jede spätere Änderung dieser Prop nur, wenn sie einen Watcher darauf
 * hat. Ohne ihn zeigt sie dauerhaft den Stand vom Öffnen — und schreibt ihn
 * beim nächsten Speichern zurück, wodurch die Änderung von aussen verloren
 * geht. Genau das passierte den Ausnahmen eines Pauschalantrags: unten an der
 * Produktegruppe aufgenommen, oben blieb die Ausnahme stehen.
 *
 * Der Guard findet die Konstruktion im Quelltext und verlangt zu jeder in
 * `data()` gelesenen Prop einen Eintrag in `watch`. Wo ein Spiegel ohne
 * Nachziehen richtig ist, steht die Datei mit Begründung in AUSNAHMEN.
 */
const KOMPONENTEN = resolve(dirname(fileURLToPath(import.meta.url)), '../components')

// Datei → Begründung, warum der lokale Spiegel bewusst nicht nachzieht.
const AUSNAHMEN = {}

/** Der Inhalt eines Options-Blocks («props», «data», «watch») samt Klammern. */
function block(quelle, name) {
  const start = quelle.search(new RegExp('(^|[\\s,{])' + name + '\\s*(\\(\\s*\\))?\\s*[:{]', 'm'))
  if (start < 0) return ''
  const auf = quelle.indexOf('{', quelle.indexOf(name, start) + name.length)
  if (auf < 0) return ''
  let tiefe = 0
  for (let i = auf; i < quelle.length; i++) {
    if (quelle[i] === '{') tiefe++
    else if (quelle[i] === '}' && --tiefe === 0) return quelle.slice(auf + 1, i)
  }
  return ''
}

/**
 * Die Schlüssel der obersten Ebene eines Blocks (Prop- bzw. Watcher-Namen).
 * Ein Watcher steht als Objekt (`prop: { handler }`) oder als Methoden-Kurzform
 * (`prop(neu) {}`) — beide zählen, sonst meldet der Guard die Kurzform als Lücke.
 */
function schluessel(inhalt) {
  const namen = []
  let tiefe = 0
  for (const zeile of inhalt.split('\n')) {
    const treffer = tiefe === 0 && zeile.match(/^\s*'?([A-Za-z_$][\w$]*)'?\s*[:(]/)
    if (treffer) namen.push(treffer[1])
    for (const zeichen of zeile) {
      if ('{(['.includes(zeichen)) tiefe++
      else if ('})]'.includes(zeichen)) tiefe--
    }
  }
  return namen
}

describe('Komponenten mit lokalem Prop-Spiegel', () => {
  it('ziehen jede in data() gespiegelte Prop über einen Watcher nach (F101)', () => {
    const fehler = []
    for (const datei of readdirSync(KOMPONENTEN).filter(n => n.endsWith('.vue'))) {
      if (AUSNAHMEN[datei]) continue
      const quelle = readFileSync(join(KOMPONENTEN, datei), 'utf8')
      const props = schluessel(block(quelle, 'props'))
      if (!props.length) continue
      const daten = block(quelle, 'data')
      const beobachtet = new Set(schluessel(block(quelle, 'watch')))
      for (const prop of props) {
        if (!new RegExp('\\bthis\\.' + prop + '\\b').test(daten)) continue
        if (beobachtet.has(prop)) continue
        fehler.push(datei + ': data() spiegelt die Prop «' + prop + '», ohne sie zu beobachten')
      }
    }
    expect(fehler, fehler.join('\n')).toEqual([])
  })
})
