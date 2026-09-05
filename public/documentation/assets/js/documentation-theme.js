const themeButton = document.querySelector('[data-documentation-theme-toggle]');

function updateThemeButton() {
    const isDark = document.documentElement.dataset.theme === 'dark';
    themeButton.textContent = isDark ? 'Helle Ansicht' : 'Dunkle Ansicht';
    themeButton.setAttribute('aria-label', isDark ? 'Helle Ansicht aktivieren' : 'Dunkle Ansicht aktivieren');
}

themeButton.addEventListener('click', () => {
    const theme = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
    document.documentElement.dataset.theme = theme;
    document.documentElement.dataset.themeChoice = theme;

    try {
        localStorage.setItem('theme', theme);
    } catch {
        // The selected theme still applies when preference storage is unavailable.
    }

    updateThemeButton();
});

updateThemeButton();
