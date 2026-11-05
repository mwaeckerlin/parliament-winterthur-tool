import { describe, it, expect } from 'vitest'
import { readFileSync } from 'node:fs'

/**
 * Die Marken, unter denen die Pipeline die Images veröffentlicht.
 *
 * Jede Version trägt ihr führendes «v», gleich wie der Git-Tag derselben
 * Auslieferung: `nginx-v1.9.10`, `nginx-nc35-v1.8.18`. Eine Marke wie
 * `nginx-1.9.9` ist unzulässig; sie entsteht auf `master`, wenn dort keine
 * Regel greift und damit auch kein Versionspräfix.
 *
 * Die Regeln stehen in `.github/workflows/docker.yml` als `branch-tags`; der
 * wiederverwendete Workflow von mwaeckerlin/scratch nimmt die ERSTE Regel, die
 * den Zweig oder den Git-Tag vollständig trifft. Dieser Test rechnet dieselbe
 * Auswahl nach und prüft die Form jeder Marke, die dabei herauskommt.
 *
 * vitest läuft im Projekt-Root.
 */
const WORKFLOW = '.github/workflows/docker.yml'
const COMPOSE = 'docker-compose.yml'

/** Die Regelliste aus der Zeile `branch-tags:` des Workflows. */
function regeln() {
  const text = readFileSync(WORKFLOW, 'utf8')
  const zeile = text.split('\n').find((z) => z.trim().startsWith('branch-tags:'))
  expect(zeile, 'der Workflow nennt keine branch-tags').toBeTruthy()
  // Der YAML-Wert steht in einfachen Anführungszeichen: darin bedeutet nur «''»
  // ein Anführungszeichen, alles andere steht wörtlich da. Der Rest ist damit
  // schon das JSON, das der Workflow bekommt — «\\1» ist dessen Maskierung für
  // den Rückverweis «\1» der Regel.
  const roh = zeile.slice(zeile.indexOf(':') + 1).trim().replace(/^'|'$/g, '')
  return JSON.parse(roh)
}

/** Die Image-Marken, die die Compose-Datei dieses Projekts baut. */
function marken() {
  return readFileSync(COMPOSE, 'utf8')
    .split('\n')
    .map((z) => z.match(/^\s*image:\s*mwaeckerlin\/parliament-winterthur-tool:(\S+)/))
    .filter(Boolean)
    .map((m) => m[1])
}

/** Suffix und Versionspräfix für einen Zweig bzw. einen Git-Tag. */
function treffer(art, name, liste) {
  for (const regel of liste) {
    if (!(art in regel)) { continue }
    const gefunden = new RegExp(`^(?:${regel[art]})$`).exec(name)
    if (gefunden) {
      const suffix = regel.suffix.replace(/\\(\d)/g, (_, n) => gefunden[Number(n)])
      return { suffix, praefix: regel.version || '' }
    }
  }
  return null
}

/** Alle Marken eines Images für einen Zweig oder Git-Tag, ohne den Bautag. */
function alleMarken(art, name, version, liste) {
  const t = treffer(art, name, liste)
  expect(t, `keine Regel trifft ${art} ${name}`).toBeTruthy()
  return marken().map((marke) => {
    const basis = marke + t.suffix
    // Ein Git-Tag trägt seine Version schon im Suffix, also keine zweite.
    return art === 'tag' ? [basis] : [basis, `${basis}-${t.praefix}${version}`]
  })
}

