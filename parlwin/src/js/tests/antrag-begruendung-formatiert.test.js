import { readFileSync } from 'node:fs'
import { describe, it, expect } from 'vitest'

/**
 * Die Begründung eines Antrags ist formatierter Text (F132).
 *
 * Marc am 24.10.2026: «Das Begründungsfeld bei den Anträgen muss die
 * mehrzeilige Komponente mit den Formatierungen sein.»
 *
 * Geprüft wird, dass überall dieselbe geteilte Komponente steht — im
 * Antragsformular, im Pauschalantrag und in der Erfassungstabelle — und dass
 * der formatierte Text auch formatiert angezeigt und gedruckt wird.
 *
 * vitest läuft im Projekt-Root.
 */
const FORMULAR = readFileSync('parlwin/src/js/components/BudgetAntragForm.vue', 'utf8')
const PAUSCHAL = readFileSync('parlwin/src/js/components/BudgetPauschalForm.vue', 'utf8')
const ERFASSEN = readFileSync('parlwin/src/js/components/BudgetAntraegeErfassen.vue', 'utf8')
const LISTE = readFileSync('parlwin/src/js/components/Budgetliste.vue', 'utf8')
const PDF = readFileSync('parlwin/templates/budget_antraege_pdf.php', 'utf8')

/** Der Abschnitt einer Datei, der die Begründung trägt. */
function begruendungsFeld(quelle) {
  const stelle = quelle.indexOf('Begründung')
  return stelle === -1 ? '' : quelle.slice(Math.max(0, stelle - 400), stelle + 400)
}

describe('Begründung eines Antrags', () => {
  it('ist im Antragsformular die geteilte Formatierungs-Komponente', () => {
    expect(FORMULAR, 'das Antragsformular hat ein einzeiliges Feld').toMatch(/<PwWysiwyg[\s\S]{0,200}form\.begruendung/)
    expect(begruendungsFeld(FORMULAR), 'das einzeilige Feld steht noch da').not.toMatch(/NcTextField[^>]*label="Begründung"/)
  })

  it('ist im Pauschalantrag dieselbe Komponente', () => {
    expect(PAUSCHAL).toMatch(/<PwWysiwyg/)
    expect(PAUSCHAL).not.toMatch(/NcTextField v-model="begruendung"/)
  })

  it('ist in der Erfassungstabelle dieselbe Komponente', () => {
    expect(ERFASSEN).toMatch(/<PwWysiwyg[\s\S]{0,200}z\.begruendung/)
  })

  it('wird in jeder Ansicht formatiert dargestellt', () => {
    // Eine Begründung mit Formatierung darf nicht als Markdown-Quelltext
    // dastehen; gesäubert wird sie mit demselben Weg wie eine Notiz.
    const roh = LISTE.match(/\{\{\s*a\.begruendung\s*\}\}/g) || []
    expect(roh, 'die Begründung steht irgendwo als roher Text').toEqual([])
    expect(LISTE).toMatch(/v-html="markdownZuHtml\(a\.begruendung\)"/)
  })

  it('wird auch auf dem Papier formatiert gedruckt', () => {
    expect(PDF).toMatch(/RichText::alsHtml\(\(string\) \(\$a\['begruendung'\]/)
    expect(PDF, 'das PDF druckt den Quelltext der Formatierung').not.toMatch(/p\(\(string\) \(\$a\['begruendung'\]/)
  })
})
