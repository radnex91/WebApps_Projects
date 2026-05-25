/* ============================================================
   Sidebar — Navigation PMS Hôtelière
   S'adapte automatiquement au thème actif
   ============================================================ */

import React, { useState } from 'react';
import { useTheme } from '../index';

const MENU_SECTIONS = [
  {
    label: 'Hôtellerie',
    role: ['admin', 'receptionist', 'manager'],
    items: [
      { href: '/reservations', icon: 'calendar-check', label: 'Réservations' },
      { href: '/rooms',        icon: 'door-open',     label: 'Chambres' },
      { href: '/room-types',   icon: 'tags',          label: 'Types de chambres' },
      { href: '/clients',      icon: 'people',        label: 'Clients' },
      { href: '/services',     icon: 'cup-hot',       label: 'Services' },
      { href: '/housekeeping', icon: 'droplet',       label: 'Entretien' },
    ],
  },
  {
    label: 'Ressources Humaines',
    role: ['admin', 'hr', 'manager'],
    items: [
      { href: '/employees',   icon: 'person-badge', label: 'Employés' },
      { href: '/departments', icon: 'diagram-3',    label: 'Départements' },
      { href: '/attendance',  icon: 'clock',        label: 'Présences' },
      { href: '/leaves',      icon: 'calendar-heart', label: 'Congés' },
      { href: '/payroll',     icon: 'cash-stack',   label: 'Paie' },
    ],
  },
  {
    label: 'Comptabilité',
    role: ['admin', 'accountant', 'manager'],
    items: [
      { href: '/invoices',          icon: 'receipt',                 label: 'Factures' },
      { href: '/payments',          icon: 'credit-card',             label: 'Paiements' },
      { href: '/expenses',          icon: 'cart',                    label: 'Dépenses' },
      { href: '/taxes',             icon: 'percent',                 label: 'Taxes' },
      { href: '/reports/balance',   icon: 'file-earmark-bar-graph',  label: 'Bilan' },
      { href: '/reports/income',    icon: 'graph-up',                label: 'Résultat' },
    ],
  },
];

export default function Sidebar({ collapsed, onToggle, role = 'admin', currentPath = '/' }) {
  const { theme } = useTheme();
  const t = theme.colors;

  const isActive = (href) => currentPath.startsWith(href);

  return (
    <aside
      className={`sidebar ${collapsed ? 'collapsed' : ''}`}
      style={{
        width: collapsed ? 70 : 260,
        background: t.surface.sidebar,
        borderRight: `1px solid ${t.border.DEFAULT}`,
        transition: theme.effects.transition,
      }}
    >
      {/* Header */}
      <div className="sidebar-header" style={{ borderBottom: `1px solid ${t.border.DEFAULT}` }}>
        <a href="/" className="sidebar-brand" style={{ fontFamily: theme.typography.fontFamily.heading.join(',') }}>
          <span className="sidebar-logo" style={{
            background: theme.id === 'dark'
              ? `linear-gradient(135deg, ${t.accent.muted}, rgba(99,102,241,0.15))`
              : t.accent.muted,
            color: t.primary[500],
            border: `1px solid ${t.accent.DEFAULT}30`,
          }}>A</span>
          {!collapsed && <span style={{ fontWeight: 700, fontSize: '1.1rem' }}>AureliaHost</span>}
        </a>
        <button onClick={onToggle} className="sidebar-toggle" style={{ color: t.text.muted }}
          aria-label={collapsed ? 'Expand sidebar' : 'Collapse sidebar'}>
          ☰
        </button>
      </div>

      {/* Navigation */}
      <nav className="sidebar-nav">
        <a href="/" className={`sidebar-link ${isActive('/') ? 'active' : ''}`}
          style={{
            color: isActive('/') ? t.primary[500] : t.text.secondary,
            background: isActive('/') ? t.accent.muted : 'transparent',
            borderRadius: theme.effects.radius.sm,
            fontFamily: theme.typography.fontFamily.body.join(','),
          }}>
          <i className="icon-speedometer" /> <span>Dashboard</span>
        </a>

        {MENU_SECTIONS.filter(s => s.role.includes(role)).map(section => (
          <div key={section.label}>
            <div className="sidebar-section-label"
              style={{ color: t.text.muted, fontSize: '0.62rem', letterSpacing: '2px', textTransform: 'uppercase' }}>
              {!collapsed && section.label}
            </div>
            {section.items.map(item => {
              const active = isActive(item.href);
              return (
                <a key={item.href} href={item.href}
                  className={`sidebar-link ${active ? 'active' : ''}`}
                  style={{
                    color: active ? t.primary[500] : t.text.secondary,
                    background: active ? t.accent.muted : 'transparent',
                    borderRadius: theme.effects.radius.sm,
                    borderLeft: active ? `3px solid ${t.primary[500]}` : '3px solid transparent',
                    padding: '0.55rem 0.75rem',
                    display: 'flex',
                    alignItems: 'center',
                    gap: '0.65rem',
                    fontSize: '0.84rem',
                    fontWeight: 500,
                    textDecoration: 'none',
                    transition: theme.effects.transition,
                    cursor: 'pointer',
                  }}>
                  <i className={`icon-${item.icon}`} style={{ fontSize: '1.05rem', width: 20, textAlign: 'center' }} />
                  {!collapsed && <span>{item.label}</span>}
                </a>
              );
            })}
          </div>
        ))}
      </nav>

      {/* Footer */}
      <div className="sidebar-footer" style={{ borderTop: `1px solid ${t.border.DEFAULT}` }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', padding: '0.5rem' }}>
          <div style={{
            width: 34, height: 34, borderRadius: 8,
            background: `linear-gradient(135deg, ${t.primary[500]}, ${t.secondary[500]})`,
            color: '#fff', display: 'flex', alignItems: 'center', justifyContent: 'center',
            fontWeight: 600, fontSize: '0.8rem',
          }}>U</div>
          {!collapsed && (
            <div>
              <div style={{ fontSize: '0.8rem', fontWeight: 600, color: t.text.primary }}>Admin</div>
              <div style={{ fontSize: '0.7rem', color: t.text.muted }}>admin@aureliahost.com</div>
            </div>
          )}
        </div>
      </div>

      <style jsx>{`
        .sidebar-link:hover { background: ${t.accent.muted} !important; }
        .sidebar-section-label { padding: 1rem 0.5rem 0.4rem; }
        .sidebar { display: flex; flex-direction: column; height: 100vh; position: fixed; left: 0; top: 0; z-index: 100; overflow: hidden; }
        .sidebar-nav { flex: 1; overflow-y: auto; padding: 0.75rem; }
        .sidebar-header { height: 64px; display: flex; align-items: center; justify-content: space-between; padding: 0 1rem; }
        .sidebar-brand { display: flex; align-items: center; gap: 0.75rem; text-decoration: none; color: inherit; }
        .sidebar-logo { width: 36px; height: 36px; border-radius: 9px; display: flex; align-items: center; justify-content: center; }
        .sidebar-footer { flex-shrink: 0; }
      `}</style>
    </aside>
  );
}
