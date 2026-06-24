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
    },
  },
  output: {
    path: path.join(__dirname, 'js'),
    publicPath: '/apps/parlwin/js/',
    filename: '[name].js',
    chunkFilename: 'chunks/[name].[contenthash].js',
    clean: {
      keep: /\.gitkeep$/,
    },
  },
  optimization: {
    ...(webpackConfig.optimization || {}),
    minimizer: [new TerserPlugin({ extractComments: false })],
  },
}
