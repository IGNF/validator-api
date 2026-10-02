const path = require('path');
const CopyWebpackPlugin = require('copy-webpack-plugin');

/*
 * Demo client (IGNF/validator-api-client) : the built bundle and its static files (css, fonts,
 * images) are copied in public/ and loaded by templates/demo.html.twig.
 */
const clientDir = path.dirname(require.resolve('@ignf/validator-client/package.json'));

// directories of public/ produced by this build (the other files of public/ are kept)
const GENERATED_DIRS = ['build/', 'vendor/', 'css/', 'font/', 'img/'];

module.exports = {
  entry: './assets/demo.js',
  output: {
    path: path.resolve(__dirname, 'public'),
    filename: 'build/demo.js',
    // removes the outdated files (ex : chunks of a previous version of the client)
    clean: {
      keep: (asset) => !GENERATED_DIRS.some((dir) => asset.startsWith(dir)),
    },
  },
  plugins: [
    new CopyWebpackPlugin({
      patterns: [
        {
          from: path.join(clientDir, 'dist'),
          to: 'vendor/validator-api-client',
          // already minified by the client build (not processed again by terser)
          info: { minimized: true },
        },
        {
          from: path.join(clientDir, 'public/css'),
          to: 'css',
        },
        {
          from: path.join(clientDir, 'public/img'),
          to: 'img',
        },
        {
          from: path.join(clientDir, 'public/font'),
          to: 'font',
          // only woff2, woff, ttf and svg are referenced by style-carto.css
          globOptions: {
            ignore: ['**/*.eot', '**/*.otf', '**/selection.json'],
          },
        },
      ],
    }),
  ],
  // the copied files of the client are large (swagger-ui), demo.js is tiny
  performance: {
    hints: false,
  },
};
