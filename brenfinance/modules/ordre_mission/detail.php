<?php
require_once __DIR__ . '/../../includes/functions.php';
requireModuleAccess('ordre_mission');
$pageTitle = 'Détail ordre de mission';

$db = getDB();
$userId = $_SESSION['user_id'];

$id = (int)($_GET['id'] ?? 0);
$om = $db->prepare("SELECT om.*, CONCAT(u.nom,' ',u.prenom) as demandeur_nom, u.fonction as demandeur_fonction, u.email as demandeur_email, s.nom as service_nom, d.libelle as destination_nom, mp.libelle as mode_paiement_nom, c.libelle as caisse_nom
    FROM ordres_mission om
    JOIN utilisateurs u ON om.demandeur_id=u.id
    JOIN services s ON om.service_id=s.id
    LEFT JOIN destinations d ON om.destination_id=d.id
    LEFT JOIN modes_paiement mp ON om.mode_paiement_id=mp.id
    LEFT JOIN caisses c ON om.caisse_id=c.id
    WHERE om.id=?");
$om->execute([$id]);
$om = $om->fetch();

if (!$om) { flash('danger', 'Ordre de mission introuvable.'); header('Location: index.php'); exit; }

$lignes = $db->prepare("SELECT * FROM lignes_ordre_mission WHERE ordre_mission_id=? ORDER BY ordre");
$lignes->execute([$id]);
$lignes = $lignes->fetchAll();

$validations = $db->prepare("SELECT vom.*, CONCAT(u.nom,' ',u.prenom) as valideur_nom FROM validations_ordre_mission vom JOIN utilisateurs u ON vom.valideur_id=u.id WHERE vom.ordre_mission_id=? ORDER BY vom.date_validation");
$validations->execute([$id]);
$validations = $validations->fetchAll();

$entreprise = getEntreprise();

$badges = ['brouillon'=>'gray','soumis'=>'info','valide_hierarchie'=>'purple','approuve'=>'teal','execute'=>'success','rejete'=>'danger','renvoye'=>'warning','annule'=>'danger'];
$labels = ['brouillon'=>'Brouillon','soumis'=>'Soumis','valide_hierarchie'=>'Val. Hiérarchie','approuve'=>'Approuvé','execute'=>'Exécuté','rejete'=>'Rejeté','renvoye'=>'Renvoyé','annule'=>'Annulé'];
$prioriteLabels = ['normale'=>'Normale','urgente'=>'Urgente','tres_urgente'=>'Très urgente'];
$prioriteBadges = ['normale'=>'gray','urgente'=>'warning','tres_urgente'=>'danger'];

$canEdit = in_array($om['statut'], ['brouillon','renvoye']) && (int)$om['demandeur_id'] === $userId;
$canValidateN1 = $om['statut'] === 'soumis' && hasPermission('ordre_mission', 'valider_hierarchie');
$canValidateDAF = $om['statut'] === 'valide_hierarchie' && hasPermission('ordre_mission', 'valider_daf');

if ($canValidateN1) {
    $rR = $db->prepare("SELECT responsable_id FROM services WHERE id=?");
    $rR->execute([$om['service_id']]);
    $rId = $rR->fetchColumn();
    if ($rId && (int)$rId !== $userId && !hasPermission('ordre_mission', 'all') && !hasPermission('all', 'all')) $canValidateN1 = false;
}

$canSubmit = $om['statut'] === 'brouillon' && (int)$om['demandeur_id'] === $userId;

// Workflow stepper
$steps = [
    ['soumis','Soumis'],
    ['valide_hierarchie','Val. Hiérarchie'],
    ['approuve','Approuvé'],
    ['execute','Exécuté'],
];
$statusOrder = ['brouillon'=>0,'renvoye'=>0,'soumis'=>1,'valide_hierarchie'=>2,'approuve'=>3,'execute'=>4];
$currentStep = $statusOrder[$om['statut']] ?? 0;
$rejected = in_array($om['statut'], ['rejete','annule']);

// Pieces jointes
$pieces = json_decode($om['pieces_jointes'] ?? '[]', true);

// Nb jours
$nbJours = max(1, (int)((strtotime($om['date_retour']) - strtotime($om['date_depart'])) / 86400) + 1);

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header d-flex justify-between align-center">
  <div>
    <h1>Ordre de mission <code><?= sanitize($om['numero']) ?></code></h1>
    <p>Créé le <?= date('d/m/Y H:i', strtotime($om['created_at'])) ?></p>
  </div>
  <div class="d-flex gap-8">
    <?php if ($canEdit): ?>
    <a href="modifier.php?id=<?= $om['id'] ?>" class="btn btn-outline"><i class="fa-solid fa-pen"></i> Modifier</a>
    <?php endif; ?>
    <?php if ($canSubmit): ?>
    <form method="post" action="index.php" style="display:inline">
      <input type="hidden" name="action" value="soumettre">
      <input type="hidden" name="ordre_mission_id" value="<?= $om['id'] ?>">
      <button type="submit" class="btn btn-primary">Soumettre pour validation</button>
    </form>
    <?php endif; ?>
    <a href="index.php" class="btn btn-outline"><i class="fa-solid fa-arrow-left"></i> Retour</a>
  </div>
</div>

<!-- Workflow stepper -->
<div class="card mb-20">
  <div class="card-body" style="padding:20px 24px">
    <div class="workflow">
      <?php foreach ($steps as $i => [$st, $lbl]):
        $done = $currentStep > $i || ($currentStep === $i && !in_array($om['statut'], ['brouillon','renvoye']));
        $active = $currentStep === $i && !in_array($om['statut'], ['brouillon','renvoye','rejete','annule']);
      ?>
      <div class="workflow-step">
        <div class="workflow-step-inner">
          <div class="step-circle <?= $done ? 'done' : '' ?> <?= $active ? 'active' : '' ?> <?= $rejected && $i === $currentStep ? 'rejected' : '' ?>">
            <?php if ($done): ?><i class="fa-solid fa-check"></i><?php elseif ($rejected && $i === $currentStep): ?><i class="fa-solid fa-xmark"></i><?php else: ?><?= $i + 1 ?><?php endif; ?>
          </div>
          <div class="step-label <?= $active ? 'active' : '' ?>"><?= $lbl ?></div>
        </div>
        <?php if ($i < count($steps) - 1): ?>
        <div class="workflow-line <?= $done ? 'done' : '' ?>"></div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <div style="text-align:center;margin-top:10px">
      <span class="badge badge-<?= $badges[$om['statut']] ?? 'gray' ?>" style="font-size:13px;padding:6px 16px"><?= $labels[$om['statut']] ?? $om['statut'] ?></span>
      <?php if ($om['statut'] === 'renvoye' && $om['commentaire_rejet']): ?>
      <div style="margin-top:6px;font-size:12px;color:var(--warning)">Motif : <?= sanitize($om['commentaire_rejet']) ?></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Fiche ordre de mission -->
<div class="card mb-20">
  <div class="card-header" style="background:var(--surface2);border-bottom:2px solid var(--primary)">
    <span class="card-title" style="font-size:15px">ORDRE DE MISSION — <?= sanitize($om['numero']) ?></span>
    <span style="margin-left:auto;font-size:12px;color:var(--text3)">Émis le <?= date('d/m/Y', strtotime($om['created_at'])) ?></span>
  </div>
  <div class="card-body" style="padding:24px">

    <!-- En-tête entreprise -->
    <div style="background:var(--surface2);padding:12px 18px;border-radius:var(--radius);margin-bottom:20px;font-size:12px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
      <div>
        <strong style="color:var(--primary);font-size:14px"><?= sanitize($entreprise['nom'] ?? 'BrenFinance') ?></strong><br>
        <span style="color:var(--text3)"><?= sanitize($entreprise['adresse'] ?? '') ?><?php if (!empty($entreprise['telephone'])): ?> — <?= sanitize($entreprise['telephone']) ?><?php endif; ?></span>
      </div>
      <div style="text-align:right">
        <div><span style="color:var(--text3)">Réf :</span> <strong style="color:var(--primary)"><?= sanitize($om['numero']) ?></strong></div>
        <div><span style="color:var(--text3)">Date :</span> <strong><?= date('d/m/Y', strtotime($om['created_at'])) ?></strong></div>
        <div><span style="color:var(--text3)">Priorité :</span> <span class="badge badge-<?= $prioriteBadges[$om['priorite']] ?? 'gray' ?>"><?= $prioriteLabels[$om['priorite']] ?? $om['priorite'] ?></span></div>
      </div>
    </div>

    <!-- 1. Identification du collaborateur -->
    <div style="font-size:12px;font-weight:700;color:var(--primary);margin-bottom:8px;text-transform:uppercase;letter-spacing:0.5px">1. Identification du collaborateur</div>
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:0;border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;margin-bottom:20px;font-size:13px">
      <div style="padding:10px 14px;border-right:1px solid var(--border);border-bottom:1px solid var(--border)">
        <div style="color:var(--text3);font-size:11px;margin-bottom:2px">Nom & Prénom</div>
        <div style="font-weight:600"><?= sanitize($om['demandeur_nom']) ?></div>
      </div>
      <div style="padding:10px 14px;border-right:1px solid var(--border);border-bottom:1px solid var(--border)">
        <div style="color:var(--text3);font-size:11px;margin-bottom:2px">Fonction</div>
        <div style="font-weight:600"><?= !empty($om['demandeur_fonction']) ? sanitize($om['demandeur_fonction']) : '—' ?></div>
      </div>
      <div style="padding:10px 14px;border-bottom:1px solid var(--border)">
        <div style="color:var(--text3);font-size:11px;margin-bottom:2px">Service / Direction</div>
        <div style="font-weight:600"><?= sanitize($om['service_nom']) ?></div>
      </div>
      <div style="padding:10px 14px;border-right:1px solid var(--border)">
        <div style="color:var(--text3);font-size:11px;margin-bottom:2px">Personne à prévenir (urgence)</div>
        <div style="font-weight:600"><?= !empty($om['personne_urgence']) ? sanitize($om['personne_urgence']) : '—' ?></div>
      </div>
      <div style="padding:10px 14px" colspan="2">
        <div style="color:var(--text3);font-size:11px;margin-bottom:2px">Téléphone d'urgence</div>
        <div style="font-weight:600"><?= !empty($om['tel_urgence']) ? sanitize($om['tel_urgence']) : '—' ?></div>
      </div>
    </div>

    <!-- 2. Objet et lieu de la mission -->
    <div style="font-size:12px;font-weight:700;color:var(--primary);margin-bottom:8px;text-transform:uppercase;letter-spacing:0.5px">2. Objet et lieu de la mission</div>
    <div style="border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;margin-bottom:20px;font-size:13px">
      <div style="padding:10px 14px;border-bottom:1px solid var(--border)">
        <div style="color:var(--text3);font-size:11px;margin-bottom:2px">Objet de la mission</div>
        <div style="font-weight:600"><?= sanitize($om['objet']) ?></div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr">
        <div style="padding:10px 14px;border-right:1px solid var(--border);border-bottom:1px solid var(--border)">
          <div style="color:var(--text3);font-size:11px;margin-bottom:2px">Lieu / Ville</div>
          <div style="font-weight:600"><?= !empty($om['lieu_mission']) ? sanitize($om['lieu_mission']) : '—' ?></div>
        </div>
        <div style="padding:10px 14px;border-right:1px solid var(--border);border-bottom:1px solid var(--border)">
          <div style="color:var(--text3);font-size:11px;margin-bottom:2px">Destination</div>
          <div style="font-weight:600"><?= !empty($om['destination_nom']) ? sanitize($om['destination_nom']) : '—' ?></div>
        </div>
        <div style="padding:10px 14px;border-bottom:1px solid var(--border)">
          <div style="color:var(--text3);font-size:11px;margin-bottom:2px">Adresse complète</div>
          <div style="font-weight:600"><?= !empty($om['adresse_mission']) ? sanitize($om['adresse_mission']) : '—' ?></div>
        </div>
      </div>
    </div>

    <!-- 3. Dates, durée et transport -->
    <div style="font-size:12px;font-weight:700;color:var(--primary);margin-bottom:8px;text-transform:uppercase;letter-spacing:0.5px">3. Dates, durée et transport</div>
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:0;border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;margin-bottom:20px;font-size:13px">
      <div style="padding:10px 14px;border-right:1px solid var(--border);border-bottom:1px solid var(--border)">
        <div style="color:var(--text3);font-size:11px;margin-bottom:2px">Date de départ</div>
        <div style="font-weight:600"><?= date('d/m/Y', strtotime($om['date_depart'])) ?><?php if (!empty($om['heure_depart'])): ?> à <?= sanitize($om['heure_depart']) ?><?php endif; ?></div>
      </div>
      <div style="padding:10px 14px;border-right:1px solid var(--border);border-bottom:1px solid var(--border)">
        <div style="color:var(--text3);font-size:11px;margin-bottom:2px">Date de retour</div>
        <div style="font-weight:600"><?= date('d/m/Y', strtotime($om['date_retour'])) ?><?php if (!empty($om['heure_retour'])): ?> à <?= sanitize($om['heure_retour']) ?><?php endif; ?></div>
      </div>
      <div style="padding:10px 14px;border-right:1px solid var(--border);border-bottom:1px solid var(--border)">
        <div style="color:var(--text3);font-size:11px;margin-bottom:2px">Durée</div>
        <div style="font-weight:600"><?= $nbJours ?> jour(s)</div>
      </div>
      <div style="padding:10px 14px;border-bottom:1px solid var(--border)">
        <div style="color:var(--text3);font-size:11px;margin-bottom:2px">Moyen de transport</div>
        <div style="font-weight:600"><?= !empty($om['moyen_transport']) ? sanitize($om['moyen_transport']) : '—' ?></div>
      </div>
    </div>

    <!-- 4. Prise en charge des frais -->
    <div style="font-size:12px;font-weight:700;color:var(--primary);margin-bottom:8px;text-transform:uppercase;letter-spacing:0.5px">4. Prise en charge des frais</div>
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:0;border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;margin-bottom:20px;font-size:13px">
      <div style="padding:10px 14px;border-right:1px solid var(--border);border-bottom:1px solid var(--border)">
        <div style="color:var(--text3);font-size:11px;margin-bottom:2px">Mode de paiement</div>
        <div style="font-weight:600"><?= !empty($om['mode_paiement_nom']) ? sanitize($om['mode_paiement_nom']) : '—' ?></div>
      </div>
      <div style="padding:10px 14px;border-right:1px solid var(--border);border-bottom:1px solid var(--border)">
        <div style="color:var(--text3);font-size:11px;margin-bottom:2px">Caisse</div>
        <div style="font-weight:600"><?= !empty($om['caisse_nom']) ? sanitize($om['caisse_nom']) : '—' ?></div>
      </div>
      <div style="padding:10px 14px;border-right:1px solid var(--border);border-bottom:1px solid var(--border)">
        <div style="color:var(--text3);font-size:11px;margin-bottom:2px">Plafond hébergement / nuit</div>
        <div style="font-weight:600"><?= !empty($om['plafond_hebergement']) ? formatMontant($om['plafond_hebergement']) : '—' ?></div>
      </div>
      <div style="padding:10px 14px;border-bottom:1px solid var(--border)">
        <div style="color:var(--text3);font-size:11px;margin-bottom:2px">Plafond repas / jour</div>
        <div style="font-weight:600"><?= !empty($om['plafond_repas']) ? formatMontant($om['plafond_repas']) : '—' ?></div>
      </div>
    </div>

    <!-- 5. Budget prévisionnel -->
    <div style="font-size:12px;font-weight:700;color:var(--primary);margin-bottom:8px;text-transform:uppercase;letter-spacing:0.5px">5. Budget prévisionnel</div>
    <?php if (!empty($lignes)): ?>
    <div class="table-wrap" style="margin-bottom:20px">
      <table style="font-size:13px">
        <thead>
          <tr>
            <th>Nature de la dépense</th>
            <th style="width:80px;text-align:right">Quantité</th>
            <th style="width:140px;text-align:right">Coût unitaire</th>
            <th style="width:140px;text-align:right">Montant</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($lignes as $l): ?>
          <tr>
            <td><?= sanitize($l['libelle']) ?></td>
            <td class="amount"><?= number_format($l['quantite'], 0, ',', ' ') ?></td>
            <td class="amount"><?= formatMontant($l['cout_unitaire']) ?></td>
            <td class="amount fw-bold"><?= formatMontant($l['montant']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr style="border-top:2px solid var(--text2)">
            <td colspan="3" style="text-align:right;font-weight:700;font-size:14px">Total estimé</td>
            <td class="amount fw-bold" style="font-size:15px;color:var(--primary)"><?= formatMontant($om['montant']) ?></td>
          </tr>
          <tr>
            <td colspan="3" style="text-align:right;font-style:italic;color:var(--text3);font-size:12px">Montant en lettres</td>
            <td style="font-style:italic;font-size:12px"><?= montantEnLettres($om['montant']) ?></td>
          </tr>
        </tfoot>
      </table>
    </div>
    <?php else: ?>
    <div style="padding:16px;text-align:center;color:var(--text3);border:1px solid var(--border);border-radius:var(--radius);margin-bottom:20px">Aucun détail de frais renseigné</div>
    <?php endif; ?>

    <!-- 6. Pièces jointes -->
    <?php if (!empty($pieces)): ?>
    <div style="font-size:12px;font-weight:700;color:var(--primary);margin-bottom:8px;text-transform:uppercase;letter-spacing:0.5px">6. Pièces jointes</div>
    <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:20px">
      <?php foreach ($pieces as $p): ?>
      <a href="<?= BASE_URL ?>/uploads/ordres_mission/<?= sanitize($p['fichier']) ?>" target="_blank" style="display:flex;align-items:center;gap:6px;padding:8px 12px;border:1px solid var(--border);border-radius:var(--radius);font-size:12px;text-decoration:none;color:var(--text2)">
        <span style="font-size:18px"><?= fileIcon($p['type'] ?? '') ?></span>
        <span><?= sanitize($p['nom']) ?></span>
        <span style="color:var(--text3)">(<?= formatFileSize($p['taille'] ?? 0) ?>)</span>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Signatures -->
    <div style="font-size:12px;font-weight:700;color:var(--primary);margin-bottom:12px;text-transform:uppercase;letter-spacing:0.5px">7. Visa et signatures</div>
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:8px">
      <?php
      $validHierarchie = null;
      $validDAF = null;
      foreach ($validations as $v) {
          if ($v['etape'] === 'hierarchie' && $v['action'] === 'approuve') $validHierarchie = $v;
          if ($v['etape'] === 'daf' && $v['action'] === 'approuve') $validDAF = $v;
      }
      ?>
      <div style="border:1px solid var(--border);border-radius:var(--radius);padding:16px;text-align:center">
        <div style="font-size:11px;text-transform:uppercase;color:var(--text3);margin-bottom:14px;font-weight:600">Visa hiérarchique</div>
        <?php if ($validHierarchie): ?>
          <div style="color:var(--success);font-size:20px;margin-bottom:4px"><i class="fa-solid fa-check"></i></div>
          <div style="font-weight:600;font-size:12px"><?= sanitize($validHierarchie['valideur_nom']) ?></div>
          <div style="color:var(--text3);font-size:11px"><?= date('d/m/Y H:i', strtotime($validHierarchie['date_validation'])) ?></div>
        <?php else: ?>
          <div style="color:var(--text3);font-size:12px;margin:20px 0">En attente</div>
        <?php endif; ?>
      </div>
      <div style="border:1px solid var(--border);border-radius:var(--radius);padding:16px;text-align:center">
        <div style="font-size:11px;text-transform:uppercase;color:var(--text3);margin-bottom:14px;font-weight:600">Visa DAF</div>
        <?php if ($validDAF): ?>
          <div style="color:var(--success);font-size:20px;margin-bottom:4px"><i class="fa-solid fa-check"></i></div>
          <div style="font-weight:600;font-size:12px"><?= sanitize($validDAF['valideur_nom']) ?></div>
          <div style="color:var(--text3);font-size:11px"><?= date('d/m/Y H:i', strtotime($validDAF['date_validation'])) ?></div>
        <?php else: ?>
          <div style="color:var(--text3);font-size:12px;margin:20px 0">En attente</div>
        <?php endif; ?>
      </div>
      <div style="border:1px solid var(--border);border-radius:var(--radius);padding:16px;text-align:center">
        <div style="font-size:11px;text-transform:uppercase;color:var(--text3);margin-bottom:14px;font-weight:600">Lu et approuvé</div>
        <div style="font-weight:600;font-size:12px"><?= sanitize($om['demandeur_nom']) ?></div>
        <div style="color:var(--text3);font-size:11px;margin-top:4px"><?= !empty($om['demandeur_fonction']) ? sanitize($om['demandeur_fonction']) : '' ?></div>
        <div style="margin-top:30px;border-top:1px dashed var(--border);padding-top:6px;font-size:10px;color:var(--text3)">Signature</div>
      </div>
    </div>

  </div>
</div>

<!-- Validation actions -->
<?php if ($canValidateN1 || $canValidateDAF): ?>
<div class="card mb-20">
  <div class="card-header"><span class="card-title">Validation requise</span></div>
  <div class="card-body">
    <form method="post" action="index.php">
      <input type="hidden" name="action" value="valider">
      <input type="hidden" name="ordre_mission_id" value="<?= $om['id'] ?>">
      <input type="hidden" name="etape" value="<?= $canValidateN1 ? 'hierarchie' : 'daf' ?>">
      <input type="hidden" name="from_detail" value="1">
      <div class="form-row-3">
        <div class="form-group">
          <label class="form-label">Décision <span class="req">*</span></label>
          <select name="decision" class="form-control" required>
            <option value="approuve">Approuver</option>
            <option value="renvoi">Renvoyer pour révision</option>
            <option value="rejete">Rejeter</option>
          </select>
        </div>
        <div class="form-group" style="grid-column:span 2">
          <label class="form-label">Commentaire</label>
          <textarea name="commentaire" class="form-control" rows="2" placeholder="Motif, conditions..."></textarea>
        </div>
      </div>
      <div style="text-align:right;margin-top:8px">
        <button type="submit" class="btn btn-primary">Confirmer la validation</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- Validation history -->
<?php if (!empty($validations)): ?>
<div class="card mb-20">
  <div class="card-header"><span class="card-title">Historique des validations</span></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Étape</th><th>Valideur</th><th>Décision</th><th>Commentaire</th><th>Date</th></tr></thead>
      <tbody>
        <?php foreach ($validations as $v):
          $etapeLabels = ['hierarchie'=>'Hiérarchie','daf'=>'DAF','execution'=>'Exécution'];
          $actionLabels = ['approuve'=>'Approuvé','rejete'=>'Rejeté','renvoi'=>'Renvoyé'];
          $actionBadges = ['approuve'=>'success','rejete'=>'danger','renvoi'=>'warning'];
        ?>
        <tr>
          <td><span class="badge badge-purple"><?= $etapeLabels[$v['etape']] ?? $v['etape'] ?></span></td>
          <td><?= sanitize($v['valideur_nom']) ?></td>
          <td><span class="badge badge-<?= $actionBadges[$v['action']] ?? 'gray' ?>"><?= $actionLabels[$v['action']] ?? $v['action'] ?></span></td>
          <td style="max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= !empty($v['commentaire']) ? sanitize($v['commentaire']) : '—' ?></td>
          <td style="white-space:nowrap"><?= date('d/m/Y H:i', strtotime($v['date_validation'])) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>