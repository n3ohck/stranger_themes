/**
 * Tailwind se limita a resources/js/pos y a su blade: el panel de Backpack trae
 * su propio Bootstrap y no debe verse afectado por el reset de Tailwind.
 */
module.exports = {
    content: [
        './resources/js/pos/**/*.{vue,js}',
        './resources/views/pos.blade.php',
        './resources/js/tienda/**/*.{vue,js}',
        './resources/views/tienda.blade.php',
    ],
    theme: {
        extend: {
            colors: {
                // Base oscura del punto de venta anterior, ordenada en escala.
                noche: {
                    900: '#0b0d12',
                    800: '#12151d',
                    700: '#1a1f2b',
                    600: '#242a38',
                    500: '#323a4d',
                },
                sangre: {
                    600: '#a11414',
                    500: '#c81e1e',
                    400: '#e03131',
                },
                acento: {
                    600: '#5b21b6',
                    500: '#7c3aed',
                    400: '#9563f5',
                },
                // Valores tomados de strangerthemes.com.
                terror: {
                    fondo: '#1C1C21',
                    panel: '#232329',
                    borde: '#33333c',
                    texto: '#858585',
                    rojo: '#B11724',
                    rojoClaro: '#d4202f',
                },
            },
            fontFamily: {
                sans: ['Inter', 'system-ui', '-apple-system', 'Segoe UI', 'sans-serif'],
                ticket: ['ui-monospace', 'SFMono-Regular', 'Menlo', 'monospace'],
                // Tipografías del sitio público, para que la tienda se sienta
                // parte de strangerthemes.com y no de otro producto.
                marca: ['Montserrat', 'system-ui', 'sans-serif'],
                titular: ['"Jim Nightshade"', 'cursive'],
            },
            spacing: {
                // Alto mínimo cómodo para operar con el dedo en pantalla táctil.
                touch: '3.25rem',
            },
        },
    },
    plugins: [],
};
