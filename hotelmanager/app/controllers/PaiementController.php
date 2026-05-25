<?php
/**
 * HotelPro Suite - PaiementController
 * Vue consolidée de tous les paiements reçus
 */

class PaiementController
{
    public function index(): void
    {
        $page_num = max(1, (int)($_GET['p'] ?? 1));
        $offset   = ($page_num - 1) * ITEMS_PER_PAGE;
        $mode     = $_GET['mode'] ?? '';

        $where  = ['1=1'];
        $params = [];
        if ($mode) { $where[] = 'p.mode = ?'; $params[] = $mode; }

        $whereStr = implode(' AND ', $where);

        $total = (int) Database::query(
            "SELECT COUNT(*) FROM paiements p WHERE $whereStr",
            $params
        )->fetchColumn();

        $paiements = Database::query(
            "SELECT p.*, f.reference AS fac_ref, f.total_ttc,
                    CONCAT(c.prenom,' ',c.nom) AS client_nom,
                    CONCAT(u.prenom,' ',u.nom) AS encaisse_par
             FROM paiements p
             JOIN factures f ON f.id = p.facture_id
             JOIN clients c ON c.id = f.client_id
             JOIN users u ON u.id = p.user_id
             WHERE $whereStr
             ORDER BY p.date_paiement DESC
             LIMIT ? OFFSET ?",
            array_merge($params, [ITEMS_PER_PAGE, $offset])
        )->fetchAll();

        $total_pages = ceil($total / ITEMS_PER_PAGE);

        // Total encaissé
        $total_encaisse = (float) Database::query(
            "SELECT COALESCE(SUM(montant),0) FROM paiements WHERE " . $whereStr,
            $params
        )->fetchColumn();

        require APP_ROOT . '/app/views/layouts/header.php';

        $page_title = 'Paiements';
        ?>

<div class="section-header mb-3">
  <h2><i class="bi bi-credit-card me-2"></i>Paiements
    <span class="badge bg-secondary ms-2"><?= $total ?></span>
  </h2>
  <div class="fw-bold text-success"><?= format_money($total_encaisse) ?> encaissés</div>
</div>

<!-- Filtre mode -->
<div class="d-flex gap-2 mb-3">
  <?php foreach ([''=>'Tous','especes'=>'💵 Espèces','mobile_money'=>'📱 Mobile Money','carte'=>'💳 Carte','virement'=>'🏦 Virement'] as $v=>$l): ?>
  <a href="?page=paiements&mode=<?= $v ?>"
     class="btn btn-sm <?= $mode===$v ? 'btn-primary' : 'btn-outline-secondary' ?>">
    <?= $l ?>
  </a>
  <?php endforeach; ?>
</div>

<div class="card">
  <div class="card-body p-0">
    <table class="table mb-0">
      <thead>
        <tr>
          <th>Date</th><th>Facture</th><th>Client</th>
          <th>Mode</th><th>Réf. transaction</th>
          <th class="text-end">Montant</th><th>Encaissé par</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($paiements)): ?>
        <tr><td colspan="7" class="text-center text-muted py-4">Aucun paiement enregistré.</td></tr>
        <?php endif; ?>
        <?php foreach ($paiements as $p): ?>
        <tr>
          <td class="small"><?= format_datetime($p['date_paiement']) ?></td>
          <td>
            <a href="?page=facturation&action=show&id=<?= $p['facture_id'] ?>"
               class="text-primary fw-semibold small">
              <?= e($p['fac_ref']) ?>
            </a>
          </td>
          <td class="small"><?= e($p['client_nom']) ?></td>
          <td>
            <?php
            $mode_icons = ['especes'=>'💵','mobile_money'=>'📱','carte'=>'💳','virement'=>'🏦','cheque'=>'📄'];
            echo ($mode_icons[$p['mode']] ?? '💰') . ' ';
            echo ucfirst(str_replace('_',' ',$p['mode']));
            ?>
          </td>
          <td class="small text-muted"><?= $p['reference_paiement'] ? e($p['reference_paiement']) : '—' ?></td>
          <td class="text-end fw-bold text-success"><?= format_money($p['montant']) ?></td>
          <td class="small text-muted"><?= e($p['encaisse_par']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($total_pages > 1): ?>
<nav class="mt-3">
  <ul class="pagination pagination-sm justify-content-end">
    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
    <li class="page-item <?= $i == $page_num ? 'active' : '' ?>">
      <a class="page-link" href="?page=paiements&p=<?= $i ?>"><?= $i ?></a>
    </li>
    <?php endfor; ?>
  </ul>
</nav>
<?php endif; ?>

        <?php
        require APP_ROOT . '/app/views/layouts/footer.php';
    }
}
