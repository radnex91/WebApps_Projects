/* ============================================================
   ThemeProvider + useTheme — Système de thèmes PMS Hôtelier
   ============================================================ */

import React, { createContext, useContext, useState, useEffect, useCallback } from 'react';
import { corporateTheme, corporateCSS } from './theme-corporate';
import { minimalTheme, minimalCSS } from './theme-minimal';
import { darkTheme, darkCSS } from './theme-dark';

/* ===== Registre des thèmes ===== */
const THEMES = {
  corporate: { config: corporateTheme, css: corporateCSS },
  minimal:   { config: minimalTheme,   css: minimalCSS },
  dark:      { config: darkTheme,      css: darkCSS },
};

const DEFAULT_THEME = 'corporate';

/* ===== Contexte ===== */
const ThemeContext = createContext(null);

export function ThemeProvider({ children, defaultTheme = DEFAULT_THEME }) {
  const [themeId, setThemeId] = useState(() => {
    try { return localStorage.getItem('pms-theme') || defaultTheme; }
    catch { return defaultTheme; }
  });

  /* Injecter les CSS variables */
  useEffect(() => {
    const theme = THEMES[themeId];
    if (!theme) return;

    const styleId = 'pms-theme-vars';
    let styleEl = document.getElementById(styleId);
    if (!styleEl) {
      styleEl = document.createElement('style');
      styleEl.id = styleId;
      document.head.appendChild(styleEl);
    }
    styleEl.textContent = theme.css;

    /* Appliquer data-theme sur <html> */
    document.documentElement.setAttribute('data-theme', themeId);

    /* Charger Google Fonts si pas déjà fait */
    const fontUrl = theme.config.typography.googleFonts;
    if (fontUrl && !document.querySelector(`link[href="${fontUrl}"]`)) {
      const link = document.createElement('link');
      link.rel = 'stylesheet';
      link.href = fontUrl;
      document.head.appendChild(link);
    }
  }, [themeId]);

  const switchTheme = useCallback((id) => {
    setThemeId(id);
    try { localStorage.setItem('pms-theme', id); } catch {}
  }, []);

  const theme = THEMES[themeId]?.config ?? THEMES[DEFAULT_THEME].config;

  return (
    <ThemeContext.Provider value={{ theme, themeId, switchTheme, themes: THEMES }}>
      {children}
    </ThemeContext.Provider>
  );
}

export function useTheme() {
  const ctx = useContext(ThemeContext);
  if (!ctx) throw new Error('useTheme() must be used within <ThemeProvider>');
  return ctx;
}

/* ===== ThemeSwitcher — boutons de sélection ===== */
export function ThemeSwitcher({ className = '' }) {
  const { themeId, switchTheme, themes } = useTheme();

  const labels = {
    corporate: { icon: '◇', label: 'Corporate Luxe' },
    minimal:   { icon: '○', label: 'Minimal Boutique' },
    dark:      { icon: '◆', label: 'Dark Pro' },
  };

  return (
    <div className={`theme-switcher ${className}`}>
      {Object.entries(themes).map(([id, t]) => (
        <button
          key={id}
          onClick={() => switchTheme(id)}
          className={`theme-btn ${id === themeId ? 'active' : ''}`}
          title={t.config.description}
        >
          <span className="theme-dot">{labels[id]?.icon}</span>
          <span className="theme-label">{labels[id]?.label}</span>
        </button>
      ))}
    </div>
  );
}
