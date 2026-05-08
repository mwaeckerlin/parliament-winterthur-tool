const webpackConfig = require('@nextcloud/webpack-vue-config')
const path = require('path')

module.exports = {
  ...webpackConfig,
  entry: {
    'parliamentwinterthur-main': path.join(__dirname, 'src', 'js', 'main.js'),
  },
  output: {
    path: path.join(__dirname, 'js'),
    publicPath: '/apps/parliamentwinterthur/js/',
    filename: '[name].js',
    chunkFilename: 'chunks/[name].[contenthash].js',
    clean: {
      keep: /\.gitkeep$/,
    },
  },
}
