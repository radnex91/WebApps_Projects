<?php
declare(strict_types=1);
/**
 * Suivi des caissiers — classement par recette et mérite.
 *
 * Page admin/direction : recettes par caissier sur une période, score de
 * mérite normalisé (recette + volume + panier + assiduité), classement
 * podium + tableau, et gestion des récompenses (table recompenses_caissiers).
 *
 * Exclut les ventes annulées (ventes.est_annulee = 1).
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/settings.php';
requirePermission('suivi_caissiers.voir');
$db = getDB();

// ══════════════════════════════════════════════════════════════
// Schéma idempotent (menus, permissions, table des récompenses)
// ══════════════════════════════════════════════════════════════
function suivi_caissiers_ensure_schema(PDO $db): void
{
    // Table des récompenses
    $db->exec("
        CREATE TABLE IF NOT EXISTS recompenses_caissiers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            caissier_id INT NOT NULL,
            periode_debut DATE NOT NULL,
            periode_fin DATE NOT NULL,
            rang INT DEFAULT NULL,
            titre VARCHAR(150) NOT NULL,
            montant DECIMAL(14,2) DEFAULT NULL,
            note TEXT,
            created_by INT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_recomp_caissier (caissier_id),
            INDEX idx_recomp_periode (periode_debut, periode_fin)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Permissions (idempotent)
    $db->exec("
        INSERT INTO permissions (code, libelle, module)
        VALUES
            ('suivi_caissiers.voir',        'Voir le suivi des caissiers', 'suivi_caissiers'),
            ('suivi_caissiers.recompenser', 'Enregistrer des récompenses de caissiers', 'suivi_caissiers')
        ON DUPLICATE KEY UPDATE libelle = VALUES(libelle)
    ");
    $db->exec("
        INSERT IGNORE INTO role_permissions (role_id, permission_id)
        SELECT r.id, p.id
        FROM roles r
        JOIN permissions p ON p.code IN ('suivi_caissiers.voir', 'suivi_caissiers.recompenser')
        WHERE r.code IN ('admin', 'directeur')
    ");
    // Consultation seule (pas de récompense) pour les autres rôles d'encadrement.
    $db->exec("
        INSERT IGNORE INTO role_permissions (role_id, permission_id)
        SELECT r.id, p.id
        FROM roles r
        JOIN permissions p ON p.code = 'suivi_caissiers.voir'
        WHERE r.code IN ('manager', 'superviseur', 'informaticien', 'regisseur')
    ");

    // Menu (insertion unique + décalage des positions suivantes)
    $exists = $db->query("SELECT COUNT(*) FROM menus WHERE code = 'suivi_caissiers'")->fetchColumn();
    if (!$exists) {
        $db->exec("UPDATE menus SET position = position + 1 WHERE position >= 16");
        $db->prepare("INSERT INTO menus (code, libelle, actif, position) VALUES ('suivi_caissiers', 'Suivi des caissiers', 1, 16)")
           ->execute();
    }
}
try { suivi_caissiers_ensure_schema($db); } catch (Throwable $e) { /* non bloquant */ }

// ══════════════════════════════════════════════════════════════
// Période analysée
// ══════════════════════════════════════════════════════════════
$now    = new DateTimeImmutable('today');
$action = $_GET['action'] ?? 'dashboard';

$preset = is_string($_GET['periode'] ?? null) ? $_GET['periode'] : 'mois';
$dateDebut = $now->format('Y-m-d');
$dateFin   = $now->format('Y-m-d');
$periodeLbl = "Aujourd'hui";

