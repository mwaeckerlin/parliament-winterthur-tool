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

function kuerzeleRendern() {
  const liste = document.getElementById('pw-kuerzel-liste')
  if (!liste) return

  const kuerzelInput = document.getElementById('pw-status-kuerzel-json')
  let kuerzel = {}
  if (kuerzelInput && kuerzelInput.value) {
    try {
      kuerzel = JSON.parse(kuerzelInput.value)
    } catch {
      kuerzel = {}
    }
  }

  liste.innerHTML = ''
  Object.entries(kuerzel).forEach(([suchtext, kuerzel_wert]) => {
    const row = document.createElement('div')
    row.className = 'pw-kuerzel-row'
    row.innerHTML = `
      <input type="text" class="pw-kuerzel-suchtext" value="${escapeHtml(suchtext)}" placeholder="Suchtext" />
      <input type="text" class="pw-kuerzel-wert" value="${escapeHtml(kuerzel_wert)}" placeholder="Kürzel" list="pw-status-kuerzel-liste" />
      <button type="button" class="button pw-kuerzel-delete" title="Löschen">×</button>
    `
    liste.appendChild(row)

    const deleteBtn = row.querySelector('.pw-kuerzel-delete')
    deleteBtn.addEventListener('click', (e) => {
      e.preventDefault()
      row.remove()
      kurzelAutoSpeichern()
    })

    const inputs = row.querySelectorAll('input')
    inputs.forEach(input => {
      input.addEventListener('change', kurzelAutoSpeichern)
      input.addEventListener('blur', kurzelAutoSpeichern)
      input.addEventListener('input', kurzelAutoSpeichern)
    })
  })
}

function kurzelAutoSpeichern() {
  clearTimeout(kurzelSaveTimer)
  showStatusMessage('pw-kuerzel-status', 'Speichern...', false)

  kurzelSaveTimer = setTimeout(() => {
    const liste = document.getElementById('pw-kuerzel-liste')
    if (!liste) return

    const kuerzel = {}
    document.querySelectorAll('#pw-kuerzel-liste .pw-kuerzel-row').forEach(row => {
      const suchtext = row.querySelector('.pw-kuerzel-suchtext').value.trim()
      const wert = row.querySelector('.pw-kuerzel-wert').value.trim()
      if (suchtext && wert) {
        kuerzel[suchtext] = wert
      }
    })

    const kuerzelInput = document.getElementById('pw-status-kuerzel-json')
    if (kuerzelInput) {
      kuerzelInput.value = JSON.stringify(kuerzel)
    }

    axios
      .post(generateUrl('/apps/parlwin/settings/status-kuerzel'), { status_kuerzel: kuerzel })
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
  const liste = document.getElementById('pw-kuerzel-liste')
  if (!liste) return

  const row = document.createElement('div')
  row.className = 'pw-kuerzel-row'
  row.innerHTML = `
    <input type="text" class="pw-kuerzel-suchtext" value="" placeholder="Suchtext" />
    <input type="text" class="pw-kuerzel-wert" value="" placeholder="Kürzel" list="pw-status-kuerzel-liste" />
    <button type="button" class="button pw-kuerzel-delete" title="Löschen">×</button>
  `
  liste.appendChild(row)

  const deleteBtn = row.querySelector('.pw-kuerzel-delete')
  deleteBtn.addEventListener('click', (e) => {
    e.preventDefault()
    row.remove()
    kurzelAutoSpeichern()
  })

  const inputs = row.querySelectorAll('input')
  inputs.forEach(input => {
    input.addEventListener('change', kurzelAutoSpeichern)
    input.addEventListener('blur', kurzelAutoSpeichern)
    input.addEventListener('input', kurzelAutoSpeichern)
  })

  row.querySelector('.pw-kuerzel-suchtext').focus()
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
          if (g.status_kurz && !seen.has(g.status_kurz)) {
            seen.add(g.status_kurz)
            const option = document.createElement('option')
            option.value = g.status_kurz
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

  // Verstecke den manuellen Speichern-Button (auto-save übernimmt es)
  const speichernBtn = document.getElementById('pw-kuerzel-speichern')
  if (speichernBtn) {
    speichernBtn.style.display = 'none'
  }

  // Ordner + Kalender manuell anlegen
  const fraktionsraumBtn = document.getElementById('pw-fraktionsraum-sicherstellen')
  if (fraktionsraumBtn) {
    fraktionsraumBtn.addEventListener('click', (e) => {
      e.preventDefault()
      showStatusMessage('pw-fraktionsraum-status', 'Wird angelegt...', false)
      axios
        .post(generateUrl('/apps/parlwin/sitzungstypen/fraktionsraum-sicherstellen'))
        .then(() => {
          showStatusMessage('pw-fraktionsraum-status', 'Fertig', false)
        })
        .catch((err) => {
          console.error('Fehler beim Anlegen der Fraktionsinfrastruktur:', err)
          showStatusMessage('pw-fraktionsraum-status', `Fehler: ${err.response?.data?.fehler || err.message}`, true)
        })
    })
  }
})
