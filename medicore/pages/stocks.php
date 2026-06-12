<?php
$currentPage = 'stocks';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

// ── Créer un article stock ──
if (can('stocks.create') && $_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'create_stock') {
    csrf_verify();
    $v = (new Validator())->required('nom','Nom')->int_range('quantite',0,9999999,'Quantité');
    if (!$v->passes()) { $flash=['red',$v->first_error()]; }
    else {
        $qte   = post_int('quantite');
        $seuil = post_int('seuil_alerte',50);
        $statut = $qte<=$seuil*0.5?'critique':($qte<=$seuil?'bas':'normal');
        db_exec("INSERT INTO stocks (nom,categorie,quantite,unite,valeur_unitaire,fournisseur,seuil_alerte,statut) VALUES (?,?,?,?,?,?,?,?)",
            [$v->get('nom'),post_str('categorie'),$qte,post_str('unite'),post_float('valeur_unitaire'),post_str('fournisseur'),$seuil,$statut]);
        logActivity('Stock ajouté: '.$v->get('nom'), 'blue', 'stock');
        header('Location: '.APP_URL.'/stocks.php?ok=1'); exit;
    }
}

// ── Modifier quantité (inline) ──
if (can('stocks.update_qte') && $_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'update_qte') {
    csrf_verify();
    $id  = post_int('stock_id');
    $qte = post_int('quantite');
    $s   = assert_owns('stocks', $id);
    $seuil  = (int)$s['seuil_alerte'];
    $statut = $qte<=$seuil*0.5?'critique':($qte<=$seuil?'bas':'normal');
    db_exec("UPDATE stocks SET quantite=?,statut=? WHERE id=?",[$qte,$statut,$id]);
    logActivity("Stock #$id mis à jour: $qte", 'blue', 'stock', $id);
    header('Location: '.APP_URL.'/stocks.php?ok=1'); exit;
}

// ── Modifier article stock ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'update_stock' && can('stocks.create')) {
    csrf_verify();
    $id = post_int('stock_id');
    $nom = trim(post_str('nom'));
    if (!$nom) { $flash = ['red','Nom obligatoire.']; }
    else {
        $cat  = in_whitelist(post_str('categorie'),['consommable','equipement','protection','autre'],'consommable');
        $qte  = max(0, post_int('quantite', 0));
        $val  = max(0, (float)($_POST['valeur_unitaire'] ?? 0));
        $seuil= max(0, post_int('seuil_alerte', 0));
        $statut = $qte <= 0 ? 'critique' : ($qte <= $seuil ? 'bas' : 'normal');
        db_exec(
            "UPDATE stocks SET nom=?,categorie=?,quantite=?,valeur_unitaire=?,seuil_alerte=?,fournisseur=?,unite=?,statut=? WHERE id=?",
            [$nom,$cat,$qte,$val,$seuil,post_str('fournisseur'),post_str('unite'),$statut,$id]
        );
        logActivity("Stock modifié: $nom (qté: $qte)", 'blue', 'stock', $id);
        $flash = ['green','Article mis à jour.'];
        header('Location: '.APP_URL.'/stocks.php?saved=1'); exit;
    }
}

// ── CRÉER UNE ENTRÉE STOCK ──
if (can('stocks.entry') && $_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'create_entry') {
    csrf_verify();
    $type  = in_whitelist(post_str('type'), ['stock','medicament'], 'stock');
    $date  = post_str('date_reception');
    $fourn = post_str('fournisseur');
    $notes = post_str('notes');

    $article_ids  = $_POST['article_id']  ?? [];
    $quantites    = $_POST['eqty']         ?? [];
    $prix_u       = $_POST['epu_raw']      ?? [];

    // Validation
    $valid = !empty($date);
    $lignes_ok = false;
    $montant_total = 0;
    $lignes = [];
    for ($i = 0; $i < count($article_ids); $i++) {
        $aid = (int)($article_ids[$i] ?? 0);
        $qty = max(0, (int)($quantites[$i] ?? 0));
        $pu  = max(0, (float)($prix_u[$i] ?? 0));
        if ($aid > 0 && $qty > 0) {
            $tl = $qty * $pu;
            $montant_total += $tl;
            $lignes[] = ['article_id' => $aid, 'quantite' => $qty, 'prix_unitaire' => $pu, 'total_ligne' => $tl];
            $lignes_ok = true;
        }
    }
    if (!$valid || !$lignes_ok) {
        $flash = ['red', 'Date et au moins un article avec quantité > 0 requis.'];
    } else {
        // Générer référence
        $next = (int)db_scalar("SELECT COALESCE(MAX(CAST(SUBSTRING(reference,9) AS UNSIGNED)),0)+1 FROM stock_entries WHERE reference LIKE ?", ['ENT-'.date('Y').'%']);
        $ref = 'ENT-' . date('Y') . '-' . str_pad($next, 5, '0', STR_PAD_LEFT);

        $uid = (int)($_SESSION['user_id'] ?? 0);
        $entry_id = db_exec(
            "INSERT INTO stock_entries (reference,type,fournisseur,date_reception,notes,utilisateur_id,montant_total) VALUES (?,?,?,?,?,?,?)",
            [$ref, $type, $fourn, $date, $notes, $uid, $montant_total]
        );
        foreach ($lignes as $l) {
            db_exec(
                "INSERT INTO stock_entry_lignes (entry_id,article_id,article_type,quantite,prix_unitaire,total_ligne) VALUES (?,?,?,?,?,?)",
                [$entry_id, $l['article_id'], $type, $l['quantite'], $l['prix_unitaire'], $l['total_ligne']]
            );
        }
        logActivity("Entrée stock créée: $ref ($type)", 'blue', 'stock_entry', $entry_id);
        header('Location: '.APP_URL.'/stocks.php?entry_ok=1'); exit;
    }
}

