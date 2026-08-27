import { describe, it, expect, vi, beforeEach } from 'vitest'
import { shallowMount, flushPromises } from '@vue/test-utils'
import Budgetliste from '../components/Budgetliste.vue'
import App from '../App.vue'
import PwLoeschen from '../components/PwLoeschen.vue'
import axios from '@nextcloud/axios'

vi.mock('@nextcloud/auth', () => ({ getCurrentUser: () => ({ uid: 'u', displayName: 'U' }) }))
vi.mock('../realtime', () => ({ subscribeRealtime: () => () => {} }))

// Beispiel-Ansicht in der Struktur der Budget-API (BudgetService::ansicht).
function ansichtFixture() {
  return {
    jahr: { jahr: 2026, steuerfuss: 125, steuerertrag: 250000000, personalsteuer: 24, novemberbriefImportiert: false, steuerfussAutomatik: true },
    departemente: ['Präsidiales', 'Finanzen'],
    produktegruppen: [
      {
        id: 1, code: '121', name: 'Personalamt', departement: 'Präsidiales',
        globalkredit: { ist: 4061060, sollVorjahr: 4395510, soll: 4866124, plan1: 0, plan2: 0, plan3: 0 },
        aufwand: { ist: 6514117, sollVorjahr: 6863996, soll: 7428985 },
        ertrag: { ist: 2453057, sollVorjahr: 2468486, soll: 2562862 },
        stellen: { ist: 17.2, sollVorjahr: 16.85, soll: 19.5 },
        produkte: [{ nummer: 1, name: 'Personalrecht', nettokosten: { ist: 1057730, sollVorjahr: 1145451, soll: 1135916 } }],
        begruendungAbweichung: 'Aufbau von Stellen',
        erlaeuterungStellen: 'Differenz von + 2.65 Stellen',
        begruendungFap: 'Schwerpunkt Projekt WIN HR',
        massnahmen: 'Personalbefragung und Digitalisierung',
        auftrag: 'Das Personalamt bearbeitet die personalrechtlichen Fragen.',
        zielvorgaben: [
          { zielNummer: 2, zielTitel: '2 Kundenorientierung zentrales Personalmanagement', messgroesse: 'Prozentsatz zufrieden', werte: ['90', '85', '85', '85', '85', '85'], soll: '85' },
        ],
      },
      {
        id: 2, code: '263', name: 'Städtische Allgemeinkosten/Erlöse', departement: 'Finanzen',
        globalkredit: { ist: 0, sollVorjahr: 0, soll: -3000000, plan1: 0, plan2: 0, plan3: 0 },
        aufwand: { ist: 0, sollVorjahr: 0, soll: 1000000 },
        ertrag: { ist: 0, sollVorjahr: 0, soll: 4000000 },
        stellen: { ist: 0, sollVorjahr: 0, soll: 0 },
        produkte: [],
      },
    ],
    investitionen: [
      { id: 5, departement: 'Bau und Mobilität', cluster: 'Strassen', projekt: 'Tösstalstrasse', bu: 2000000, fap1: 1000000, fap2: 0, fap3: 0, gesamtkosten: 5000000, bereitsGetaetigt: 500000, planungskosten: 200000 },
    ],
    antraege: [
      { id: 10, bereich: 'globalbudget', zielRef: '121', betragDelta: -100000, stellenDelta: 0, antragsteller: 'Fraktion X', begruendung: 'Sparen', automatisch: false, entscheid: 'offen', phase: 'fraktion' },
      { id: 11, bereich: 'globalbudget', zielRef: '121', betragDelta: -20000, stellenDelta: 0, antragsteller: '', begruendung: 'Pauschalkürzung', automatisch: true, entscheid: 'offen', phase: 'fraktion' },
      { id: 12, bereich: 'globalbudget', zielRef: '121', betragDelta: -30000, stellenDelta: 0, antragsteller: 'SVP', begruendung: 'Offizieller Kürzungsantrag', automatisch: false, entscheid: 'offen', phase: 'sitzung' },
      { id: 13, bereich: 'personal', zielRef: '121', betragDelta: -200000, stellenDelta: -1, antragsteller: 'Fraktion X', begruendung: 'Stelle kürzen', automatisch: false, entscheid: 'offen', phase: 'fraktion' },
      { id: 14, bereich: 'investition', zielRef: '5', betragDelta: -100000, stellenDelta: 0, antragsteller: 'Fraktion X', begruendung: 'Projekt kürzen', automatisch: false, entscheid: 'offen', phase: 'fraktion' },
    ],
    verteilung: { automatikEin: true, zielModus: 'schwarze_null', zielBetrag: 0, haltung: 'einreichen', ausnahmen: [] },
    pauschalantraege: [
      { id: 30, betrag: -500000, prozent: 0, haltung: 'einreichen', herkunft: 'eigene', antragsteller: '', begruendung: 'Sparpaket', ausnahmen: [] },
    ],
    summen: { ausgaben: 8428985, einnahmen: 6562862, ergebnis: -1866123, stellen: 19.5, ausgabenDiff: 500000, einnahmenDiff: 100000, ergebnisDiff: -400000, stellenDiff: 2.65 },
    standardBetragProStelle: 200000,
    kommissionZuordnung: [
      { departement: 'Präsidiales', kommission: 'SK Präsidiales' },
      { departement: 'Finanzen', kommission: 'SK Finanzen' },
    ],
    // Eigene Fraktion aus der Konfiguration (F94): Antragsteller-Vorbelegung.
    eigeneFraktion: 'GLP',
  }
}

async function mitAnsicht() {
  const wrapper = shallowMount(Budgetliste)
  await flushPromises()
  return wrapper
}

