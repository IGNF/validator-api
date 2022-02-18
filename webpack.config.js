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
          {
            from: "./node_modules/@ignf/validator-client/dist",
            to: "./vendor/validator-api-client"
          },
          {
            from: "./node_modules/@ignf/validator-client/public/css/",
            to: "css/"
          },
          {
            from: "./node_modules/@ignf/validator-client/public/img/",
            to: "img/"
          },
          {
            from: "./node_modules/@ignf/validator-client/public/font/",
            to: "font/"
          }
        ]
      })
    ]
  }
];
