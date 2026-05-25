<?php
/**
 * PDF generation for invoices.
 * Uses TCPDF to generate downloadable PDF invoices.
 */
require_once __DIR__ . '/auth.php';
requireLogin('admin');

require_once __DIR__ . '/db.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
  http_response_code(400);
  die('ID facture requis');
}

$facture = getFactureById($id);
if (!$facture) {
  http_response_code(404);
  die('Facture introuvable');
}

$params = getParametres();
$logoPath = getLogoPath();
$entrepriseNom = $params['entreprise_nom'] ?? 'ESADISS';
$entrepriseAdresse = $params['entreprise_adresse'] ?? '';
$entrepriseTel = $params['entreprise_telephone'] ?? '';
$entrepriseEmail = $params['entreprise_email'] ?? '';
$devise = $params['devise'] ?? 'FCFA';

// Check if TCPDF is available
$tcpdfPath = __DIR__ . '/tcpdf/tcpdf.php';
if (!file_exists($tcpdfPath)) {
  // Fallback: generate HTML invoice if TCPDF not installed
  generateHtmlInvoice($facture, $params, $logoPath, $entrepriseNom, $entrepriseAdresse, $entrepriseTel, $entrepriseEmail, $devise);
  exit;
}

require_once $tcpdfPath;

$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->SetCreator('ESADISS');
$pdf->SetAuthor($entrepriseNom);
$pdf->SetTitle('Facture ' . $facture['numero']);
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, 15);
$pdf->AddPage();

// Logo
if ($logoPath && file_exists(__DIR__ . '/' . $logoPath)) {
  $pdf->Image(__DIR__ . '/' . $logoPath, 15, 15, 40);
}

// Header
$pdf->SetFont('helvetica', 'B', 18);
$pdf->Cell(0, 10, $entrepriseNom, 0, 1);
$pdf->SetFont('helvetica', '', 9);
if ($entrepriseAdresse) $pdf->Cell(0, 5, $entrepriseAdresse, 0, 1);
if ($entrepriseTel) $pdf->Cell(0, 5, 'Tel: ' . $entrepriseTel, 0, 1);
if ($entrepriseEmail) $pdf->Cell(0, 5, $entrepriseEmail, 0, 1);
$pdf->Ln(10);

// Client info
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(0, 6, 'Facture : ' . $facture['numero'], 0, 1);
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 5, 'Client : ' . ($facture['client_nom'] ?? $facture['client_login'] ?? ''), 0, 1);
$pdf->Cell(0, 5, 'Date : ' . $facture['date_facture'], 0, 1);
if ($facture['echeance']) $pdf->Cell(0, 5, 'Echeance : ' . $facture['echeance'], 0, 1);
$statutLabels = ['brouillon' => 'Brouillon', 'envoyee' => 'Envoyee', 'payee_partiellement' => 'Payee partiellement', 'payee' => 'Payee', 'annulee' => 'Annulee'];
$pdf->Cell(0, 5, 'Statut : ' . ($statutLabels[$facture['statut']] ?? $facture['statut']), 0, 1);
$pdf->Ln(5);

// Line items table
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetFillColor(240, 240, 240);
$pdf->Cell(80, 7, 'Designation', 1, 0, 'L', true);
$pdf->Cell(25, 7, 'Qte', 1, 0, 'C', true);
$pdf->Cell(35, 7, 'Prix unitaire', 1, 0, 'R', true);
$pdf->Cell(35, 7, 'Total', 1, 1, 'R', true);

$pdf->SetFont('helvetica', '', 9);
foreach ($facture['lignes'] as $ligne) {
  $pdf->Cell(80, 6, $ligne['designation'], 1, 0, 'L');
  $pdf->Cell(25, 6, number_format($ligne['quantite'], 2, ',', ' '), 1, 0, 'C');
  $pdf->Cell(35, 6, number_format($ligne['prix_unitaire'], 0, ',', ' ') . ' ' . $devise, 1, 0, 'R');
  $pdf->Cell(35, 6, number_format($ligne['total_ligne'], 0, ',', ' ') . ' ' . $devise, 1, 1, 'R');
}

// Totals
$pdf->Ln(5);
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(140, 6, 'Total HT', 0, 0, 'R');
$pdf->Cell(35, 6, number_format($facture['montant_ht'], 0, ',', ' ') . ' ' . $devise, 0, 1, 'R');
if ($facture['tva'] > 0) {
  $tvaMontant = $facture['montant_ttc'] - $facture['montant_ht'];
  $pdf->Cell(140, 6, 'TVA (' . number_format($facture['tva'], 1, ',', ' ') . '%)', 0, 0, 'R');
  $pdf->Cell(35, 6, number_format($tvaMontant, 0, ',', ' ') . ' ' . $devise, 0, 1, 'R');
}
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(140, 7, 'Total TTC', 0, 0, 'R');
$pdf->Cell(35, 7, number_format($facture['montant_ttc'], 0, ',', ' ') . ' ' . $devise, 0, 1, 'R');
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(140, 6, 'Paye', 0, 0, 'R');
$pdf->Cell(35, 6, number_format($facture['montant_paye'], 0, ',', ' ') . ' ' . $devise, 0, 1, 'R');
$reste = $facture['montant_ttc'] - $facture['montant_paye'];
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(140, 6, 'Reste a payer', 0, 0, 'R');
$pdf->Cell(35, 6, number_format($reste, 0, ',', ' ') . ' ' . $devise, 0, 1, 'R');

if ($facture['notes']) {
  $pdf->Ln(10);
  $pdf->SetFont('helvetica', '', 9);
  $pdf->Cell(0, 5, 'Notes : ' . $facture['notes'], 0, 1);
}

