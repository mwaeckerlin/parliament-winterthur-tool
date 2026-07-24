import { describe, it, expect } from 'vitest'
import { readFileSync } from 'fs'
import { mount } from '@vue/test-utils'
import Aktionszeitleiste from '../components/Aktionszeitleiste.vue'

// vitest läuft im Projekt-Root
const geschaeftSrc = readFileSync('parlwin/src/js/components/GeschaeftDetail.vue', 'utf8')
const vorstossSrc = readFileSync('parlwin/src/js/components/Vorstoesseliste.vue', 'utf8')

// Die Aktionszeitleiste bei Geschäften UND Vorstössen nutzt dieselbe Komponente
// (Aktionszeitleiste) — es darf keine zweite Zeitleisten-Implementierung geben.
describe('Aktionszeitleiste — eine geteilte Komponente für Geschäft und Vorstoss', () => {
  it('GeschaeftDetail importiert und verwendet Aktionszeitleiste', () => {
    expect(geschaeftSrc).toMatch(/import\s+Aktionszeitleiste\s+from\s+['"]\.\/Aktionszeitleiste\.vue['"]/)
    expect(geschaeftSrc).toMatch(/<Aktionszeitleiste/)
  })

  it('Vorstoesseliste importiert und verwendet Aktionszeitleiste', () => {
    expect(vorstossSrc).toMatch(/import\s+Aktionszeitleiste\s+from\s+['"]\.\/Aktionszeitleiste\.vue['"]/)
    expect(vorstossSrc).toMatch(/<Aktionszeitleiste/)
  })

  it('GeschaeftDetail enthält keinen eigenen Zeitleisten-Code mehr', () => {
    expect(geschaeftSrc).not.toContain('zeitleisteEintraege')
    expect(geschaeftSrc).not.toContain('tlDrop')
    expect(geschaeftSrc).not.toContain('zeitleisteReihenfolge')
  })

  it('rendert den Text einer Beschluss-Aktion', () => {
    const beschluss = {
      id: 9, aktionTyp: 'beschluss', titel: 'Zustimmen', text: 'Mehrheitlich',
      aktionCode: 'unterstuetzen', autorUid: 'testuser', autorName: 'Test User',
      erstelltAm: '2026-07-14T11:00:00+00:00', entscheidGueltig: true, geloescht: false,
    }
    const wrapper = mount(Aktionszeitleiste, { props: { aktionen: [beschluss] } })
    expect(wrapper.text()).toContain('Zustimmen')
    expect(wrapper.text()).toContain('Mehrheitlich')
    expect(wrapper.text()).not.toContain('Noch keine Aktionen vorhanden.')
  })

  it('zeigt bei leeren Aktionen den Hinweis «Noch keine Aktionen vorhanden.»', () => {
    const wrapper = mount(Aktionszeitleiste, { props: { aktionen: [] } })
    expect(wrapper.text()).toContain('Noch keine Aktionen vorhanden.')
  })
})
