/**
 * tailwind.config.js
 * Extends Tailwind with AssocMAP brand tokens.
 */
/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './resources/**/*.blade.php',
        './resources/**/*.js',
    ],
    theme: {
        extend: {
            colors: {
                'assocmap-primary'   : '#1F6E3D',
                'assocmap-hover'     : '#17572F',
                'assocmap-bg'        : '#EEF5F2',
                'assocmap-card'      : '#FFFFFF',
                'assocmap-border'    : '#D8E6DD',
                'assocmap-text'      : '#1F2937',
                'assocmap-secondary' : '#64748B',
            },
            fontFamily: {
                inter: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
            },
            boxShadow: {
                card: '0 2px 16px 0 rgba(31,110,61,0.08), 0 1px 4px 0 rgba(0,0,0,0.04)',
            },
        },
    },
    plugins: [],
};
