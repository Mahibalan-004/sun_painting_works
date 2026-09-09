/* ============================================================
   SUN PAINTING WORKS - MULTI-THEME ENGINE (LIGHT / DARK / SYSTEM)
   ============================================================ */

(function () {
  const THEME_KEY = 'spw_theme_preference';

  function getStoredTheme() {
    return localStorage.getItem(THEME_KEY) || 'system';
  }

  function getSystemTheme() {
    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  }

  function resolveTheme(preference) {
    if (preference === 'system') {
      return getSystemTheme();
    }
    return preference;
  }

  function applyTheme(preference) {
    const effectiveTheme = resolveTheme(preference);
    document.documentElement.setAttribute('data-theme', effectiveTheme);
    document.documentElement.setAttribute('data-theme-preference', preference);
    
    // Dispatch custom event for 3D canvas and other components
    window.dispatchEvent(new CustomEvent('themeChanged', { 
      detail: { preference: preference, effectiveTheme: effectiveTheme } 
    }));
    
    // Update theme selector UI elements if present on page
    updateThemeSelectorUI(preference);
  }

  function updateThemeSelectorUI(preference) {
    const selectors = document.querySelectorAll('.theme-select-input');
    selectors.forEach(select => {
      if (select.value !== preference) {
        select.value = preference;
      }
    });

    const buttons = document.querySelectorAll('.theme-btn');
    buttons.forEach(btn => {
      if (btn.getAttribute('data-theme-value') === preference) {
        btn.classList.add('active');
      } else {
        btn.classList.remove('active');
      }
    });
  }

  // Set initial theme immediately before DOM ready to prevent flickering
  const initialPref = getStoredTheme();
  applyTheme(initialPref);

  // Listen for system theme changes
  window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
    if (getStoredTheme() === 'system') {
      applyTheme('system');
    }
  });

  // Global set function exposed to window
  window.setThemePreference = function (preference) {
    if (['light', 'dark', 'system'].includes(preference)) {
      localStorage.setItem(THEME_KEY, preference);
      applyTheme(preference);
    }
  };

  window.getThemePreference = getStoredTheme;

  document.addEventListener('DOMContentLoaded', () => {
    updateThemeSelectorUI(getStoredTheme());

    // Bind change listener to any theme select dropdowns
    document.addEventListener('change', (e) => {
      if (e.target && e.target.classList.contains('theme-select-input')) {
        window.setThemePreference(e.target.value);
      }
    });

    // Bind click listener to any theme buttons
    document.addEventListener('click', (e) => {
      const btn = e.target.closest('.theme-btn');
      if (btn && btn.hasAttribute('data-theme-value')) {
        window.setThemePreference(btn.getAttribute('data-theme-value'));
      }
    });
  });
})();