switch ($preset) {
    case 'aujourdhui': $dateDebut = $now->format('Y-m-d'); $periodeLbl = "Aujourd'hui"; break;
    case 'semaine':
        $dateDebut = $now->modify('monday this week')->format('Y-m-d');
        $periodeLbl = 'Cette semaine';
        break;
    case 'mois':      $dateDebut = $now->format('Y-m-01');   $periodeLbl = 'Ce mois'; break;
    case 'trimestre':
        $moisQ = (int)(floor(((int)$now->format('n') - 1) / 3) * 3 + 1);
        $dateDebut = $now->format('Y') . '-' . str_pad((string)$moisQ, 2, '0', STR_PAD_LEFT) . '-01';
        $periodeLbl = 'Ce trimestre';
        break;
    case 'annee':     $dateDebut = $now->format('Y-01-01');   $periodeLbl = "Cette année"; break;
    case '30j':       $dateDebut = $now->modify('-29 days')->format('Y-m-d'); $periodeLbl = '30 derniers jours'; break;
    case 'perso':
        $d1 = DateTime::createFromFormat('Y-m-d', (string)($_GET['debut'] ?? ''));
        $d2 = DateTime::createFromFormat('Y-m-d', (string)($_GET['fin'] ?? ''));
        if ($d1 instanceof DateTime) $dateDebut = $d1->format('Y-m-d');
        if ($d2 instanceof DateTime) $dateFin = max($dateDebut, $d2->format('Y-m-d'));
        $periodeLbl = 'Du ' . date('d/m/Y', strtotime($dateDebut)) . ' au ' . date('d/m/Y', strtotime($dateFin));
        break;
    default: $periodeLbl = "Aujourd'hui";
}