// ── VALIDER UNE ENTRÉE (incrémenter le stock) ──
if (can('stocks.entry') && $_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'validate_entry') {
    csrf_verify();
    $id = post_int('entry_id');
    $entry = assert_owns('stock_entries', $id);
    if ($entry['statut'] !== 'en_attente') {
        $flash = ['red', 'Cette entrée a déjà été traitée.'];
    } else {
        $lignes = db_select("SELECT * FROM stock_entry_lignes WHERE entry_id=?", [$id]);
        foreach ($lignes as $l) {
            if ($l['article_type'] === 'stock') {
                db_exec("UPDATE stocks SET quantite = quantite + ? WHERE id=?", [$l['quantite'], $l['article_id']]);
                $s = db_row("SELECT quantite, seuil_alerte FROM stocks WHERE id=?", [$l['article_id']]);
                if ($s) {
                    $st = $s['quantite'] <= $s['seuil_alerte']*0.5 ? 'critique' : ($s['quantite'] <= $s['seuil_alerte'] ? 'bas' : 'normal');
                    db_exec("UPDATE stocks SET statut=? WHERE id=?", [$st, $l['article_id']]);
                }
            } else {
                db_exec("UPDATE medicaments SET stock_actuel = stock_actuel + ? WHERE id=?", [$l['quantite'], $l['article_id']]);
                $m = db_row("SELECT stock_actuel, stock_minimum FROM medicaments WHERE id=?", [$l['article_id']]);
                if ($m) {
                    $st = $m['stock_actuel'] <= $m['stock_minimum']*0.5 ? 'critique' : ($m['stock_actuel'] <= $m['stock_minimum'] ? 'bas' : 'normal');
                    db_exec("UPDATE medicaments SET statut=? WHERE id=?", [$st, $l['article_id']]);
                }
            }
        }
        db_exec("UPDATE stock_entries SET statut='validee' WHERE id=?", [$id]);
        logActivity("Entrée stock validée: {$entry['reference']}", 'green', 'stock_entry', $id);
        header('Location: '.APP_URL.'/stocks.php?entry_validated=1'); exit;
    }
}

// ── ANNULER UNE ENTRÉE ──
if (can('stocks.entry') && get_str('action') === 'annuler_entry') {
    $id = get_signed_id('id', 'stock_entry');
    $entry = assert_owns('stock_entries', $id);
    if ($entry['statut'] !== 'en_attente') {
        $flash = ['red', 'Cette entrée a déjà été traitée.'];
    } else {
        db_exec("UPDATE stock_entries SET statut='annulee' WHERE id=?", [$id]);
        logActivity("Entrée stock annulée: {$entry['reference']}", 'red', 'stock_entry', $id);
        header('Location: '.APP_URL.'/stocks.php?entry_cancelled=1'); exit;
    }
}

// Charger article à modifier
$editStock = null;
if (get_int('edit_id') > 0 && can('stocks.create')) {
    $editStock = db_row("SELECT * FROM stocks WHERE id=?", [get_int('edit_id')]);
}

require_once __DIR__ . '/../includes/layout.php';
requirePageAccess('stocks');

$filtre = in_whitelist(get_str('filtre'),['','normal','bas','critique'],'');
$search = get_str('search');
$where  = '1=1';
$params = [];
if ($filtre) { $where.=' AND statut=?'; $params[]=$filtre; }
if ($search) { $like="%$search%"; $where.=' AND (nom LIKE ? OR categorie LIKE ? OR fournisseur LIKE ?)'; $params=array_merge($params,[$like,$like,$like]); }

$stocks = db_select("SELECT * FROM stocks WHERE $where ORDER BY statut DESC,nom", $params);
$total_val = array_sum(array_map(fn($s)=>$s['quantite']*$s['valeur_unitaire'],$stocks));

