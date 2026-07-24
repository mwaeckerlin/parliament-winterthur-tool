// Test-Mock für den per Webpack-Alias '@changelog' eingebundenen CHANGELOG.md-Rohtext.
// Enthält bewusst mehrere Einträge derselben Minor-Version (1.8.x) sowie eine
// ältere Minor-Version (1.7.x), damit das Aufklapp-Verhalten prüfbar ist.
export default [
  '# Changelog',
  '',
  '- 2026-06-25 **1.8.1**',
  '    - Testeintrag fett: **wichtig**',
  '',
  '- 2026-06-24 **1.8.0**',
  '    - Weiterer Eintrag der aktuellen Minor-Version',
  '',
  '- 2026-06-20 **1.7.9**',
  '    - Eintrag einer älteren Minor-Version',
  '',
].join('\n')
