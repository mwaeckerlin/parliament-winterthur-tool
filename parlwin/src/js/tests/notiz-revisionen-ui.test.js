import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import PwWysiwyg from '../components/PwWysiwyg.vue'

async function mountEditor(props = {}) {
  const wrapper = mount(PwWysiwyg, { props: { modelValue: 'Aktuell', ...props } })
  await new Promise((r) => setTimeout(r, 0))
  await wrapper.vm.$nextTick()
  return wrapper
}

const REVISIONEN = [
  { id: 1, text: 'Version 1', autorName: 'Test', erstelltAm: '2026-07-14T09:00:00+00:00' },
  { id: 2, text: 'Version 2', autorName: 'Test', erstelltAm: '2026-07-14T10:00:00+00:00' },
]

// Beim Bearbeiten entsteht eine Versions-History. Durch sie lässt sich
// zurück- und vorblättern; ein Doppelpfeil springt zur neuesten Fassung.
describe('PwWysiwyg — Revisions-Navigation', () => {
  it('zeigt keine Navigation, wenn es keine älteren Versionen gibt', async () => {
    const wrapper = await mountEditor({ revisionen: [] })
    expect(wrapper.find('button[title="Eine Version zurück"]').exists()).toBe(false)
    wrapper.unmount()
  })

  it('zeigt den Zurück-Pfeil, sobald ältere Versionen existieren', async () => {
    const wrapper = await mountEditor({ revisionen: REVISIONEN })
    expect(wrapper.find('button[title="Eine Version zurück"]').exists()).toBe(true)
    wrapper.unmount()
  })

  it('blättert mit ← eine Version zurück und zeigt deren Text', async () => {
    const wrapper = await mountEditor({ revisionen: REVISIONEN })
    await wrapper.find('button[title="Eine Version zurück"]').trigger('click')
    expect(wrapper.vm.editor.storage.markdown.getMarkdown()).toContain('Version 2')
    wrapper.unmount()
  })

  it('blättert mit → wieder vorwärts', async () => {
    const wrapper = await mountEditor({ revisionen: REVISIONEN })
    await wrapper.find('button[title="Eine Version zurück"]').trigger('click')
    await wrapper.find('button[title="Eine Version zurück"]').trigger('click')
    await wrapper.find('button[title="Eine Version vorwärts"]').trigger('click')
    expect(wrapper.vm.editor.storage.markdown.getMarkdown()).toContain('Version 2')
    wrapper.unmount()
  })

  it('springt mit dem Doppelpfeil zur neuesten Fassung zurück', async () => {
    const wrapper = await mountEditor({ revisionen: REVISIONEN })
    await wrapper.find('button[title="Eine Version zurück"]').trigger('click')
    expect(wrapper.find('button[title="Zur neuesten Version"]').exists()).toBe(true)
    await wrapper.find('button[title="Zur neuesten Version"]').trigger('click')
    expect(wrapper.vm.editor.storage.markdown.getMarkdown()).toContain('Aktuell')
    wrapper.unmount()
  })

  it('zeigt auf der neuesten Fassung weder Vorwärts- noch Doppelpfeil', async () => {
    const wrapper = await mountEditor({ revisionen: REVISIONEN })
    expect(wrapper.find('button[title="Eine Version vorwärts"]').exists()).toBe(false)
    expect(wrapper.find('button[title="Zur neuesten Version"]').exists()).toBe(false)
    wrapper.unmount()
  })

  it('ist beim Blättern in alten Versionen nicht bearbeitbar', async () => {
    const wrapper = await mountEditor({ revisionen: REVISIONEN })
    await wrapper.find('button[title="Eine Version zurück"]').trigger('click')
    expect(wrapper.vm.editor.isEditable).toBe(false)
    wrapper.unmount()
  })

  // Gespeichert/angezeigt wird beim » der tatsächliche Arbeitsstand, nicht die
  // zuletzt gespeicherte Fassung: der Nutzer bearbeitet, blättert zurück und mit »
  // muss sein Arbeitsstand zurückkommen.
  it('kehrt mit » zum bearbeiteten Arbeitsstand zurück, nicht zur gespeicherten Fassung', async () => {
    const wrapper = await mountEditor({ revisionen: REVISIONEN }) // modelValue = 'Aktuell'
    wrapper.vm.editor.commands.setContent('Mein Arbeitsstand', true)
    await wrapper.vm.$nextTick()
    await wrapper.find('button[title="Eine Version zurück"]').trigger('click')
    await wrapper.find('button[title="Zur neuesten Version"]').trigger('click')
    expect(wrapper.vm.editor.storage.markdown.getMarkdown()).toContain('Mein Arbeitsstand')
    expect(wrapper.vm.editor.storage.markdown.getMarkdown()).not.toContain('Aktuell')
    wrapper.unmount()
  })

  // Der Back-Pfeil setzt den Editor read-only (setEditable(false)); der dadurch
  // ausgelöste programmatische Fokus-Verlust darf beim Blättern NICHT speichern.
  it('löst beim Blättern keinen Blur (kein Speichern) aus', async () => {
    const wrapper = await mountEditor({ revisionen: REVISIONEN })
    await wrapper.find('button[title="Eine Version zurück"]').trigger('click')
    // Der programmatische Fokus-Verlust kommt ohne relatedTarget.
    wrapper.vm.handleBlur({ relatedTarget: null })
    expect(wrapper.emitted('blur')).toBeFalsy()
    wrapper.unmount()
  })

  it('löst ausserhalb des Blätterns bei echtem Fokus-Verlust einen Blur aus', async () => {
    const wrapper = await mountEditor({ revisionen: REVISIONEN })
    wrapper.vm.handleBlur({ relatedTarget: null })
    expect(wrapper.emitted('blur')).toBeTruthy()
    wrapper.unmount()
  })

  it('meldet über «versionAngezeigt» den angezeigten Verlaufsstand (Text der alten Fassung bzw. null)', async () => {
    const wrapper = await mountEditor({ revisionen: REVISIONEN })
    await wrapper.find('button[title="Eine Version zurück"]').trigger('click')
    // Jüngste archivierte Fassung = letzte im Array.
    const zurueck = wrapper.emitted('versionAngezeigt')
    expect(zurueck.at(-1)[0]).toBe('Version 2')
    await wrapper.find('button[title="Zur neuesten Version"]').trigger('click')
    const neueste = wrapper.emitted('versionAngezeigt')
    expect(neueste.at(-1)[0]).toBe(null)
    wrapper.unmount()
  })
})