$pdf->Output('facture_' . $facture['numero'] . '.pdf', 'I');

function generateHtmlInvoice($facture, $params, $logoPath, $entrepriseNom, $entrepriseAdresse, $entrepriseTel, $entrepriseEmail, $devise) {
  $statutLabels = ['brouillon' => 'Brouillon', 'envoyee' => 'Envoyee', 'payee_partiellement' => 'Payee partiellement', 'payee' => 'Payee', 'annulee' => 'Annulee'];
  $reste = $facture['montant_ttc'] - $facture['montant_paye'];
  $tvaMontant = $facture['montant_ttc'] - $facture['montant_ht'];
  ?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Facture <?php echo htmlspecialchars($facture['numero']); ?></title>
  <style>
    body { font-family: Arial, Helvetica, sans-serif; color: #222; max-width: 800px; margin: 0 auto; padding: 2rem; }
    .header { display: flex; justify-content: space-between; margin-bottom: 2rem; }
    .header-left img { max-height: 60px; }
    .header-left h1 { margin: 0; font-size: 1.5rem; }
    .header-left p { margin: 0.1rem 0; font-size: 0.85rem; color: #555; }
    .info { margin-bottom: 1.5rem; font-size: 0.9rem; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 1rem; }
    th { background: #f3f3f3; text-align: left; padding: 0.5rem; border: 1px solid #ddd; font-size: 0.85rem; }
    td { padding: 0.4rem 0.5rem; border: 1px solid #ddd; font-size: 0.85rem; }
    td:last-child, th:last-child { text-align: right; }
    td:nth-child(2), th:nth-child(2) { text-align: center; }
    .totals { margin-left: auto; width: 300px; }
    .totals div { display: flex; justify-content: space-between; padding: 0.2rem 0; font-size: 0.9rem; }
    .totals .ttc { font-weight: bold; font-size: 1.1rem; border-top: 2px solid #222; padding-top: 0.4rem; margin-top: 0.2rem; }
    .totals .reste { color: #dc2626; font-weight: bold; }
    @media print { body { padding: 0; } }
  </style>
</head>
<body>
  <div class="header">
    <div class="header-left">
      <?php if ($logoPath): ?>
        <img src="<?php echo htmlspecialchars($logoPath); ?>" alt="Logo">
      <?php endif; ?>
      <h1><?php echo htmlspecialchars($entrepriseNom); ?></h1>
      <?php if ($entrepriseAdresse): ?><p><?php echo htmlspecialchars($entrepriseAdresse); ?></p><?php endif; ?>
      <?php if ($entrepriseTel): ?><p>Tel : <?php echo htmlspecialchars($entrepriseTel); ?></p><?php endif; ?>
      <?php if ($entrepriseEmail): ?><p><?php echo htmlspecialchars($entrepriseEmail); ?></p><?php endif; ?>
    </div>
  </div>
  <div class="info">
    <strong>Facture :</strong> <?php echo htmlspecialchars($facture['numero']); ?><br>
    <strong>Client :</strong> <?php echo htmlspecialchars($facture['client_nom'] ?? $facture['client_login'] ?? ''); ?><br>
    <strong>Date :</strong> <?php echo htmlspecialchars($facture['date_facture']); ?><br>
    <?php if ($facture['echeance']): ?><strong>Echeance :</strong> <?php echo htmlspecialchars($facture['echeance']); ?><br><?php endif; ?>
    <strong>Statut :</strong> <?php echo htmlspecialchars($statutLabels[$facture['statut']] ?? $facture['statut']); ?>
  </div>
  <table>
    <thead><tr><th>Designation</th><th>Qte</th><th>Prix unitaire</th><th>Total</th></tr></thead>
    <tbody>
    <?php foreach ($facture['lignes'] as $ligne): ?>
      <tr>
        <td><?php echo htmlspecialchars($ligne['designation']); ?></td>
        <td><?php echo number_format($ligne['quantite'], 2, ',', ' '); ?></td>
        <td><?php echo number_format($ligne['prix_unitaire'], 0, ',', ' '); ?> <?php echo htmlspecialchars($devise); ?></td>
        <td><?php echo number_format($ligne['total_ligne'], 0, ',', ' '); ?> <?php echo htmlspecialchars($devise); ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <div class="totals">
    <div><span>Total HT</span><span><?php echo number_format($facture['montant_ht'], 0, ',', ' '); ?> <?php echo htmlspecialchars($devise); ?></span></div>
    <?php if ($facture['tva'] > 0): ?>
      <div><span>TVA (<?php echo number_format($facture['tva'], 1, ',', ' '); ?>%)</span><span><?php echo number_format($tvaMontant, 0, ',', ' '); ?> <?php echo htmlspecialchars($devise); ?></span></div>
    <?php endif; ?>
    <div class="ttc"><span>Total TTC</span><span><?php echo number_format($facture['montant_ttc'], 0, ',', ' '); ?> <?php echo htmlspecialchars($devise); ?></span></div>
    <div><span>Paye</span><span><?php echo number_format($facture['montant_paye'], 0, ',', ' '); ?> <?php echo htmlspecialchars($devise); ?></span></div>
    <?php if ($reste > 0): ?>
      <div class="reste"><span>Reste a payer</span><span><?php echo number_format($reste, 0, ',', ' '); ?> <?php echo htmlspecialchars($devise); ?></span></div>
    <?php endif; ?>
  </div>
  <?php if ($facture['notes']): ?>
    <p style="margin-top:1.5rem;font-size:0.9rem;"><strong>Notes :</strong> <?php echo htmlspecialchars($facture['notes']); ?></p>
  <?php endif; ?>
  <script>window.print();</script>
</body>
</html>
<?php
}