describe('Budgetliste', () => {
  beforeEach(() => {
    axios.get.mockReset().mockImplementation((url) => {
      const u = String(url)
      if (u.includes('/budget/verfuegbar')) { return Promise.resolve({ data: { verfuegbar: [2025, 2024], importierbar: [2025, 2024] } }) }
      if (u.includes('/budget/jahre')) { return Promise.resolve({ data: [{ jahr: 2026 }, { jahr: 2025 }] }) }
      return Promise.resolve({ data: ansichtFixture() })
    })
    axios.post.mockReset().mockResolvedValue({ data: {} })
    axios.put.mockReset().mockResolvedValue({ data: ansichtFixture() })
    axios.delete.mockReset().mockResolvedValue({ data: {} })
  })

  // F74
  it('Budget-Tab steht nach Vorstössen und vor Mitgliedern (vor Sitzungstypen) und lädt die Jahre beim Mount', () => {
    const keys = App.data().ansichten.map(a => a.key)
    expect(keys.indexOf('budget')).toBe(keys.indexOf('vorstoesse') + 1)
    expect(keys.indexOf('mitglieder')).toBe(keys.indexOf('budget') + 1)
    expect(keys.indexOf('sitzungstypen')).toBe(keys.indexOf('mitglieder') + 1)
    shallowMount(Budgetliste)
    expect(axios.get.mock.calls.some(c => String(c[0]).includes('/budget/jahre'))).toBe(true)
  })

  // F75
  it('bietet die vorhandenen Jahre als Auswahl, neuestes zuerst', async () => {
    const w = await mitAnsicht()
    expect(w.vm.jahrOptionen.map(o => o.value)).toEqual([2026, 2025])
  })

  // F76
  it('lädt beim Departementswechsel neu und übergibt den Filter', async () => {
    const w = await mitAnsicht()
    axios.get.mockClear()
    w.vm.departementOption = { value: 'Finanzen', label: 'Finanzen' }
    await w.vm.ladeAnsicht(false)
    const call = axios.get.mock.calls.find(c => String(c[0]).includes('/budget/2026'))
    expect(call[1].params.departement).toBe('Finanzen')
  })

  // F77
  it('übergibt Kostensteigerungs-Filter (Prozent und absolut)', async () => {
    const w = await mitAnsicht()
    axios.get.mockClear()
    w.vm.minProzent = '5'
    w.vm.minAbsolut = '1000000'
    await w.vm.ladeAnsicht(false)
    const call = axios.get.mock.calls.find(c => String(c[0]).includes('/budget/2026'))
    expect(call[1].params.minProzent).toBe('5')
    expect(call[1].params.minAbsolut).toBe('1000000')
  })

  // F78
  it('gruppiert Produktegruppen nach Departement in Listenreihenfolge', async () => {
    const w = await mitAnsicht()
    expect(w.vm.gruppenNachDepartement.map(d => d.name)).toEqual(['Präsidiales', 'Finanzen'])
  })

  // F79
  it('zeigt die Summen aus der Ansicht und formatiert Franken schweizerisch', async () => {
    const w = await mitAnsicht()
    expect(w.vm.summen.ergebnis).toBe(-1866123)
    expect(w.vm.fr(1234567)).toBe('1’234’567')
    expect(w.vm.fr(-100000)).toBe('−100’000')
  })

  // F102: die Übersicht vergleicht Stadtratsbudget (ohne unsere Anträge) mit dem
  // Fraktionsbudget (mit Anträgen) und zeigt die Differenz.
  it('zeigt die Übersicht als Vergleich Stadtratsbudget/Fraktionsbudget mit Differenz', async () => {
    axios.get.mockImplementation((url) => {
      const u = String(url)
      if (u.includes('/budget/verfuegbar')) { return Promise.resolve({ data: { verfuegbar: [], importierbar: [] } }) }
      if (u.includes('/budget/jahre')) { return Promise.resolve({ data: [{ jahr: 2026 }] }) }
      const a = ansichtFixture()
      a.summenStadtrat = { ...a.summen, ausgaben: 9000000, einnahmen: 6562862, ergebnis: -2437138, stellen: 19.5 }
      return Promise.resolve({ data: a })
    })
    const w = await mitAnsicht()
    expect(w.find('.pw-budget-vergleich').exists(), 'Vergleichstabelle fehlt').toBe(true)
    const html = w.html()
    expect(html).toContain('Stadtratsbudget')
    expect(html).toContain('Fraktionsbudget')
    expect(html).toContain('Differenz')
    expect(html).toContain(w.vm.fr(9000000)) // Stadtrat-Ausgaben
    expect(html).toContain(w.vm.fr(8428985)) // Fraktion-Ausgaben
    expect(html).toContain(w.vm.diff(8428985 - 9000000)) // Differenz der Ausgaben
  })

  // F80: der Globalkredit steht auf der Karte; die Produkte (Information) stehen im
  // Vollbild-Detail (F110), damit die Karte geschlossen übersichtlich bleibt.
  it('zeigt je Produktegruppe den Globalkredit; die Produkte stehen im Vollbild-Detail', async () => {
    const w = await mitAnsicht()
    const g = w.vm.gruppenNachDepartement[0].gruppen[0]
    expect(g.globalkredit.soll).toBe(4866124)
    expect(w.html()).toContain(w.vm.fr(4866124))
    w.vm.pgDetailOeffnen('121')
    expect(w.vm.pgDetail.produkte[0].name).toBe('Personalrecht')
    expect(w.vm.pgDetail.produkte[0].nettokosten.soll).toBe(1135916)
  })

  // F80/F110: der Auftragstext steht als Information im Vollbild-Detail.
  it('zeigt den Auftragstext der Produktegruppe im Vollbild-Detail', async () => {
    const w = await mitAnsicht()
    w.vm.pgDetailOeffnen('121')
    expect(w.vm.pgDetail.auftrag).toContain('Das Personalamt bearbeitet die personalrechtlichen Fragen.')
  })

  // F80/F110: alle Erläuterungen/Begründungen stehen im Vollbild-Detail.
  it('führt alle Erläuterungen und Begründungen der Produktegruppe im Vollbild-Detail', async () => {
    const w = await mitAnsicht()
    w.vm.pgDetailOeffnen('121')
    const g = w.vm.pgDetail
    expect(g.erlaeuterungStellen).toContain('Differenz von + 2.65 Stellen')
    expect(g.begruendungFap).toContain('Schwerpunkt Projekt WIN HR')
    expect(g.massnahmen).toContain('Personalbefragung und Digitalisierung')
  })

  // F89: die künstliche Produktegruppe wird abgesetzt gezeigt und trägt keine
  // Antrags-Bedienelemente.
  it('zeigt die künstliche Produktegruppe abgesetzt und ohne Antragsbedienung', async () => {
    const fix = ansichtFixture()
    fix.produktegruppen.push({
      id: 99, code: 'IV', name: 'Interne Verrechnung / Abgrenzung', departement: 'Finanzen',
      kuenstlich: true, antragbar: false,
      globalkredit: { ist: 0, sollVorjahr: 0, soll: -15503292, plan1: 0, plan2: 0, plan3: 0 },
      aufwand: { ist: 0, sollVorjahr: 0, soll: -163718893 },
      ertrag: { ist: 0, sollVorjahr: 0, soll: -148215601 },
      stellen: { ist: 0, sollVorjahr: 0, soll: 0 },
      produkte: [],
    })
    axios.get.mockImplementation((url) => {
      const u = String(url)
      if (u.includes('/budget/verfuegbar')) { return Promise.resolve({ data: { verfuegbar: [], importierbar: [] } }) }
      if (u.includes('/budget/jahre')) { return Promise.resolve({ data: [{ jahr: 2026 }] }) }
      return Promise.resolve({ data: fix })
    })
    const w = await mitAnsicht()
    const karten = w.findAll('.pw-budget-kuenstlich')
    expect(karten).toHaveLength(1)
    const html = karten[0].html()
    expect(html).toContain('Rechnerische Position')
    expect(html).toContain('Interne Verrechnung / Abgrenzung')
    expect(html).not.toContain('+ Antrag')
    expect(html).not.toContain('Produktegruppe IV')
  })

  // F84: automatisch erzeugte Anträge sind erkennbar UND eigens filterbar.
  it('filtert Anträge nach Art (alle / nur manuelle / nur automatische)', async () => {
    const w = await mitAnsicht()
    // Standard «alle»: manueller und automatischer Antrag der Produktegruppe 121.
    expect(w.vm.antraegeFuer('121').map(a => a.id).sort()).toEqual([10, 11])
    // Nur automatische.
    w.vm.antragsartOption = { value: 'automatisch', label: 'Nur automatische' }
    expect(w.vm.antraegeFuer('121').map(a => a.id)).toEqual([11])
    expect(w.vm.antraegeFuer('121')[0].automatisch).toBe(true)
    // Nur manuelle.
    w.vm.antragsartOption = { value: 'manuell', label: 'Nur manuelle' }
    expect(w.vm.antraegeFuer('121').map(a => a.id)).toEqual([10])
    expect(w.vm.antraegeFuer('121')[0].automatisch).toBe(false)
  })

  // F93: Sitzungsmodus trennt Fraktionsanträge von offiziellen Sitzungsanträgen
  it('schaltet in den Sitzungsmodus und zeigt nur Sitzungsanträge', async () => {
    const w = await mitAnsicht()
    // Vorbereitung: nur Fraktionsanträge.
    expect(w.vm.aktivePhase).toBe('fraktion')
    expect(w.vm.antraegeFuer('121').map(a => a.id).sort()).toEqual([10, 11])
    // Umschalten → nur der offizielle Sitzungsantrag, Reload mit phase=sitzung.
    w.vm.sitzungsmodusUmschalten(true)
    await flushPromises()
    expect(w.vm.aktivePhase).toBe('sitzung')
    expect(w.vm.antraegeFuer('121').map(a => a.id)).toEqual([12])
    const call = axios.get.mock.calls.reverse().find(c => /\/budget\/\d+(\?|$)/.test(String(c[0])) && c[1] && c[1].params && c[1].params.phase === 'sitzung')
    expect(call, 'kein Reload mit phase=sitzung').toBeTruthy()
  })

  // F93: ein im Sitzungsmodus gestellter Antrag ist ein Sitzungsantrag
  it('stellt im Sitzungsmodus einen Sitzungsantrag (phase=sitzung)', async () => {
    const w = await mitAnsicht()
    w.vm.sitzungsmodus = true
    w.vm.neu['121'].betrag = '-40000'
    await w.vm.antragGlobalbudget('121')
    const call = axios.post.mock.calls.find(c => String(c[0]).includes('/budget/2026/antraege'))
    expect(call[1].phase).toBe('sitzung')
  })

  // F76: zwei verknüpfte Filter — Kommission (aus der Zuordnung) und Departement
  it('bietet den Kommission-Filter aus der Zuordnung und verknüpft ihn mit dem Departement', async () => {
    const w = await mitAnsicht()
    // Kommissionen aus der Zuordnung.
    expect(w.vm.kommissionOptionen.map(o => o.value).sort()).toEqual(['SK Finanzen', 'SK Präsidiales'])
    // Ohne Kommission: alle Departemente.
    expect(w.vm.departementOptionen.map(o => o.value).sort()).toEqual(['Finanzen', 'Präsidiales'])
    // Kommission wählen → Departemente auf deren Departement eingeengt, Departement-Auswahl geleert.
    w.vm.departementOption = { value: 'Finanzen', label: 'Finanzen' }
    w.vm.kommissionGewechselt({ value: 'SK Präsidiales', label: 'SK Präsidiales' })
    expect(w.vm.departementOption, 'Departement-Auswahl nach Kommissionswechsel nicht geleert').toBeNull()
    expect(w.vm.departementOptionen.map(o => o.value)).toEqual(['Präsidiales'])
    await flushPromises()
    // Reload sendet den kommission-Parameter.
    const call = axios.get.mock.calls.reverse().find(c => /\/budget\/\d+(\?|$)/.test(String(c[0])) && c[1] && c[1].params && c[1].params.kommission)
    expect(call, 'kein Reload mit kommission-Parameter').toBeTruthy()
    expect(call[1].params.kommission).toBe('SK Präsidiales')
  })

  // F81 + F82
  it('stellt Anträge nur auf Produktegruppen und ordnet sie ihr zu', async () => {
    const w = await mitAnsicht()
    expect(w.vm.antraegeFuer('121').map(a => a.id).sort()).toEqual([10, 11])
    w.vm.neu['121'].betrag = '-50000'
    w.vm.neu['121'].antragsteller = 'Y'
    await w.vm.antragGlobalbudget('121')
    const call = axios.post.mock.calls.find(c => String(c[0]).includes('/budget/2026/antraege'))
    expect(call[1].bereich).toBe('globalbudget')
    expect(call[1].zielRef).toBe('121')
    expect(call[1].betragDelta).toBe(-50000)
  })

  // F84 + F85: ein Pauschalantrag trägt einen Ziel-Typ; ein absolutes Ziel (hier
  // festes Defizit) wird über pauschalAendern gesetzt.
  it('setzt den Ziel-Typ eines Pauschalantrags auf ein absolutes Ziel', async () => {
    const w = await mitAnsicht()
    await w.vm.pauschalAendern(30, { zielModus: 'festes_defizit', zielBetrag: -100000, haltung: 'einreichen', ausnahmen: [] })
    const call = axios.put.mock.calls.find(c => String(c[0]).includes('/budget/pauschal/30'))
    expect(call[1].zielModus).toBe('festes_defizit')
    expect(call[1].zielBetrag).toBe(-100000)
  })

  // F86/F87: Personal- und Investitionsanträge werden je Ziel aufgelistet
  it('listet Personal- und Investitionsanträge je Ziel', async () => {
    const w = await mitAnsicht()
    expect(w.vm.antraegeBereich('personal', '121').map(a => a.id)).toEqual([13])
    expect(w.vm.antraegeBereich('investition', 5).map(a => a.id)).toEqual([14])
    // Kein Übergriff auf andere Bereiche.
    expect(w.vm.antraegeBereich('personal', '121')[0].bereich).toBe('personal')
  })

  // F86
  it('stellt Personalanträge mit Stellen und (Default-)Betrag', async () => {
    const w = await mitAnsicht()
    w.vm.neuPersonal['121'].stellen = '-2'
    await w.vm.antragPersonal('121')
    const call = axios.post.mock.calls.find(c => c[1] && c[1].bereich === 'personal')
    expect(call[1].stellenDelta).toBe(-2)
    expect(call[1].zielRef).toBe('121')
  })

  // F87
  it('stellt Investitionsanträge je Projekt', async () => {
    const w = await mitAnsicht()
    w.vm.neuInv[5].betrag = '-1000000'
    await w.vm.antragInvestition(w.vm.ansicht.investitionen[0])
    const call = axios.post.mock.calls.find(c => c[1] && c[1].bereich === 'investition')
    expect(call[1].zielRef).toBe('5')
    expect(call[1].betragDelta).toBe(-1000000)
  })

  // F88 + F96: der Steuerfuss-Antrag wird in Prozentpunkten gestellt
  it('leitet den Steuerprozent-Wert ab und stellt einen Steuerfuss-Antrag in Prozentpunkten', async () => {
    const w = await mitAnsicht()
    expect(w.vm.wertProProzent).toBe(2000000) // 250M / 125
    w.vm.steuerfussManuell = '123'
    await w.vm.steuerfussAntragSpeichern()
    const call = axios.post.mock.calls.find(c => c[1] && c[1].bereich === 'steuerfuss')
    expect(call[1].prozentDelta).toBe(-2) // 123 − 125 = −2 Prozentpunkte (F96); den CHF-Effekt rechnet der Server
  })

  // F94 + F97: Herkunft und Haltung werden mitgesendet
  it('sendet Herkunft und Haltung eines Antrags mit', async () => {
    const w = await mitAnsicht()
    w.vm.neu['121'].betrag = '10000'
    w.vm.neu['121'].herkunft = 'fremde'
    w.vm.neu['121'].haltung = 'unterstuetzen'
    await w.vm.antragGlobalbudget('121')
    const call = axios.post.mock.calls.find(c => String(c[0]).includes('/budget/2026/antraege'))
    expect(call[1].herkunft).toBe('fremde')
    expect(call[1].haltung).toBe('unterstuetzen')
    expect(call[1].betragDelta).toBe(-10000) // Standard ist Reduktion (−)
  })

  // F95: der Umschalter Mehrausgabe kehrt das Vorzeichen um
  it('kehrt bei Mehrausgabe das Vorzeichen von CHF und Prozent um', async () => {
    const w = await mitAnsicht()
    w.vm.neu['121'].betrag = '10000'
    w.vm.neu['121'].prozent = '5'
    w.vm.neu['121'].mehrausgabe = true
    await w.vm.antragGlobalbudget('121')
    const call = axios.post.mock.calls.find(c => String(c[0]).includes('/budget/2026/antraege'))
    expect(call[1].betragDelta).toBe(10000)
    expect(call[1].prozentDelta).toBe(5)
  })

  // F98: unterstützen wir den Antrag, ist die eigene Fraktion automatisch dabei —
  // die eigene Fraktion kommt aus der Konfiguration (ansicht.eigeneFraktion, «GLP»).
  it('nimmt die eigene Fraktion automatisch in die Unterstützer auf', async () => {
    const w = await mitAnsicht()
    expect(w.vm.eigeneFraktionName).toBe('GLP')
    w.vm.neu['121'].betrag = '5000'
    w.vm.neu['121'].haltung = 'einreichen'
    await w.vm.antragGlobalbudget('121')
    const call = axios.post.mock.calls.find(c => String(c[0]).includes('/budget/2026/antraege'))
    expect(call[1].unterstuetzer).toContain('GLP')
  })

  // F100: den Einreichen-Entscheid eines Pauschalantrags umschalten
  it('schaltet den Einreichen-Entscheid eines Pauschalantrags', async () => {
    const w = await mitAnsicht()
    await w.vm.pauschalAendern(30, { haltung: 'nicht_einreichen' })
    const call = axios.put.mock.calls.find(c => String(c[0]).includes('/budget/pauschal/30'))
    expect(call[1].haltung).toBe('nicht_einreichen')
  })

  // F101: eine Position von einem Pauschalantrag ausnehmen
  it('nimmt eine Position von einem Pauschalantrag aus', async () => {
    const w = await mitAnsicht()
    await w.vm.pauschalAendern(30, { ausnahmen: ['121'] })
    const call = axios.put.mock.calls.find(c => String(c[0]).includes('/budget/pauschal/30'))
    expect(call[1].ausnahmen).toContain('121')
  })

  // F101: der Ausnahme-Toggle direkt an der Produktegruppe trägt den PG-Code in die
  // Ausnahmenliste des Pauschalantrags ein (Toggle aus) bzw. wieder aus (Toggle ein).
  it('schaltet die PG-Ausnahme eines Pauschalantrags über den PG-Toggle', async () => {
    const w = await mitAnsicht()
    const p = { id: 30, ausnahmen: [] }
    expect(w.vm.pgAusgenommen(p, '121')).toBe(false)
    // Toggle aus (teilnehmen=false) → PG wird ausgenommen.
    w.vm.pgPauschalToggle(p, '121', false)
    let call = axios.put.mock.calls.find(c => String(c[0]).includes('/budget/pauschal/30'))
    expect(call[1].ausnahmen).toContain('121')
    // Ist die PG schon ausgenommen, meldet pgAusgenommen true; Toggle ein entfernt sie.
    expect(w.vm.pgAusgenommen({ id: 30, ausnahmen: ['121'] }, '121')).toBe(true)
    w.vm.pgPauschalToggle({ id: 30, ausnahmen: ['121'] }, '121', true)
    call = axios.put.mock.calls.filter(c => String(c[0]).includes('/budget/pauschal/30')).pop()
    expect(call[1].ausnahmen).not.toContain('121')
  })

  // F94: der Antragsteller eines neuen (eigenen) Antrags ist per Default die eigene
  // Fraktion (aus der Konfiguration, ansicht.eigeneFraktion), nicht die Person.
  it('belegt den Antragsteller neuer eigener Anträge mit der eigenen Fraktion vor', async () => {
    const w = await mitAnsicht()
    expect(w.vm.eigeneFraktionName).toBe('GLP')
    expect(w.vm.leererAntrag().antragsteller).toBe('GLP')
    // Die eigene Fraktion ist auch bei eigenen Anträgen als Option wählbar (zuoberst).
    expect(w.vm.antragstellerOptionen('eigene')[0]).toMatchObject({ value: 'GLP' })
    // Und sie ist im Pauschalantrag-Fraktionsmenü enthalten.
    expect(w.vm.fraktionOptionen.some(o => o.value === 'GLP')).toBe(true)
  })

  // F97: die Haltung eines bestehenden Antrags ändern (getrennt vom Sitzungs-Beschluss)
  it('ändert die Haltung eines bestehenden Antrags', async () => {
    const w = await mitAnsicht()
    await w.vm.antragHaltung(10, 'nicht_einreichen')
    const call = axios.put.mock.calls.find(c => String(c[0]).includes('/budget/antraege/10'))
    expect(call[1].haltung).toBe('nicht_einreichen')
  })

  // F97: die Haltung ist ein Toggle statt eines Dropdowns — eigene «Antrag stellen»
  // (einreichen/nicht_einreichen), fremde «Unterstützen» (unterstuetzen/nicht_unterstuetzen).
  it('bildet den Haltungs-Toggle je nach Herkunft ab', async () => {
    const w = await mitAnsicht()
    const eigen = { id: 20, herkunft: 'eigene', haltung: 'einreichen' }
    const fremd = { id: 21, herkunft: 'fremde', haltung: 'nicht_unterstuetzen' }
    // Beschriftung und Zustand hängen an Herkunft und Haltung.
    expect(w.vm.haltungLabel(eigen)).toBe('Antrag stellen')
    expect(w.vm.haltungLabel(fremd)).toBe('Unterstützen')
    expect(w.vm.haltungAn(eigen)).toBe(true)
    expect(w.vm.haltungAn(fremd)).toBe(false)
    // Umschalten bildet den Bool auf die herkunftsrichtige Haltung ab.
    w.vm.haltungUmschalten(eigen, false)
    expect(axios.put.mock.calls.find(c => String(c[0]).includes('/budget/antraege/20'))[1].haltung).toBe('nicht_einreichen')
    w.vm.haltungUmschalten(fremd, true)
    expect(axios.put.mock.calls.find(c => String(c[0]).includes('/budget/antraege/21'))[1].haltung).toBe('unterstuetzen')
  })

  // F97/F102a: der Beschluss (✓/✗) erscheint nur im Sitzungsmodus, nicht in der Vorbereitung.
  it('zeigt den Beschluss nur im Sitzungsmodus', async () => {
    const w = await mitAnsicht()
    expect(w.vm.sitzungsmodus).toBe(false)
    expect(w.find('.pw-entscheid-knoepfe').exists()).toBe(false)
  })

  // Einheitliches Löschen: eine Antragszeile (nicht automatisch) trägt den geteilten
  // ✕-Knopf (PwLoeschen), nicht mehr einen eigenen NcButton.
  it('nutzt für das Antrag-Löschen den einheitlichen ✕-Knopf (PwLoeschen)', async () => {
    const w = await mitAnsicht()
    const labels = w.findAllComponents(PwLoeschen).map(l => l.props('label'))
    expect(labels).toContain('Antrag löschen')
  })

  // F104: eine Verknüpfung von Hand setzen
  it('setzt eine Verknüpfung von Hand', async () => {
    const w = await mitAnsicht()
    await w.vm.verknuepfungWaehlen(12, 10)
    const call = axios.put.mock.calls.find(c => String(c[0]).includes('/budget/antraege/12/verknuepfung'))
    expect(call[1].zielId).toBe(10)
  })

  // F100: weitere, unabhängige Pauschalanträge
  it('listet weitere Pauschalanträge und legt einen neuen an', async () => {
    const w = await mitAnsicht()
    expect(w.vm.pauschalantraege.map(p => p.id)).toEqual([30])
    await w.vm.pauschalErstellen()
    const call = axios.post.mock.calls.find(c => String(c[0]).includes('/budget/2026/pauschal'))
    expect(call, 'Pauschalantrag wird nicht angelegt').toBeTruthy()
  })

  it('ändert und löscht einen Pauschalantrag', async () => {
    const w = await mitAnsicht()
    await w.vm.pauschalAendern(30, { prozent: -10, betrag: 0, haltung: 'einreichen', ausnahmen: ['121'] })
    const put = axios.put.mock.calls.find(c => String(c[0]).includes('/budget/pauschal/30'))
    expect(put[1].prozent).toBe(-10)
    expect(put[1].ausnahmen).toContain('121')
    await w.vm.pauschalLoeschen(30)
    const del = axios.delete.mock.calls.find(c => String(c[0]).includes('/budget/pauschal/30'))
    expect(del, 'Pauschalantrag wird nicht gelöscht').toBeTruthy()
  })

  // Antragsformular: «Abbrechen» schliesst das Formular, ohne einen Antrag anzulegen.
  it('schliesst das Antragsformular über formSchliessen (Abbrechen)', async () => {
    const w = await mitAnsicht()
    w.vm.formOeffnen('g121')
    expect(w.vm.formOffen['g121']).toBe(true)
    w.vm.formSchliessen('g121')
    expect(w.vm.formOffen['g121']).toBeUndefined()
  })

  // F88: der Automatik-Schalter wird am Server pro Jahr gespeichert (überlebt Reload);
  // beim Abschalten fällt der Steuerfuss auf den Stadtratsantrag zurück.
  it('schaltet die Steuerfuss-Automatik am Server um und gibt bei Aus das manuelle Feld frei', async () => {
    let automatikAn = true
    axios.get.mockImplementation((url) => {
      const u = String(url)
      if (u.includes('/budget/verfuegbar')) { return Promise.resolve({ data: { verfuegbar: [2025], importierbar: [2025] } }) }
      if (u.includes('/budget/jahre')) { return Promise.resolve({ data: [{ jahr: 2026 }] }) }
      const a = ansichtFixture()
      a.jahr.steuerfussAutomatik = automatikAn
      return Promise.resolve({ data: a })
    })
    axios.put.mockImplementation((url, body) => {
      if (String(url).includes('/steuerfuss-automatik')) { automatikAn = !!(body && body.an) }
      return Promise.resolve({ data: {} })
    })
    const w = await mitAnsicht()
    expect(w.vm.steuerfussAutomatik, 'Default: Automatik ein').toBe(true)
    await w.vm.steuerfussAutomatikUmschalten(false)
    expect(
      axios.put.mock.calls.some(c => String(c[0]).includes('/steuerfuss-automatik') && c[1].an === false),
      'schaltet die Automatik am Server aus (persistiert)',
    ).toBe(true)
    expect(w.vm.steuerfussAutomatik, 'nach Abschalten ist die Automatik aus (manuelles Feld frei)').toBe(false)
    await w.vm.steuerfussAutomatikUmschalten(true)
    expect(w.vm.steuerfussAutomatik, 'wieder eingeschaltet').toBe(true)
  })

  // F88: «Antrag stellen» ist ein Toggle (kein Knopf mehr): ein legt den Steuerfuss-
  // antrag an, aus löscht ihn (zurück zum Stadtratsantrag). Behebt den Mehrfachklick-
  // Bug und die fehlende Löschmöglichkeit.
  it('stellt und löscht den Steuerfuss-Antrag über den «Antrag stellen»-Toggle', async () => {
    const w = await mitAnsicht()
    w.vm.ansicht.jahr.steuerfussAutomatik = false
    w.vm.steuerfussManuell = '123'
    await w.vm.steuerfussAntragStellenUmschalten(true)
    expect(
      axios.post.mock.calls.some(c => c[1] && c[1].bereich === 'steuerfuss'),
      'Toggle ein legt den Antrag an',
    ).toBe(true)
    // jetzt existiert ein manueller Antrag → Toggle-Zustand ist «ein», Aus löscht ihn.
    w.vm.ansicht.antraege.push({ id: 77, bereich: 'steuerfuss', quelle: 'manuell', prozentDelta: -2, phase: 'fraktion' })
    expect(w.vm.steuerfussAntragStellenAn, 'mit Antrag ist der Toggle ein').toBe(true)
    await w.vm.steuerfussAntragStellenUmschalten(false)
    expect(
      axios.delete.mock.calls.some(c => String(c[0]).includes('/budget/antraege/77')),
      'Toggle aus löscht den Antrag',
    ).toBe(true)
  })

  // Bug (Mehrfachklick): das langsame Neurechnen darf bei Mehrfachauslösung nicht
  // mehrere gleiche Anträge erzeugen — die Laufsperre lässt nur einen durch.
  it('erzeugt bei Mehrfachauslösung keinen doppelten Steuerfuss-Antrag', async () => {
    const w = await mitAnsicht()
    w.vm.ansicht.jahr.steuerfussAutomatik = false
    w.vm.steuerfussManuell = '123'
    await Promise.all([w.vm.steuerfussAntragSpeichern(), w.vm.steuerfussAntragSpeichern(), w.vm.steuerfussAntragSpeichern()])
    const posts = axios.post.mock.calls.filter(c => c[1] && c[1].bereich === 'steuerfuss')
    expect(posts.length, 'nur ein Antrag trotz drei Auslösungen').toBe(1)
  })

  // F109: ein reiner Zielvorgaben-Antrag (ohne Budgetwert) wird gestellt und trägt
  // die Zielvorgaben-Änderungen mit (npnp: ohne die Guard-Lockerung kein POST).
  it('stellt einen reinen Zielvorgaben-Antrag ohne Budgetwert', async () => {
    const w = await mitAnsicht()
    w.vm.neu['121'].zielAenderungen = [{ zielNummer: 2, messgroesse: 'Prozentsatz zufrieden', neuerWert: '100' }]
    await w.vm.antragGlobalbudget('121')
    const call = axios.post.mock.calls.find(c => c[1] && c[1].zielRef === '121' && Array.isArray(c[1].zielAenderungen) && c[1].zielAenderungen.length)
    expect(call, 'ein Antrag mit Zielvorgaben-Änderung wird gesendet, auch ohne Betrag').toBeTruthy()
    expect(call[1].zielAenderungen[0].neuerWert).toBe('100')
    expect(call[1].betragDelta).toBeUndefined()
  })

  // F109: eine reine Einsparungsverteilung (ohne oberen Betrag) wird gesendet; die
  // Summe wird serverseitig zum PG-Betrag.
  it('stellt einen Antrag mit Einsparungsverteilung, auch ohne oberen Betrag', async () => {
    const w = await mitAnsicht()
    w.vm.neu['121'].aufteilung = [
      { ebene: 'produkt', ref: '1', betrag: -20000 },
      { ebene: 'pg-kosten', ref: 'Personalkosten', betrag: -30000 },
    ]
    await w.vm.antragGlobalbudget('121')
    const call = axios.post.mock.calls.find(c => c[1] && c[1].zielRef === '121' && Array.isArray(c[1].aufteilung) && c[1].aufteilung.length)
    expect(call, 'ein Antrag mit Aufteilung wird gesendet, auch ohne oberen Betrag').toBeTruthy()
    expect(call[1].aufteilung.length).toBe(2)
    expect(call[1].betragDelta).toBeUndefined()
  })

  // F110: die Produktegruppe öffnet ihr Vollbild-Detail (Zielvorgaben, Produkte,
  // Erläuterungen) statt die Details inline in die Karte zu quetschen.
  it('öffnet das Vollbild-Detail einer Produktegruppe und schliesst es wieder', async () => {
    const w = await mitAnsicht()
    expect(w.vm.pgDetail).toBe(null)
    w.vm.pgDetailOeffnen('121')
    expect(w.vm.pgDetailCode).toBe('121')
    expect(w.vm.pgDetail.code).toBe('121')
    expect(w.vm.pgDetail.zielvorgaben.length).toBe(1)
    w.vm.pgDetailSchliessen()
    expect(w.vm.pgDetail).toBe(null)
  })

  // Bedienung: der Tabwechsel merkt die Scrollposition je Tab (nur RAM) und stellt
  // sie beim Zurückwechseln wieder her — ein neuer Tab startet oben.
  it('merkt die Scrollposition je Tab und stellt sie beim Zurückwechseln wieder her', async () => {
    const w = await mitAnsicht()
    const fake = { scrollTop: 0, scrollHeight: 1000, clientHeight: 300 }
    w.vm.scrollContainer = () => fake
    // Auf dem globalbudget-Tab nach unten gescrollt, dann zu steuerfuss wechseln.
    fake.scrollTop = 420
    w.vm.tabWechseln('steuerfuss')
    await w.vm.$nextTick()
    expect(w.vm.aktiverTab).toBe('steuerfuss')
    expect(fake.scrollTop, 'ein noch nicht besuchter Tab startet oben').toBe(0)
    // Im steuerfuss-Tab gescrollt, dann zurück zu globalbudget.
    fake.scrollTop = 130
    w.vm.tabWechseln('globalbudget')
    await w.vm.$nextTick()
    expect(fake.scrollTop, 'die gemerkte Position des globalbudget-Tabs ist wieder da').toBe(420)
  })

  // F89 + F91
  it('importiert ein vergangenes Budgetjahr aus der Auswahl der verfügbaren Jahre', async () => {
    const w = await mitAnsicht()
    await w.vm.neuOeffnen()
    // «+ Neu» bietet die Jahre mit vorhandenen Unterlagen als Liste an (kein Freitext).
    expect(w.vm.importierbareOptionen.map(o => o.value)).toEqual([2025, 2024])
    w.vm.neuMitNovemberbrief = true
    await w.vm.jahrImportieren()
    const call = axios.post.mock.calls.find(c => String(c[0]).includes('/import'))
    expect(String(call[0])).toContain('/budget/2025/import')
    expect(call[1].mitNovemberbrief).toBe(true)
  })

  // F90
  it('liest den Novemberbrief ein', async () => {
    const w = await mitAnsicht()
    await w.vm.novemberbriefEinlesen()
    expect(axios.post.mock.calls.some(c => String(c[0]).includes('/budget/2026/novemberbrief'))).toBe(true)
  })

  // F90: im Sitzungsmodus lassen sich die tatsächlichen Sitzungsanträge aus dem
  // Drehbuch der Budgetsitzung live einlesen.
  it('liest die Sitzungsanträge aus dem Drehbuch ein', async () => {
    const w = await mitAnsicht()
    w.vm.sitzungsmodusUmschalten(true)
    await w.vm.$nextTick()
    axios.post.mockResolvedValueOnce({ data: { sitzungsantraegeNeu: 35, sitzungsantraegeGefunden: 35 } })
    await w.vm.sitzungsantraegeEinlesen()
    // Der Live-Abruf geht an den Drehbuch-Endpunkt; danach ist der Fortschritt beendet.
    expect(axios.post.mock.calls.some(c => String(c[0]).includes('/budget/2026/sitzungsantraege'))).toBe(true)
    expect(w.vm.sitzungsantraegeLaeuft).toBe(false)
  })

  // F91: «Budget neu einlesen» ist destruktiv — Dialog plus Verstanden-Checkbox,
  // erst mit gesetzter Checkbox wird tatsächlich neu eingelesen.
  it('liest das Budget erst nach doppelter Bestätigung neu ein', async () => {
    const w = await mitAnsicht()
    expect(w.html()).toContain('Budget neu einlesen')
    w.vm.reimportOeffnen()
    expect(w.vm.reimportOffen).toBe(true)
    expect(w.vm.reimportVerstanden).toBe(false)
    // Ohne Checkbox passiert nichts.
    await w.vm.reimportAusfuehren()
    expect(axios.post.mock.calls.some(c => String(c[0]).includes('/reimport'))).toBe(false)
    // Mit gesetzter Checkbox wird neu eingelesen, der Dialog schliesst sofort und
    // am Ende ist der Fortschritt beendet.
    w.vm.reimportVerstanden = true
    await w.vm.reimportAusfuehren()
    expect(axios.post.mock.calls.some(c => String(c[0]).includes('/budget/2026/reimport'))).toBe(true)
    expect(w.vm.reimportOffen).toBe(false)
    expect(w.vm.reimportLaeuft).toBe(false)
  })

  // F92
  it('öffnet das Anträge-PDF', async () => {
    const w = await mitAnsicht()
    const open = vi.spyOn(window, 'open').mockImplementation(() => {})
    w.vm.pdfOeffnen()
    expect(open.mock.calls[0][0]).toContain('/budget/2026/antraege-pdf')
    open.mockRestore()
  })

  // F93
  it('trägt einen Entscheid für die Live-Verfolgung ein', async () => {
    const w = await mitAnsicht()
    await w.vm.entscheidSetzen(10, 'angenommen')
    const call = axios.put.mock.calls.find(c => String(c[0]).includes('/budget/antraege/10/entscheid'))
    expect(call[1].status).toBe('angenommen')
  })

  // F93: cross-session Live-Verfolgung — ein budget.updated-Ereignis aus einer
  // anderen Sitzung lädt die Ansicht neu (Beschlüsse erscheinen sofort überall).
  it('lädt bei einem budget.updated-Realtime-Ereignis neu', async () => {
    const w = await mitAnsicht()
    const ansichtRufe = () => axios.get.mock.calls.filter(c => /\/budget\/\d+(\?|$)/.test(String(c[0]))).length
    const vorher = ansichtRufe()
    w.vm.handleRealtime({ type: 'budget.updated' })
    await flushPromises()
    expect(ansichtRufe()).toBeGreaterThan(vorher)
  })

  // F93: ein fremdes Realtime-Ereignis löst KEINE Neuladung aus.
  it('ignoriert fremde Realtime-Ereignisse', async () => {
    const w = await mitAnsicht()
    const ansichtRufe = () => axios.get.mock.calls.filter(c => /\/budget\/\d+(\?|$)/.test(String(c[0]))).length
    const vorher = ansichtRufe()
    w.vm.handleRealtime({ type: 'geschaeft.updated' })
    await flushPromises()
    expect(ansichtRufe()).toBe(vorher)
  })

  // F100: alle Pauschalanträge stehen einheitlich in EINEM Kasten «Pauschalanträge»;
  // den früheren Automatik-Block (Switch «Defizit automatisch verteilen», Knopf
  // «Defizit verteilen») gibt es nicht mehr, die Liste startet leer.
  it('führt alle Pauschalanträge einheitlich in einem Kasten «Pauschalanträge» zusammen', async () => {
    const w = await mitAnsicht()
    const kasten = w.find('.pw-pauschalantraege')
    expect(kasten.exists(), 'Pauschalanträge-Kasten fehlt').toBe(true)
    expect(kasten.find('h3').text()).toBe('Pauschalanträge')
    expect(kasten.find('.pw-pauschal-automatik').exists(), 'alter Automatik-Block noch da').toBe(false)
    const html = w.html()
    expect(html).not.toContain('Defizit automatisch als Pauschalkürzung verteilen')
    expect(html).not.toContain('Defizit verteilen')
  })

  // F89: Header-Link zum Budget-Geschäft (Weisung) auf der Parlamentswebseite.
  it('zeigt einen Link zur Weisung (Budget-Geschäft), wenn vorhanden', async () => {
    const w = await mitAnsicht()
    // Fixture ohne Weisungsquelle → kein Link.
    expect(w.vm.weisungQuelle).toBe('')
    expect(w.find('a.pw-weisung-link').exists()).toBe(false)
    // Server liefert Link + Nummer → Link erscheint.
    w.vm.ansicht.jahr = { ...w.vm.ansicht.jahr, weisungQuelle: 'https://parlament.winterthur.ch/_rte/information/2572570', weisungNummer: '2025.110' }
    await w.vm.$nextTick()
    const link = w.find('a.pw-weisung-link')
    expect(link.exists(), 'Weisungs-Link fehlt').toBe(true)
    expect(link.attributes('href')).toContain('2572570')
    expect(link.attributes('target')).toBe('_blank')
    expect(link.text()).toContain('2025.110')
  })

  // Punkt 2: der «Novemberbrief einlesen»-Knopf erscheint nur bei tatsächlicher
  // Verfügbarkeit (vom Server bestimmt) — im August fehlt er.
  it('zeigt «Novemberbrief einlesen» nur bei tatsächlicher Verfügbarkeit', async () => {
    const w = await mitAnsicht()
    // Fixture ohne novemberbriefVerfuegbar → Knopf fehlt (auch wenn nicht importiert).
    expect(w.vm.ansicht.jahr.novemberbriefImportiert).toBe(false)
    expect(w.vm.novemberbriefMoeglich).toBe(false)
    // Server meldet einen verfügbaren Brief → Knopf erscheint.
    w.vm.ansicht.jahr = { ...w.vm.ansicht.jahr, novemberbriefVerfuegbar: true }
    await w.vm.$nextTick()
    expect(w.vm.novemberbriefMoeglich).toBe(true)
  })

  // Punkt 5: die automatische Steuerfusssenkung ist ein echter Antrag (quelle
  // «pauschal»); er bestimmt den effektiven Steuerfuss und lässt die Automatik an.
  it('liest den effektiven Steuerfuss aus dem automatischen Senkungs-Antrag', async () => {
    const w = await mitAnsicht()
    w.vm.ansicht.antraege = [
      ...w.vm.ansicht.antraege,
      { id: 99, bereich: 'steuerfuss', quelle: 'pauschal', prozentDelta: -5, betragDelta: -10000000, phase: 'fraktion' },
    ]
    await w.vm.$nextTick()
    expect(w.vm.steuerfussAntragAuto.id).toBe(99)
    expect(w.vm.steuerfussAntrag, 'der automatische Antrag ist kein manueller').toBe(null)
    expect(w.vm.steuerfussAutomatik, 'Automatik bleibt aktiv').toBe(true)
    expect(w.vm.steuerfussEffektiv).toBe(120) // 125 − 5 Prozentpunkte
  })

  // Regressionsfall zum −234%-Bug: ohne Steuerfuss-Antrag darf der effektive
  // Steuerfuss NICHT mehr aus dem Überschuss hochgerechnet werden (der alte Code
  // rechnete 125 − ⌊Überschuss/WertProProzent⌋ ohne Deckelung → negativ).
  it('rechnet den effektiven Steuerfuss nicht mehr aus dem Überschuss (kein negativer Wert)', async () => {
    const w = await mitAnsicht()
    w.vm.ansicht.summen = { ...w.vm.ansicht.summen, ergebnis: 1500000000 } // grosser Überschuss
    w.vm.ansicht.antraege = w.vm.ansicht.antraege.filter(a => a.bereich !== 'steuerfuss')
    await w.vm.$nextTick()
    expect(w.vm.steuerfussEffektiv, 'ohne Antrag bleibt der geltende Steuerfuss stehen').toBe(125)
  })

  // F88: der Steuerfuss-Tab zeigt den beantragten Steuerfuss und die Differenz zum Vorjahr.
  it('zeigt den beantragten Steuerfuss und die Differenz zum Vorjahr', async () => {
    axios.get.mockImplementation((url) => {
      const u = String(url)
      if (u.includes('/budget/verfuegbar')) { return Promise.resolve({ data: { verfuegbar: [], importierbar: [] } }) }
      if (u.includes('/budget/jahre')) { return Promise.resolve({ data: [{ jahr: 2026 }] }) }
      const a = ansichtFixture()
      a.jahr = { ...a.jahr, steuerfuss: 125, steuerfussVorjahr: 122 }
      return Promise.resolve({ data: a })
    })
    const w = await mitAnsicht()
    w.vm.aktiverTab = 'steuerfuss'
    await w.vm.$nextTick()
    expect(w.vm.steuerfussVorjahr).toBe(122)
    expect(w.vm.steuerfussDiffVorjahr).toBe('+3%')
    const html = w.html()
    expect(html).toContain('Stadtratsantrag Steuerfuss')
    expect(html).toContain('Differenz zum Vorjahr')
  })
})
