import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'

let kurzelSaveTimer = null
const KURZEL_SAVE_DELAY = 5000

function showStatusMessage(elementId, message, isError = false) {
  const el = document.getElementById(elementId)
  if (!el) return
  el.textContent = message
  el.className = `pw-sync-status ${isError ? 'pw-error' : 'pw-success'}`
  if (!isError) {
    setTimeout(() => {
      if (el.textContent === message) {
        el.textContent = ''
        el.className = 'pw-sync-status'
      }
    }, 2500)
  }
}

// Erstellt eine Eingabezeile (Suchtext + Kürzel + Löschen) mit Auto-Save.
function kuerzelZeileErstellen(suche = '', kuerzel = '') {
  const liste = document.getElementById('pw-kuerzel-liste')
  if (!liste) return null

  const row = document.createElement('div')
  row.className = 'pw-kuerzel-row'
  row.innerHTML = `
    <input type="text" class="pw-kuerzel-suchtext" value="${escapeHtml(suche)}" placeholder="Suchtext" list="pw-status-kuerzel-liste" />
    <input type="text" class="pw-kuerzel-wert" value="${escapeHtml(kuerzel)}" placeholder="Kürzel" />
    <button type="button" class="button pw-kuerzel-delete" title="Löschen">×</button>
  `
  liste.appendChild(row)

  row.querySelector('.pw-kuerzel-delete').addEventListener('click', (e) => {
    e.preventDefault()
    row.remove()
    kurzelAutoSpeichern()
  })
  row.querySelectorAll('input').forEach((input) => {
    input.addEventListener('change', kurzelAutoSpeichern)
    input.addEventListener('blur', kurzelAutoSpeichern)
    input.addEventListener('input', kurzelAutoSpeichern)
  })
  return row
}

// Lädt die gespeicherten Kürzel vom Server (Liste von {suche, kuerzel}) und rendert sie.
function kuerzeleRendern() {
  const liste = document.getElementById('pw-kuerzel-liste')
  if (!liste) return

  axios
    .get(generateUrl('/apps/parlwin/settings/status-kuerzel'))
    .then((response) => {
      const eintraege = Array.isArray(response.data) ? response.data : []
      liste.innerHTML = ''
      eintraege.forEach((e) => kuerzelZeileErstellen(e.suche || '', e.kuerzel || ''))
    })
    .catch((err) => {
      console.error('Fehler beim Laden der Status-Kürzel:', err)
    })
}

function kurzelAutoSpeichern() {
  clearTimeout(kurzelSaveTimer)
  showStatusMessage('pw-kuerzel-status', 'Speichern...', false)

  kurzelSaveTimer = setTimeout(() => {
    const liste = document.getElementById('pw-kuerzel-liste')
    if (!liste) return

    const eintraege = []
    document.querySelectorAll('#pw-kuerzel-liste .pw-kuerzel-row').forEach((row) => {
      const suche = row.querySelector('.pw-kuerzel-suchtext').value.trim()
      const kuerzel = row.querySelector('.pw-kuerzel-wert').value.trim()
      if (suche && kuerzel) {
        eintraege.push({ suche, kuerzel })
      }
    })

    axios
      .post(generateUrl('/apps/parlwin/settings/status-kuerzel'), { status_kuerzel: eintraege })
      .then(() => {
        showStatusMessage('pw-kuerzel-status', 'Gespeichert', false)
      })
      .catch((err) => {
        console.error('Fehler beim Speichern der Kürzel:', err)
        showStatusMessage('pw-kuerzel-status', 'Fehler beim Speichern', true)
      })
  }, KURZEL_SAVE_DELAY)
}

function kuerzleHinzufuegen() {
  const row = kuerzelZeileErstellen('', '')
  if (row) row.querySelector('.pw-kuerzel-suchtext').focus()
}

function ladeStatusWerte() {
  axios
    .get(generateUrl('/apps/parlwin/geschaefte?show_erledigt=1&limit=2000'))
    .then((response) => {
      if (response.data && Array.isArray(response.data)) {
        const datalist = document.getElementById('pw-status-kuerzel-liste')
        if (!datalist) return
        datalist.innerHTML = ''
        const seen = new Set()
        response.data.forEach((g) => {
          if (g.status && !seen.has(g.status)) {
            seen.add(g.status)
            const option = document.createElement('option')
            option.value = g.status
            datalist.appendChild(option)
          }
        })
      }
    })
    .catch((err) => {
      console.error('Fehler beim Laden der Status-Werte:', err)
    })
}

function escapeHtml(text) {
  const div = document.createElement('div')
  div.textContent = text
  return div.innerHTML
}

document.addEventListener('DOMContentLoaded', () => {
  // Status-Kürzel: Initial-Laden und Auto-Save
  kuerzeleRendern()
  ladeStatusWerte()

  const hinzufuegenBtn = document.getElementById('pw-kuerzel-hinzufuegen')
  if (hinzufuegenBtn) {
    hinzufuegenBtn.addEventListener('click', (e) => {
      e.preventDefault()
      kuerzleHinzufuegen()
    })
  }
})
