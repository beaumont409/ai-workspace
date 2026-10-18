// ===== Theme Switcher =====

document.addEventListener('DOMContentLoaded', () => {
    const themeToggle = document.getElementById('theme-toggle');
    const html = document.documentElement;

    const themes = ['cyberpunk', 'luxury', 'modern', 'business'];
    let currentThemeIndex = 0;

    // Load saved theme
    const savedTheme = localStorage.getItem('nexacore-theme');
    if (savedTheme) {
        html.setAttribute('data-theme', savedTheme);
        currentThemeIndex = themes.indexOf(savedTheme);
    }

    themeToggle.addEventListener('click', () => {
        currentThemeIndex = (currentThemeIndex + 1) % themes.length;
        const newTheme = themes[currentThemeIndex];
        html.setAttribute('data-theme', newTheme);
        localStorage.setItem('nexacore-theme', newTheme);

        // Add a brief flash effect
        document.body.style.opacity = '0.8';
        setTimeout(() => {
            document.body.style.opacity = '1';
        }, 150);
    });
});
