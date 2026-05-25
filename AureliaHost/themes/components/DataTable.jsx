/* ============================================================
   DataTable — Liste de données PMS
   Guest list, room status, invoices, employees...
   ============================================================ */

import React from 'react';
import { useTheme } from '../index';
import { StatusBadge } from './RoomStatusBadge';

export default function DataTable({
  columns = [],
  rows = [],
  onRowClick,
  emptyMessage = 'Aucune donnée à afficher.',
}) {
  const { theme } = useTheme();
  const t = theme.colors;

  return (
    <div style={{
      background: t.surface.card,
      border: `1px solid ${t.border.DEFAULT}`,
      borderRadius: theme.effects.radius.md,
      boxShadow: theme.effects.shadow.card,
      overflow: 'hidden',
    }}>
      <div style={{ overflowX: 'auto' }}>
        <table style={{
          width: '100%',
          borderCollapse: 'collapse',
          fontSize: '0.85rem',
          fontFamily: theme.typography.fontFamily.body.join(','),
        }}>
          <thead>
            <tr style={{
              background: theme.id === 'dark' ? 'rgba(255,255,255,0.02)' : 'rgba(0,0,0,0.02)',
            }}>
              {columns.map((col, i) => (
                <th key={i} style={{
                  padding: '0.8rem 1rem',
                  textAlign: 'left',
                  fontSize: '0.7rem',
                  fontWeight: 600,
                  textTransform: 'uppercase',
                  letterSpacing: '0.7px',
                  color: t.text.muted,
                  borderBottom: `2px solid ${t.border.DEFAULT}`,
                  fontFamily: theme.typography.fontFamily.body.join(','),
                  whiteSpace: 'nowrap',
                }}>
                  {col.header}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {rows.length === 0 ? (
              <tr>
                <td colSpan={columns.length} style={{
                  padding: '3rem 1rem',
                  textAlign: 'center',
                  color: t.text.muted,
                  fontSize: '0.9rem',
                }}>
                  {emptyMessage}
                </td>
              </tr>
            ) : (
              rows.map((row, ri) => (
                <tr
                  key={ri}
                  onClick={() => onRowClick?.(row)}
                  style={{
                    cursor: onRowClick ? 'pointer' : 'default',
                    transition: theme.effects.transition,
                    borderBottom: `1px solid ${t.border.light || t.border.DEFAULT}`,
                  }}
                  onMouseEnter={e => { e.currentTarget.style.background = theme.colors.accent.muted; }}
                  onMouseLeave={e => { e.currentTarget.style.background = 'transparent'; }}
                >
                  {columns.map((col, ci) => (
                    <td key={ci} style={{
                      padding: '0.7rem 1rem',
                      color: t.text.primary,
                      verticalAlign: 'middle',
                    }}>
                      {col.render ? col.render(row[col.accessor], row) : row[col.accessor]}
                    </td>
                  ))}
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>

      {/* Pagination placeholder */}
      {rows.length > 10 && (
        <div style={{
          display: 'flex',
          justifyContent: 'space-between',
          alignItems: 'center',
          padding: '0.75rem 1rem',
          borderTop: `1px solid ${t.border.DEFAULT}`,
          fontSize: '0.8rem',
          color: t.text.secondary,
          background: theme.id === 'dark' ? 'rgba(0,0,0,0.12)' : 'rgba(0,0,0,0.01)',
        }}>
          <span>{rows.length} résultats</span>
          <div style={{ display: 'flex', gap: '4px' }}>
            {[1, 2, 3].map(p => (
              <button key={p} style={{
                padding: '0.3rem 0.65rem',
                borderRadius: theme.effects.radius.sm,
                border: `1px solid ${t.border.DEFAULT}`,
                background: p === 1 ? t.primary[500] : 'transparent',
                color: p === 1 ? '#fff' : t.text.secondary,
                cursor: 'pointer',
                fontSize: '0.8rem',
                fontFamily: theme.typography.fontFamily.body.join(','),
              }}>{p}</button>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}

/* ===== DataTable avec statuts de chambre (exemple prêt à l'emploi) ===== */
export function RoomStatusTable({ rooms = [], onRoomClick }) {
  const columns = [
    { header: 'N°', accessor: 'number' },
    { header: 'Étage', accessor: 'floor' },
    { header: 'Type', accessor: 'type' },
    { header: 'Prix/nuit', accessor: 'price', render: (v) => `${v} DH` },
    {
      header: 'Statut',
      accessor: 'status',
      render: (v) => <StatusBadge status={v} type={
        v === 'Disponible' ? 'success' : v === 'Occupée' ? 'error' : v === 'Maintenance' ? 'warning' : 'info'
      } />,
    },
  ];
  return <DataTable columns={columns} rows={rooms} onRowClick={onRoomClick} emptyMessage="Aucune chambre trouvée." />;
}