// ══════════════════════════════════════════════════════════════
// Actions POST : récompenses
// ══════════════════════════════════════════════════════════════
$postAction = $_POST['action'] ?? null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_string($postAction)) {
    if ($postAction === 'recompense_add' && hasPermission('suivi_caissiers.recompenser')) {
        verifyCsrf();
        $cid    = (int)($_POST['caissier_id'] ?? 0);
        $titre  = trim((string)($_POST['titre'] ?? ''));
        $rang   = (int)($_POST['rang'] ?? 0) ?: null;
        $montant = (($_POST['montant'] ?? '') !== '') ? (float)$_POST['montant'] : null;
        $note   = trim((string)($_POST['note'] ?? ''));
        $pDeb   = DateTime::createFromFormat('Y-m-d', (string)($_POST['periode_debut'] ?? ''));
        $pFin   = DateTime::createFromFormat('Y-m-d', (string)($_POST['periode_fin'] ?? ''));
        try {
            if ($cid <= 0) throw new InvalidArgumentException('Caissier invalide.');
            if ($titre === '') throw new InvalidArgumentException('Le titre de la récompense est requis.');
            if (!($pDeb instanceof DateTime) || !($pFin instanceof DateTime)) throw new InvalidArgumentException('Période invalide.');
            if ($pFin < $pDeb) { $t = $pDeb; $pDeb = $pFin; $pFin = $t; }
            $db->prepare("
                INSERT INTO recompenses_caissiers (caissier_id, periode_debut, periode_fin, rang, titre, montant, note, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([$cid, $pDeb->format('Y-m-d'), $pFin->format('Y-m-d'), $rang, $titre, $montant,
                         $note !== '' ? $note : null, currentUser()['id']]);
            auditLog('recompense', "Récompense « $titre » attribuée au caissier #$cid ($periodeLbl)");
            flash('Récompense enregistrée.', 'success');
        } catch (Throwable $e) {
            auditLog('recompense_echec', $e->getMessage());
            flash('Erreur : ' . $e->getMessage(), 'error');
        }
        header('Location: ' . url('suivi_caissiers', ['periode' => $preset, 'debut' => $dateDebut, 'fin' => $dateFin, 'onglet' => 'recompenses']));
        exit;
    }
    if ($postAction === 'recompense_del' && hasPermission('suivi_caissiers.recompenser')) {
        verifyCsrf();
        $rid = (int)($_POST['id'] ?? 0);
        $st  = $db->prepare("DELETE FROM recompenses_caissiers WHERE id = ?");
        $st->execute([$rid]);
        auditLog('recompense', "Récompense #$rid supprimée");
        flash('Récompense supprimée.', 'success');
        header('Location: ' . url('suivi_caissiers', ['onglet' => 'recompenses']));
        exit;
    }
}

// ══════════════════════════════════════════════════════════════
// Classement des caissiers (recettes de la période)
// ══════════════════════════════════════════════════════════════
$wherePeriode = "v.est_annulee = 0 AND v.created_at >= " . $db->quote($dateDebut) . "
                 AND v.created_at < DATE_ADD(" . $db->quote($dateFin) . ", INTERVAL 1 DAY)";

$classement = $db->query("
    SELECT u.id, u.prenom, u.nom, r.libelle AS role_libelle,
           COALESCE(rec.recette, 0)       AS recette,
           COALESCE(rec.nb_ventes, 0)     AS nb_ventes,
           COALESCE(rec.nb_articles, 0)   AS nb_articles,
           COALESCE(rec.jours, 0)         AS jours,
           COALESCE(rec.recette_especes, 0) AS recette_especes,
           rec.derniere_vente
    FROM utilisateurs u
    JOIN roles r ON u.role_id = r.id
    LEFT JOIN (
        SELECT v.caissier_id,
               SUM(v.total)                    AS recette,
               COUNT(*)                        AS nb_ventes,
               COUNT(DISTINCT DATE(v.created_at)) AS jours,
               SUM(CASE WHEN v.mode_paiement = 'espèces' THEN v.total ELSE 0 END) AS recette_especes,
               MAX(v.created_at)               AS derniere_vente,
               (SELECT COALESCE(SUM(vl.quantite), 0)
                FROM vente_lignes vl
                JOIN ventes v2 ON v2.id = vl.vente_id
                WHERE v2.caissier_id = v.caissier_id AND v2.est_annulee = 0
                  AND v2.created_at >= " . $db->quote($dateDebut) . "
                  AND v2.created_at < DATE_ADD(" . $db->quote($dateFin) . ", INTERVAL 1 DAY)) AS nb_articles
        FROM ventes v
        WHERE v.est_annulee = 0
          AND v.created_at >= " . $db->quote($dateDebut) . "
          AND v.created_at < DATE_ADD(" . $db->quote($dateFin) . ", INTERVAL 1 DAY)
        GROUP BY v.caissier_id
    ) rec ON rec.caissier_id = u.id
    WHERE u.actif = 1 AND r.code IN ('caissier', 'pharmacien', 'admin', 'directeur', 'manager', 'superviseur', 'regisseur', 'informaticien')
    HAVING nb_ventes > 0 OR recette > 0
    ORDER BY recette DESC, nb_ventes DESC, u.nom
")->fetchAll();

// Modes de paiement par caissier (pour le détail dépliable)
$byMode = [];
foreach ($db->query("
    SELECT v.caissier_id, v.mode_paiement, COUNT(*) AS nb, SUM(v.total) AS total
    FROM ventes v
    WHERE v.est_annulee = 0
      AND v.created_at >= " . $db->quote($dateDebut) . "
      AND v.created_at < DATE_ADD(" . $db->quote($dateFin) . ", INTERVAL 1 DAY)
    GROUP BY v.caissier_id, v.mode_paiement
")->fetchAll() as $m) {
    $byMode[(int)$m['caissier_id']][$m['mode_paiement']] = ['nb' => (int)$m['nb'], 'total' => (float)$m['total']];
}

// Récompenses de la période
$recompenses = $db->query("
    SELECT rc.*, u.prenom, u.nom AS u_nom
    FROM recompenses_caissiers rc
    JOIN utilisateurs u ON rc.caissier_id = u.id
    ORDER BY rc.periode_debut DESC, rc.rang ASC, rc.id DESC
    LIMIT 200
")->fetchAll();

// ══════════════════════════════════════════════════════════════
// Score de mérite (normalisé sur le meilleur de chaque indicateur)
//   55 % recette · 20 % nb ventes · 10 % panier moyen · 15 % assiduité
// ══════════════════════════════════════════════════════════════
$maxRecette = 0.0; $maxVentes = 0; $maxPanier = 0.0; $maxJours = 0;
foreach ($classement as $i => &$c) {
    $panier    = (int)$c['nb_ventes'] > 0 ? (float)$c['recette'] / (int)$c['nb_ventes'] : 0.0;
    $c['_panier']    = $panier;
    $maxRecette  = max($maxRecette, (float)$c['recette']);
    $maxVentes   = max($maxVentes, (int)$c['nb_ventes']);
    $maxPanier   = max($maxPanier, $panier);
    $maxJours    = max($maxJours, (int)$c['jours']);
}
foreach ($classement as $i => &$c) {
    $score = 0.0;
    if ($maxRecette > 0) $score += 55 * ((float)$c['recette'] / $maxRecette);
    if ($maxVentes  > 0) $score += 20 * ((int)$c['nb_ventes'] / $maxVentes);
    if ($maxPanier  > 0) $score += 10 * ((float)$c['_panier'] / $maxPanier);
    if ($maxJours   > 0) $score += 15 * ((int)$c['jours'] / $maxJours);
    $c['score'] = round($score, 1);
    $c['rang']  = $i + 1;
    $c['niveau'] = $c['score'] >= 80 ? 'Or' : ($c['score'] >= 60 ? 'Argent' : ($c['score'] >= 40 ? 'Bronze' : 'En progression'));
}
unset($c);

// ── Ordre de mérite : tri par score décroissant ──────────────
// Le score (55 % recette + 20 % volume + 10 % panier + 15 % assiduité)
// détermine le rang officiel et le podium. La meilleure recette reste
// mise en évidence par un badge dédié dans le tableau.
usort($classement, fn($a, $b) => $b['score'] <=> $a['score']);
foreach ($classement as $i => &$c) { $c['rang'] = $i + 1; }
unset($c);
$idxMeilleureRecette = 0;
foreach ($classement as $i => $c) if ((float)$c['recette'] > (float)$classement[$idxMeilleureRecette]['recette']) $idxMeilleureRecette = $i;
$onglet = (($_GET['onglet'] ?? '') === 'recompenses') ? 'recompenses' : 'classement';

// ── Export Excel du classement ────────────────────────────────
if ($action === 'export') {
    require_once __DIR__ . '/../includes/export_xlsx.php';
    $rows = [];
    foreach ($classement as $c) {
        $rows[] = [
            $c['rang'], trim(($c['prenom'] ?? '') . ' ' . ($c['nom'] ?? '')), $c['role_libelle'],
            (float)$c['recette'], (int)$c['nb_ventes'], (int)$c['nb_articles'],
            round($c['_panier'], 2), (int)$c['jours'], (float)$c['recette_especes'],
            (float)$c['score'], $c['niveau'],
        ];
    }
    export_xlsx_send('classement_caissiers_' . $dateDebut . '_' . $dateFin, 'Classement',
        ['Rang', 'Caissier', 'Rôle', 'Recette totale', 'Nb ventes', 'Nb articles',
         'Panier moyen', 'Jours travaillés', 'Dont espèces', 'Score mérite', 'Niveau'], $rows);
}

layout_head('Suivi des caissiers', 'suivi_caissiers');
showFlash();
?>
<div class="page-header">
  <h1>🏆 Suivi des caissiers</h1>
  <div class="flex gap-8">
    <a href="<?= url('suivi_caissiers', ['action' => 'export', 'periode' => $preset, 'debut' => $dateDebut, 'fin' => $dateFin]) ?>"
       class="btn btn-ghost" title="Exporter le classement au format Excel (.xlsx)"><?= icon('download', 14) ?> Exporter</a>
  </div>
</div>

<!-- Filtres période -->
<div class="card no-print" style="margin-bottom:16px;">
  <div class="card-pad" style="padding:12px 16px;">
    <form method="GET" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap;">
      <div class="form-group" style="margin:0;">
        <label>Période</label>
        <select name="periode" onchange="this.form.submit()" style="min-width:170px;">
          <?php foreach (['aujourdhui' => "Aujourd'hui", 'semaine' => 'Cette semaine', 'mois' => 'Ce mois', 'trimestre' => 'Ce trimestre', 'annee' => "Cette année", '30j' => '30 derniers jours', 'perso' => 'Personnalisé…'] as $v => $l): ?>
          <option value="<?= e($v) ?>" <?= $preset === $v ? 'selected' : '' ?>><?= e($l) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php if ($preset === 'perso'): ?>
      <div class="form-group" style="margin:0;"><label>Du</label><input type="date" name="debut" value="<?= e($dateDebut) ?>"></div>
      <div class="form-group" style="margin:0;"><label>Au</label><input type="date" name="fin" value="<?= e($dateFin) ?>"></div>
      <?php endif; ?>
      <input type="hidden" name="onglet" value="<?= e($onglet) ?>">
      <button type="submit" class="btn btn-ghost btn-sm"><?= icon('filter', 13) ?> Appliquer</button>
      <span style="margin-left:auto;color:var(--text3);font-size:12px;"><?= e($periodeLbl) ?></span>
    </form>
  </div>
</div>

<?php if (!$classement): ?>
<div class="card"><div class="card-pad" style="text-align:center;color:var(--text3);padding:40px;">
  Aucune vente enregistrée sur la période sélectionnée.
</div></div>
<?php else: ?>

<!-- Stats clés -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);">
  <div class="stat-card s-teal">
    <div class="stat-icon" style="color:var(--teal2);opacity:.25;"><?= icon('trending', 28) ?></div>
    <div class="stat-label">Recette totale de la période</div>
    <div class="stat-value c-teal" style="font-size:20px;"><?= fmtMoney(array_sum(array_map(fn($c) => (float)$c['recette'], $classement))) ?></div>
  </div>
  <div class="stat-card s-blue">
    <div class="stat-icon" style="color:var(--blue);opacity:.25;"><?= icon('cart', 28) ?></div>
    <div class="stat-label">Ventes réalisées</div>
    <div class="stat-value c-blue"><?= array_sum(array_map(fn($c) => (int)$c['nb_ventes'], $classement)) ?></div>
  </div>
  <div class="stat-card s-gold">
    <div class="stat-icon" style="color:var(--gold);opacity:.25;"><?= icon('trophy', 28) ?></div>
    <div class="stat-label">Meilleur caissier</div>
    <div class="stat-value" style="font-size:16px;color:var(--gold);"><?= e(trim(($classement[0]['prenom'] ?? '') . ' ' . ($classement[0]['nom'] ?? ''))) ?></div>
  </div>
  <div class="stat-card s-purple">
    <div class="stat-icon" style="color:var(--purple);opacity:.25;"><?= icon('users', 28) ?></div>
    <div class="stat-label">Caissiers actifs</div>
    <div class="stat-value c-purple"><?= count($classement) ?></div>
  </div>
</div>

<!-- Onglets -->
<div class="flex gap-8" style="margin:16px 0 12px;">
  <a href="<?= url('suivi_caissiers', ['periode' => $preset, 'debut' => $dateDebut, 'fin' => $dateFin]) ?>" class="btn btn-sm <?= $onglet === 'classement' ? 'btn-primary' : 'btn-ghost' ?>">Classement & mérite</a>
  <a href="<?= url('suivi_caissiers', ['periode' => $preset, 'debut' => $dateDebut, 'fin' => $dateFin, 'onglet' => 'recompenses']) ?>" class="btn btn-sm <?= $onglet === 'recompenses' ? 'btn-primary' : 'btn-ghost' ?>">🎁 Récompenses (<?= count($recompenses) ?>)</a>
</div>

<?php if ($onglet === 'classement'): ?>

<!-- Podium (top 3) -->
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:16px;">
  <?php foreach (array_slice($classement, 0, 3) as $c): $med = ['🥇', '🥈', '🥉'][$c['rang'] - 1]; ?>
  <div class="card" style="text-align:center;padding:20px 14px;border-color:<?= $c['rang'] === 1 ? 'var(--gold)' : 'var(--border)' ?>;">
    <div style="font-size:34px;"><?= $med ?></div>
    <div style="font-weight:700;font-size:15px;margin:6px 0 2px;"><?= e(trim(($c['prenom'] ?? '') . ' ' . ($c['nom'] ?? ''))) ?></div>
    <div style="font-size:11px;color:var(--text3);margin-bottom:8px;"><?= e($c['role_libelle']) ?></div>
    <div style="font-size:22px;font-weight:800;color:var(--teal2);font-family:var(--font-title);"><?= fmtMoney((float)$c['recette']) ?></div>
    <div style="font-size:11px;color:var(--text3);"><?= (int)$c['nb_ventes'] ?> vente(s) · score <?= e((string)$c['score']) ?></div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Tableau complet -->
<div class="card">
  <div class="card-header">
    <div class="card-title">Classement par ordre de mérite — <?= e($periodeLbl) ?></div>
    <span class="text-sm" style="color:var(--text3);">Score = 55 % recette + 20 % volume + 10 % panier moyen + 15 % assiduité (normalisés sur le leader)</span>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th style="width:44px;">#</th><th>Caissier</th><th>Recette totale</th><th>Dont espèces</th>
          <th>Ventes</th><th>Articles</th><th>Panier moyen</th><th>Jours</th>
          <th style="min-width:170px;">Score de mérite</th><th>Niveau</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($classement as $i => $c): $med = $c['rang'] <= 3 ? ['🥇', '🥈', '🥉'][$c['rang'] - 1] . ' ' : ''; $pct = min(100, (float)$c['score']); ?>
        <tr>
          <td class="fw-mono"><?= $med ?><?= $c['rang'] ?></td>
          <td>
            <strong><?= e(trim(($c['prenom'] ?? '') . ' ' . ($c['nom'] ?? ''))) ?></strong>
            <?php if ($i === $idxMeilleureRecette): ?><span class="badge badge-gold" title="Meilleure recette de la période">💰 Meilleure recette</span><?php endif; ?>
            <div class="text-sm" style="color:var(--text3);"><?= e($c['role_libelle']) ?></div>
            <?php if (!empty($byMode[(int)$c['id']])): ?>
            <details style="margin-top:4px;">
              <summary class="text-sm" style="color:var(--blue);cursor:pointer;">Détail des encaissements</summary>
              <div class="text-sm" style="color:var(--text2);padding:4px 0;">
                <?php foreach ($byMode[(int)$c['id']] as $mode => $d): ?>
                  <span class="badge badge-gray" style="margin:2px;"><?= e(ucfirst($mode)) ?> : <?= fmtMoney((float)$d['total']) ?> (<?= (int)$d['nb'] ?>)</span>
                <?php endforeach; ?>
              </div>
            </details>
            <?php endif; ?>
          </td>
          <td class="fw-mono" style="font-weight:700;color:var(--teal2);"><?= fmtMoney((float)$c['recette']) ?></td>
          <td class="fw-mono"><?= fmtMoney((float)$c['recette_especes']) ?></td>
          <td><?= (int)$c['nb_ventes'] ?></td>
          <td><?= (int)$c['nb_articles'] ?></td>
          <td class="fw-mono"><?= fmtMoney($c['_panier']) ?></td>
          <td><?= (int)$c['jours'] ?></td>
          <td>
            <div style="display:flex;align-items:center;gap:8px;">
              <div style="flex:1;height:8px;background:var(--bg2);border-radius:99px;overflow:hidden;">
                <div style="height:100%;width:<?= (float)$c['score'] ?>%;background:<?= $c['score'] >= 80 ? 'var(--teal2)' : ($c['score'] >= 60 ? 'var(--blue)' : ($c['score'] >= 40 ? 'var(--gold)' : 'var(--text3)')) ?>;border-radius:99px;"></div>
              </div>
              <span class="fw-mono" style="font-size:12px;min-width:44px;text-align:right;"><?= e((string)$c['score']) ?></span>
            </div>
          </td>
          <td><span class="badge <?= $c['niveau'] === 'Or' ? 'badge-gold' : ($c['niveau'] === 'Argent' ? 'badge-blue' : ($c['niveau'] === 'Bronze' ? 'badge-orange' : 'badge-gray')) ?>"><?= e($c['niveau']) ?></span></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php elseif ($onglet === 'recompenses'): ?>

<div style="display:grid;grid-template-columns:1fr 1.4fr;gap:16px;align-items:start;">
  <!-- Formulaire -->
  <div class="card">
    <div class="card-header"><div class="card-title">🎁 Attribuer une récompense</div></div>
    <?php if (!hasPermission('suivi_caissiers.recompenser')): ?>
    <div class="card-pad text-sm" style="color:var(--text3);">Seuls les administrateurs et directeurs peuvent attribuer des récompenses.</div>
    <?php else: ?>
    <form method="POST" class="card-pad" style="display:grid;gap:10px;">
      <input type="hidden" name="action" value="recompense_add">
      <input type="hidden" name="csrf" value="<?= csrf() ?>">
      <div class="form-group">
        <label>Caissier récompensé *</label>
        <select name="caissier_id" required style="width:100%;">
          <?php foreach ($classement as $c): ?>
          <option value="<?= (int)$c['id'] ?>">
            <?= $c['rang'] <= 3 ? ['🥇', '🥈', '🥉'][$c['rang'] - 1] . ' ' : '' ?><?= e(trim(($c['prenom'] ?? '') . ' ' . ($c['nom'] ?? ''))) ?> — <?= fmtMoney((float)$c['recette']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Titre de la récompense *</label>
        <input type="text" name="titre" required placeholder="Ex. : 1er du mois — meilleur vendeur" style="width:100%;">
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
        <div class="form-group"><label>Rang (classement)</label>
          <input type="number" name="rang" min="1" max="99" style="width:100%;"></div>
        <div class="form-group"><label>Montant / valeur (<?= e(getParam('devise_symbole', 'FCFA')) ?>)</label>
          <input type="number" name="montant" min="0" step="0.01" style="width:100%;"></div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
        <div class="form-group"><label>Période — du</label><input type="date" name="periode_debut" value="<?= e($dateDebut) ?>" style="width:100%;"></div>
        <div class="form-group"><label>au</label><input type="date" name="periode_fin" value="<?= e($dateFin) ?>" style="width:100%;"></div>
      </div>
      <div class="form-group">
        <label>Note</label>
        <textarea name="note" rows="2" style="width:100%;" placeholder="Motif, commentaire…"></textarea>
      </div>
      <button type="submit" class="btn btn-primary"><?= icon('check', 14) ?> Enregistrer la récompense</button>
      <p class="text-sm" style="color:var(--text3);margin:0;">Période pré-remplie : <?= e($periodeLbl) ?>. Modifiable librement.</p>
    </form>
    <?php endif; ?>
  </div>

  <!-- Historique -->
  <div class="card">
    <div class="card-header"><div class="card-title">Récompenses attribuées</div></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Date</th><th>Caissier</th><th>Récompense</th><th>Période</th><th>Valeur</th><?php if (hasPermission('suivi_caissiers.recompenser')): ?><th></th><?php endif; ?></tr></thead>
        <tbody>
        <?php if (!$recompenses): ?>
        <tr><td colspan="7" class="empty">Aucune récompense enregistrée pour le moment.</td></tr>
        <?php else: foreach ($recompenses as $rc): ?>
        <tr>
          <td><?= date('d/m/Y', strtotime($rc['created_at'])) ?></td>
          <td><strong><?= e(trim(($rc['prenom'] ?? '') . ' ' . ($rc['u_nom'] ?? ''))) ?></strong></td>
          <td>
            <?= $rc['rang'] ? '<span class="badge badge-gold">#' . (int)$rc['rang'] . '</span> ' : '' ?>
            <?= e($rc['titre']) ?>
            <?php if (!empty($rc['note'])): ?><div class="text-sm" style="color:var(--text3);"><?= e($rc['note']) ?></div><?php endif; ?>
          </td>
          <td class="text-sm"><?= date('d/m/y', strtotime($rc['periode_debut'])) ?> → <?= date('d/m/y', strtotime($rc['periode_fin'])) ?></td>
          <td class="fw-mono"><?= $rc['montant'] !== null ? fmtMoney((float)$rc['montant']) : '—' ?></td>
          <td><?= $rc['rang'] === 1 ? '🥇' : ($rc['rang'] === 2 ? '🥈' : ($rc['rang'] === 3 ? '🥉' : '')) ?></td>
          <?php if (hasPermission('suivi_caissiers.recompenser')): ?>
          <td>
            <form method="POST" style="display:inline;" onsubmit="return confirm('Supprimer cette récompense ?');">
              <input type="hidden" name="action" value="recompense_del">
              <input type="hidden" name="csrf" value="<?= csrf() ?>">
              <input type="hidden" name="id" value="<?= (int)$rc['id'] ?>">
              <button type="submit" class="btn btn-ghost btn-xs" title="Supprimer"><?= icon('trash', 13) ?></button>
            </form>
          </td>
          <?php endif; ?>
        </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>
<?php endif; ?>
<?php layout_foot();