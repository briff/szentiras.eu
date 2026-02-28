import './quickSearch.js';

// Theme switching functionality
document.addEventListener('DOMContentLoaded', function() {
    const themeToggle = document.querySelector('.theme-toggle');
    const themeIcon = document.querySelector('.theme-icon');
    
    if (!themeToggle || !themeIcon) return;
    
    // Get current theme from localStorage or check system preference
    const getPreferredTheme = () => {
        const storedTheme = localStorage.getItem('theme');
        if (storedTheme) {
            return storedTheme;
        }
        
        // Check system preference
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            return 'dark';
        }
        
        return 'light';
    };
    
    const currentTheme = getPreferredTheme();
    
    // Apply the theme on page load
    if (currentTheme === 'dark') {
        document.documentElement.setAttribute('data-theme', 'dark');
        themeIcon.classList.remove('bi-moon-stars');
        themeIcon.classList.add('bi-sun');
    } else {
        document.documentElement.removeAttribute('data-theme');
        themeIcon.classList.remove('bi-sun');
        themeIcon.classList.add('bi-moon-stars');
    }
    
    // Toggle theme on button click
    themeToggle.addEventListener('click', function() {
        const html = document.documentElement;
        const isDark = html.getAttribute('data-theme') === 'dark';
        
        if (isDark) {
            html.removeAttribute('data-theme');
            themeIcon.classList.remove('bi-sun');
            themeIcon.classList.add('bi-moon-stars');
            localStorage.setItem('theme', 'light');
        } else {
            html.setAttribute('data-theme', 'dark');
            themeIcon.classList.remove('bi-moon-stars');
            themeIcon.classList.add('bi-sun');
            localStorage.setItem('theme', 'dark');
        }
    });
    
    // Listen for system theme changes
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
        // Only apply system theme if user hasn't explicitly set a preference
        if (!localStorage.getItem('theme')) {
            if (e.matches) {
                document.documentElement.setAttribute('data-theme', 'dark');
                themeIcon.classList.remove('bi-moon-stars');
                themeIcon.classList.add('bi-sun');
            } else {
                document.documentElement.removeAttribute('data-theme');
                themeIcon.classList.remove('bi-sun');
                themeIcon.classList.add('bi-moon-stars');
            }
        }
    });
});

$('#semanticSearchForm').on('submit', function (event) {
    event.preventDefault();
    $('#interstitial').show();
    event.target.submit();
});

$('.interstitial').on('click', () =>
    $('#interstitial').show()
);


window.addEventListener('pageshow', (event) => {
    $('#interstitial').hide()
});