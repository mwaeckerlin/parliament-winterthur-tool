const webpackConfig = require('@nextcloud/webpack-vue-config')
const path = require('path')
const TerserPlugin = require('terser-webpack-plugin')

module.exports = {
  ...webpackConfig,
  entry: {
    'parlwin-main': path.join(__dirname, 'src', 'js', 'main.js'),
    'calendar-prefill': path.join(__dirname, 'src', 'js', 'calendar-prefill.js'),
    'admin': path.join(__dirname, 'src', 'js', 'admin.js'),
  },
  module: {
    ...(webpackConfig.module || {}),
    rules: [
      ...((webpackConfig.module && webpackConfig.module.rules) || []),
      // CHANGELOG.md als Rohtext einbinden (für die Changelog-Ansicht).
      { test: /\.md$/, type: 'asset/source' },
    ],
  },
  resolve: {
    ...(webpackConfig.resolve || {}),
    alias: {
      ...((webpackConfig.resolve && webpackConfig.resolve.alias) || {}),
      '@changelog': path.join(__dirname, '..', 'CHANGELOG.md'),
      '@readme': path.join(__dirname, '..', 'README.md'),
    },
  },
  output: {
    path: path.join(__dirname, 'js'),
    publicPath: '/apps/parlwin/js/',
    filename: '[name].js',
    chunkFilename: 'chunks/[name].[contenthash].js',
    // Vor jedem Bau leert webpack das Verzeichnis; README.md beschreibt es und bleibt stehen.
    clean: {
      keep: /(^|\/)(\.gitkeep|README\.md)$/,
    },
  },
  optimization: {
    ...(webpackConfig.optimization || {}),
    minimizer: [new TerserPlugin({ extractComments: false })],
  },
  // WARNING-BEGRUENDUNG (bei jedem Lauf neu pruefen, Review: 2026-12-02):
  // webpacks Standardbudget von 244 KiB zielt auf Seiten, die ueber langsame
  // Mobilnetze ausgeliefert werden. Diese App ist eine Nextcloud-Erweiterung:
  // sie wird aus derselben Instanz geladen wie Nextcloud selbst und bringt
  // zwingend Vue 3, die Nextcloud-Komponentenbibliothek und den Editor mit —
  // allein diese Abhaengigkeiten liegen weit ueber 244 KiB. Das Budget ist
  // deshalb nicht erreichbar, ein Nachladen einzelner Teile wuerde es ebenfalls
  // nicht unterschreiten. Statt die Warnung abzuschalten (das wuerde jede echte
  // Regression verbergen) steht hier ein realistisches Budget mit gut 10 %
  // Luft: waechst ein Bundle deutlich, warnt der Build wieder.
  // Entfernen bzw. senken, sobald die Abhaengigkeiten kleiner werden.
  //
  // Gemessen am 02.11.2026: parlwin-main.js ist 2'537'871 Bytes. Gewachsen ist
  // es durch die Sicherheitsaktualisierungen desselben Tages — tiptap 3.23 auf
  // 3.30, vue 3.5.34 auf 3.5.44, @nextcloud/dialogs und markdown-it —, die
  // Luecken in den mitgelieferten Bibliotheken schliessen. Das Budget steht
  // darum auf 2'800'000 Bytes, wieder mit gut 10 % Luft.
  performance: {
    hints: 'warning',
    maxAssetSize: 2_800_000,
    maxEntrypointSize: 2_800_000,
  },
}
