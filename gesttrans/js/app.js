// GestTrans Pro — app.js
'use strict';

function toggleSidebar() {
    const s = document.getElementById('sidebar');
    if (!s) return;
    s.classList.toggle('collapsed');
    localStorage.setItem('gt_sb', s.classList.contains('collapsed') ? '1' : '0');
}
document.addEventListener('DOMContentLoaded', () => {
    const s = document.getElementById('sidebar');
    if (s && localStorage.getItem('gt_sb') === '1') s.classList.add('collapsed');
    // Auto-dismiss flash
    const f = document.getElementById('flash-msg');
    if (f) setTimeout(() => { f.style.opacity='0'; f.style.transition='opacity .5s'; setTimeout(()=>f.remove(),500); }, 5000);
});

function openModal(id)  { const m=document.getElementById(id); if(m) m.classList.add('open'); }
function closeModal(id) { const m=document.getElementById(id); if(m) m.classList.remove('open'); }
document.addEventListener('click', e => { if(e.target.classList.contains('modal-over')) e.target.classList.remove('open'); });
document.addEventListener('keydown', e => { if(e.key==='Escape') document.querySelectorAll('.modal-over.open').forEach(m=>m.classList.remove('open')); });

function fmt(n) { return new Intl.NumberFormat('fr-FR').format(Math.round(n)); }

// Calcul automatique recette nette bordereau
function calcRecetteNette() {
    const r  = parseFloat(document.getElementById('recette_totale')?.value || 0);
    const c  = parseFloat(document.getElementById('carburant')?.value || 0);
    const p  = parseFloat(document.getElementById('peage_total')?.value || 0);
    const ra = parseFloat(document.getElementById('retenue_agence')?.value || 0);
    const rc = parseFloat(document.getElementById('ration_chauffeur')?.value || 0);
    const a  = parseFloat(document.getElementById('autres_depenses')?.value || 0);
    const nette = r - c - p - ra - rc - a;
    const el = document.getElementById('recette_nette_display');
    if (el) el.textContent = fmt(nette) + ' FCFA';
    const inp = document.getElementById('recette_nette_val');
    if (inp) inp.value = nette.toFixed(2);
    // Couleur
    if (el) el.style.color = nette >= 0 ? '#fff' : '#fca5a5';
    // Remplir détails
    const fields = {'d-recette':r,'d-carb':c,'d-peage':p,'d-retenue':ra,'d-ration':rc,'d-autres':a};
    Object.entries(fields).forEach(([id,v])=>{ const el2=document.getElementById(id); if(el2) el2.textContent=fmt(v)+' FCFA'; });
}
