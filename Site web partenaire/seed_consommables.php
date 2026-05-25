<?php
/**
 * Script pour insérer des consommables informatiques dans la base.
 * À lancer une seule fois, puis supprimer.
 */
require_once __DIR__ . '/config.php';

$pdo = new PDO(
  'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
  DB_USER, DB_PASS,
  [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$pdo->exec('DELETE FROM produits');
$pdo->exec('ALTER TABLE produits AUTO_INCREMENT = 1');

$produits = [
  // ── Toners HP ──
  ['HP 79A (CF279A) Toner Noir', 'CF279A', 'Toner compatible HP LaserJet Pro M12/M26', 8000, 11000],
  ['HP 85A (CE285A) Toner Noir', 'CE285A', 'Toner compatible HP LaserJet Pro P1102/M1212', 8000, 10500],
  ['HP 35A (CB435A) Toner Noir', 'CB435A', 'Toner compatible HP LaserJet P1005/P1006', 8000, 10000],
  ['HP 36A (CB436A) Toner Noir', 'CB436A', 'Toner compatible HP LaserJet P1505/M1120', 8000, 10000],
  ['HP 78A (CE278A) Toner Noir', 'CE278A', 'Toner compatible HP LaserJet P1560/P1606', 9000, 12000],
  ['HP 80A (CF280A) Toner Noir', 'CF280A', 'Toner compatible HP LaserJet Pro M401/M425', 10000, 13000],
  ['HP 26A (CF226A) Toner Noir', 'CF226A', 'Toner compatible HP LaserJet Pro M427/M428', 12000, 15000],
  ['HP 30A (CF230A) Toner Noir', 'CF230A', 'Toner compatible HP LaserJet Pro M130/M132', 10000, 12000],
  ['HP 44A (CF244A) Toner Noir', 'CF244A', 'Toner compatible HP LaserJet Pro M28/M31', 8000, 10000],
  ['HP 83A (CF283A) Toner Noir', 'CF283A', 'Toner compatible HP LaserJet Pro M201/M225', 12000, 15000],
  ['HP 305A (CE410A) Toner Noir', 'CE410A', 'Toner compatible HP LaserJet Pro CM1415/CP1525', 12000, 16000],
  ['HP 305A (CE411A) Toner Cyan', 'CE411A', 'Toner cyan compatible HP Color LaserJet CP1525', 14000, 18000],
  ['HP 305A (CE412A) Toner Jaune', 'CE412A', 'Toner jaune compatible HP Color LaserJet CP1525', 14000, 18000],
  ['HP 305A (CE413A) Toner Magenta', 'CE413A', 'Toner magenta compatible HP Color LaserJet CP1525', 14000, 18000],
  ['HP 410A (CF410A) Toner Noir', 'CF410A', 'Toner noir compatible HP Color LaserJet M452/M477', 14000, 18000],
  ['HP 410A (CF411A) Toner Cyan', 'CF411A', 'Toner cyan compatible HP Color LaserJet M452/M477', 16000, 20000],
  ['HP 410A (CF412A) Toner Jaune', 'CF412A', 'Toner jaune compatible HP Color LaserJet M452/M477', 16000, 20000],
  ['HP 410A (CF413A) Toner Magenta', 'CF413A', 'Toner magenta compatible HP Color LaserJet M452/M477', 16000, 20000],
  ['HP 507A (CE400A) Toner Noir', 'CE400A', 'Toner compatible HP LaserJet Enterprise 500/M575', 15000, 19000],
  ['HP 59A (CF259A) Toner Noir', 'CF259A', 'Toner compatible HP LaserJet Pro M29/M31', 9000, 12000],

  // ── Toners Canon ──
  ['Canon 054 Toner Noir', '054H BK', 'Toner compatible Canon imageCLASS MF641Cw/MF645Cx', 12000, 15000],
  ['Canon 054 Toner Cyan', '054H C', 'Toner cyan compatible Canon imageCLASS MF641Cw', 14000, 17000],
  ['Canon 054 Toner Jaune', '054H Y', 'Toner jaune compatible Canon imageCLASS MF641Cw', 14000, 17000],
  ['Canon 054 Toner Magenta', '054H M', 'Toner magenta compatible Canon imageCLASS MF641Cw', 14000, 17000],
  ['Canon 055 Toner Noir', '055H BK', 'Toner compatible Canon imageCLASS MF743Cdw', 14000, 18000],
  ['Canon C-EXV 42 Toner Noir', 'C-EXV 42', 'Toner compatible Canon iR C3020/C3025', 18000, 22000],
  ['Canon C-EXV 42 Toner Cyan', 'C-EXV 42 C', 'Toner cyan compatible Canon iR C3020', 20000, 25000],

  // ── Toners Brother ──
  ['Brother TN-2410 Toner Noir', 'TN-2410', 'Toner compatible Brother HL-L2310/L2350', 8000, 10000],
  ['Brother TN-2420 Toner Noir', 'TN-2420', 'Toner compatible Brother HL-L2350DW/L2370', 9000, 11000],
  ['Brother TN-1050 Toner Noir', 'TN-1050', 'Toner compatible Brother HL-L2360/DCP-L2520', 8000, 10000],
  ['Brother TN-3260 Toner Noir', 'TN-3260', 'Toner compatible Brother MFC-L2700/HL-L2365', 10000, 13000],
  ['Brother TN-3280 Toner Noir Haute Capacite', 'TN-3280', 'Toner haute capacite compatible Brother MFC-L2740', 13000, 16000],
  ['Brother TN-900 Toner Noir', 'TN-900', 'Toner compatible Brother MFC-L8900CDW', 18000, 22000],

  // ── Toners Samsung / Xerox ──
  ['Samsung MLT-D111S Toner Noir', 'MLT-D111S', 'Toner compatible Samsung Xpress M2020/M2070', 8000, 10000],
  ['Samsung MLT-D115S Toner Noir', 'MLT-D115S', 'Toner compatible Samsung Xpress M2830/M2885', 10000, 13000],
  ['Samsung MLT-D307L Toner Noir', 'MLT-D307L', 'Toner haute capacite compatible Samsung SCX-5739', 20000, 25000],
  ['Xerox 006R04399 Toner Noir', '006R04399', 'Toner compatible Xerox VersaLink B405', 15000, 19000],

  // ── Tambours (Drum) ──
  ['HP 19A Tambour (F2V29A)', 'F2V29A', 'Tambour compatible HP LaserJet Pro M12/M26', 10000, 13000],
  ['HP 32A Tambour (CF232A)', 'CF232A', 'Tambour compatible HP LaserJet Pro M130/M132', 12000, 15000],
  ['HP 49A Tambour (CF249A)', 'CF249A', 'Tambour compatible HP LaserJet Pro M28/M31', 10000, 13000],
  ['Canon 054 Tambour', '054 Drum', 'Tambour compatible Canon imageCLASS MF641Cw/MF645Cx', 20000, 25000],
  ['Brother DR-2410 Tambour', 'DR-2410', 'Tambour compatible Brother HL-L2310/L2350', 10000, 13000],
  ['Brother DR-3200 Tambour', 'DR-3200', 'Tambour compatible Brother MFC-L2700/HL-L2365', 12000, 15000],

  // ── Cartouches encre HP ──
  ['HP 305 Encre Noir', 'F8Q45AE', 'Cartouche encre compatible HP DeskJet Plus 4120/2375', 3500, 5000],
  ['HP 305 Encre Tri-couleur', 'F8Q46AE', 'Cartouche encre tri-couleur compatible HP DeskJet Plus 4120', 3500, 5000],
  ['HP 680 Encre Noir', 'Z4V53AE', 'Cartouche encre compatible HP DeskJet 2130/3630', 3000, 4500],
  ['HP 680 Encre Tri-couleur', 'Z4V54AE', 'Cartouche encre tri-couleur compatible HP DeskJet 2130/3630', 3500, 5000],
  ['HP 67 Encre Noir', '3YZ56AE', 'Cartouche encre compatible HP DeskJet 1255/2755', 3500, 5000],
  ['HP 67 Encre Tri-couleur', '3YZ57AE', 'Cartouche encre tri-couleur compatible HP DeskJet 1255/2755', 3500, 5000],
  ['HP 952 Encre Noir', 'F6U13AN', 'Cartouche encre compatible HP OfficeJet Pro 7740/8210', 5000, 7000],
  ['HP 952 Encre Cyan', 'F6U14AN', 'Cartouche encre cyan HP OfficeJet Pro 7740/8210', 5000, 7000],
  ['HP 952 Encre Jaune', 'F6U15AN', 'Cartouche encre jaune HP OfficeJet Pro 7740/8210', 5000, 7000],
  ['HP 952 Encre Magenta', 'F6U16AN', 'Cartouche encre magenta HP OfficeJet Pro 7740/8210', 5000, 7000],

  // ── Cartouches encre Canon ──
  ['Canon PG-445 Encre Noir', 'PG-445', 'Cartouche encre noire compatible Canon PIXMA TS3350/G3050', 3000, 4500],
  ['Canon CL-446 Encre Couleur', 'CL-446', 'Cartouche encre couleur compatible Canon PIXMA TS3350/G3050', 3500, 5000],
  ['Canon PG-545 Encre Noir', 'PG-545', 'Cartouche encre noire compatible Canon PIXMA TS3150', 3000, 4500],
  ['Canon CL-546 Encre Couleur', 'CL-546', 'Cartouche encre couleur compatible Canon PIXMA TS3150', 3500, 5000],
  ['Canon PGI-570 Encre Noir', 'PGI-570', 'Cartouche encre noire compatible Canon PIXMA TS8150', 4500, 6000],
  ['Canon CLI-571 Encre Cyan', 'CLI-571 C', 'Cartouche encre cyan compatible Canon PIXMA TS8150', 3500, 5000],
  ['Canon CLI-571 Encre Jaune', 'CLI-571 Y', 'Cartouche encre jaune compatible Canon PIXMA TS8150', 3500, 5000],
  ['Canon CLI-571 Encre Magenta', 'CLI-571 M', 'Cartouche encre magenta compatible Canon PIXMA TS8150', 3500, 5000],

  // ── Kits de maintenance ──
  ['HP Kit de maintenance C9726A', 'C9726A', 'Kit de transfert compatible HP Color LaserJet 4600/4650', 25000, 32000],
  ['HP Kit de bourrage (RM2-5761)', 'RM2-5761', 'Kit de piece detache pour HP LaserJet Enterprise', 15000, 20000],
  ['HP Ceinture de transfert (CF341A)', 'CF341A', 'Ceinture de transfert compatible HP Color LaserJet M452/M477', 22000, 28000],
  ['Canon Kit de maintenance C-EXV 44', 'C-EXV 44', 'Kit de maintenance compatible Canon iR C3020i', 30000, 38000],
  ['Brother Kit entretien BU-330CL', 'BU-330CL', 'Kit de maintenance compatible Brother MFC-L8900CDW', 20000, 25000],
  ['Cartouche de maintenance MC-G02', 'MC-G02', 'Cartouche de maintenance compatible Epson EcoTank', 20000, 25000],
  ['Cartouche de maintenance MC-G04', 'MC-G04', 'Cartouche de maintenance compatible Epson EcoTank L6170', 20000, 25000],

  // ── Poudre (recharge toner) ──
  ['Poudre toner HP Noir 80g', 'PDR-HP-BK', 'Poudre de recharge pour toner HP noir (80g)', 1500, 2000],
  ['Poudre toner Canon Noir 80g', 'PDR-CAN-BK', 'Poudre de recharge pour toner Canon noir (80g)', 1500, 2000],
  ['Poudre toner Brother Noir 80g', 'PDR-BRO-BK', 'Poudre de recharge pour toner Brother noir (80g)', 1400, 1800],
  ['Poudre toner Samsung Noir 80g', 'PDR-SAM-BK', 'Poudre de recharge pour toner Samsung noir (80g)', 1400, 1800],
  ['Poudre toner Noir universelle 80g', 'PDR-UNI-BK', 'Poudre de recharge universelle noir (80g)', 1200, 1600],
  ['Poudre toner Cyan 80g', 'PDR-UNI-C', 'Poudre de recharge toner couleur cyan (80g)', 1800, 2500],
  ['Poudre toner Jaune 80g', 'PDR-UNI-Y', 'Poudre de recharge toner couleur jaune (80g)', 1800, 2500],
  ['Poudre toner Magenta 80g', 'PDR-UNI-M', 'Poudre de recharge toner couleur magenta (80g)', 1800, 2500],
  ['Poudre XL Noir 140g', 'PDR-XL-BK', 'Poudre de recharge toner noir grande capacite (140g)', 2200, 3000],

  // ── Papier & fournitures ──
  ['Papier A4 80g Ramette 500 feuilles', 'PAP-A4-80', 'Papier impression A4 80g/m2 blanc', 3000, 4000],
  ['Papier A4 100g Ramette 500 feuilles', 'PAP-A4-100', 'Papier premium A4 100g/m2 pour presentations', 5000, 6500],
  ['Papier A3 80g Ramette 500 feuilles', 'PAP-A3-80', 'Papier impression A3 80g/m2 blanc', 5000, 6500],
  ['Papier photo A4 Brillant 50 feuilles', 'PAP-PH-A4', 'Papier photo brillant A4 260g/m2', 6000, 8000],
  ['Etiquettes A4 24 etiquettes / feuille x100', 'ETQ-A4-24', 'Feuilles d\'etiquettes autocollantes A4', 4000, 5500],
  ['Rouleau papier thermique 80mm x 50m', 'TH80x50', 'Rouleau papier thermique pour caisse / TPE', 1500, 2500],
  ['Rouleau papier thermique 57mm x 40m', 'TH57x40', 'Rouleau papier thermique pour imprimante portable', 1000, 1800],
];

$stmt = $pdo->prepare('INSERT INTO produits (nom, reference, description, prix_partenaire, prix_client, unite, actif, gestion_stock) VALUES (?, ?, ?, ?, ?, ?, 1, 1)');

$count = 0;
foreach ($produits as $p) {
  $stmt->execute([$p[0], $p[1], $p[2], $p[3], $p[4], 'FCFA']);
  $count++;
}

echo $count . ' produits inseres avec succes !';