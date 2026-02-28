import './quickSearch.js';

// Theme switching functionality with three states: light, dark, system
document.addEventListener('DOMContentLoaded', function() {
    const themeToggle = document.querySelector('.theme-toggle');
    const themeIcon = document.querySelector('.theme-icon');
    
    if (!themeToggle || !themeIcon) return;
    
    // Get stored theme from localStorage, default to 'system'
    const getStoredTheme = () => {
        const stored = localStorage.getItem('theme');
        // Accept only 'light', 'dark', or 'system'
        if (stored === 'light' || stored === 'dark' || stored === 'system') {
            return stored;
        }
        return 'system'; // default
    };
    
    // Get applied theme (light/dark) based on stored theme and system preference
    const getAppliedTheme = (storedTheme) => {
        if (storedTheme === 'light') return 'light';
        if (storedTheme === 'dark') return 'dark';
        // storedTheme is 'system' or invalid -> follow system preference
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            return 'dark';
        }
        return 'light';
    };
    
    // Update UI to reflect the given stored theme
    const applyStoredTheme = (storedTheme) => {
        const applied = getAppliedTheme(storedTheme);
        
        // Update data-theme attribute
        if (applied === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
        } else {
            document.documentElement.removeAttribute('data-theme');
        }
        
        // Update icon
        themeIcon.classList.remove('bi-moon-stars', 'bi-sun', 'bi-laptop');
        if (storedTheme === 'system') {
            themeIcon.classList.add('bi-laptop');
        } else if (storedTheme === 'dark') {
            themeIcon.classList.add('bi-sun');
        } else { // light
            themeIcon.classList.add('bi-moon-stars');
        }
        
        // Update button title
        let title = 'Sötét/világos mód váltása';
        if (storedTheme === 'system') {
            title = 'Rendszer alapján (sötét/világos)';
        } else if (storedTheme === 'dark') {
            title = 'Sötét mód';
        } else {
            title = 'Világos mód';
        }
        themeToggle.setAttribute('title', title);
    };
    
    // Determine next theme in cycle: light -> dark -> system -> light
    const getNextStoredTheme = (currentStoredTheme) => {
        if (currentStoredTheme === 'light') return 'dark';
        if (currentStoredTheme === 'dark') return 'system';
        return 'light'; // system -> light
    };
    
    const storedTheme = getStoredTheme();
    applyStoredTheme(storedTheme);
    
    // Toggle theme on button click
    themeToggle.addEventListener('click', function() {
        const current = getStoredTheme();
        const next = getNextStoredTheme(current);
        localStorage.setItem('theme', next);
        applyStoredTheme(next);
    });
    
    // Listen for system theme changes
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
        const stored = getStoredTheme();
        // Only apply system theme if stored theme is 'system'
        if (stored === 'system') {
            applyStoredTheme('system');
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