import { describe, it, expect } from 'vitest'
import { readFileSync } from 'fs'
import { mount } from '@vue/test-utils'
import PwWysiwyg from '../components/PwWysiwyg.vue'

// vitest läuft im Projekt-Root
const css = readFileSync('parlwin/src/css/style.scss', 'utf8')

async function mountEditor(modelValue = '') {
  const wrapper = mount(PwWysiwyg, { props: { modelValue } })
  await new Promise((r) => setTimeout(r, 0))
  await wrapper.vm.$nextTick()
  return wrapper
}

// Ohne Absatz-Knopf lässt sich eine Überschrift oder ein Listenpunkt nicht mehr
// zu normalem Fliesstext zurücksetzen — der Knopf gehört in die Werkzeugleiste.
describe('PwWysiwyg — vollständige Werkzeugleiste', () => {
  it('bietet einen Absatz-Knopf zum Zurücksetzen der Formatierung', async () => {
    const wrapper = await mountEditor('')
    const titel = wrapper.findAll('button.pw-wysiwyg__btn').map(b => b.attributes('title'))
    expect(titel, 'Absatz-Knopf fehlt in der Werkzeugleiste').toContain('Absatz')
    wrapper.unmount()
  })

  it('behält die übrigen Formatier-Knöpfe', async () => {
    const wrapper = await mountEditor('')
    const titel = wrapper.findAll('button.pw-wysiwyg__btn').map(b => b.attributes('title'))
    expect(titel.some(t => t?.startsWith('Fett'))).toBe(true)
    expect(titel).toContain('Aufzählung')
    wrapper.unmount()
  })
})

// Die Vorschau im Editor muss exakt so aussehen wie die fertig gerenderte Notiz:
// gleiche Absatzabstände, und Aufzählungen mit sichtbaren Punkten.
describe('Notiz-Darstellung — Vorschau und fertige Notiz teilen dasselbe CSS', () => {
  it('nutzt einen gemeinsamen Selektor für Editor-Inhalt und gerenderte Notiz', () => {
    expect(
      /\.pw-notiz-inhalt[^{]*,[^{]*\.pw-wysiwyg__editor \.ProseMirror|\.pw-wysiwyg__editor \.ProseMirror[^{]*,[^{]*\.pw-notiz-inhalt/.test(css),
      'Es muss eine gemeinsame CSS-Regel für .pw-notiz-inhalt und den Editor-Inhalt geben',
    ).toBe(true)
  })

  it('zeigt Aufzählungen mit Punkten und nummerierte Listen mit Zahlen', () => {
    expect(/list-style:\s*disc/.test(css), 'ul braucht sichtbare Aufzählungspunkte').toBe(true)
    expect(/list-style:\s*decimal/.test(css), 'ol braucht sichtbare Nummern').toBe(true)
  })

  it('gibt Absätzen einen sichtbaren Abstand (nicht margin 0)', () => {
    const block = css.match(/\.pw-notiz-inhalt[\s\S]{0,2000}?\n\}/)
    expect(block, 'Gemeinsamer Inhalts-Block fehlt').toBeTruthy()
    expect(/p\s*\{[^}]*margin[^}]*0\.?\d*em/.test(block[0]) || /p\s*\+\s*p/.test(block[0]))
      .toBe(true)
  })
})