$stats = [
    'total'    => (int)db_scalar("SELECT COUNT(*) FROM stocks"),
    'critique' => (int)db_scalar("SELECT COUNT(*) FROM stocks WHERE statut='critique'"),
    'bas'      => (int)db_scalar("SELECT COUNT(*) FROM stocks WHERE statut='bas'"),
    'valeur'   => (float)db_scalar("SELECT COALESCE(SUM(quantite*valeur_unitaire),0) FROM stocks"),
];
$statutBadge = ['normal'=>'badge-green','bas'=>'badge-yellow','critique'=>'badge-red'];
$catOptions  = ['consommable','equipement','protection','autre'];

// Articles pour le sélecteur d'entrée stock
$stock_articles = db_select("SELECT id, nom, valeur_unitaire, unite FROM stocks ORDER BY nom");
$med_articles   = db_select("SELECT id, nom, prix_unitaire, unite FROM medicaments ORDER BY nom");

// Historique des entrées
$recent_entries = [];
if (can('stocks.entry')) {
    $recent_entries = db_select(
        "SELECT se.*, CONCAT(u.prenom,' ',u.nom) AS utilisateur_nom
         FROM stock_entries se
         LEFT JOIN utilisateurs u ON u.id = se.utilisateur_id
         ORDER BY se.created_at DESC LIMIT 20"
    );
    foreach ($recent_entries as &$re) {
        $re['lignes'] = db_select(
            "SELECT sel.*,
                    CASE WHEN sel.article_type='stock' THEN s.nom ELSE m.nom END AS article_nom,
                    CASE WHEN sel.article_type='stock' THEN s.unite ELSE m.unite END AS article_unite
             FROM stock_entry_lignes sel
             LEFT JOIN stocks s ON s.id = sel.article_id AND sel.article_type = 'stock'
             LEFT JOIN medicaments m ON m.id = sel.article_id AND sel.article_type = 'medicament'
             WHERE sel.entry_id = ?",
            [$re['id']]
        );
    }
    unset($re);
}
?>

<?php if (isset($_GET['ok'])): ?><div class="alert alert-green alert-auto"> Mis à jour.</div><?php endif; ?>
<?php if (isset($_GET['saved'])): ?>
<div class="alert alert-green alert-auto"> Modifications enregistrées avec succès.</div>
<?php endif; ?>
<?php if (isset($_GET['entry_ok'])): ?>
<div class="alert alert-green alert-auto">📥 Entrée stock enregistrée. En attente de validation.</div>
<?php endif; ?>
<?php if (isset($_GET['entry_validated'])): ?>
<div class="alert alert-green alert-auto">✅ Entrée stock validée — stocks mis à jour.</div>
<?php endif; ?>
<?php if (isset($_GET['entry_cancelled'])): ?>
<div class="alert alert-yellow alert-auto">❌ Entrée stock annulée.</div>
<?php endif; ?>
<?php if (!empty($flash)): ?><div class="alert alert-red alert-auto"> <?= h($flash[1]) ?></div><?php endif; ?>

<div class="page-header-row">
  <div><h2>📦 Stocks & Matériel</h2><p><?= $stats['total'] ?> références — Valeur totale : <?= fmt_money($stats['valeur']) ?></p></div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <?php if (can('stocks.entry')): ?>
    <button class="btn btn-green" onclick="document.getElementById('modal-entree').style.display='flex'">📥 Entrée stock</button>
    <?php endif; ?>
    <?php if (can('stocks.create')): ?>
    <button class="btn btn-blue" onclick="document.getElementById('modal-stock').style.display='flex'">+ Ajouter article</button>
    <?php endif; ?>
  </div>
</div>

<div class="stats-grid mb-24">
  <div class="stat-card blue"><div class="stat-icon blue">📦</div><div class="stat-value"><?= $stats['total'] ?></div><div class="stat-label">Références</div></div>
  <div class="stat-card green"><div class="stat-icon green">💰</div><div class="stat-value"><?= fmt_money($stats['valeur']) ?></div><div class="stat-label">Valeur du stock</div></div>
  <div class="stat-card yellow"><div class="stat-icon yellow">⚠️</div><div class="stat-value"><?= $stats['bas'] ?></div><div class="stat-label">Stocks bas</div></div>
  <div class="stat-card red"><div class="stat-icon red">🚨</div><div class="stat-value"><?= $stats['critique'] ?></div><div class="stat-label">Stocks critiques</div></div>
</div>

<?php if ($stats['critique']>0): ?>
<div class="alert alert-red mb-24">🚨 <strong><?= $stats['critique'] ?> article(s) en stock critique</strong> — Commande urgente recommandée.</div>
<?php endif; ?>

