<?php
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
requireModuleAccess('rh');

$pageTitle = 'Ressources Humaines';
$db = getDB();

// Stats
$employesActifs = $db->query("SELECT COUNT(*) FROM employes e JOIN contrats_employes ce ON ce.employe_id=e.id WHERE e.statut='actif' AND ce.statut='actif'")->fetchColumn();
$masseSalariale = $db->query("SELECT COALESCE(SUM(ce.salaire_base),0) FROM contrats_employes ce JOIN employes e ON ce.employe_id=e.id WHERE e.statut='actif' AND ce.statut='actif'")->fetchColumn();

$parContrat = $db->query("
    SELECT ce.type_contrat, COUNT(*) as nb
    FROM contrats_employes ce
    JOIN employes e ON ce.employe_id=e.id
    WHERE e.statut='actif' AND ce.statut='actif'
    GROUP BY ce.type_contrat
    ORDER BY nb DESC
")->fetchAll();

$parAgence = $db->query("
    SELECT COALESCE(a.nom,'Non affecté') as agence, COUNT(*) as nb
    FROM employes e
    JOIN contrats_employes ce ON ce.employe_id=e.id
    LEFT JOIN agences a ON e.agence_id=a.id
    WHERE e.statut='actif' AND ce.statut='actif'
    GROUP BY e.agence_id
    ORDER BY nb DESC
")->fetchAll();

$recentEmbauches = $db->query("
    SELECT e.id, e.nom, e.prenom, e.matricule, e.utilisateur_id, ce.date_embauche, ce.type_contrat, a.nom as agence
    FROM employes e
    JOIN contrats_employes ce ON ce.employe_id=e.id
    LEFT JOIN agences a ON e.agence_id=a.id
    WHERE e.statut='actif'
    ORDER BY ce.date_embauche DESC
    LIMIT 10
")->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px">
    <div>
        <h1 style="font-size:22px;font-weight:700;color:var(--text);margin:0">Ressources Humaines</h1>
        <p style="color:var(--text3);font-size:13px;margin:4px 0 0">Gestion des employés et contrats</p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <?php if (hasPermission('rh', 'modifier')): ?>
        <a href="employes.php" class="btn btn-primary"><i class="ph-bold ph-plus"></i> Créer un employé</a>
        <?php endif; ?>
        <?php if (hasPermission('rh', 'importer')): ?>
        <a href="import_employes.php" class="btn btn-outline"><i class="ph-bold ph-upload-simple"></i> Importer</a>
        <?php endif; ?>
        <a href="employes.php" class="btn btn-outline"><i class="ph-bold ph-list"></i> Liste des employés</a>
    </div>
</div>

<div class="stats-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin-bottom:24px">
    <div class="stat-card">
        <div class="stat-label">Employés actifs</div>
        <div class="stat-value"><?= $employesActifs ?></div>
    </div>
    <div class="stat-card info">
        <div class="stat-label">Masse salariale</div>
        <div class="stat-value"><?= formatMontant($masseSalariale) ?></div>
    </div>
</div>

<?php if ($parContrat): ?>
<div class="card" style="margin-bottom:20px">
    <div class="card-header">
        <span class="card-title">Par type de contrat</span>
    </div>
    <div class="card-body">
        <div style="display:flex;gap:10px;flex-wrap:wrap">
            <?php foreach ($parContrat as $pc): ?>
            <span class="badge badge-info" style="font-size:12px;padding:5px 12px">
                <?= strtoupper(sanitize($pc['type_contrat'])) ?> : <?= $pc['nb'] ?>
            </span>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($parAgence): ?>
<div class="card" style="margin-bottom:20px">
    <div class="card-header">
        <span class="card-title">Par agence</span>
    </div>
    <div class="card-body">
        <div style="display:flex;gap:10px;flex-wrap:wrap">
            <?php foreach ($parAgence as $pa): ?>
            <span class="badge badge-gray" style="font-size:12px;padding:5px 12px">
                <?= sanitize($pa['agence']) ?> : <?= $pa['nb'] ?>
            </span>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($recentEmbauches): ?>
<div class="card">
    <div class="card-header">
        <span class="card-title">Dernières embauches</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Matricule</th>
                    <th>Date embauche</th>
                    <th>Contrat</th>
                    <th>Agence</th>
                    <th>Compte</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentEmbauches as $e): ?>
                <tr>
                    <td><?= sanitize($e['prenom'] . ' ' . $e['nom']) ?></td>
                    <td><?= sanitize($e['matricule'] ?? '-') ?></td>
                    <td><?= sanitize($e['date_embauche']) ?></td>
                    <td><span class="badge badge-info"><?= strtoupper(sanitize($e['type_contrat'])) ?></span></td>
                    <td><?= sanitize($e['agence'] ?? '-') ?></td>
                    <td>
                        <?php if ($e['utilisateur_id']): ?>
                        <span class="badge badge-success">Oui</span>
                        <?php else: ?>
                        <span class="badge badge-gray">Non</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>