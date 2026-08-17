const mix = require('laravel-mix');

/*
 |--------------------------------------------------------------------------
 | Mix Asset Management
 |--------------------------------------------------------------------------
 |
 | Dos aplicaciones independientes:
 |
 |   app.js  -> dashboard y reportes con Inertia, dentro del panel de Backpack.
 |   pos.js  -> punto de venta, SPA propia servida en /pos.
 |
 | Se compilan por separado a propósito: el POS no debe arrastrar element-plus
 | ni chart.js, y el panel no debe cargar el reset de Tailwind.
 |
 */
mix.js('resources/js/app.js', 'public/js')
    .postCss("resources/css/app.css", "public/css", [
        //
    ]);

mix.js('resources/js/pos/main.js', 'public/js/pos.js')
    .postCss('resources/css/pos.css', 'public/css', [
        require('tailwindcss'),
        require('autoprefixer'),
    ]);

mix.vue();

mix.webpackConfig({
    output: {
        chunkFilename: "js/[name].js?id=[chunkhash]",
    },
});