<!-- Filtres -->
<div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap">
  <form method="GET" style="display:flex;gap:6px;flex-wrap:wrap;flex:1">
    <input type="text" name="search" value="<?= h($search) ?>" placeholder="🔍 Article, catégorie, fournisseur..."
      style="flex:1;padding:8px 12px;background:var(--surface);border:1px solid var(--border2);border-radius:8px;color:var(--text);font-size:13px;outline:none">
    <?php foreach ([''=>'Tous','critique'=>'🔴 Critiques','bas'=>'🟡 Bas','normal'=>'🟢 Normal'] as $v2=>$l): ?>
    <label style="display:flex;align-items:center;padding:7px 12px;background:var(--surface);border:1px solid <?= $filtre===$v2?'var(--accent)':'var(--border)' ?>;border-radius:8px;cursor:pointer;font-size:12px;color:<?= $filtre===$v2?'var(--accent2)':'var(--text2)' ?>">
      <input type="radio" name="filtre" value="<?= $v2 ?>" <?= $filtre===$v2?'checked':'' ?> onchange="this.form.submit()" style="display:none"><?= $l ?>
    </label>
    <?php endforeach; ?>
    <?php if ($search): ?><a href="stocks.php" class="btn btn-ghost btn-sm">✕</a><?php endif; ?>
  </form>
</div>

<div class="card">
  <div class="card-header"><h3>Inventaire</h3><span style="font-size:12px;color:var(--text2)"><?= count($stocks) ?> articles</span></div>
  <table>
    <thead><tr><th>Article</th><th>Catégorie</th><th>Quantité</th><th>Seuil</th><th>Valeur unit.</th><th>Valeur totale</th><th>Fournisseur</th><th>Statut</th><th>Modifier qt</th></tr></thead>
    <tbody>
    <?php foreach ($stocks as $s):
      $rowBg = $s['statut']==='critique'?'background:rgba(var(--red-rgb),.05)':($s['statut']==='bas'?'background:rgba(var(--yellow-rgb),.04)':'');
    ?>
    <tr style="<?= $rowBg ?>">
      <td><strong><?= h($s['nom']) ?></strong></td>
      <td style="font-size:12px"><?= h(ucfirst($s['categorie']??'')) ?></td>
      <td>
        <div style="display:flex;align-items:center;gap:8px">
          <span style="font-weight:600;min-width:40px;color:<?= $s['statut']==='critique'?'var(--red)':($s['statut']==='bas'?'var(--yellow)':'var(--text)') ?>">
            <?= number_format((int)$s['quantite']) ?>
          </span>
          <span style="font-size:11px;color:var(--text3)"><?= h($s['unite']??'') ?></span>
          <?php if ($s['seuil_alerte'] > 0): ?>
          <div style="flex:1;max-width:80px;height:4px;background:var(--border2);border-radius:2px;overflow:hidden">
            <div style="height:100%;border-radius:2px;width:<?= min(100,round($s['quantite']/$s['seuil_alerte']*50)) ?>%;background:<?= $s['statut']==='critique'?'var(--red)':($s['statut']==='bas'?'var(--yellow)':'var(--green)') ?>"></div>
          </div>
          <?php endif; ?>
        </div>
      </td>
      <td style="font-size:12px;color:var(--text2)"><?= number_format((int)$s['seuil_alerte']) ?></td>
      <td><?= fmt_money((float)$s['valeur_unitaire']) ?></td>
      <td style="font-weight:600;color:var(--green)"><?= fmt_money((float)$s['quantite']*(float)$s['valeur_unitaire']) ?></td>
      <td style="font-size:12px;color:var(--text2)"><?= h($s['fournisseur']??'') ?></td>
      <td><span class="badge <?= $statutBadge[$s['statut']]??'badge-gray' ?>"><?= ($s['statut']==='critique'?'🔴 ':($s['statut']==='bas'?'🟡 ':'🟢 ')).ucfirst($s['statut']) ?></span></td>
      <td>
        <div style="display:flex;gap:4px;align-items:center;flex-wrap:wrap">
        <?php if (can('stocks.create')): ?>
        <a href="stocks.php?edit_id=<?= (int)$s['id'] ?>" class="btn btn-sm btn-ghost" title="Modifier">✏️</a>
        <?php endif; ?>
        <form method="POST" style="display:flex;gap:4px;align-items:center">
          <input type="hidden" name="action" value="update_qte">
          <input type="hidden" name="stock_id" value="<?= (int)$s['id'] ?>">
          <?= csrf_field() ?>
          <input type="number" name="quantite" value="<?= (int)$s['quantite'] ?>" min="0" style="width:70px;padding:5px 8px;background:var(--bg);border:1px solid var(--border2);border-radius:6px;color:var(--text);font-size:12px;outline:none">
          <button type="submit" class="btn btn-sm btn-blue">✓</button>
        </form>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($stocks)): ?><tr><td colspan="9" style="text-align:center;padding:32px;color:var(--text3)">Aucun article trouvé</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<!-- ════════════════ HISTORIQUE DES ENTRÉES ════════════════ -->
