/* ============================================================
   DashboardCards — KPIs hôteliers
   Occupancy, Revenue, Reservations, etc.
   ============================================================ */

import React from 'react';
import { useTheme } from '../index';

const KPI_CARDS = [
  { key: 'rooms',        icon: 'door-open',     label: 'Chambres totales',  gradient: 'primary' },
  { key: 'available',    icon: 'check-circle',  label: 'Disponibles',       gradient: 'success' },
  { key: 'occupied',     icon: 'x-circle',      label: 'Occupées',          gradient: 'error' },
  { key: 'reservations', icon: 'calendar-check', label: 'Réserv. actives',  gradient: 'info' },
  { key: 'unpaid',       icon: 'receipt',       label: 'Fact. impayées',   gradient: 'warning' },
  { key: 'revenue',      icon: 'cash-stack',    label: 'CA du mois',        gradient: 'secondary' },
];

const GRADIENT_MAP = {
  primary:   ['#2563eb', '#1d4ed8'],
  success:   ['#059669', '#047857'],
  error:     ['#dc2626', '#b91c1c'],
  info:      ['#0891b2', '#0e7490'],
  warning:   ['#d97706', '#b45309'],
  secondary: ['#4b5563', '#374151'],
};

export default function DashboardCards({ data = {} }) {
  const { theme } = useTheme();

  return (
    <div style={{
      display: 'grid',
      gridTemplateColumns: 'repeat(auto-fit, minmax(170px, 1fr))',
      gap: '0.85rem',
      marginBottom: '1.25rem',
    }}>
      {KPI_CARDS.map(card => {
        const [from, to] = GRADIENT_MAP[card.gradient];
        return (
          <div key={card.key} style={{
            background: `linear-gradient(135deg, ${from}, ${to})`,
            border: `1px solid ${from}40`,
            borderRadius: theme.effects.radius.md,
            padding: '1.25rem 1rem',
            textAlign: 'center',
            color: '#fff',
            boxShadow: `0 4px 18px ${from}30`,
            transition: theme.effects.transition,
            cursor: 'default',
          }}>
            <i className={`icon-${card.icon}`} style={{ fontSize: '1.8rem', opacity: 0.8 }} />
            <h3 style={{
              fontSize: '1.75rem',
              fontWeight: 700,
              margin: '0.5rem 0 0.15rem',
              fontFamily: theme.typography.fontFamily.heading.join(','),
              letterSpacing: '-0.5px',
            }}>
              {data[card.key] ?? '—'}
            </h3>
            <small style={{
              fontSize: '0.7rem',
              textTransform: 'uppercase',
              letterSpacing: '0.6px',
              fontWeight: 500,
              opacity: 0.85,
              fontFamily: theme.typography.fontFamily.body.join(','),
            }}>
              {card.label}
            </small>
          </div>
        );
      })}
    </div>
  );
}
