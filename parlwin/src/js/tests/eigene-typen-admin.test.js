import { describe, it, expect, beforeEach } from 'vitest'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, resolve } from 'node:path'
import axios from '@nextcloud/axios'

const adminTemplate = resolve(dirname(fileURLToPath(import.meta.url)), '../../../templates/admin.php')

// Feature: Die Typen, die beim Anlegen eines eigenen Geschäfts zur Auswahl
// stehen, pflegt der Administrator — eine Zeile je Typ, mit Hinzufügen und
// Löschen, gespeichert wird ohne Speichern-Knopf.
describe('Admin: Typen für eigene Geschäfte', () => {
  beforeEach(() => {
    document.body.innerHTML = '<div id="pw-typen-liste"></div><span id="pw-typen-status"></span>'
    axios.get.mockReset()
    axios.post.mockReset()
  })

  it('die Admin-Seite enthält den Typen-Bereich (Liste + Hinzufügen)', () => {
    // Genaue Kennung prüfen: eine umbenannte Kennung enthielte den Namen als
    // Teilstring und würde eine kaputte Seite als heil melden.
    // Boolean statt Textvergleich: sonst druckt der Fehlerfall die ganze Seite.
    const src = readFileSync(adminTemplate, 'utf8')
    expect(src.includes('id="pw-typen-liste"'), 'Liste der Typen fehlt auf der Admin-Seite').toBe(true)
    expect(src.includes('id="pw-typen-hinzufuegen"'), 'Knopf zum Hinzufügen fehlt').toBe(true)
  })

  it('lädt die gepflegten Typen und rendert je eine Zeile mit Löschknopf', async () => {
    axios.get.mockResolvedValue({ data: ['Motion', 'Postulat'] })
    const { ladeEigeneTypen } = await import('../admin')
    await ladeEigeneTypen()

    const zeilen = document.querySelectorAll('#pw-typen-liste .pw-typen-row')
    expect(zeilen).toHaveLength(2)
    expect(zeilen[0].querySelector('input[type="text"]').value).toBe('Motion')
    expect(zeilen[1].querySelector('input[type="text"]').value).toBe('Postulat')
    expect(zeilen[0].querySelector('.pw-typen-delete')).not.toBeNull()
  })

  it('liest die Typen aus den Eingabezeilen — leere Zeilen zählen nicht', async () => {
    axios.get.mockResolvedValue({ data: ['Motion', '', '  Postulat  '] })
    const { ladeEigeneTypen, sammleEigeneTypen } = await import('../admin')
    await ladeEigeneTypen()

    expect(sammleEigeneTypen()).toEqual(['Motion', 'Postulat'])
  })
})