<?php if (can('stocks.entry')): ?>
<div class="card" style="margin-top:24px">
  <div class="card-header">
    <h3>📥 Historique des entrées</h3>
    <span style="font-size:12px;color:var(--text2)"><?= count($recent_entries) ?> entrée(s)</span>
  </div>
  <table>
    <thead><tr><th>Référence</th><th>Type</th><th>Date</th><th>Fournisseur</th><th>Montant</th><th>Statut</th><th>Par</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($recent_entries as $e):
      $eBadge = ['en_attente'=>'badge-yellow','validee'=>'badge-green','annulee'=>'badge-red'];
      $eLabel = ['en_attente'=>'⏳ En attente','validee'=>'✅ Validée','annulee'=>'✕ Annulée'];
    ?>
    <tr>
      <td><strong><?= h($e['reference']) ?></strong></td>
      <td style="font-size:12px"><?= $e['type']==='stock'?'📦 Matériel':'💊 Médicament' ?></td>
      <td style="font-size:12px"><?= fmt_date($e['date_reception']) ?></td>
      <td style="font-size:12px;color:var(--text2)"><?= h($e['fournisseur']??'-') ?></td>
      <td><strong style="color:var(--green)"><?= fmt_money((float)$e['montant_total']) ?></strong></td>
      <td><span class="badge <?= $eBadge[$e['statut']]??'badge-gray' ?>"><?= $eLabel[$e['statut']]??$e['statut'] ?></span></td>
      <td style="font-size:12px;color:var(--text2)"><?= h($e['utilisateur_nom']??'-') ?></td>
      <td style="display:flex;gap:4px;align-items:center">
        <button type="button" class="btn btn-sm btn-ghost" onclick="toggleEntryLignes(<?= (int)$e['id'] ?>)">🧾</button>
        <?php if ($e['statut'] === 'en_attente'): ?>
        <form method="POST" style="display:inline">
          <input type="hidden" name="action" value="validate_entry">
          <input type="hidden" name="entry_id" value="<?= (int)$e['id'] ?>">
          <?= csrf_field() ?>
          <button type="submit" class="btn btn-sm btn-green" onclick="return confirm('Valider cette entrée ? Les stocks seront mis à jour.')">✓</button>
        </form>
        <a href="stocks.php?action=annuler_entry&id=<?= (int)$e['id'] ?>&tok=<?= url_sign((int)$e['id'], 'stock_entry') ?>"
           class="btn btn-sm btn-red" data-confirm="Annuler cette entrée ?">✕</a>
        <?php endif; ?>
      </td>
    </tr>
    <tr id="entry-lignes-<?= (int)$e['id'] ?>" style="display:none">
      <td colspan="8" style="padding:12px 24px;background:var(--surface2)">
        <div style="font-size:11px;font-weight:600;color:var(--text2);text-transform:uppercase;letter-spacing:.04em;margin-bottom:8px">Articles de l'entrée</div>
        <?php foreach ($e['lignes'] as $l): ?>
        <div style="display:flex;align-items:center;gap:16px;font-size:13px;padding:4px 0;border-bottom:1px solid var(--border)">
          <span style="flex:1"><strong><?= h($l['article_nom']) ?></strong></span>
          <span style="color:var(--text2)">Qté: <strong style="color:var(--text)"><?= (int)$l['quantite'] ?></strong> <?= h($l['article_unite']??'') ?></span>
          <span style="color:var(--text2)"><?= fmt_money((float)$l['prix_unitaire']) ?>/u</span>
          <span style="color:var(--green);font-weight:600;min-width:80px;text-align:right"><?= fmt_money((float)$l['total_ligne']) ?></span>
        </div>
        <?php endforeach; ?>
        <?php if ($e['notes']): ?>
        <div style="margin-top:8px;font-size:12px;color:var(--text3)">📝 <?= h($e['notes']) ?></div>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($recent_entries)): ?>
    <tr><td colspan="8" style="text-align:center;padding:32px;color:var(--text3)">Aucune entrée enregistrée</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<!-- ════════════════ MODAL ENTRÉE STOCK ════════════════ -->
