import { describe, it, expect, vi, beforeEach } from 'vitest'
import { shallowMount, flushPromises } from '@vue/test-utils'
import { BESTAND_SCHLUESSEL } from '../geschaefte-bestand.js'
import { titelAehnlichkeit } from '../utils'
import GeschaeftVerknuepfenDialog from '../components/GeschaeftVerknuepfenDialog.vue'

vi.mock('../utils', async (original) => {
  const echt = await original()
  return { ...echt, titelAehnlichkeit: vi.fn(echt.titelAehnlichkeit) }
})

/**
 * F33, F70, F150: Der Dialog «Mit Geschäft verknüpfen» nimmt die geteilte
 * Liste und zeichnet davon wie die Ansichten höchstens 500 Treffer, die
 * neuesten, geordnet nach der Ähnlichkeit zum Titel. Die Ähnlichkeit wird je
 * Geschäft einmal berechnet.
 */
const BESTAND = Array.from({ length: 1200 }, (_, i) => ({
  id: i + 1,
  nummer: `2020.${i + 1}`,
  titel: i === 3 ? 'Mehr Velowege in der Altstadt' : `Geschäft ${i + 1}`,
  status: 'Erledigt',
  datum: `2020-01-01T00:00:${String(i % 60).padStart(2, '0')}.${String(i).padStart(4, '0')}`,
}))

async function dialog(props = {}) {
  const bestand = { laden: () => Promise.resolve(BESTAND.map((g) => ({ ...g }))) }
  const w = shallowMount(GeschaeftVerknuepfenDialog, {
    props: { titel: 'Velowege', inklusiveErledigt: true, ...props },
    global: { provide: { [BESTAND_SCHLUESSEL]: bestand }, stubs: { teleport: true } },
  })
  await flushPromises()
  return w
}

describe('Dialog «Mit Geschäft verknüpfen» bei einem grossen Bestand', () => {
  beforeEach(() => { titelAehnlichkeit.mockClear() })

  it('zeichnet höchstens die neuesten 500 Geschäfte', async () => {
    const w = await dialog({ titel: 'Ohne Treffer' })
    const eintraege = w.findAll('.pw-verknuepfen-eintrag')
    expect(eintraege, 'der Dialog zeichnet mehr als 500 Geschäfte').toHaveLength(500)
    const neueste = [...BESTAND].sort((a, b) => b.datum.localeCompare(a.datum)).slice(0, 500).map((g) => g.id).sort((a, b) => a - b)
    expect(w.vm.aehnliche.map((g) => g.id).sort((a, b) => a - b)).toEqual(neueste)
  })

  it('berechnet die Ähnlichkeit je Geschäft einmal', async () => {
    const w = await dialog()
    titelAehnlichkeit.mockClear()
    // Ein Tastendruck in der Suche ordnet die Treffer neu.
    w.vm.suche = 'Geschäft'
    await w.vm.$nextTick()
    expect(w.vm.aehnliche.length).toBeGreaterThan(0)
    expect(titelAehnlichkeit.mock.calls.length, 'die Sortierung rechnet die Ähnlichkeit je Vergleich neu').toBeLessThanOrEqual(BESTAND.length)
  })

  it('findet über die Suche auch ein älteres Geschäft und stellt das ähnlichste zuoberst', async () => {
    const w = await dialog()
    w.vm.suche = 'Velowege'
    await w.vm.$nextTick()
    expect(w.vm.aehnliche.map((g) => g.id)).toEqual([4])
    w.vm.suche = ''
    await w.vm.$nextTick()
    expect(w.vm.aehnliche).toHaveLength(500)
  })
})
