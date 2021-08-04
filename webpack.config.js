const path = require('path');
const CopyWebpackPlugin = require('copy-webpack-plugin');

module.exports = [
  {
    entry: './assets/demo.js',
    output: {
      path: path.resolve(__dirname, 'public'),
      filename: 'build/demo.js'
    },
    plugins: [
      new CopyWebpackPlugin({
        patterns: [
          //{ from: './node_modules/jquery/dist', to: './vendor/jquery' },
          { from: "./node_modules/@ignf/validator-client/dist", to: "./vendor/validator-api-client" },
          { from: "./docs/favicon.ico", to: "./favicon.ico", }
        ]
      })
    ]
  }
];
