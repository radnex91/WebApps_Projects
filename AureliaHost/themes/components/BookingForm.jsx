/* ============================================================
   BookingForm — Formulaire de réservation / check-in
   Adaptatif au thème, validation inline, premium inputs
   ============================================================ */

import React, { useState } from 'react';
import { useTheme } from '../index';

export default function BookingForm({ onSubmit, initialData = {}, mode = 'reservation' }) {
  const { theme } = useTheme();
  const t = theme.colors;

  const [form, setForm] = useState({
    client:      initialData.client || '',
    room:        initialData.room || '',
    checkin:     initialData.checkin || '',
    checkout:    initialData.checkout || '',
    adults:      initialData.adults || 1,
    children:    initialData.children || 0,
    notes:       initialData.notes || '',
    services:    initialData.services || [],
  });
  const [errors, setErrors] = useState({});
  const [submitting, setSubmitting] = useState(false);

  const handleChange = (field) => (e) => {
    setForm(prev => ({ ...prev, [field]: e.target.value }));
    if (errors[field]) setErrors(prev => ({ ...prev, [field]: null }));
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    const errs = {};
    if (!form.client)   errs.client   = 'Client requis';
    if (!form.room)     errs.room     = 'Chambre requise';
    if (!form.checkin)  errs.checkin  = 'Date d\'arrivée requise';
    if (!form.checkout) errs.checkout = 'Date de départ requise';
    if (Object.keys(errs).length > 0) { setErrors(errs); return; }

    setSubmitting(true);
    try { await onSubmit?.(form); }
    finally { setSubmitting(false); }
  };

  const inputStyle = {
    width: '100%',
    padding: '0.6rem 0.85rem',
    background: t.surface.input,
    border: `1px solid ${t.border.DEFAULT}`,
    borderRadius: theme.effects.radius.sm,
    color: t.text.primary,
    fontSize: '0.875rem',
    fontFamily: theme.typography.fontFamily.body.join(','),
    transition: theme.effects.transition,
    outline: 'none',
  };

  const labelStyle = {
    display: 'block',
    fontSize: '0.78rem',
    fontWeight: 600,
    color: t.text.secondary,
    marginBottom: '0.35rem',
    textTransform: 'uppercase',
    letterSpacing: '0.5px',
    fontFamily: theme.typography.fontFamily.body.join(','),
  };

  return (
    <form onSubmit={handleSubmit} style={{
      background: t.surface.card,
      border: `1px solid ${t.border.DEFAULT}`,
      borderRadius: theme.effects.radius.md,
      boxShadow: theme.effects.shadow.card,
      padding: '1.5rem',
    }}>
      <h3 style={{
        fontFamily: theme.typography.fontFamily.heading.join(','),
        fontWeight: theme.typography.fontWeight.heading,
        fontSize: '1.1rem',
        color: t.text.primary,
        marginBottom: '1.25rem',
      }}>
        {mode === 'reservation' ? 'Nouvelle réservation' : 'Check-in'}
      </h3>

      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '1rem' }}>
        {/* Client */}
        <div>
          <label style={labelStyle}>Client</label>
          <select style={inputStyle} value={form.client} onChange={handleChange('client')}
            onFocus={e => { e.target.style.borderColor = t.border.focus; e.target.style.boxShadow = `0 0 0 3px ${t.accent.muted}`; }}
            onBlur={e => { e.target.style.borderColor = t.border.DEFAULT; e.target.style.boxShadow = 'none'; }}>
            <option value="">Sélectionner un client...</option>
            <option value="1">Jean Dupont</option>
            <option value="2">Marie Lambert</option>
          </select>
          {errors.client && <span style={{ color: t.status.error.text, fontSize: '0.72rem', marginTop: '0.25rem', display: 'block' }}>{errors.client}</span>}
        </div>

        {/* Chambre */}
        <div>
          <label style={labelStyle}>Chambre</label>
          <select style={inputStyle} value={form.room} onChange={handleChange('room')}
            onFocus={e => { e.target.style.borderColor = t.border.focus; e.target.style.boxShadow = `0 0 0 3px ${t.accent.muted}`; }}
            onBlur={e => { e.target.style.borderColor = t.border.DEFAULT; e.target.style.boxShadow = 'none'; }}>
            <option value="">Sélectionner une chambre...</option>
            <option value="101">101 — Chambre Simple (350 DH)</option>
            <option value="201">201 — Chambre Double (500 DH)</option>
            <option value="301">301 — Suite Junior (850 DH)</option>
          </select>
          {errors.room && <span style={{ color: t.status.error.text, fontSize: '0.72rem', marginTop: '0.25rem', display: 'block' }}>{errors.room}</span>}
        </div>

        {/* Check-in */}
        <div>
          <label style={labelStyle}>Date d'arrivée</label>
          <input type="date" style={inputStyle} value={form.checkin} onChange={handleChange('checkin')}
            onFocus={e => { e.target.style.borderColor = t.border.focus; e.target.style.boxShadow = `0 0 0 3px ${t.accent.muted}`; }}
            onBlur={e => { e.target.style.borderColor = t.border.DEFAULT; e.target.style.boxShadow = 'none'; }} />
          {errors.checkin && <span style={{ color: t.status.error.text, fontSize: '0.72rem', marginTop: '0.25rem', display: 'block' }}>{errors.checkin}</span>}
        </div>

        {/* Check-out */}
        <div>
          <label style={labelStyle}>Date de départ</label>
          <input type="date" style={inputStyle} value={form.checkout} onChange={handleChange('checkout')}
            onFocus={e => { e.target.style.borderColor = t.border.focus; e.target.style.boxShadow = `0 0 0 3px ${t.accent.muted}`; }}
            onBlur={e => { e.target.style.borderColor = t.border.DEFAULT; e.target.style.boxShadow = 'none'; }} />
          {errors.checkout && <span style={{ color: t.status.error.text, fontSize: '0.72rem', marginTop: '0.25rem', display: 'block' }}>{errors.checkout}</span>}
        </div>

        {/* Adultes */}
        <div>
          <label style={labelStyle}>Adultes</label>
          <input type="number" min="1" max="10" style={inputStyle} value={form.adults} onChange={handleChange('adults')} />
        </div>

        {/* Enfants */}
        <div>
          <label style={labelStyle}>Enfants</label>
          <input type="number" min="0" max="10" style={inputStyle} value={form.children} onChange={handleChange('children')} />
        </div>
      </div>

      {/* Notes */}
      <div style={{ marginTop: '1rem' }}>
        <label style={labelStyle}>Notes</label>
        <textarea rows="2" style={{ ...inputStyle, resize: 'vertical' }} value={form.notes} onChange={handleChange('notes')}
          placeholder="Préférences, demandes spéciales..." />
      </div>

      {/* Submit */}
      <div style={{ marginTop: '1.25rem', display: 'flex', gap: '0.75rem', justifyContent: 'flex-end' }}>
        <button type="button" style={{
          padding: '0.6rem 1.25rem',
          background: 'transparent',
          border: `1px solid ${t.border.DEFAULT}`,
          borderRadius: theme.effects.radius.sm,
          color: t.text.secondary,
          cursor: 'pointer',
          fontSize: '0.85rem',
          fontFamily: theme.typography.fontFamily.body.join(','),
          transition: theme.effects.transition,
        }}>Annuler</button>
        <button type="submit" disabled={submitting} style={{
          padding: '0.6rem 1.5rem',
          background: submitting ? t.text.muted : `linear-gradient(135deg, ${t.primary[500]}, ${t.primary[600]})`,
          border: 'none',
          borderRadius: theme.effects.radius.sm,
          color: '#fff',
          fontWeight: 600,
          cursor: submitting ? 'not-allowed' : 'pointer',
          fontSize: '0.85rem',
          fontFamily: theme.typography.fontFamily.body.join(','),
          boxShadow: theme.effects.shadow.elevate,
          transition: theme.effects.transition,
          display: 'flex',
          alignItems: 'center',
          gap: '0.4rem',
        }}>
          {submitting ? '⏳' : '✓'} {submitting ? 'Enregistrement...' : 'Confirmer la réservation'}
        </button>
      </div>
    </form>
  );
}
