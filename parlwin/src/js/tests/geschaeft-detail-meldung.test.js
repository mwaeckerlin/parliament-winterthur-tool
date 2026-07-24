import { describe, it, expect, vi, beforeEach } from 'vitest'
import { shallowMount } from '@vue/test-utils'
import GeschaeftDetail from '../components/GeschaeftDetail.vue'
import NotizenListe from '../components/NotizenListe.vue'

vi.mock('../realtime', () => ({
  subscribeRealtime: () => vi.fn(),
}))

vi.mock('@nextcloud/axios', () => ({
  default: {
    get: vi.fn(() => Promise.resolve({ data: { aktionen: [], zustaendigkeiten: [] } })),
    post: vi.fn(() => Promise.resolve({ data: { id: 99, aktionTyp: 'notiz', text: 'Test', titel: '', autorUid: 'testuser', geloescht: false } })),
    put: vi.fn(() => Promise.resolve({ data: { id: 99, aktionTyp: 'notiz', text: 'Test', titel: '', autorUid: 'testuser', geloescht: false } })),
    delete: vi.fn(() => Promise.resolve({ data: null })),
  },
}))

vi.mock('@nextcloud/dialogs', () => ({
  showSuccess: vi.fn(),
  showError: vi.fn(),
  showWarning: vi.fn(),
}))

import axios from '@nextcloud/axios'
import { showSuccess, showError } from '@nextcloud/dialogs'

const mockGeschaeft = {
  id: 1,
  nummer: '2024/001',
  titel: 'Testgeschäft',
  typ: 'Motion',
  status: 'pendent',
  datum: '2024-01-01',
  aktionen: [],
  fraktionsstatus: null,
  fraktionssitzung: null,
  zustaendig: [],
}

function mountDetail() {
  return shallowMount(GeschaeftDetail, {
    props: { geschaeftId: 1, mitglieder: [], traktandumKontext: null },
    data() {
      return { geschaeft: mockGeschaeft, laden: false }
    },
    global: {
      stubs: { NcSelect: true, PwMultiSelect: true, GeschaeftDokumente: true, NotizenListe: true },
    },
  })
}

// Öffnet in der geteilten NotizenListe einen neuen Notiz-Editor mit Text und
// schliesst ihn final ab (Ok/Blur) — die Rückmeldung erscheint als Nextcloud-Toast.
function mountListe() {
  return shallowMount(NotizenListe, {
    props: { basisUrl: 'geschaefte/1', notizen: [], aktuelleUid: 'testuser' },
  })
}

async function neueNotizAbschliessen(wrapper, text = 'Neue Notiz') {
  wrapper.vm.editorOffen = true
  wrapper.vm.editorModus = 'neu'
  wrapper.vm.aktiveNotizId = null
  wrapper.vm.aktiveNotizText = text
  await wrapper.vm.notizAbschliessen()
  await wrapper.vm.$nextTick()
}

// Rückmeldungen gehören in die Nextcloud-Benachrichtigung (Toast), nicht in
// eine eigene Textzeile am Seitenende, die niemand sieht.
describe('Notizen — Rückmeldung über Nextcloud-Benachrichtigung', () => {
  beforeEach(() => { vi.clearAllMocks() })

  it('meldet gespeicherte Notiz über die Nextcloud-Benachrichtigung', async () => {
    const wrapper = mountListe()
    await neueNotizAbschliessen(wrapper)
    expect(showSuccess).toHaveBeenCalled()
    wrapper.unmount()
  })

  it('meldet einen Speicherfehler über die Nextcloud-Benachrichtigung', async () => {
    axios.post.mockRejectedValueOnce(new Error('Serverfehler'))
    const wrapper = mountListe()
    await neueNotizAbschliessen(wrapper)
    expect(showError).toHaveBeenCalled()
    wrapper.unmount()
  })

  it('rendert im Geschäfts-Detail keine eigene Meldungs-Zeile mehr', () => {
    const wrapper = mountDetail()
    expect(wrapper.find('.pw-meldung').exists()).toBe(false)
    wrapper.unmount()
  })
})
