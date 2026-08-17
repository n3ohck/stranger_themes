/**
 * Tailwind se limita a resources/js/pos y a su blade: el panel de Backpack trae
 * su propio Bootstrap y no debe verse afectado por el reset de Tailwind.
 */
module.exports = {
    content: [
        './resources/js/pos/**/*.{vue,js}',
        './resources/views/pos.blade.php',
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
            },
            fontFamily: {
                sans: ['Inter', 'system-ui', '-apple-system', 'Segoe UI', 'sans-serif'],
                ticket: ['ui-monospace', 'SFMono-Regular', 'Menlo', 'monospace'],
            },
            spacing: {
                // Alto mínimo cómodo para operar con el dedo en pantalla táctil.
                touch: '3.25rem',
            },
        },
    },
    plugins: [],
};
