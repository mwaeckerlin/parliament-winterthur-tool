import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import BudgetPauschalForm from '../components/BudgetPauschalForm.vue'
import PwLoeschen from '../components/PwLoeschen.vue'
import PwMultiSelect from '../components/PwMultiSelect.vue'

// F100/F101: ein einzelner Pauschalantrag — Einsparung in CHF oder Prozent
// (immer negativ), Einreichen-Entscheid, ausgenommene Produktegruppen.
function mounted(pauschal, extra = {}) {
  return mount(BudgetPauschalForm, {
    props: {
      pauschal,
      produktegruppen: [{ code: '121', name: 'Personalamt' }, { code: '157', name: 'Subventionsverträge' }],
      fraktionOptionen: [{ value: 'GLP', label: 'GLP' }, { value: 'SP', label: 'SP' }],
      eigeneFraktion: 'GLP',
      ...extra,
    },
    global: { stubs: { NcButton: true, NcSelect: true, NcTextField: true, NcCheckboxRadioSwitch: true, PwMultiSelect: true } },
  })
}

describe('BudgetPauschalForm', () => {
  it('meldet eine CHF-Einsparung als negativen Betrag (F100)', () => {
    const w = mounted({ id: 1, betrag: 0, prozent: 0, haltung: 'einreichen', ausnahmen: [] })
    w.vm.betragMag = '500000'
    w.vm.speichern()
    expect(w.emitted().save[0][0]).toMatchObject({ betrag: -500000, prozent: 0 })
  })

  it('meldet eine Prozent-Einsparung mit Vorrang und negativem Vorzeichen (F100)', () => {
    const w = mounted({ id: 1, betrag: 0, prozent: 0, haltung: 'einreichen', ausnahmen: [] })
    w.vm.prozentMag = '10'
    w.vm.speichern()
    expect(w.emitted().save[0][0]).toMatchObject({ prozent: -10, betrag: 0 })
  })

  it('übernimmt den Einreichen-Entscheid und die Ausnahmen (F100/F101)', () => {
    const w = mounted({ id: 1, betrag: -100, prozent: 0, haltung: 'einreichen', ausnahmen: ['121'] })
    w.vm.einreichen = false
    w.vm.speichern()
    const f = w.emitted().save[0][0]
    expect(f.haltung).toBe('nicht_einreichen')
    expect(f.ausnahmen).toContain('121')
  })

  it('belegt den Antragsteller mit der eigenen Fraktion vor und meldet ihn (F94)', () => {
    const w = mounted({ id: 1, betrag: -100, prozent: 0, haltung: 'einreichen', ausnahmen: [] })
    expect(w.vm.antragsteller).toBe('GLP')
    w.vm.speichern()
    expect(w.emitted().save[0][0].antragsteller).toBe('GLP')
  })

  it('übernimmt einen abweichend gewählten Antragsteller (F94)', () => {
    const w = mounted({ id: 1, betrag: -100, prozent: 0, haltung: 'einreichen', ausnahmen: [], antragsteller: 'SP' })
    expect(w.vm.antragsteller).toBe('SP')
    w.vm.antragsteller = 'GLP'
    w.vm.speichern()
    expect(w.emitted().save[0][0].antragsteller).toBe('GLP')
  })

  // F84/F85: Ziel-Typ «fester Ertrag» meldet einen positiven Zielbetrag, ohne
  // Einsparungs-Betrag/-Prozent.
  it('meldet ein absolutes Ziel «fester Ertrag» mit positivem Zielbetrag', () => {
    const w = mounted({ id: 1, zielTyp: 'einsparungen', betrag: 0, prozent: 0, haltung: 'einreichen', ausnahmen: [] })
    w.vm.zielTyp = 'fester_ertrag'
    w.vm.zielBetragMag = '2000000'
    w.vm.speichern()
    expect(w.emitted().save[0][0]).toMatchObject({ zielModus: 'fester_ertrag', zielBetrag: 2000000, betrag: 0, prozent: 0 })
  })

  // F84/F85: «festes Defizit» meldet einen negativen Zielbetrag.
  it('meldet ein absolutes Ziel «festes Defizit» mit negativem Zielbetrag', () => {
    const w = mounted({ id: 1, zielTyp: 'einsparungen', betrag: 0, prozent: 0, haltung: 'einreichen', ausnahmen: [] })
    w.vm.zielTyp = 'festes_defizit'
    w.vm.zielBetragMag = '300000'
    w.vm.speichern()
    expect(w.emitted().save[0][0]).toMatchObject({ zielModus: 'festes_defizit', zielBetrag: -300000 })
  })

  // Schwarze Null: kein Betrag, kein Zielbetrag.
  it('meldet «schwarze Null» ohne Betrag', () => {
    const w = mounted({ id: 1, zielTyp: 'schwarze_null', betrag: 0, prozent: 0, haltung: 'einreichen', ausnahmen: [] })
    w.vm.speichern()
    expect(w.emitted().save[0][0]).toMatchObject({ zielModus: 'schwarze_null', zielBetrag: 0, betrag: 0, prozent: 0 })
  })

  // Kein «Übernehmen»-Knopf mehr: eine Änderung wird automatisch (entprellt) übernommen.
  it('speichert automatisch nach einer Änderung (entprellt), ohne «Übernehmen»-Knopf', () => {
    vi.useFakeTimers()
    try {
      const w = mounted({ id: 1, betrag: -100, prozent: 0, haltung: 'einreichen', ausnahmen: [] })
      w.vm.betragMag = '250000'
      w.vm.autoSpeichern()
      expect(w.emitted().save).toBeUndefined() // entprellt: noch nicht gemeldet
      vi.advanceTimersByTime(600)
      expect(w.emitted().save[0][0]).toMatchObject({ betrag: -250000 })
    } finally {
      vi.useRealTimers()
    }
  })

  // Bug 2026-08-28 (F101): der Ausnahme-Schalter an der Produktegruppe schreibt die
  // Ausnahmen über den Server und lädt die Ansicht neu — der Editor bekommt sie als
  // neue Prop. Er kopierte sie beim Erzeugen einmal nach data und sah jede spätere
  // Änderung nie: unten aufgenommen, oben blieb die Ausnahme stehen.
  it('zeigt eine von aussen gesetzte Ausnahme im Auswahlfeld an (F101)', async () => {
    const w = mounted({ id: 1, betrag: -100, prozent: 0, haltung: 'einreichen', ausnahmen: ['121'] })
    expect(w.findComponent(PwMultiSelect).props('modelValue').map(o => o.value)).toEqual(['121'])
    await w.setProps({ pauschal: { id: 1, betrag: -100, prozent: 0, haltung: 'einreichen', ausnahmen: ['157'] } })
    expect(w.findComponent(PwMultiSelect).props('modelValue').map(o => o.value)).toEqual(['157'])
  })

  // Der zweite Teil desselben Bugs: der veraltete Stand wurde beim nächsten
  // Speichern zurückgeschrieben und hat die Änderung von unten wieder verworfen.
  it('meldet nach einer Änderung von aussen den neuen Stand (F101)', async () => {
    const w = mounted({ id: 1, betrag: -100, prozent: 0, haltung: 'einreichen', ausnahmen: ['121'] })
    await w.setProps({ pauschal: { id: 1, betrag: -100, prozent: 0, haltung: 'einreichen', ausnahmen: [] } })
    w.vm.speichern()
    expect(w.emitted().save[0][0].ausnahmen).toEqual([])
  })

  // Auch die übrigen Felder ziehen nach (eine parallele Sitzung ändert denselben
  // Pauschalantrag), UND eine noch nicht gespeicherte Eingabe wird dabei nie
  // überschrieben — sonst frisst eine eintreffende Antwort das gerade Getippte.
  it('zieht Betrag und Begründung von aussen nach, verwirft aber keine offene Eingabe (F100)', async () => {
    vi.useFakeTimers()
    try {
      const w = mounted({ id: 1, betrag: -100, prozent: 0, haltung: 'einreichen', ausnahmen: [], begruendung: 'alt' })
      await w.setProps({ pauschal: { id: 1, betrag: -250000, prozent: 0, haltung: 'einreichen', ausnahmen: [], begruendung: 'neu' } })
      expect(w.vm.betragMag).toBe('250000')
      expect(w.vm.begruendung).toBe('neu')
      w.vm.begruendung = 'gerade getippt'
      w.vm.autoSpeichern()
      await w.setProps({ pauschal: { id: 1, betrag: -250000, prozent: 0, haltung: 'einreichen', ausnahmen: [], begruendung: 'neu' } })
      expect(w.vm.begruendung).toBe('gerade getippt')
      vi.advanceTimersByTime(600)
      expect(w.emitted().save[0][0].begruendung).toBe('gerade getippt')
    } finally {
      vi.useRealTimers()
    }
  })

  // Rückfall 2026-08-29 (F101): Nach dem ersten Speichern kam von unten nichts mehr
  // an. Der Vorrang der offenen Eingabe hängt am laufenden Entprell-Timer — der
  // wurde beim Auslösen nie zurückgesetzt und blockierte das Nachziehen für immer.
  it('zieht nach einem abgeschlossenen Speichern wieder von aussen nach (F101)', async () => {
    vi.useFakeTimers()
    try {
      const w = mounted({ id: 1, betrag: -100, prozent: 0, haltung: 'einreichen', ausnahmen: [] })
      w.vm.betragMag = '250000'
      w.vm.autoSpeichern()
      vi.advanceTimersByTime(600)
      expect(w.emitted().save[0][0]).toMatchObject({ betrag: -250000 })
      // Jetzt wird unten an der Produktegruppe eine Ausnahme gesetzt: die Ansicht
      // wird neu geladen, der Editor bekommt den neuen Stand als Prop.
      await w.setProps({ pauschal: { id: 1, betrag: -250000, prozent: 0, haltung: 'einreichen', ausnahmen: ['157'] } })
      expect(w.vm.ausnahmen).toEqual(['157'])
      expect(w.findComponent(PwMultiSelect).props('modelValue').map(o => o.value)).toEqual(['157'])
    } finally {
      vi.useRealTimers()
    }
  })

  // Löschen ist überall der einheitliche ✕-Knopf (PwLoeschen), kein Text/kein Papierkorb.
  it('nutzt für Löschen den einheitlichen ✕-Knopf (PwLoeschen)', () => {
    const w = mounted({ id: 1, betrag: -100, prozent: 0, haltung: 'einreichen', ausnahmen: [] })
    const loeschen = w.findComponent(PwLoeschen)
    expect(loeschen.exists()).toBe(true)
    expect(loeschen.props('label')).toBe('Pauschalantrag löschen')
    loeschen.vm.$emit('click')
    expect(w.emitted().delete).toBeTruthy()
  })
})