describe('Docker-Marken der Pipeline', () => {
  it('baut drei Images', () => {
    expect(marken().sort()).toEqual(['nginx', 'php-fpm', 'realtime'])
  })

  it('master trägt die Version mit führendem v', () => {
    const gebaut = alleMarken('branch', 'master', '1.9.10', regeln()).flat()
    expect(gebaut.sort()).toEqual([
      'nginx', 'nginx-v1.9.10',
      'php-fpm', 'php-fpm-v1.9.10',
      'realtime', 'realtime-v1.9.10',
    ].sort())
  })

  it('ein nc-Zweig trägt seine Nextcloud-Linie und die Version mit v', () => {
    const gebaut = alleMarken('branch', 'nc35', '1.9.10', regeln()).flat()
    expect(gebaut.sort()).toEqual([
      'nginx-nc35', 'nginx-nc35-v1.9.10',
      'php-fpm-nc35', 'php-fpm-nc35-v1.9.10',
      'realtime-nc35', 'realtime-nc35-v1.9.10',
    ].sort())
  })

  it('ein Release-Tag trägt Linie und Version aus seinem Namen', () => {
    const gebaut = alleMarken('tag', 'nc35-v1.8.18', null, regeln()).flat()
    expect(gebaut.sort()).toEqual([
      'nginx-nc35-v1.8.18', 'php-fpm-nc35-v1.8.18', 'realtime-nc35-v1.8.18',
    ].sort())
  })

  it('ein Neubau-Tag v<X.Y.Z>-<n> veröffentlicht unter den Marken seines Releases', () => {
    const gebaut = alleMarken('tag', 'v1.8.18-1', null, regeln()).flat()
    expect(gebaut.sort()).toEqual([
      'nginx-v1.8.18', 'php-fpm-v1.8.18', 'realtime-v1.8.18',
    ].sort())
  })

  it('der Auftrag mit «ref» kennt dieselben Tag-Regeln wie der Push', () => {
    const zeilen = readFileSync(WORKFLOW, 'utf8').split('\n')
      .filter((z) => z.trim().startsWith('branch-tags:'))
      .map((z) => JSON.parse(z.slice(z.indexOf(':') + 1).trim().replace(/^'|'$/g, '')))
    expect(zeilen.length, 'der Workflow hat einen Job für den Push und einen für «ref»').toBe(2)
    const tagRegeln = (liste) => liste.filter((r) => 'tag' in r).map((r) => r.tag)
    expect(tagRegeln(zeilen[1])).toEqual(tagRegeln(zeilen[0]))
  })

  it('keine Marke trägt eine Version ohne führendes v', () => {
    const liste = regeln()
    const verstoesse = []
    for (const zweig of ['master', 'nc33', 'nc34', 'nc35']) {
      for (const gebaut of alleMarken('branch', zweig, '1.9.10', liste).flat()) {
        if (/-\d+\.\d+/.test(gebaut)) { verstoesse.push(`${zweig}: ${gebaut}`) }
      }
    }
    expect(
      verstoesse,
      'Eine Version steht nie ohne ihr v — der Zweig braucht eine Regel mit «version»:\n'
      + verstoesse.join('\n'),
    ).toEqual([])
  })

  it('jeder gebaute Zweig hat eine Regel', () => {
    const liste = regeln()
    const ohne = ['master', 'nc33', 'nc34', 'nc35'].filter((z) => !treffer('branch', z, liste))
    expect(ohne, `ohne Regel gäbe es keine Version in der Marke: ${ohne.join(', ')}`).toEqual([])
  })
})

/**
 * Wann und wofür die Pipeline baut. Die gemeinsame Vorlage von
 * mwaeckerlin/scratch baut für amd64 und arm64, solange der Aufruf keine
 * eigene Liste `architectures` setzt. Nach einem grünen Bau von `master` führt
 * sie das Skript aus `version-branches` mit dem Commit des Pushes in
 * `BASE_COMMIT` aus und baut jeden Zweig, den es geschoben hat, mit der
 * Eingabe `build`; das Schieben braucht `contents: write`.
 */
describe('Bau und Veröffentlichung der Pipeline', () => {
  const workflow = () => readFileSync(WORKFLOW, 'utf8')

  it('baut bei jedem Push auf master und auf jeden Linien-Zweig', () => {
    expect(workflow()).toMatch(/^ {4}branches: \[master, "nc\*"\]$/m)
  })

  it('baut für amd64 und arm64', () => {
    const eigeneListen = workflow().split('\n').filter((z) => /^\s*architectures:/.test(z))
    const ohneBeide = eigeneListen.filter((z) => !/amd64/.test(z) || !/arm64/.test(z))
    expect(ohneBeide, 'eine eigene Liste architectures lässt eine Architektur weg').toEqual([])
  })

  it('ein Push auf master erzeugt die Linien-Zweige aus genau diesem Commit neu und baut sie', () => {
    const text = workflow()
    expect(text).toMatch(/^\s+version-base: master$/m)
    expect(text).toMatch(/^\s+version-branches: \.\/create-branches\.sh$/m)
    expect(text).toMatch(/^\s+contents: write$/m)
    expect(text).toMatch(/^ {6}build:$/m)
    const skript = readFileSync('create-branches.sh', 'utf8')
    expect(skript).toMatch(/^\s+git checkout -B "\$branch" "\$BASE_COMMIT"$/m)
    expect(skript).toMatch(/^\s+git push -f origin "\$branch"$/m)
  })
})
