import { fileURLToPath } from 'node:url'
import { defineConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'

// Jeder Pfad geht von dieser Datei aus. Mit absoluten Pfaden lief die Suite nur
// auf dem einen Rechner, auf dem sie geschrieben wurde: Auf dem GitHub-Runner
// liegt das Repository woanders, keine einzige Zuordnung griff, und 63 Tests
// scheiterten an «Failed to resolve import "@nextcloud/axios"» (02.10.2026).
const hier = (pfad) => fileURLToPath(new URL(pfad, import.meta.url))

const attrappe = (name) => hier(`./parlwin/src/js/tests/__mocks__/${name}`)
const ncKomponente = attrappe('@nextcloud/vue/NcComponent.js')

const ncKomponenten = [
  'NcContent', 'NcAppNavigation', 'NcAppNavigationItem', 'NcAppContent',
  'NcIconSvgWrapper', 'NcSelect', 'NcActions', 'NcActionButton',
  'NcActionCaption', 'NcButton', 'NcCheckboxRadioSwitch', 'NcLoadingIcon',
  'NcTextField', 'NcEmptyContent',
]

export default defineConfig({
  plugins: [vue()],
  test: {
    environment: 'jsdom',
    globals: true,
    setupFiles: [hier('./parlwin/src/js/tests/setup.js')],
    exclude: ['tests/e2e/**', 'node_modules'],
  },
  resolve: {
    alias: {
      '@changelog': attrappe('changelog.js'),
      '@readme': attrappe('readme.js'),
      '@nextcloud/router': attrappe('@nextcloud/router.js'),
      '@nextcloud/auth': attrappe('@nextcloud/auth.js'),
      '@nextcloud/axios': attrappe('@nextcloud/axios.js'),
      '@nextcloud/dialogs/style.css': attrappe('empty.js'),
      '@nextcloud/dialogs': attrappe('@nextcloud/dialogs.js'),
      ...Object.fromEntries(
        ncKomponenten.map((name) => [`@nextcloud/vue/components/${name}`, ncKomponente]),
      ),
      '../realtime': attrappe('realtime.js'),
      './realtime': attrappe('realtime.js'),
    },
  },
})
