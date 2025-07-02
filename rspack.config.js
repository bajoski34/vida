const path = require('path');

const CLIENT_DIR = path.resolve(__dirname, 'assets');
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

const entry = {
  index: CLIENT_DIR + '/blocks/index.js',
  settings: CLIENT_DIR + '/admin/settings/index.js',
  editor: CLIENT_DIR + '/admin/editor/index.js',
};


module.exports = {
  mode: process.env.NODE_ENV === 'production' ? 'production' : 'development',
  devtool: process.env.NODE_ENV === 'production' ? 'hidden-source-map' : 'source-map',
  entry,
  output: {
    path: path.resolve(__dirname, 'build'),
    filename: '[name].js',
  },
  resolve: {
    extensions: ['.json', '.js', '.jsx'],
    modules: [CLIENT_DIR, 'node_modules'],
    alias: {
      wcvidaveend: CLIENT_DIR,
    },
    fallback: {
      process: require.resolve('process/browser'),
    },
  },
  module: {
    rules: [
      {
        test: /\.(png|jpg|svg|jpeg|gif|ico)$/,
        type: 'asset/resource',
      },
      {
        test: /\.s[ac]ss$/i,
        use: [
          'style-loader',   // Injects CSS into DOM
          'css-loader',     // Turns CSS into CommonJS
          'sass-loader',    // Compiles Sass to CSS
        ],
      },
      {
        test: /\.(js|jsx)$/,
        exclude: /node_modules/,
        use: {
          loader: "babel-loader",
          options: {
            presets: ["@babel/preset-react"],
          },
        },
      },
    ],
  },
  optimization: {
    splitChunks: false,
    minimize: process.env.NODE_ENV === 'production',
  },
  plugins: [],
  externals: {
    '@woocommerce/blocks-registry': 'wc.wcBlocksRegistry',
    '@woocommerce/settings': 'wc.wcSettings',
  },
};
