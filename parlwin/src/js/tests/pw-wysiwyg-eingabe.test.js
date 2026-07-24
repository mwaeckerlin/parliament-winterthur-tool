import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import PwWysiwyg from '../components/PwWysiwyg.vue'

async function mountEditor(modelValue = '') {
  const wrapper = mount(PwWysiwyg, { props: { modelValue } })
  // Editor wird in mounted() erzeugt
  await new Promise((r) => setTimeout(r, 0))
  await wrapper.vm.$nextTick()
  return wrapper
}

// Die Eingabefläche soll direkt in der Komponente liegen — ohne zusätzlichen
// Container, der Grösse und Scrollverhalten verfälscht.
describe('PwWysiwyg — minimalistische Struktur', () => {
  it('rendert die Eingabefläche ohne zusätzlichen Container', async () => {
    const wrapper = await mountEditor('')
    expect(wrapper.find('.pw-wysiwyg__body').exists()).toBe(false)
    wrapper.unmount()
  })

  it('hängt die Eingabefläche direkt unter die Wurzel', async () => {
    const wrapper = await mountEditor('')
    const wurzel = wrapper.find('.pw-wysiwyg').element
    const editor = wrapper.find('.pw-wysiwyg__editor').element
    expect(editor.parentElement).toBe(wurzel)
    wrapper.unmount()
  })
})

// Ein Klick auf einen Toolbar-Knopf darf den Editor-Fokus nicht verlieren:
// sonst feuert blur, die Notiz wird vorzeitig gespeichert und der Text gelöscht.
describe('PwWysiwyg — Toolbar löst keinen Fokus-Verlust aus', () => {
  it('verhindert den Fokus-Verlust beim Klick auf jeden Toolbar-Knopf', async () => {
    const wrapper = await mountEditor('Mein Text')
    const knoepfe = wrapper.findAll('button.pw-wysiwyg__btn')
    expect(knoepfe.length).toBeGreaterThan(0)
    for (const knopf of knoepfe) {
      const ev = new MouseEvent('mousedown', { bubbles: true, cancelable: true })
      knopf.element.dispatchEvent(ev)
      expect(
        ev.defaultPrevented,
        `mousedown auf "${knopf.attributes('title')}" muss den Fokus-Verlust verhindern`,
      ).toBe(true)
    }
    wrapper.unmount()
  })

  it('emittiert kein blur, wenn der Fokus in die eigene Toolbar wandert', async () => {
    const wrapper = await mountEditor('Mein Text')
    const knopf = wrapper.find('button.pw-wysiwyg__btn').element
    wrapper.vm.editor.options.onBlur({ editor: wrapper.vm.editor, event: { relatedTarget: knopf } })
    await wrapper.vm.$nextTick()
    expect(wrapper.emitted('blur')).toBeFalsy()
    wrapper.unmount()
  })

  it('emittiert blur, wenn der Fokus die Komponente wirklich verlässt', async () => {
    const wrapper = await mountEditor('Mein Text')
    const aussen = document.createElement('button')
    document.body.appendChild(aussen)
    wrapper.vm.editor.options.onBlur({ editor: wrapper.vm.editor, event: { relatedTarget: aussen } })
    await wrapper.vm.$nextTick()
    expect(wrapper.emitted('blur')).toBeTruthy()
    aussen.remove()
    wrapper.unmount()
  })
})
