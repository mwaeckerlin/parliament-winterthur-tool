import { defineConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
  plugins: [vue()],
  test: {
    environment: 'jsdom',
    globals: true,
    setupFiles: ['./parlwin/src/js/tests/setup.js'],
    exclude: ['tests/e2e/**', 'node_modules'],
  },
  resolve: {
    alias: {
      '@changelog': '/home/marc/git/mwaeckerlin/parliament-winterthur-tool/parlwin/src/js/tests/__mocks__/changelog.js',
      '@nextcloud/router': '/home/marc/git/mwaeckerlin/parliament-winterthur-tool/parlwin/src/js/tests/__mocks__/@nextcloud/router.js',
      '@nextcloud/auth': '/home/marc/git/mwaeckerlin/parliament-winterthur-tool/parlwin/src/js/tests/__mocks__/@nextcloud/auth.js',
      '@nextcloud/axios': '/home/marc/git/mwaeckerlin/parliament-winterthur-tool/parlwin/src/js/tests/__mocks__/@nextcloud/axios.js',
      '@nextcloud/dialogs/style.css': '/home/marc/git/mwaeckerlin/parliament-winterthur-tool/parlwin/src/js/tests/__mocks__/empty.js',
      '@nextcloud/dialogs': '/home/marc/git/mwaeckerlin/parliament-winterthur-tool/parlwin/src/js/tests/__mocks__/@nextcloud/dialogs.js',
      '@nextcloud/vue/components/NcContent': '/home/marc/git/mwaeckerlin/parliament-winterthur-tool/parlwin/src/js/tests/__mocks__/@nextcloud/vue/NcComponent.js',
      '@nextcloud/vue/components/NcAppNavigation': '/home/marc/git/mwaeckerlin/parliament-winterthur-tool/parlwin/src/js/tests/__mocks__/@nextcloud/vue/NcComponent.js',
      '@nextcloud/vue/components/NcAppNavigationItem': '/home/marc/git/mwaeckerlin/parliament-winterthur-tool/parlwin/src/js/tests/__mocks__/@nextcloud/vue/NcComponent.js',
      '@nextcloud/vue/components/NcAppContent': '/home/marc/git/mwaeckerlin/parliament-winterthur-tool/parlwin/src/js/tests/__mocks__/@nextcloud/vue/NcComponent.js',
      '@nextcloud/vue/components/NcIconSvgWrapper': '/home/marc/git/mwaeckerlin/parliament-winterthur-tool/parlwin/src/js/tests/__mocks__/@nextcloud/vue/NcComponent.js',
      '@nextcloud/vue/components/NcSelect': '/home/marc/git/mwaeckerlin/parliament-winterthur-tool/parlwin/src/js/tests/__mocks__/@nextcloud/vue/NcComponent.js',
      '@nextcloud/vue/components/NcActions': '/home/marc/git/mwaeckerlin/parliament-winterthur-tool/parlwin/src/js/tests/__mocks__/@nextcloud/vue/NcComponent.js',
      '@nextcloud/vue/components/NcActionButton': '/home/marc/git/mwaeckerlin/parliament-winterthur-tool/parlwin/src/js/tests/__mocks__/@nextcloud/vue/NcComponent.js',
      '@nextcloud/vue/components/NcActionCaption': '/home/marc/git/mwaeckerlin/parliament-winterthur-tool/parlwin/src/js/tests/__mocks__/@nextcloud/vue/NcComponent.js',
      '@nextcloud/vue/components/NcButton': '/home/marc/git/mwaeckerlin/parliament-winterthur-tool/parlwin/src/js/tests/__mocks__/@nextcloud/vue/NcComponent.js',
      '@nextcloud/vue/components/NcCheckboxRadioSwitch': '/home/marc/git/mwaeckerlin/parliament-winterthur-tool/parlwin/src/js/tests/__mocks__/@nextcloud/vue/NcComponent.js',
      '@nextcloud/vue/components/NcLoadingIcon': '/home/marc/git/mwaeckerlin/parliament-winterthur-tool/parlwin/src/js/tests/__mocks__/@nextcloud/vue/NcComponent.js',
      '@nextcloud/vue/components/NcTextField': '/home/marc/git/mwaeckerlin/parliament-winterthur-tool/parlwin/src/js/tests/__mocks__/@nextcloud/vue/NcComponent.js',
      '@nextcloud/vue/components/NcEmptyContent': '/home/marc/git/mwaeckerlin/parliament-winterthur-tool/parlwin/src/js/tests/__mocks__/@nextcloud/vue/NcComponent.js',
      '../realtime': '/home/marc/git/mwaeckerlin/parliament-winterthur-tool/parlwin/src/js/tests/__mocks__/realtime.js',
      './realtime': '/home/marc/git/mwaeckerlin/parliament-winterthur-tool/parlwin/src/js/tests/__mocks__/realtime.js',
    },
  },
})
