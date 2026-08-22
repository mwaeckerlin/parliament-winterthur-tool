import { describe, it, expect, vi, beforeEach } from 'vitest'
import { shallowMount, flushPromises } from '@vue/test-utils'
import Budgetliste from '../components/Budgetliste.vue'
import App from '../App.vue'
import axios from '@nextcloud/axios'

vi.mock('@nextcloud/auth', () => ({ getCurrentUser: () => ({ uid: 'u', displayName: 'U' }) }))
vi.mock('../realtime', () => ({ subscribeRealtime: () => () => {} }))

// Beispiel-Ansicht in der Struktur der Budget-API (BudgetService::ansicht).
function ansichtFixture() {
  return {
    jahr: { jahr: 2026, steuerfuss: 125, steuerertrag: 250000000, personalsteuer: 24, novemberbriefImportiert: false },
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
    verteilung: { automatikEin: true, zielModus: 'schwarze_null', zielBetrag: 0 },
    summen: { ausgaben: 8428985, einnahmen: 6562862, ergebnis: -1866123, stellen: 19.5, ausgabenDiff: 500000, einnahmenDiff: 100000, ergebnisDiff: -400000, stellenDiff: 2.65 },
    standardBetragProStelle: 200000,
    kommissionZuordnung: [
      { departement: 'Präsidiales', kommission: 'SK Präsidiales' },
      { departement: 'Finanzen', kommission: 'SK Finanzen' },
    ],
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

  // F80
  it('listet je Produktegruppe den Globalkredit und die Produkte mit Nettokosten', async () => {
    const w = await mitAnsicht()
    const g = w.vm.gruppenNachDepartement[0].gruppen[0]
    expect(g.globalkredit.soll).toBe(4866124)
    expect(g.produkte[0].name).toBe('Personalrecht')
    // Produkte als Information: Name UND Nettokosten (Soll) werden gerendert.
    const html = w.html()
    expect(html).toContain('Personalrecht')
    expect(html).toContain(w.vm.fr(1135916))
  })

  // F80: Auftragstext als Information je Produktegruppe
  it('zeigt den Auftragstext der Produktegruppe als Information', async () => {
    const w = await mitAnsicht()
    expect(w.html()).toContain('Das Personalamt bearbeitet die personalrechtlichen Fragen.')
  })

  // F80: alle Erläuterungen/Begründungen aus dem Buch werden angezeigt
  it('zeigt Erläuterungen und Begründungen der Produktegruppe', async () => {
    const w = await mitAnsicht()
    const html = w.html()
    expect(html).toContain('Differenz von + 2.65 Stellen')
    expect(html).toContain('Schwerpunkt Projekt WIN HR')
    expect(html).toContain('Personalbefragung und Digitalisierung')
    expect(html).toContain('Begründung FAP')
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

  // F83 + F84 + F85
  it('setzt die Pauschalverteilung mit Automatik und Zielmodus', async () => {
    const w = await mitAnsicht()
    await w.vm.verteilungAendern('zielModus', 'defizit')
    const call = axios.put.mock.calls.find(c => String(c[0]).includes('/budget/2026/verteilung'))
    expect(call[1].zielModus).toBe('defizit')
    expect(call[1].automatikEin).toBe(true)
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

  // F88
  it('leitet den Steuerprozent-Wert ab und stellt einen Steuerfuss-Antrag', async () => {
    const w = await mitAnsicht()
    expect(w.vm.wertProProzent).toBe(2000000) // 250M / 125
    w.vm.steuerfussManuell = '123'
    await w.vm.steuerfussAntragStellen()
    const call = axios.post.mock.calls.find(c => c[1] && c[1].bereich === 'steuerfuss')
    expect(call[1].betragDelta).toBe(-4000000) // (123-125) * 2M
  })

  // F88: die Automatik muss ausdrücklich abgeschaltet werden, dann erscheint das manuelle Feld
  it('schaltet die Steuerfuss-Automatik ab und gibt die manuelle Bearbeitung frei', async () => {
    const w = await mitAnsicht()
    expect(w.vm.steuerfussAutomatik, 'ohne Antrag ist die Automatik ein').toBe(true)
    w.vm.steuerfussAutomatikUmschalten(false)
    expect(w.vm.steuerfussAutomatik, 'nach Abschalten ist die Automatik aus (manuelles Feld frei)').toBe(false)
    w.vm.steuerfussAutomatikUmschalten(true)
    expect(w.vm.steuerfussAutomatik, 'wieder eingeschaltet').toBe(true)
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
})
