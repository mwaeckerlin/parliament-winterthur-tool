#!/usr/bin/env node
/**
 * Aggregiert alle JUnit-XML-Dateien eines Verzeichnisses zu einer
 * Gesamt-Zusammenfassung über sämtliche Testsuiten.
 *
 * Ausgabe:
 *   - Gesamtzahl der Tests sowie Anzahl erfolgreich / fehlgeschlagen /
 *     übersprungen.
 *   - Jeder NICHT erfolgreiche Test (Fehler oder übersprungen) wird namentlich
 *     aufgelistet, MIT seiner Fehlermeldung.
 *
 * Die Fehlermeldung stand früher nur im Bericht der jeweiligen Suite, nicht in
 * dieser Zusammenfassung. Am 2026-08-28 nannte jeder rote Budget-Test wörtlich
 * «Budget-Import 2026 fehlgeschlagen (500)» — gelesen wurde nur die Zahl der
 * roten Tests, und der Serverfehler blieb bis in die laufende Instanz stehen.
 *
 * Exit-Code: 0 nur dann, wenn jeder Test erfolgreich war. Sobald ein Test
 * fehlschlägt ODER übersprungen wird (oder gar keine Tests liefen), ist der
 * Exit-Code 1 – der gesamte Testlauf gilt als fehlgeschlagen.
 */

import { readdirSync, readFileSync, existsSync } from 'node:fs'
import { join } from 'node:path'

const dir = process.argv[2]
if (!dir || !existsSync(dir)) {
  console.error(`JUnit-Verzeichnis nicht gefunden: ${dir}`)
  process.exit(1)
}

const attr = (tag, name) => {
  // Wortgrenze, damit `name=` nicht fälschlich in `classname=` matcht.
  const m = tag.match(new RegExp(`\\b${name}\\s*=\\s*"([^"]*)"`))
  return m ? decode(m[1]) : ''
}
const decode = (s) => s
  .replace(/&lt;/g, '<').replace(/&gt;/g, '>')
  .replace(/&quot;/g, '"').replace(/&#0?39;/g, "'").replace(/&amp;/g, '&')

let total = 0
let passed = 0
const nichtErfolgreich = [] // { suite, name, art, meldung }

/**
 * Die Meldung eines fehlgeschlagenen Testfalls: bevorzugt das `message`-Attribut
 * von `<failure>`/`<error>`, sonst deren Textinhalt. Auf die erste aussagekräftige
 * Zeile gekürzt, damit die Zusammenfassung lesbar bleibt.
 */
const meldungAus = (inner) => {
  const tag = inner.match(/<(?:failure|error)\b([^>]*)(?:\/>|>([\s\S]*?)<\/(?:failure|error)>)/)
  if (!tag) return ''
  const roh = attr(tag[1], 'message') || decode((tag[2] || '').replace(/<!\[CDATA\[|\]\]>/g, ''))
  const zeile = roh.split('\n').map((z) => z.trim()).find((z) => z !== '') || ''
  return zeile.length > 200 ? zeile.slice(0, 197) + '…' : zeile
}

const files = readdirSync(dir).filter((f) => f.endsWith('.xml'))
for (const file of files) {
  const xml = readFileSync(join(dir, file), 'utf8')
  // Jeden <testcase …> bzw. <testcase …>…</testcase> erfassen.
  const re = /<testcase\b([^>]*?)(\/>|>([\s\S]*?)<\/testcase>)/g
  let m
  while ((m = re.exec(xml)) !== null) {
    const tagAttr = m[1]
    const inner = m[3] || ''
    const name = attr(tagAttr, 'name') || '(ohne Namen)'
    const suite = attr(tagAttr, 'classname') || attr(tagAttr, 'file') || file
    total += 1
    if (/<(failure|error)\b/.test(inner)) {
      nichtErfolgreich.push({ suite, name, art: 'FEHLER', meldung: meldungAus(inner) })
    } else if (/<skipped\b/.test(inner)) {
      nichtErfolgreich.push({ suite, name, art: 'ÜBERSPRUNGEN', meldung: '' })
    } else {
      passed += 1
    }
  }
}

const fehlerAnzahl = nichtErfolgreich.filter((t) => t.art === 'FEHLER').length
const skipAnzahl = nichtErfolgreich.filter((t) => t.art === 'ÜBERSPRUNGEN').length

console.log('')
console.log('================ Test-Zusammenfassung ================')
console.log(`  Tests gesamt:   ${total}`)
console.log(`  Erfolgreich:    ${passed}`)
console.log(`  Fehlgeschlagen: ${fehlerAnzahl}`)
console.log(`  Übersprungen:   ${skipAnzahl}`)
console.log('======================================================')

if (total === 0) {
  console.log('FEHLGESCHLAGEN: Es wurden keine Tests ausgeführt.')
  process.exit(1)
}

if (nichtErfolgreich.length > 0) {
  console.log('')
  console.log('Nicht erfolgreich:')
  for (const t of nichtErfolgreich) {
    console.log(`  ✗ [${t.art}] ${t.suite} › ${t.name}`)
    if (t.meldung) console.log(`      ${t.meldung}`)
  }

  // Gleiche Meldungen zusammenfassen: Eine Ursache, die zwanzig Tests umwirft,
  // ist EIN Befund — und steht hier, statt sich in der Liste zu verstecken.
  const nachMeldung = new Map()
  for (const t of nichtErfolgreich) {
    if (!t.meldung) continue
    nachMeldung.set(t.meldung, (nachMeldung.get(t.meldung) || 0) + 1)
  }
  const haeufig = [...nachMeldung.entries()].filter(([, n]) => n > 1).sort((a, b) => b[1] - a[1])
  if (haeufig.length > 0) {
    console.log('')
    console.log('Häufigste Fehlermeldungen (dieselbe Ursache in mehreren Tests):')
    for (const [meldung, n] of haeufig) {
      console.log(`  ${String(n).padStart(3)}× ${meldung}`)
    }
  }
  console.log('')
  console.log(`FEHLGESCHLAGEN: ${nichtErfolgreich.length} von ${total} Tests nicht erfolgreich.`)
  process.exit(1)
}

console.log('')
console.log(`ERFOLGREICH: alle ${total} Tests bestanden.`)
process.exit(0)
