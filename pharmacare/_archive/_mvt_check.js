
(function(){
  var form   = document.getElementById('periode-form');
  var hidden = document.getElementById('periode-hidden');
  var dDebut = document.getElementById('date-debut');
  var dFin   = document.getElementById('date-fin');

  function iso(d){ return d.toISOString().slice(0,10); }
  function today(){ return new Date(); }

  // Raccourcis prédéfinis : on désactive les dates et on soumet
  window.selectPreset = function(val){
    hidden.value = val;
    if (dDebut) dDebut.disabled = true;
    if (dFin)   dFin.disabled   = true;
    form.submit();
  };

  // Dès qu'on touche aux dates → bascule en mode personnalisé
  window.onCustomDate = function(){
    hidden.value = 'perso';
    // corrige l'ordre si fin < debut
    if (dDebut && dFin && dFin.value && dDebut.value && dFin.value < dDebut.value) {
      var tmp = dDebut.value; dDebut.value = dFin.value; dFin.value = tmp;
    }
  };

  // Raccourcis rapides de plage personnalisée
  window.quickRange = function(days){
    var end = today();
    var start = new Date(end.getTime() - (days - 1) * 86400000);
    dDebut.value = iso(start);
    dFin.value   = iso(end);
    onCustomDate();
    form.submit();
  };

  window.thisMonth = function(){
    var n = new Date();
    dDebut.value = n.getFullYear() + '-' + String(n.getMonth()+1).padStart(2,'0') + '-01';
    dFin.value   = iso(n);
    onCustomDate();
    form.submit();
  };

  // ── Impression du rapport (throttle) ─────────────────────
  window.printRapport = function(){
    if (!rateLimitClick('print.rapport', 10, 60000)) { rateLimitWarn('print.rapport', 10, 60000); return; }
    var src = document.getElementById('print-rapport-content');
    if (!src) { window.print(); return; }

    // Iframe caché : pas de blocage par le pop-up blocker
    var old = document.getElementById('print-rapport-iframe');
    if (old) old.parentNode.removeChild(old);

    var iframe = document.createElement('iframe');
    iframe.id = 'print-rapport-iframe';
    iframe.style.cssText = 'position:fixed;width:0;height:0;border:0;left:-9999px;top:0;';
    document.body.appendChild(iframe);

    var doc = iframe.contentWindow.document;
    doc.open();
    doc.write('<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">'
      + '<title>Rapport des mouvements de vente</title>'
      + '<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">'
      + '<style>'
      + '*{margin:0;padding:0;box-sizing:border-box;}'
      + 'body{font-family:\'Manrope\',sans-serif;color:#1e293b;padding:24px;max-width:1000px;margin:0 auto;}'
      + '.pr-header{text-align:center;border-bottom:2px solid #1e293b;padding-bottom:14px;margin-bottom:18px;}'
      + '.pr-app{font-size:20px;font-weight:700;letter-spacing:.3px;}'
      + '.pr-title{font-size:16px;font-weight:600;margin-top:4px;color:#334155;}'
      + '.pr-period{font-size:13px;margin-top:8px;color:#475569;}'
      + '.pr-meta{font-size:11px;color:#94a3b8;margin-top:4px;}'
      + '.pr-table{width:100%;border-collapse:collapse;font-size:11px;margin-top:6px;}'
      + '.pr-table th{background:#1e293b;color:#fff;padding:7px 6px;text-align:left;font-size:10px;text-transform:uppercase;letter-spacing:.3px;}'
      + '.pr-table th.num{text-align:right;}'
      + '.pr-table td{padding:6px 6px;border-bottom:1px solid #e2e8f0;}'
      + '.pr-table td.num{text-align:right;font-family:\'DM Mono\',monospace;}'
      + '.pr-table tbody tr:nth-child(even){background:#f8fafc;}'
      + '.pr-table tfoot td{font-weight:700;border-top:2px solid #1e293b;background:#f1f5f9;padding:8px 6px;}'
      + '.pr-table tfoot td.num{font-family:\'DM Mono\',monospace;}'
      + '.pr-empty{text-align:center;padding:24px;color:#94a3b8;}'
      + '.pr-summary{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-top:18px;border-top:1px solid #e2e8f0;padding-top:14px;}'
      + '.pr-summary div{font-size:12px;}'
      + '.pr-summary span{color:#64748b;}'
      + '.pr-summary strong{font-family:\'DM Mono\',monospace;}'
      + '@media print{body{padding:0;max-width:none;}@page{margin:12mm;size:A4 landscape;}}'
      + '</style></head><body>' + src.innerHTML + '</body></html>');
    doc.close();

    // Lancer l'impression une fois le contenu (et les polices) prêt
    var done = false;
    function launch(){
      if (done) return; done = true;
      try { iframe.contentWindow.focus(); iframe.contentWindow.print(); } catch (e) { window.print(); }
    }
    if (doc.readyState === 'complete') {
      setTimeout(launch, 250);
    } else {
      iframe.onload = function(){ setTimeout(launch, 250); };
      setTimeout(launch, 1500); // filet de sécurité
    }
  };

  // ── Tableau des mouvements ───────────────────────────────
  var mvtTable = document.getElementById('mvt-table');
  var mvtRows  = mvtTable ? mvtTable.querySelectorAll('tbody tr[data-search]') : [];
  var LIMIT    = 20;
  var expanded = false;

  function applyMvtView(){
    if (!mvtRows.length) return;
    var q = (document.getElementById('mvt-search').value || '').toLowerCase().trim();
    var shown = 0;
    mvtRows.forEach(function(r){
      var match = !q || r.getAttribute('data-search').indexOf(q) !== -1;
      var visible = match && (expanded || shown < LIMIT);
      r.style.display = visible ? '' : 'none';
      if (visible) shown++;
    });
  }

  window.filterMvt = function(){
    // quand on filtre, on affiche tous les résultats correspondants
    if (!expanded){
      expanded = true;
      var more = document.getElementById('mvt-more');
      if (more) more.style.display = 'none';
    }
    applyMvtView();
    var cnt = document.getElementById('mvt-count');
    if (cnt) {
      var q = (document.getElementById('mvt-search').value || '').toLowerCase().trim();
      var n = 0;
      mvtRows.forEach(function(r){ if (!q || r.getAttribute('data-search').indexOf(q) !== -1) n++; });
      cnt.textContent = n + ' ligne(s)';
    }
  };

  window.showAllMvt = function(){
    expanded = true;
    applyMvtView();
    var more = document.getElementById('mvt-more');
    if (more) more.style.display = 'none';
  };

  window.exportMvtCSV = function(){
    if (!rateLimitClick('export.mvt', 10, 60000)) { rateLimitWarn('export.mvt', 10, 60000); return; }
    if (!mvtRows.length) return;
    var headers = ['Date/Heure','Reference','Client','Caissier','Medicament','Qte','Prix unitaire','Total ligne','Paiement','Statut'];
    var lines = [headers.join(';')];
    mvtRows.forEach(function(r){
      var cells = r.querySelectorAll('td');
      if (cells.length < 10) return;
      // reconstruit les valeurs brutes à partir du contenu textuel des cellules
      var raw = [];
      for (var i = 0; i < 10; i++) {
        var txt = cells[i].textContent.replace(/\s+/g, ' ').trim();
        // les montants contiennent le symbole devise + espaces → on garde tel quel
        raw.push('"' + txt.replace(/"/g, '""') + '"');
      }
      lines.push(raw.join(';'));
    });
    var blob = new Blob(["﻿" + lines.join("\n")], {type: 'text/csv;charset=utf-8;'});
    var a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'mouvements_ventes_2026-04-18_2026-07-16.csv';
    document.body.appendChild(a); a.click(); document.body.removeChild(a);
  };

  // état initial : limiter à 20 lignes
  applyMvtView();
})();
