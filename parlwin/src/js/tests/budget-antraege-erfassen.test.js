import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import BudgetAntraegeErfassen from '../components/BudgetAntraegeErfassen.vue'
import { csvLesen, csvZeile, trennzeichen, zeilenPruefen, vorlageCsv } from '../tabellen-datei'

vi.mock('@nextcloud/axios')

/**
 * Viele Anträge erfassen (F126) und aus einer Tabelle übernehmen (F127).
 *
 * Marc am 24.10.2026: «Die Eingabe von vielen Anträgen, irgendwie 50 Anträge
 * oder so, ist sehr mühsam. Wie kann man das vereinfachen? Erstens, dass die
 * Eingabe einfacher ist. Zweitens, was vielleicht gut wäre, wäre so etwas wie
 * ein Excel-Upload oder so.»
 *
 * vitest läuft im Projekt-Root.
 */
const POSITIONEN = [
  { value: 'pg:121', nummer: '121', label: '121 Personalamt', basis: 4000000, bereich: 'globalbudget', zielTyp: 'produktegruppe', zielRef: '121' },
  { value: 'pg:142', nummer: '142', label: '142 Stadtentwicklung', basis: 6000000, bereich: 'globalbudget', zielTyp: 'produktegruppe', zielRef: '142' },
]

function erfassen() {
  return mount(BudgetAntraegeErfassen, {
    props: {
      positionOptionen: POSITIONEN,
      antragstellerOptionen: [{ value: 'Marc Wäckerlin', label: 'Marc Wäckerlin' }],
      fraktionOptionen: [],
      letzterAntragsteller: 'Marc Wäckerlin',
    },
  })
}

describe('Anträge erfassen', () => {
  it('belegt den Antragsteller mit dem zuletzt verwendeten vor', () => {
    expect(erfassen().vm.vorgaben.antragsteller).toBe('Marc Wäckerlin')
  })

  it('rechnet den Prozentwert aus dem Betrag und der Basis der Position', () => {
    const w = erfassen()
    const z = w.vm.zeilen[0]
    z.position = 'pg:121'
    w.vm.betragGesetzt(z, '40000')
    expect(z.prozent).toBe('1')
  })

  it('öffnet mit der Eingabetaste die nächste Zeile', () => {
    const w = erfassen()
    const z = w.vm.zeilen[0]
    z.position = 'pg:121'
    z.betrag = '1000'
    w.vm.zeileSpeichern(0)
    expect(w.vm.zeilen).toHaveLength(2)
  })

  it('lässt eine Zeile ohne Position und ohne Betrag liegen', () => {
    const w = erfassen()
    w.vm.zeileSpeichern(0)
    expect(w.vm.zeilen, 'eine leere Zeile öffnete die nächste').toHaveLength(1)
    expect(w.vm.speicherbar).toBe(false)
  })

  it('gibt eine Kürzung mit negativem Betrag und die gemeinsamen Felder weiter', async () => {
    const w = erfassen()
    const z = w.vm.zeilen[0]
    z.position = 'pg:142'
    z.betrag = '50000'
    z.begruendung = 'Verzicht auf Projekt'
    w.vm.alleSpeichern()
    const [[antraege]] = w.emitted('anlegen')
    expect(antraege).toHaveLength(1)
    expect(antraege[0].betragDelta).toBe(-50000)
    expect(antraege[0].antragsteller).toBe('Marc Wäckerlin')
    expect(antraege[0].position).toBe('pg:142')
  })

  it('gibt eine Mehrausgabe mit positivem Betrag weiter', () => {
    const w = erfassen()
    const z = w.vm.zeilen[0]
    z.position = 'pg:121'
    z.betrag = '20000'
    z.mehrausgabe = true
    w.vm.alleSpeichern()
    const [[antraege]] = w.emitted('anlegen')
    expect(antraege[0].betragDelta).toBe(20000)
  })
})

describe('Tabelle übernehmen', () => {
  it('erkennt das Trennzeichen der Datei', () => {
    expect(trennzeichen('a;b;c')).toBe(';')
    expect(trennzeichen('a,b,c')).toBe(',')
  })

  it('schützt ein Trennzeichen im Anführungszeichen', () => {
    expect(csvZeile('121;"Kürzung; deutlich";5', ';')).toEqual(['121', 'Kürzung; deutlich', '5'])
  })

  it('liest die Vorlage mit ihren vier Spalten', () => {
    expect(csvLesen(vorlageCsv())[0]).toEqual(['Position', 'Richtung', 'Betrag', 'Begründung'])
  })

  it('ordnet eine Zeile ihrer Position zu und macht daraus einen Antrag', () => {
    const zeilen = csvLesen('Position;Richtung;Betrag;Begründung\n121;Reduktion;50000;Weniger Personal\n')
    const [erste] = zeilenPruefen(zeilen, POSITIONEN)
    expect(erste.fehler).toBeUndefined()
    expect(erste.antrag.position).toBe('pg:121')
    expect(erste.antrag.betragDelta).toBe(-50000)
    expect(erste.antrag.begruendung).toBe('Weniger Personal')
  })

  it('nimmt eine Position auch unter ihrem Namen an', () => {
    const zeilen = csvLesen('Position;Betrag\n142 Stadtentwicklung;1000\n')
    expect(zeilenPruefen(zeilen, POSITIONEN)[0].antrag.position).toBe('pg:142')
  })

  it('liest einen Betrag in Schweizer Schreibweise', () => {
    const zeilen = csvLesen("Position;Betrag\n121;1’200’000\n")
    expect(zeilenPruefen(zeilen, POSITIONEN)[0].antrag.betragDelta).toBe(-1200000)
  })

  it('macht aus einem Pluszeichen eine Mehrausgabe', () => {
    const zeilen = csvLesen('Position;Betrag\n121;+1000\n')
    expect(zeilenPruefen(zeilen, POSITIONEN)[0].antrag.betragDelta).toBe(1000)
  })

  it('macht aus der Spalte «Richtung» eine Mehrausgabe', () => {
    const zeilen = csvLesen('Position;Richtung;Betrag\n121;Mehrausgabe;1000\n')
    expect(zeilenPruefen(zeilen, POSITIONEN)[0].antrag.betragDelta).toBe(1000)
  })

  it('nennt eine unbekannte Position als Befund, statt sie zu übernehmen', () => {
    const zeilen = csvLesen('Position;Betrag\n999;1000\n')
    const [erste] = zeilenPruefen(zeilen, POSITIONEN)
    expect(erste.antrag).toBeUndefined()
    expect(erste.fehler).toBe('Unbekannte Position')
  })

  it('nennt einen Betrag ohne Zahl als Befund', () => {
    const zeilen = csvLesen('Position;Betrag\n121;viel\n')
    expect(zeilenPruefen(zeilen, POSITIONEN)[0].fehler).toBe('Betrag ist keine Zahl')
  })

  it('zählt die Zeile der Datei mit, damit ein Befund auffindbar ist', () => {
    const zeilen = csvLesen('Position;Betrag\n121;1000\n999;1000\n')
    expect(zeilenPruefen(zeilen, POSITIONEN)[1].zeile).toBe(3)
  })
})
