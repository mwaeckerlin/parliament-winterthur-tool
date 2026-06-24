export function vollerName(m) {
  return `${m.vorname || ''} ${m.name || ''}`.trim()
}

export function personKey(m) {
  const externId = m.externId || m.extern_id || ''
  if (externId) return `mitglied:${externId}`
  return `name:${vollerName(m)}`
}

export function parseNotizen(raw) {
  if (!raw) return []
  try {
    const arr = typeof raw === 'string' ? JSON.parse(raw) : raw
    return Array.isArray(arr) ? arr : []
  } catch {
    return []
  }
}

import MarkdownIt from 'markdown-it'
import DOMPurify from 'dompurify'

// Notizen werden intern als Markdown gespeichert (siehe PwWysiwyg). Für die
// Anzeige wird mit demselben Parser (markdown-it) nach HTML gerendert und
// anschliessend gesäubert (XSS-Schutz vor v-html).
const markdownRenderer = new MarkdownIt({ html: false, linkify: true, breaks: true })

export function markdownZuHtml(text) {
  if (!text) return ''
  return DOMPurify.sanitize(markdownRenderer.render(String(text)))
}
