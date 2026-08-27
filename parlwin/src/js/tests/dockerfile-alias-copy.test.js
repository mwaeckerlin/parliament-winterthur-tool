import { describe, it, expect } from 'vitest'
import { readFileSync } from 'node:fs'

/**
 * Build-Guard: Jede Datei, die der Webpack-Build über einen `@…`-Alias als
 * Rohtext einbindet (z.B. @readme → README.md, @changelog → CHANGELOG.md), muss
 * im Dockerfile.php-fpm in die App-Build-Stage kopiert werden UND darf nicht von
 * `.dockerignore` aus dem Build-Context ausgeschlossen sein — sonst scheitert
 * `npm run build:app` bzw. der COPY im Image (und damit `npm run start`), während
 * es lokal funktioniert, weil die Datei dort ohnehin liegt.
 *
 * vitest läuft im Projekt-Root.
 */
function aliasZiele() {
  const webpack = readFileSync('parlwin/webpack.js', 'utf8')
  // Alias-Ziele der Form: '@name': path.join(__dirname, '..', 'DATEI')
  return [...webpack.matchAll(/['"]@\w+['"]\s*:\s*path\.join\([^)]*['"]([^'"]+\.md)['"]\s*\)/g)]
    .map((m) => m[1])
}

describe('Webpack-Alias-Zieldateien sind im Image-Build verfügbar', () => {
  it('jede @-Alias-Datei aus webpack.js wird im php-fpm-Image-Build kopiert', () => {
    const ziele = aliasZiele()
    expect(ziele.length, 'keine @-Alias-Zieldateien in webpack.js gefunden').toBeGreaterThan(0)

    const dockerfile = readFileSync('Dockerfile.php-fpm', 'utf8')
    for (const datei of ziele) {
      const kopiert = new RegExp('^COPY\\b.*\\b' + datei.replace('.', '\\.') + '\\b', 'm').test(dockerfile)
      expect(kopiert, `${datei} wird im Dockerfile.php-fpm nicht in die Build-Stage kopiert`).toBe(true)
    }
  })

  it('keine @-Alias-Datei wird von .dockerignore aus dem Build-Context ausgeschlossen', () => {
    const ziele = aliasZiele()
    const zeilen = readFileSync('.dockerignore', 'utf8')
      .split('\n')
      .map((z) => z.trim())
      .filter((z) => z !== '' && !z.startsWith('#') && !z.startsWith('!'))
    for (const datei of ziele) {
      const ausgeschlossen = zeilen.some((z) => z === datei || z === '*.md' || z === '**/*.md')
      expect(ausgeschlossen, `${datei} ist in .dockerignore ausgeschlossen — fehlt dann im Build-Context`).toBe(false)
    }
  })
})
