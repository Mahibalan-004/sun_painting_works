/* ============================================================
   SUN PAINTING WORKS - PERMANENT LIGHT THEME ENGINE
   ============================================================ */

(function () {
  function applyTheme() {
    document.documentElement.setAttribute('data-theme', 'light');
    document.documentElement.setAttribute('data-theme-preference', 'light');
    window.dispatchEvent(new CustomEvent('themeChanged', { 
      detail: { preference: 'light', effectiveTheme: 'light' } 
    }));
  }

  applyTheme();

  window.setThemePreference = function () { applyTheme(); };
  window.getThemePreference = function () { return 'light'; };

  document.addEventListener('DOMContentLoaded', () => {
    applyTheme();
  });
})();

