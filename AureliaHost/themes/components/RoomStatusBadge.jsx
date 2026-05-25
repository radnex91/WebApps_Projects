/* ============================================================
   RoomStatusBadge — Statut de chambre
   occupied | vacant | maintenance | reserved
   ============================================================ */

import React from 'react';
import { useTheme } from '../index';

const STATUS_MAP = {
  available:    { label: 'Disponible',  colorKey: 'success' },
  occupied:     { label: 'Occupée',    colorKey: 'error' },
  maintenance:  { label: 'Maintenance', colorKey: 'warning' },
  reserved:     { label: 'Réservée',   colorKey: 'info' },
};

export default function RoomStatusBadge({ status = 'available', size = 'md' }) {
  const { theme } = useTheme();
  const t = theme.colors;
  const config = STATUS_MAP[status] || STATUS_MAP.available;
  const colors = t.status[config.colorKey];

  const sizes = {
    sm: { padding: '0.15em 0.45em', fontSize: '0.7rem', dotSize: 6 },
    md: { padding: '0.28em 0.7em', fontSize: '0.78rem', dotSize: 8 },
    lg: { padding: '0.4em 0.9em', fontSize: '0.88rem', dotSize: 9 },
  };
  const s = sizes[size];

  return (
    <span style={{
      display: 'inline-flex',
      alignItems: 'center',
      gap: '0.4em',
      padding: s.padding,
      fontSize: s.fontSize,
      fontWeight: 500,
      borderRadius: theme.effects.radius.sm,
      background: colors.bg,
      color: colors.text,
      border: `1px solid ${colors.dot}25`,
      fontFamily: theme.typography.fontFamily.body.join(','),
      whiteSpace: 'nowrap',
    }}>
      <span style={{
        width: s.dotSize,
        height: s.dotSize,
        borderRadius: '50%',
        background: colors.dot,
        flexShrink: 0,
      }} />
      {config.label}
    </span>
  );
}

/* ===== Badge générique (statuts réservation, facture, congé...) ===== */
export function StatusBadge({ status, type = 'neutral', size = 'md' }) {
  const { theme } = useTheme();
  const t = theme.colors;
  const colors = t.status[type] || t.status.neutral;

  const sizes = {
    sm: { padding: '0.15em 0.45em', fontSize: '0.7rem' },
    md: { padding: '0.28em 0.65em', fontSize: '0.78rem' },
  };
  const s = sizes[size];

  return (
    <span style={{
      display: 'inline-block',
      padding: s.padding,
      fontSize: s.fontSize,
      fontWeight: 500,
      borderRadius: '5px',
      background: colors.bg,
      color: colors.text,
      border: `1px solid ${colors.dot}20`,
      fontFamily: theme.typography.fontFamily.body.join(','),
      letterSpacing: '0.2px',
    }}>
      {status}
    </span>
  );
}
