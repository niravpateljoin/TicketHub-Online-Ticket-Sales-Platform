/** @type {import('tailwindcss').Config} */
module.exports = {
    content: [
        './assets/**/*.{js,jsx}',
        './templates/**/*.twig',
    ],
    theme: {
        extend: {
            colors: {
                primary: {
                    DEFAULT: '#2EC4A1',
                    dark: '#25A085',
                    light: '#E6F8F4',
                },
                sidebar: {
                    bg: '#1A1D23',
                    text: '#A0AEC0',
                    active: '#FFFFFF',
                    hover: '#2D3748',
                    border: '#2D3748',
                },
                surface: {
                    DEFAULT: '#FFFFFF',
                    page: '#F7F8FA',
                },
                border: {
                    DEFAULT: '#E2E8F0',
                    input: '#CBD5E0',
                },
                text: {
                    primary: '#1A202C',
                    secondary: '#4A5568',
                    muted: '#718096',
                    disabled: '#A0AEC0',
                },
                status: {
                    success: '#38A169',
                    'success-bg': '#F0FFF4',
                    warning: '#D69E2E',
                    'warning-bg': '#FFFBEB',
                    danger: '#E53E3E',
                    'danger-bg': '#FFF5F5',
                    info: '#3182CE',
                    'info-bg': '#EBF8FF',
                    refunded: '#805AD5',
                    'refunded-bg': '#FAF5FF',
                    postponed: '#DD6B20',
                    'postponed-bg': '#FFFAF0',
                },
            },
            fontFamily: {
                sans: ['Inter', 'system-ui', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'sans-serif'],
            },
            boxShadow: {
                card: '0 1px 3px rgba(0,0,0,0.08)',
                topbar: '0 1px 3px rgba(0,0,0,0.05)',
                modal: '0 20px 60px rgba(0,0,0,0.15)',
                toast: '0 4px 12px rgba(0,0,0,0.10)',
            },
            borderRadius: {
                card: '8px',
                btn: '6px',
                badge: '99px',
            },
            width: {
                sidebar: '240px',
            },
            height: {
                topbar: '64px',
            },
        },
    },
    plugins: [],
};
