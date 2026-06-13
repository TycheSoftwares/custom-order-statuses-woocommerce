const path          = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
    ...defaultConfig,
    entry: {
        settings: path.resolve( __dirname, 'src/index.js' ),
    },
    output: {
        path    : path.resolve( __dirname, 'build' ),
        filename: '[name].js',
    },
    externals: {
        ...defaultConfig.externals,
        '@wordpress/api-fetch': 'wp.apiFetch',
        '@wordpress/i18n'     : 'wp.i18n',
        '@wordpress/date'     : 'wp.date',
    },
    resolve: {
        ...defaultConfig.resolve,
        alias: {
            ...defaultConfig.resolve?.alias,
            '@cos': path.resolve( __dirname, 'src/' ),
        },
    },
};
