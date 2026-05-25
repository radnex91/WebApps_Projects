<?php
require_once '../includes/config.php';
session_start();

// Traitement POST avant tout output HTML
$rapport = null;
$msg = '';
if (isset($_SESSION['inventaire_rapport'])) {
    $rapport = $_SESSION['inventaire_rapport'];
    unset($_SESSION['inventaire_rapport']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireAuth();
    $db = getDB();
    csrf_verify();
    if (canDo('mouvement_ajustement')) {
        $action = $_POST['action'] ?? '';
        if ($action === 'valider_inventaire') {
            $produits_ids = $_POST['produit_id'] ?? [];
            $nouvelles_qtes = $_POST['nouvelle_qte'] ?? [];
            $ajustements = [];
            $count = 0;
            try {
                $db->beginTransaction();
                foreach ($produits_ids as $i => $pid) {
                    $pid = (int)$pid;
                    $nouvelle = (int)($nouvelles_qtes[$i] ?? -1);
                    if ($pid <= 0 || $nouvelle < 0) continue;
                    $prod = $db->prepare("SELECT quantite, nom, reference, unite, prix_achat FROM produits WHERE id=? AND actif=1 FOR UPDATE");
                    $prod->execute([$pid]);
                    $prod = $prod->fetch();
                    if (!$prod || $prod['quantite'] == $nouvelle) continue;
                    $diff = $nouvelle - $prod['quantite'];
                    $db->prepare("UPDATE produits SET quantite=? WHERE id=?")->execute([$nouvelle, $pid]);
                    $db->prepare("INSERT INTO mouvements (produit_id,utilisateur_id,type,quantite,quantite_avant,quantite_apres,motif) VALUES(?,?,?,?,?,?,?)")
                       ->execute([$pid, $_SESSION['user']['id'], 'ajustement', abs($diff), $prod['quantite'], $nouvelle, 'Inventaire physique']);
                    $ajustements[] = [
                        'nom' => $prod['nom'],
                        'reference' => $prod['reference'],
                        'unite' => $prod['unite'],
                        'avant' => (int)$prod['quantite'],
                        'apres' => $nouvelle,
                        'diff' => $diff,
                        'prix_achat' => (float)$prod['prix_achat'],
                    ];
                    $count++;
                }
                $db->commit();
                $_SESSION['inventaire_rapport'] = [
                    'count' => $count,
                    'total_produits' => count($produits_ids),
                    'ajustements' => $ajustements,
                    'date' => date('d/m/Y H:i'),
                ];
                header('Location: inventaire.php');
                exit;
            } catch (Exception $e) {
                $db->rollBack();
                $_SESSION['inventaire_rapport'] = [
                    'error' => true,
                    'message' => 'Erreur lors de la validation de l\'inventaire.',
                ];
                header('Location: inventaire.php');
                exit;
            }
        }
    }
}

$page_title = 'Inventaire';
$page_id = 'inventaire';
require_once '../includes/header.php';
requireAuth();
$db = getDB();

$cat_f = (int)($_GET['cat'] ?? 0);
$where = "p.actif=1";
$params = [];
if ($cat_f) { $where .= " AND p.categorie_id=?"; $params[] = $cat_f; }

$produits = $db->prepare("
    SELECT p.*, c.nom as cat_nom, c.couleur as cat_couleur
    FROM produits p
    LEFT JOIN categories c ON p.categorie_id = c.id
    WHERE $where ORDER BY c.nom, p.nom
");
$produits->execute($params);
$produits = $produits->fetchAll();

$categories = $db->query("SELECT * FROM categories ORDER BY nom")->fetchAll();
$total_val = array_sum(array_map(fn($p) => $p['quantite'] * $p['prix_achat'], $produits));
$ent = getEntreprise();
$devise = $ent['devise'] ?? 'FCFA';
?>

<?php if ($rapport): ?>
<!-- ========== MODAL RAPPORT INVENTAIRE ========== -->
<div class="modal-bg open" id="modal-rapport" style="display:flex">
  <div class="modal" style="max-width:680px">
    <div class="modal-header">
      <div class="modal-title">
        <?php if (!empty($rapport['error'])): ?>
        <span style="color:var(--danger)">Echec de la validation</span>
        <?php else: ?>
        Inventaire validé
        <?php endif; ?>
      </div>
      <button class="modal-close" onclick="closeModal('modal-rapport')">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>

    <?php if (!empty($rapport['error'])): ?>
    <div class="modal-body">
      <div style="text-align:center;padding:20px 0">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="var(--danger)" stroke-width="2" width="48" height="48"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
        <p style="margin-top:16px;color:var(--danger);font-weight:600;font-size:15px"><?= htmlspecialchars($rapport['message']) ?></p>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal('modal-rapport')">Fermer</button>
    </div>

    <?php else: ?>
    <!-- Rapport succès -->
    <div class="rapport-banner">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" width="28" height="28"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
      <div>
        <div class="rapport-banner-title">Inventaire validé avec succès</div>
        <div class="rapport-banner-sub"><?= $rapport['count'] ?> ajustement(s) enregistré(s) le <?= htmlspecialchars($rapport['date']) ?></div>
      </div>
    </div>

    <div class="modal-body">
      <!-- Stats rapides -->
      <div class="rapport-stats">
        <div class="rapport-stat-card">
          <div class="rapport-stat-num"><?= $rapport['count'] ?></div>
          <div class="rapport-stat-label">Ajustements</div>
        </div>
        <div class="rapport-stat-card">
          <?php
          $entrees = array_filter($rapport['ajustements'], fn($a) => $a['diff'] > 0);
          $sorties = array_filter($rapport['ajustements'], fn($a) => $a['diff'] < 0);
          ?>
          <div class="rapport-stat-num" style="color:var(--success)"><?= count($entrees) ?></div>
          <div class="rapport-stat-label">Entrées</div>
        </div>
        <div class="rapport-stat-card">
          <div class="rapport-stat-num" style="color:var(--danger)"><?= count($sorties) ?></div>
          <div class="rapport-stat-label">Sorties</div>
        </div>
        <div class="rapport-stat-card">
          <?php
          $valeur_ecart = array_sum(array_map(fn($a) => abs($a['diff'] * $a['prix_achat']), $rapport['ajustements']));
          ?>
          <div class="rapport-stat-num" style="font-size:14px"><?= formatMoney($valeur_ecart) ?></div>
          <div class="rapport-stat-label">Valeur écart</div>
        </div>
      </div>

      <!-- Tableau des ajustements -->
      <div style="font-weight:700;font-size:13px;color:var(--text-2);margin-bottom:8px;text-transform:uppercase;letter-spacing:.5px">Détail des ajustements</div>
      <div class="rapport-table-wrap">
        <table class="rapport-table">
          <thead>
            <tr>
              <th>Produit</th>
              <th>Avant</th>
              <th>Après</th>
              <th>Écart</th>
              <th>Valeur</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($rapport['ajustements'] as $a): ?>
            <tr class="rapport-row <?= $a['diff'] > 0 ? 'rapport-row-pos' : 'rapport-row-neg' ?>">
              <td>
                <div style="font-weight:600;font-size:13px"><?= htmlspecialchars($a['nom']) ?></div>
                <div style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($a['reference']) ?></div>
              </td>
              <td style="font-weight:600"><?= $a['avant'] ?> <small style="font-weight:400;color:var(--muted)"><?= $a['unite'] ?></small></td>
              <td style="font-weight:700"><?= $a['apres'] ?> <small style="font-weight:400;color:var(--muted)"><?= $a['unite'] ?></small></td>
              <td>
                <span class="rapport-diff <?= $a['diff'] > 0 ? 'rapport-diff-pos' : 'rapport-diff-neg' ?>">
                  <?= $a['diff'] > 0 ? '+' : '' ?><?= $a['diff'] ?> <?= $a['unite'] ?>
                </span>
              </td>
              <td style="font-size:13px;color:var(--text-2)"><?= formatMoney(abs($a['diff'] * $a['prix_achat'])) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" onclick="closeModal('modal-rapport')">Fermer</button>
      <button type="button" class="btn btn-primary" onclick="window.location.href='mouvements.php?type=ajustement'">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M12 20V10"/><path d="M18 20V4"/><path d="M6 20v-4"/></svg>
        Voir les mouvements
      </button>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;flex-wrap:wrap">
    <div style="flex:1">
        <p style="color:var(--text-2);font-size:14px">Saisissez les quantités physiques constatées. Seuls les produits modifiés généreront un ajustement.</p>
    </div>
    <form method="GET" style="display:flex;gap:8px">
        <select class="form-control form-select" name="cat" style="width:200px" onchange="this.form.submit()">
            <option value="">Toutes catégories</option>
            <?php foreach ($categories as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $cat_f==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['nom']) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<!-- Résumé rapide -->
<div class="stats-grid" style="margin-bottom:20px">
    <div class="stat-card">
        <div class="stat-icon" style="background:#ede9fe">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#7c3aed" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        </div>
        <div><div class="stat-val"><?= count($produits) ?></div><div class="stat-label">Produits à inventorier</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#dcfce7">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        </div>
        <div><div class="stat-val" style="font-size:17px"><?= formatMoney($total_val) ?></div><div class="stat-label">Valeur totale (achat)</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#fef3c7">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/></svg>
        </div>
        <div>
            <div class="stat-val"><?= count(array_filter($produits, fn($p) => $p['quantite'] <= $p['quantite_min'])) ?></div>
            <div class="stat-label">En alerte</div>
        </div>
    </div>
</div>

<form method="POST" id="form-inventaire">
    <input type="hidden" name="action" value="valider_inventaire">
    <?= csrf_field() ?>

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
        <div style="font-size:13px;color:var(--muted)" id="modif-count">0 modification(s) en attente</div>
        <div style="display:flex;gap:10px">
            <button type="button" class="btn btn-secondary" onclick="resetAll()">Réinitialiser</button>
            <?php if (canDo('mouvement_ajustement')): ?>
            <button type="submit" class="btn btn-primary" onclick="return confirm('Valider l\'inventaire et enregistrer les ajustements ?')">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                Valider l'inventaire
            </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div style="overflow-x:auto">
            <table id="tbl-inventaire">
                <thead>
                    <tr>
                        <th>Référence</th>
                        <th>Produit</th>
                        <th>Catégorie</th>
                        <th>Stock système</th>
                        <th>Qté physique</th>
                        <th>Écart</th>
                        <th>Valeur écart</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($produits as $i => $p): ?>
                <tr id="row-<?= $p['id'] ?>" class="inv-row">
                    <td><code style="background:var(--bg);padding:2px 8px;border-radius:6px;font-size:12px"><?= htmlspecialchars($p['reference']) ?></code></td>
                    <td>
                        <input type="hidden" name="produit_id[]" value="<?= $p['id'] ?>">
                        <div style="font-weight:600;font-size:13px"><?= htmlspecialchars($p['nom']) ?></div>
                    </td>
                    <td>
                        <?php if ($p['cat_nom']): ?>
                        <span class="badge" style="background:<?= $p['cat_couleur'] ?>22;color:<?= $p['cat_couleur'] ?>"><?= htmlspecialchars($p['cat_nom']) ?></span>
                        <?php else: ?><span style="color:var(--muted)">—</span><?php endif; ?>
                    </td>
                    <td>
                        <span style="font-weight:700;color:<?= $p['quantite']==0?'var(--danger)':($p['quantite']<=$p['quantite_min']?'var(--warning)':'var(--text)') ?>">
                            <?= $p['quantite'] ?> <?= $p['unite'] ?>
                        </span>
                    </td>
                    <td>
                        <input
                            type="number"
                            name="nouvelle_qte[]"
                            class="form-control inv-input"
                            style="width:100px;padding:7px 10px"
                            min="0"
                            value="<?= $p['quantite'] ?>"
                            data-original="<?= $p['quantite'] ?>"
                            data-id="<?= $p['id'] ?>"
                            data-prix="<?= $p['prix_achat'] ?>"
                            data-unite="<?= $p['unite'] ?>"
                            onchange="updateEcart(this)"
                            oninput="updateEcart(this)">
                    </td>
                    <td id="ecart-<?= $p['id'] ?>" style="font-weight:700;font-size:14px">—</td>
                    <td id="val-ecart-<?= $p['id'] ?>" style="font-size:13px;color:var(--muted)">—</td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</form>

<style>
.rapport-banner {
  display: flex; align-items: center; gap: 14px;
  background: var(--success); color: white;
  padding: 20px 24px; border-radius: 16px 16px 0 0;
  margin: -20px -24px 0;
}
.rapport-banner-title { font-weight: 700; font-size: 16px; }
.rapport-banner-sub { font-size: 13px; opacity: .85; margin-top: 2px; }

.rapport-stats {
  display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px;
  margin-bottom: 20px;
}
.rapport-stat-card {
  background: var(--bg); border-radius: 12px; padding: 14px 16px;
  text-align: center;
}
.rapport-stat-num {
  font-weight: 800; font-size: 22px; color: var(--text);
}
.rapport-stat-label {
  font-size: 12px; color: var(--muted); margin-top: 2px; text-transform: uppercase; letter-spacing: .4px;
}

.rapport-table-wrap { max-height: 320px; overflow-y: auto; border-radius: 12px; border: 1px solid var(--border); }
.rapport-table { width: 100%; border-collapse: collapse; }
.rapport-table th {
  text-align: left; font-size: 11px; font-weight: 700;
  text-transform: uppercase; letter-spacing: .6px;
  color: var(--muted); padding: 10px 14px;
  border-bottom: 2px solid var(--border);
  position: sticky; top: 0; background: var(--card); z-index: 1;
}
.rapport-table td { padding: 10px 14px; border-bottom: 1px solid var(--border); font-size: 13px; }
.rapport-row-pos { background: #f0fdf4; }
.rapport-row-neg { background: #fef2f2; }
[data-theme="dark"] .rapport-row-pos { background: #052e16; }
[data-theme="dark"] .rapport-row-neg { background: #450a0a; }

.rapport-diff {
  display: inline-block; padding: 2px 8px; border-radius: 6px;
  font-weight: 700; font-size: 12px;
}
.rapport-diff-pos { background: #dcfce7; color: #16a34a; }
.rapport-diff-neg { background: #fef2f2; color: #ef4444; }
[data-theme="dark"] .rapport-diff-pos { background: #052e16; color: #86efac; }
[data-theme="dark"] .rapport-diff-neg { background: #450a0a; color: #fca5a5; }

@media (max-width: 600px) {
  .rapport-stats { grid-template-columns: repeat(2, 1fr); }
  .rapport-banner { flex-direction: column; text-align: center; }
}
</style>

<script>
const devise = '<?= $devise ?>';

function formatNum(n) {
    return new Intl.NumberFormat('fr-FR').format(n) + ' ' + devise;
}

function updateEcart(input) {
    const original = parseInt(input.dataset.original);
    const nouvelle = parseInt(input.value) || 0;
    const ecart = nouvelle - original;
    const prix = parseFloat(input.dataset.prix);
    const unite = input.dataset.unite;
    const id = input.dataset.id;

    const ecartEl = document.getElementById('ecart-' + id);
    const valEl = document.getElementById('val-ecart-' + id);
    const row = document.getElementById('row-' + id);

    if (ecart === 0) {
        ecartEl.textContent = '—';
        ecartEl.style.color = 'var(--muted)';
        valEl.textContent = '—';
        valEl.style.color = 'var(--muted)';
        row.style.background = '';
    } else {
        ecartEl.textContent = (ecart > 0 ? '+' : '') + ecart + ' ' + unite;
        ecartEl.style.color = ecart > 0 ? 'var(--success)' : 'var(--danger)';
        valEl.textContent = formatNum(Math.abs(ecart * prix));
        valEl.style.color = ecart > 0 ? 'var(--success)' : 'var(--danger)';
        row.style.background = ecart > 0 ? '#f0fdf4' : '#fef2f2';
    }
    countModifications();
}

function countModifications() {
    let count = 0;
    document.querySelectorAll('.inv-input').forEach(inp => {
        if (parseInt(inp.value) !== parseInt(inp.dataset.original)) count++;
    });
    document.getElementById('modif-count').textContent = count + ' modification(s) en attente';
    document.getElementById('modif-count').style.color = count > 0 ? 'var(--warning)' : 'var(--muted)';
}

function resetAll() {
    document.querySelectorAll('.inv-input').forEach(inp => {
        inp.value = inp.dataset.original;
        updateEcart(inp);
    });
}
</script>

<?php require_once '../includes/footer.php'; ?>