<?php if (can('stocks.entry')): ?>
<div id="modal-entree" class="modal-overlay" style="display:none;z-index:200;align-items:flex-start;justify-content:center;padding:20px;overflow-y:auto" role="dialog" aria-modal="true"
     onclick="if(event.target===this)this.style.display='none'">
  <div style="background:var(--surface);border:1px solid var(--border2);border-radius:16px;width:min(720px,95vw);box-shadow:0 24px 60px rgba(0,0,0,.7);margin:20px auto">

    <!-- Header -->
    <div style="padding:18px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:var(--surface);border-radius:16px 16px 0 0;z-index:1">
      <h3>📥 Nouvelle entrée stock</h3>
      <button type="button" class="modal-close" onclick="document.getElementById('modal-entree').style.display='none'" aria-label="Fermer">✕</button>
    </div>

    <form method="POST" id="form-entree" style="padding:24px">
      <input type="hidden" name="action" value="create_entry">
      <?= csrf_field() ?>

      <!-- Toggle type + date -->
      <div class="form-grid" style="margin-bottom:16px">
        <div class="form-group">
          <label>Type d'entrée *</label>
          <div style="display:flex;gap:6px">
            <label id="lbl-type-stock" style="flex:1;display:flex;align-items:center;justify-content:center;gap:6px;padding:9px 12px;border-radius:7px;cursor:pointer;border:2px solid var(--accent);background:rgba(var(--accent-rgb),.1);color:var(--accent2);font-weight:600;font-size:13px;transition:all .15s">
              <input type="radio" name="type" value="stock" checked onchange="switchType('stock')" style="display:none">📦 Matériel
            </label>
            <label id="lbl-type-med" style="flex:1;display:flex;align-items:center;justify-content:center;gap:6px;padding:9px 12px;border-radius:7px;cursor:pointer;border:1px solid var(--border2);color:var(--text2);font-weight:600;font-size:13px;transition:all .15s">
              <input type="radio" name="type" value="medicament" onchange="switchType('medicament')" style="display:none">💊 Médicaments
            </label>
          </div>
        </div>
        <div class="form-group">
          <label>Date de réception *</label>
          <input type="date" name="date_reception" value="<?= date('Y-m-d') ?>" required style="width:100%">
        </div>
        <div class="form-group form-full">
          <label>Fournisseur</label>
          <input type="text" name="fournisseur" maxlength="200" placeholder="Nom du fournisseur...">
        </div>
      </div>

      <!-- En-tête lignes -->
      <div style="margin-bottom:16px">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
          <label style="font-size:11px;font-weight:600;color:var(--text2);text-transform:uppercase;letter-spacing:.04em">Articles</label>
          <button type="button" class="btn btn-sm btn-blue" onclick="addEntryLigne()">+ Ligne</button>
        </div>
        <div style="display:grid;grid-template-columns:1fr 80px 100px 100px 28px;gap:6px;margin-bottom:6px;padding:0 2px">
          <span style="font-size:10px;color:var(--text3);font-weight:600;text-transform:uppercase">Article</span>
          <span style="font-size:10px;color:var(--text3);font-weight:600;text-transform:uppercase">Qté</span>
          <span style="font-size:10px;color:var(--text3);font-weight:600;text-transform:uppercase">Prix unit.</span>
          <span style="font-size:10px;color:var(--text3);font-weight:600;text-transform:uppercase">Total</span>
          <span></span>
        </div>
        <div id="entry-lignes"></div>

        <!-- Total temps réel -->
        <div style="display:flex;justify-content:flex-end;margin-top:12px;padding-top:12px;border-top:2px solid var(--border2)">
          <div style="background:var(--surface2);border-radius:8px;padding:12px 20px;text-align:right">
            <div style="font-size:11px;color:var(--text3);margin-bottom:4px">TOTAL ENTRÉE</div>
            <div id="entry-total-display" style="font-size:28px;font-weight:700;color:var(--green)">0 FCFA</div>
          </div>
        </div>
      </div>

      <!-- Notes -->
      <div class="form-group" style="margin-bottom:20px">
        <label>Notes</label>
        <input type="text" name="notes" maxlength="500" placeholder="N° bon livraison, commentaires...">
      </div>

      <!-- Actions -->
      <div style="display:flex;gap:10px;justify-content:flex-end;padding-top:16px;border-top:1px solid var(--border)">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('modal-entree').style.display='none'">Annuler</button>
        <button type="submit" id="btn-create-entry" class="btn btn-green" disabled>📥 Enregistrer l'entrée</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- MODAL AJOUT ARTICLE -->
<div id="modal-stock" class="modal-overlay" style="display:none;z-index:200;align-items:center;justify-content:center" role="dialog" aria-modal="true" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:var(--surface);border:1px solid var(--border2);border-radius:16px;width:min(540px,95vw);box-shadow:0 24px 60px rgba(0,0,0,.7)">
    <div style="padding:18px 24px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
      <h3>➕ Ajouter un article</h3>
      <button type="button" class="modal-close" onclick="document.getElementById('modal-stock').style.display='none'" aria-label="Fermer">✕</button>
    </div>
    <form method="POST" style="padding:24px">
      <input type="hidden" name="action" value="create_stock"><?= csrf_field() ?>
      <div class="form-grid">
        <div class="form-group form-full"><label>Nom *</label><input type="text" name="nom" required maxlength="200"></div>
        <div class="form-group"><label>Catégorie</label>
          <select name="categorie" style="padding:9px 12px;background:var(--bg);border:1px solid var(--border2);border-radius:7px;color:var(--text);font-family:inherit;font-size:13px;outline:none;width:100%">
            <?php foreach ($catOptions as $c): ?><option value="<?= $c ?>"><?= ucfirst($c) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label>Unité</label><input type="text" name="unite" maxlength="30" placeholder="unités, paires..."></div>
        <div class="form-group"><label>Quantité *</label><input type="number" name="quantite" value="0" min="0" required></div>
        <div class="form-group"><label>Seuil d'alerte</label><input type="number" name="seuil_alerte" value="50" min="0"></div>
        <div class="form-group"><label>Valeur unitaire</label><input type="number" name="valeur_unitaire" step="0.01" min="0" value="0.00"></div>
        <div class="form-group form-full"><label>Fournisseur</label><input type="text" name="fournisseur" maxlength="100"></div>
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;padding-top:16px;border-top:1px solid var(--border)">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('modal-stock').style.display='none'">Annuler</button>
        <button type="submit" class="btn btn-blue">➕ Ajouter</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL ÉDITION STOCK -->
