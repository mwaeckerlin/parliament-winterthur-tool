import { describe, it, expect } from 'vitest'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, resolve } from 'node:path'

const runnerPath = resolve(dirname(fileURLToPath(import.meta.url)), '../../../../tests/e2e/run-compose-e2e.sh')
const gesamtlaufPath = resolve(dirname(fileURLToPath(import.meta.url)), '../../../../tests/run-all.sh')

// Bug: Der Playwright-JUnit-Report lag fest unter tests/e2e/.junit im Repo.
// Eine root-owned Altlast dort (Report eines Laufs vor der docker-cp-Umstellung)
// liess `rm -rf` scheitern und brach den GESAMTEN e2e-Lauf ab, bevor ein einziger
// Browser-Test lief. Der Report muss in das frische, pro Lauf erzeugte TEMP_DIR —
// dann kann kein Alt-Report den Lauf vortäuschen und keine Altlast ihn blockieren.
describe('e2e-Runner: Playwright-Report liegt im frischen TEMP_DIR', () => {
  const skript = readFileSync(runnerPath, 'utf8')

  it('PW_REPORT zeigt ins TEMP_DIR, nicht in die Arbeitskopie', () => {
    const zeile = skript.match(/^PW_REPORT=.*$/m)
    expect(zeile, 'PW_REPORT-Zuweisung fehlt').toBeTruthy()
    expect(zeile[0]).toContain('${TEMP_DIR}')
    expect(zeile[0]).not.toContain('tests/e2e/.junit')
  })

  it('kein rm auf ein Repo-Verzeichnis für den Report', () => {
    expect(skript).not.toMatch(/rm -rf "\$\(dirname "\$PW_REPORT"\)"/)
  })

  // Bug: `docker compose run playwright` baut das Image NICHT neu — geänderte
  // Testdateien (COPY im Dockerfile) erreichten den Lauf nie, der Lauf testete
  // still den alten Stand. Das Image muss vor jedem Lauf gebaut werden.
  it('baut das Playwright-Image vor dem Lauf (aktuelle Testdateien)', () => {
    expect(skript).toMatch(/docker compose run [^\n]*--build[^\n]*playwright/)
  })

  it('reicht den Report dieses Laufs an den Aufrufer weiter (PW_JUNIT_OUT)', () => {
    expect(skript).toContain('PW_JUNIT_OUT')
  })
})

// Bug: Der Gesamtlauf holte den Browser-Report weiterhin aus dem Repo-Pfad
// tests/e2e/.junit/e2e.xml. Dort liegt seit der Umstellung auf docker cp nur noch
// eine root-owned Altlast: das `rm` scheiterte mit «Keine Berechtigung», und der
// Wochen alte Report wurde als Ergebnis DIESES Laufs in die Zusammenfassung
// übernommen. Der Gesamtlauf darf den Report nur aus dem laufenden e2e-Lauf
// beziehen.
describe('Gesamtlauf: Browser-Report stammt aus dem aktuellen Lauf', () => {
  const skript = readFileSync(gesamtlaufPath, 'utf8')

  it('greift nicht mehr auf den Repo-Pfad tests/e2e/.junit zu', () => {
    expect(skript).not.toContain('tests/e2e/.junit')
  })

  it('lässt sich den Report vom e2e-Lauf direkt in das Berichtsverzeichnis legen', () => {
    expect(skript).toMatch(/PW_JUNIT_OUT="\$\{JUNIT_DIR\}\/e2e-browser\.xml"/)
  })
})
