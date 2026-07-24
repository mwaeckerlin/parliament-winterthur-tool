import { describe, it, expect, beforeEach } from 'vitest'
import { shallowMount } from '@vue/test-utils'
import GeschaeftDokumente from '../components/GeschaeftDokumente.vue'
import axios from '@nextcloud/axios'

// Beim Erstellen eines neuen Dokuments soll der Dateiname bereits mit dem
// normalisierten Titel (Leerzeichen → _) vorbelegt sein, damit der Nutzer nur
// noch einen Zusatz («-rede») anhängen muss.
describe('GeschaeftDokumente — Dateiname mit Titel vorbelegt', () => {
  beforeEach(() => {
    axios.get.mockReset().mockResolvedValue({ data: [] })
  })

  it('füllt den Dateinamen mit dem normalisierten Titel vor (Leerzeichen und Pfadtrenner → _)', () => {
    const wrapper = shallowMount(GeschaeftDokumente, {
      props: { geschaeftId: 1, geschaeftNummer: '2026.5', titel: 'Mehr Velowege in der Stadt' },
    })
    wrapper.vm.vorlageGewaehlt({ label: 'Word', extension: 'docx' })
    expect(wrapper.vm.neuerName).toBe('Mehr_Velowege_in_der_Stadt')
  })

  it('normalisiert auch mehrfache Leerzeichen und Slashes zu einem Unterstrich', () => {
    const wrapper = shallowMount(GeschaeftDokumente, {
      props: { geschaeftId: 1, geschaeftNummer: '2026.5', titel: '  Bericht 2026/2027  Teil A ' },
    })
    wrapper.vm.vorlageGewaehlt({ label: 'Word', extension: 'docx' })
    expect(wrapper.vm.neuerName).toBe('Bericht_2026_2027_Teil_A')
  })

  it('ohne Titel bleibt der Dateiname leer', () => {
    const wrapper = shallowMount(GeschaeftDokumente, {
      props: { geschaeftId: 1, geschaeftNummer: '2026.5' },
    })
    wrapper.vm.vorlageGewaehlt({ label: 'Word', extension: 'docx' })
    expect(wrapper.vm.neuerName).toBe('')
  })
})