<?php if ($editStock && can('stocks.create')): ?>
<div id="modal-edit-stock" class="modal-overlay" style="display:flex;z-index:200;align-items:center;justify-content:center;padding:20px" role="dialog" aria-modal="true" onclick="if(event.target===this)location.href='stocks.php'">
  <div style="background:var(--surface);border:1px solid var(--border2);border-radius:16px;width:min(520px,95vw);box-shadow:0 24px 60px rgba(0,0,0,.7)">
    <div style="padding:18px 24px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
      <h3>✏️ Modifier — <?= h($editStock['nom']) ?></h3>
      <a href="stocks.php" style="color:var(--text2);text-decoration:none;font-size:18px">✕</a>
    </div>
    <form method="POST" style="padding:24px">
      <input type="hidden" name="action" value="update_stock">
      <input type="hidden" name="stock_id" value="<?= (int)$editStock['id'] ?>">
      <?= csrf_field() ?>
      <div class="form-grid">
        <div class="form-group form-full"><label>Nom de l'article *</label>
          <input type="text" name="nom" value="<?= h($editStock['nom']) ?>" required maxlength="200"></div>
        <div class="form-group"><label>Catégorie</label>
          <select name="categorie" style="padding:9px 12px;background:var(--bg);border:1px solid var(--border2);border-radius:7px;color:var(--text);font-family:inherit;font-size:13px;outline:none;width:100%">
            <?php foreach (['consommable'=>'Consommable','equipement'=>'Équipement','protection'=>'Protection','autre'=>'Autre'] as $v=>$l): ?>
            <option value="<?= $v ?>" <?= $editStock['categorie']===$v?'selected':'' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label>Unité</label>
          <input type="text" name="unite" value="<?= h($editStock['unite']??'') ?>" maxlength="20" placeholder="pièce, boîte, litre...">
        </div>
        <div class="form-group"><label>Quantité en stock</label>
          <input type="number" name="quantite" value="<?= (int)$editStock['quantite'] ?>" min="0" required>
        </div>
        <div class="form-group"><label>Seuil d'alerte</label>
          <input type="number" name="seuil_alerte" value="<?= (int)$editStock['seuil_alerte'] ?>" min="0">
        </div>
        <div class="form-group"><label>Valeur unitaire</label>
          <input type="number" name="valeur_unitaire" value="<?= (float)$editStock['valeur_unitaire'] ?>" min="0" step="0.01">
        </div>
        <div class="form-group form-full"><label>Fournisseur</label>
          <input type="text" name="fournisseur" value="<?= h($editStock['fournisseur']??'') ?>" maxlength="200">
        </div>
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:16px;padding-top:16px;border-top:1px solid var(--border)">
        <a href="stocks.php" class="btn btn-ghost">Annuler</a>
        <button type="submit" class="btn btn-blue">💾 Enregistrer</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<script>
// ── Listes d'articles pour le sélecteur ──
const STOCK_ARTICLES = <?= json_encode(array_map(fn($a) => [
    'id' => (int)$a['id'],
    'nom' => $a['nom'],
    'pu' => (float)($a['valeur_unitaire'] ?? 0),
    'unite' => $a['unite'] ?? '',
], $stock_articles), JSON_UNESCAPED_UNICODE) ?>;

const MED_ARTICLES = <?= json_encode(array_map(fn($a) => [
    'id' => (int)$a['id'],
    'nom' => $a['nom'],
    'pu' => (float)($a['prix_unitaire'] ?? 0),
    'unite' => $a['unite'] ?? '',
], $med_articles), JSON_UNESCAPED_UNICODE) ?>;

// Devise
const SYM = '<?= h(setting('currency_symbol','FCFA')) ?>';

let currentType = 'stock';
let entryLineCount = 0;

function switchType(type) {
    currentType = type;
    const ls = document.getElementById('lbl-type-stock');
    const lm = document.getElementById('lbl-type-med');
    if (type === 'stock') {
        ls.style.border = '2px solid var(--accent)';
        ls.style.background = 'rgba(var(--accent-rgb),.1)';
        ls.style.color = 'var(--accent2)';
        lm.style.border = '1px solid var(--border2)';
        lm.style.background = 'transparent';
        lm.style.color = 'var(--text2)';
    } else {
        lm.style.border = '2px solid var(--accent)';
        lm.style.background = 'rgba(var(--accent-rgb),.1)';
        lm.style.color = 'var(--accent2)';
        ls.style.border = '1px solid var(--border2)';
        ls.style.background = 'transparent';
        ls.style.color = 'var(--text2)';
    }
    // Réinitialiser les lignes
    document.getElementById('entry-lignes').innerHTML = '';
    entryLineCount = 0;
    addEntryLigne();
}

