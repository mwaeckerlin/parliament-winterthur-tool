import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import PwWysiwyg from '../components/PwWysiwyg.vue'

// Feature: Notizen werden intern als Markdown gespeichert, nicht als HTML.
// Der Editor muss Markdown als modelValue interpretieren (rendern) und beim
// Editieren wieder Markdown emittieren.
async function mountEditor(modelValue) {
  const wrapper = mount(PwWysiwyg, { props: { modelValue } })
  // Editor wird in mounted() erzeugt
  await new Promise((r) => setTimeout(r, 0))
  await wrapper.vm.$nextTick()
  return wrapper
}

describe('PwWysiwyg arbeitet intern mit Markdown', () => {
  it('rendert Markdown-Eingabe als formatiertes HTML', async () => {
    const wrapper = await mountEditor('**fett** und *kursiv*')
    const html = wrapper.vm.editor.getHTML()
    expect(html).toContain('<strong>fett</strong>')
    expect(html).toContain('<em>kursiv</em>')
    wrapper.unmount()
  })

  it('emittiert Markdown statt HTML beim Setzen von formatiertem Inhalt', async () => {
    const wrapper = await mountEditor('')
    wrapper.vm.editor.commands.setContent('<p><strong>Hallo</strong></p>')
    await wrapper.vm.$nextTick()
    const emitted = wrapper.emitted('update:modelValue')
    expect(emitted, 'update:modelValue wurde nicht emittiert').toBeTruthy()
    const letzter = emitted[emitted.length - 1][0]
    expect(letzter).toContain('**Hallo**')
    expect(letzter).not.toContain('<strong>')
    wrapper.unmount()
  })
})
