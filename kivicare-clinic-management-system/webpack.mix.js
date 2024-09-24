const mix = require('laravel-mix');

/*
 |--------------------------------------------------------------------------
 | Mix Asset Management
 |--------------------------------------------------------------------------
 |
 | Mix provides a clean, fluent API for defining some Webpack build steps
 | for your Laravel application. By default, we are compiling the Sass
 | file for the application as well as bundling up all the JS files.
 |
 */
//mix.config.fileLoaderDirs.fonts = 'assets/fonts';
//mix.setResourceRoot('/wp-content/plugins/kiviCare');

mix.js('resources/js/app.js', 'assets/js/app.min.js')
    .sass('resources/sass/app.scss', 'assets/css/app.min.css');

mix.js('resources/js/front-app.js', 'assets/js/front-app.min.js')
    .sass('resources/sass/front-app.scss', 'assets/css/front-app.min.css');

mix.autoload({
    jquery: ['$', 'window.jQuery', 'jQuery', 'jquery']
});