function addEntryLigne() {
    const container = document.getElementById('entry-lignes');
    const idx = entryLineCount++;
    const articles = currentType === 'stock' ? STOCK_ARTICLES : MED_ARTICLES;

    let opts = '<option value="">-- Choisir --</option>';
    articles.forEach(a => {
        opts += '<option value="' + a.id + '" data-pu="' + a.pu + '">' + a.nom + '</option>';
    });

    const div = document.createElement('div');
    div.id = 'el-' + idx;
    div.style.cssText = 'display:grid;grid-template-columns:1fr 80px 100px 100px 28px;gap:6px;margin-bottom:6px;align-items:center';

    const fs = 'padding:8px;background:var(--bg);border:1px solid var(--border2);border-radius:6px;color:var(--text);font-size:12px;outline:none;font-family:inherit';

    div.innerHTML =
      '<select name="article_id[]" onchange="onArticleChange(' + idx + ')" style="' + fs + ';width:100%">' + opts + '</select>' +
      '<input type="number" name="eqty[]" id="eqty-' + idx + '" value="1" min="1" oninput="calcEntryLigne(' + idx + ')" style="' + fs + ';text-align:center">' +
      '<input type="text" id="epu-' + idx + '" readonly style="' + fs + ';background:var(--surface2);border-color:var(--border);color:var(--text2)">' +
      '<input type="text" id="etl-' + idx + '" readonly style="' + fs + ';background:var(--surface2);border-color:var(--border);color:var(--green);font-weight:700">' +
      '<button type="button" onclick="removeEntryLigne(' + idx + ')" style="width:44px;height:44px;background:rgba(var(--red-rgb),.15);border:none;border-radius:6px;color:var(--red);cursor:pointer;font-size:14px;display:flex;align-items:center;justify-content:center">✕</button>';

    container.appendChild(div);
    recalcEntryTotal();
}

function onArticleChange(idx) {
    const sel = document.querySelector('#el-' + idx + ' select');
    if (!sel) return;
    const opt = sel.options[sel.selectedIndex];
    const pu = parseFloat(opt.getAttribute('data-pu') || 0);
    // Mettre à jour le champ prix unitaire affiché
    document.getElementById('epu-' + idx).value = pu > 0 ? formatEntryMoney(pu) : '';
    // Champ caché pour la valeur brute
    let hidden = document.getElementById('epu-raw-' + idx);
    if (!hidden) {
        hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'epu_raw[]';
        hidden.id = 'epu-raw-' + idx;
        document.getElementById('el-' + idx).appendChild(hidden);
    }
    hidden.value = pu;
    calcEntryLigne(idx);
}

function calcEntryLigne(idx) {
    const sel = document.querySelector('#el-' + idx + ' select');
    if (!sel) return;
    const hidden = document.getElementById('epu-raw-' + idx);
    const pu = hidden ? parseFloat(hidden.value) || 0 : 0;
    const qty = parseInt(document.getElementById('eqty-' + idx).value) || 0;
    const tl = pu * qty;
    document.getElementById('etl-' + idx).value = tl > 0 ? formatEntryMoney(tl) : '';
    recalcEntryTotal();
}

function removeEntryLigne(idx) {
    const el = document.getElementById('el-' + idx);
    if (el) el.remove();
    recalcEntryTotal();
}

function recalcEntryTotal() {
    let total = 0;
    document.querySelectorAll('[id^="epu-raw-"]').forEach(el => {
        const pu = parseFloat(el.value) || 0;
        const idx = el.id.replace('epu-raw-', '');
        const qty = parseInt(document.getElementById('eqty-' + idx)?.value) || 0;
        total += pu * qty;
    });
    total = Math.round(total * 100) / 100;
    document.getElementById('entry-total-display').textContent = total > 0 ? formatEntryMoney(total) : '0 ' + SYM;
    document.getElementById('btn-create-entry').disabled = total <= 0;
}

function formatEntryMoney(n) {
    const formatted = Math.abs(n).toLocaleString('fr-FR', {minimumFractionDigits: 0, maximumFractionDigits: 0});
    return formatted + ' ' + SYM;
}

function toggleEntryLignes(id) {
    const row = document.getElementById('entry-lignes-' + id);
    if (row) row.style.display = row.style.display === 'none' ? 'table-row' : 'none';
}

// Initialiser avec une ligne vide
document.addEventListener('DOMContentLoaded', function() {
    addEntryLigne();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php';