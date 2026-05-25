<?php
require_once __DIR__ . '/config.php';

function getPdo() {
  static $pdo = null;
  if ($pdo === null) {
    $port = defined('DB_PORT') ? (int) DB_PORT : 3306;
    $dsn = 'mysql:host=' . DB_HOST . ';port=' . $port . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    $options = [
      PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
  }
  return $pdo;
}

function getAllProduits($actifOnly = true, $search = '') {
  $pdo = getPdo();
  $sql = 'SELECT * FROM produits';
  $params = [];
  $where = [];
  if ($actifOnly) {
    $where[] = 'actif = 1';
  }
  if ($search !== '') {
    $term = '%' . trim($search) . '%';
    $where[] = '(nom LIKE ? OR reference LIKE ? OR description LIKE ?)';
    $params = array_merge($params, [$term, $term, $term]);
  }
  if (count($where) > 0) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
  }
  $sql .= ' ORDER BY nom';
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  return $stmt->fetchAll();
}

function getProduitById($id) {
  $pdo = getPdo();
  $stmt = $pdo->prepare('SELECT * FROM produits WHERE id = ?');
  $stmt->execute([(int) $id]);
  $row = $stmt->fetch();
  return $row ?: null;
}

function createProduit($data) {
  $pdo = getPdo();
  $stmt = $pdo->prepare('
    INSERT INTO produits (nom, reference, description, prix_partenaire, prix_client, unite)
    VALUES (?, ?, ?, ?, ?, ?)
  ');
  $stmt->execute([
    $data['nom'] ?? '',
    $data['reference'] ?? '',
    $data['description'] ?? '',
    (float) ($data['prix_partenaire'] ?? 0),
    (float) ($data['prix_client'] ?? 0),
    $data['unite'] ?? '€',
  ]);
  $newId = (int) $pdo->lastInsertId();
  syncVisiblePartenaireForProduit($newId);
  return $newId;
}

function updateProduit($id, $data) {
  $pdo = getPdo();
  $actif = isset($data['actif']) ? ($data['actif'] ? 1 : 0) : 1;
  $stmt = $pdo->prepare('
    UPDATE produits SET
      nom = ?, reference = ?, description = ?,
      prix_partenaire = ?, prix_client = ?, unite = ?, actif = ?
    WHERE id = ?
  ');
  $stmt->execute([
    $data['nom'] ?? '',
    $data['reference'] ?? '',
    $data['description'] ?? '',
    (float) ($data['prix_partenaire'] ?? 0),
    (float) ($data['prix_client'] ?? 0),
    $data['unite'] ?? '€',
    $actif,
    (int) $id,
  ]);
  syncVisiblePartenaireForProduit((int) $id);
}

function deleteProduit($id) {
  $pdo = getPdo();
  $stmt = $pdo->prepare('DELETE FROM produits WHERE id = ?');
  $stmt->execute([(int) $id]);
}

// ——— Utilisateurs (admin et partenaires) ———
function getUtilisateurByLogin($login) {
  $pdo = getPdo();
  $stmt = $pdo->prepare('SELECT * FROM utilisateurs WHERE login = ? AND actif = 1');
  $stmt->execute([trim($login)]);
  $row = $stmt->fetch();
  return $row ?: null;
}

function getUtilisateurById($id) {
  $pdo = getPdo();
  $stmt = $pdo->prepare('SELECT * FROM utilisateurs WHERE id = ?');
  $stmt->execute([(int) $id]);
  return $stmt->fetch() ?: null;
}

function getPartenaires() {
  $pdo = getPdo();
  $stmt = $pdo->query("SELECT id, login, nom, email, actif, created_at FROM utilisateurs WHERE role = 'partenaire' ORDER BY nom, login");
  return $stmt->fetchAll();
}

function createUtilisateur($data) {
  $pdo = getPdo();
  $login = trim((string) ($data['login'] ?? ''));
  if ($login === '') {
    throw new Exception('Identifiant requis');
  }
  $password = $data['password'] ?? '';
  if ($password === '' && $password !== '0') {
    throw new Exception('Mot de passe requis');
  }
  $hash = password_hash($password, PASSWORD_DEFAULT);
  $nom = trim((string) ($data['nom'] ?? ''));
  $email = trim((string) ($data['email'] ?? ''));
  $r = isset($data['role']) ? (string) $data['role'] : 'partenaire';
  if ($r === 'admin') {
    $role = 'admin';
  } elseif ($r === 'client') {
    $role = 'client';
  } else {
    $role = 'partenaire';
  }
  $actif = (isset($data['actif']) && !$data['actif']) ? 0 : 1;

  $stmt = $pdo->prepare('INSERT INTO utilisateurs (login, password_hash, nom, email, role, actif) VALUES (?, ?, ?, ?, ?, ?)');
  try {
    $stmt->execute([$login, $hash, $nom, $email, $role, $actif]);
  } catch (PDOException $e) {
    $code = $e->getCode();
    $msg = $e->getMessage();
    if ($code == 23000 || $code === '23000' || strpos($msg, 'Duplicate') !== false || strpos($msg, '1062') !== false) {
      throw new Exception('Cet identifiant est déjà utilisé.');
    }
    throw $e;
  }
  return (int) $pdo->lastInsertId();
}

function updateUtilisateurPassword($id, $newPassword) {
  $pdo = getPdo();
  $hash = password_hash($newPassword, PASSWORD_DEFAULT);
  $stmt = $pdo->prepare('UPDATE utilisateurs SET password_hash = ? WHERE id = ?');
  $stmt->execute([$hash, (int) $id]);
}

function getAllProduitsPublic($search = '') {
  $rows = getAllProduits(true, $search);
  $out = [];
  foreach ($rows as $r) {
    $out[] = [
      'id'        => (int) $r['id'],
      'nom'       => $r['nom'],
      'reference' => $r['reference'],
      'description' => $r['description'],
      'prix_client' => $r['prix_client'],
      'unite'     => $r['unite'],
      'image'     => $r['image'] ?? null,
    ];
  }
  return $out;
}

/** Catalogue partenaire : actifs + visibles partenaires (rupture stock masquée si visible_partenaire = 0). */
function getProduitsPourPartenaire($search = '') {
  $pdo = getPdo();
  try {
    $sql = 'SELECT * FROM produits WHERE actif = 1 AND COALESCE(visible_partenaire, 1) = 1';
    $params = [];
    if ($search !== '') {
      $term = '%' . trim($search) . '%';
      $sql .= ' AND (nom LIKE ? OR reference LIKE ? OR description LIKE ?)';
      $params = [$term, $term, $term];
    }
    $sql .= ' ORDER BY nom';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
  } catch (PDOException $e) {
    if (stripos($e->getMessage(), 'visible_partenaire') !== false) {
      return getAllProduits(true, $search);
    }
    throw $e;
  }
}

function syncVisiblePartenaireForProduit($produitId) {
  try {
    $pdo = getPdo();
    $p = getProduitById((int) $produitId);
    if (!$p) {
      return;
    }
    if ((int) ($p['gestion_stock'] ?? 1) !== 1) {
      $stmt = $pdo->prepare('UPDATE produits SET visible_partenaire = 1 WHERE id = ?');
      $stmt->execute([(int) $produitId]);
      return;
    }
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(quantite), 0) FROM stock_niveau WHERE produit_id = ?');
    $stmt->execute([(int) $produitId]);
    $total = (int) $stmt->fetchColumn();
    $vis = $total > 0 ? 1 : 0;
    $stmt = $pdo->prepare('UPDATE produits SET visible_partenaire = ? WHERE id = ?');
    $stmt->execute([$vis, (int) $produitId]);
  } catch (PDOException $e) {
    if (stripos($e->getMessage(), 'visible_partenaire') === false) {
      throw $e;
    }
  }
}

function getUtilisateursByRoleFilter($filter) {
  $pdo = getPdo();
  if ($filter === 'client') {
    $sql = "SELECT id, login, nom, email, role, actif, created_at FROM utilisateurs WHERE role = 'client' ORDER BY nom, login";
  } elseif ($filter === 'all') {
    $sql = "SELECT id, login, nom, email, role, actif, created_at FROM utilisateurs WHERE role IN ('partenaire', 'client') ORDER BY role, nom, login";
  } else {
    $sql = "SELECT id, login, nom, email, role, actif, created_at FROM utilisateurs WHERE role = 'partenaire' ORDER BY nom, login";
  }
  return $pdo->query($sql)->fetchAll();
}

function updateUtilisateurRole($id, $role) {
  if (!in_array($role, ['partenaire', 'client'], true)) {
    throw new Exception('Rôle invalide');
  }
  $u = getUtilisateurById((int) $id);
  if (!$u || $u['role'] === 'admin') {
    throw new Exception('Compte introuvable ou non modifiable');
  }
  $pdo = getPdo();
  $stmt = $pdo->prepare('UPDATE utilisateurs SET role = ? WHERE id = ? AND role != ?');
  $stmt->execute([$role, (int) $id, 'admin']);
}

function ensureDefaultStockStructure() {
  $pdo = getPdo();
  $n = (int) $pdo->query('SELECT COUNT(*) FROM depots')->fetchColumn();
  if ($n === 0) {
    $pdo->prepare('INSERT INTO depots (nom, code, ordre) VALUES (?, ?, 0)')->execute(['Principal', 'P']);
    $depotId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO rayons (depot_id, nom, code, ordre) VALUES (?, ?, ?, 0)')->execute([$depotId, 'Magasin principal', 'DEF']);
  }
}

function getDepotsAvecRayons() {
  ensureDefaultStockStructure();
  $pdo = getPdo();
  $depots = $pdo->query('SELECT id, nom, code, ordre FROM depots ORDER BY ordre, id')->fetchAll();
  $stmt = $pdo->prepare('SELECT id, depot_id, nom, code, ordre FROM rayons WHERE depot_id = ? ORDER BY ordre, id');
  foreach ($depots as &$d) {
    $stmt->execute([(int) $d['id']]);
    $d['rayons'] = $stmt->fetchAll();
  }
  unset($d);
  return $depots;
}

function getStockMatrixForAdmin() {
  ensureDefaultStockStructure();
  $pdo = getPdo();
  $sql = '
    SELECT p.id AS produit_id, p.nom AS produit_nom, COALESCE(p.gestion_stock, 1) AS gestion_stock,
           p.actif AS actif, COALESCE(p.visible_partenaire, 1) AS visible_partenaire,
           COALESCE(p.stock_min, 0) AS stock_min,
           r.id AS rayon_id, CONCAT(d.nom, " — ", r.nom) AS emplacement,
           COALESCE(sn.quantite, 0) AS quantite
    FROM produits p
    CROSS JOIN rayons r
    INNER JOIN depots d ON d.id = r.depot_id
    LEFT JOIN stock_niveau sn ON sn.produit_id = p.id AND sn.rayon_id = r.id
    WHERE COALESCE(p.gestion_stock, 1) = 1
    ORDER BY d.ordre, r.ordre, p.nom
  ';
  try {
    return $pdo->query($sql)->fetchAll();
  } catch (PDOException $e) {
    if (stripos($e->getMessage(), 'visible_partenaire') === false) {
      throw $e;
    }
    $sqlFallback = '
      SELECT p.id AS produit_id, p.nom AS produit_nom, COALESCE(p.gestion_stock, 1) AS gestion_stock,
             p.actif AS actif, 1 AS visible_partenaire,
             COALESCE(p.stock_min, 0) AS stock_min,
             r.id AS rayon_id, CONCAT(d.nom, " — ", r.nom) AS emplacement,
             COALESCE(sn.quantite, 0) AS quantite
      FROM produits p
      CROSS JOIN rayons r
      INNER JOIN depots d ON d.id = r.depot_id
      LEFT JOIN stock_niveau sn ON sn.produit_id = p.id AND sn.rayon_id = r.id
      WHERE COALESCE(p.gestion_stock, 1) = 1
      ORDER BY d.ordre, r.ordre, p.nom
    ';
    return $pdo->query($sqlFallback)->fetchAll();
  }
}

function getProduitsForStockSelect() {
  ensureDefaultStockStructure();
  $pdo = getPdo();
  $stmt = $pdo->query('SELECT id, nom FROM produits WHERE COALESCE(gestion_stock, 1) = 1 ORDER BY nom');
  return $stmt->fetchAll();
}

/** Totaux par article géré en stock (rupture = total 0) + indicateurs catalogue. */
function getStockResumeParProduit() {
  ensureDefaultStockStructure();
  $pdo = getPdo();
  $sql = '
    SELECT p.id AS produit_id, p.nom AS produit_nom,
           COALESCE(p.gestion_stock, 1) AS gestion_stock,
           p.actif AS actif, COALESCE(p.visible_partenaire, 1) AS visible_partenaire,
           COALESCE(p.stock_min, 0) AS stock_min,
           COALESCE(SUM(sn.quantite), 0) AS total
    FROM produits p
    LEFT JOIN stock_niveau sn ON sn.produit_id = p.id
    WHERE COALESCE(p.gestion_stock, 1) = 1
    GROUP BY p.id, p.nom, p.gestion_stock, p.actif, p.visible_partenaire, p.stock_min
    ORDER BY p.nom
  ';
  try {
    return $pdo->query($sql)->fetchAll();
  } catch (PDOException $e) {
    if (stripos($e->getMessage(), 'visible_partenaire') === false) {
      throw $e;
    }
    $sqlFallback = '
      SELECT p.id AS produit_id, p.nom AS produit_nom,
             COALESCE(p.gestion_stock, 1) AS gestion_stock,
             p.actif AS actif, 1 AS visible_partenaire,
             COALESCE(p.stock_min, 0) AS stock_min,
             COALESCE(SUM(sn.quantite), 0) AS total
      FROM produits p
      LEFT JOIN stock_niveau sn ON sn.produit_id = p.id
      WHERE COALESCE(p.gestion_stock, 1) = 1
      GROUP BY p.id, p.nom, p.gestion_stock, p.actif, p.stock_min
      ORDER BY p.nom
    ';
    return $pdo->query($sqlFallback)->fetchAll();
  }
}

function getStockMouvementsRecents($limit = 40) {
  $pdo = getPdo();
  $lim = max(1, min(200, (int) $limit));
  try {
    $stmt = $pdo->query('
      SELECT m.id, m.type, m.quantite, m.commentaire, m.created_at,
             p.nom AS produit_nom, r.nom AS rayon_nom, d.nom AS depot_nom,
             rd.nom AS rayon_dest_nom, dd.nom AS depot_dest_nom,
             u.login AS user_login
      FROM stock_mouvements m
      INNER JOIN produits p ON p.id = m.produit_id
      INNER JOIN rayons r ON r.id = m.rayon_id
      INNER JOIN depots d ON d.id = r.depot_id
      LEFT JOIN rayons rd ON rd.id = m.rayon_dest_id
      LEFT JOIN depots dd ON dd.id = rd.depot_id
      LEFT JOIN utilisateurs u ON u.id = m.user_id
      ORDER BY m.id DESC
      LIMIT ' . $lim
    );
    return $stmt->fetchAll();
  } catch (PDOException $e) {
    return [];
  }
}

function applyStockMovement($produitId, $rayonId, $type, $quantite, $commentaire, $userId) {
  $quantite = (int) $quantite;
  if ($quantite <= 0) {
    throw new Exception('La quantité doit être positive.');
  }
  if (!in_array($type, ['entree', 'sortie', 'vente'], true)) {
    throw new Exception('Type de mouvement invalide.');
  }
  $delta = ($type === 'entree') ? $quantite : -$quantite;

  $pdo = getPdo();
  $p = getProduitById((int) $produitId);
  if (!$p) {
    throw new Exception('Produit introuvable');
  }
  if (isset($p['gestion_stock']) && (int) $p['gestion_stock'] !== 1) {
    throw new Exception('Ce produit n’est pas géré en stock.');
  }

  $pdo->beginTransaction();
  try {
    $stmt = $pdo->prepare('SELECT quantite FROM stock_niveau WHERE produit_id = ? AND rayon_id = ? FOR UPDATE');
    $stmt->execute([(int) $produitId, (int) $rayonId]);
    $row = $stmt->fetch();
    $current = $row ? (int) $row['quantite'] : 0;
    $new = $current + $delta;
    if ($new < 0) {
      throw new Exception('Stock insuffisant pour cette opération.');
    }
    $upsert = $pdo->prepare('
      INSERT INTO stock_niveau (produit_id, rayon_id, quantite) VALUES (?, ?, ?)
      ON DUPLICATE KEY UPDATE quantite = VALUES(quantite)
    ');
    $upsert->execute([(int) $produitId, (int) $rayonId, $new]);

    $log = $pdo->prepare('INSERT INTO stock_mouvements (produit_id, rayon_id, type, quantite, commentaire, user_id) VALUES (?, ?, ?, ?, ?, ?)');
    $log->execute([(int) $produitId, (int) $rayonId, $type, $quantite, $commentaire !== '' ? $commentaire : null, $userId ? (int) $userId : null]);

    syncVisiblePartenaireForProduit((int) $produitId);

    $pdo->commit();
  } catch (Exception $e) {
    $pdo->rollBack();
    throw $e;
  }
}

// ——— Paramètres ———

function getParametres() {
  $pdo = getPdo();
  $rows = $pdo->query('SELECT cle, valeur FROM parametres')->fetchAll();
  $out = [];
  foreach ($rows as $r) {
    $out[$r['cle']] = $r['valeur'];
  }
  return $out;
}

function setParametres($pairs) {
  $pdo = getPdo();
  $stmt = $pdo->prepare('INSERT INTO parametres (cle, valeur) VALUES (?, ?) ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)');
  foreach ($pairs as $cle => $valeur) {
    $stmt->execute([$cle, $valeur]);
  }
}

function getParametre($cle, $defaut = '') {
  $pdo = getPdo();
  $stmt = $pdo->prepare('SELECT valeur FROM parametres WHERE cle = ?');
  $stmt->execute([$cle]);
  $row = $stmt->fetch();
  return $row ? $row['valeur'] : $defaut;
}

function getLogoPath() {
  $path = getParametre('entreprise_logo', '');
  return ($path !== '' && file_exists(__DIR__ . '/' . $path)) ? $path : null;
}

// ——— Dépôts & Rayons CRUD ———

function createDepot($nom, $code, $ordre) {
  $pdo = getPdo();
  $stmt = $pdo->prepare('INSERT INTO depots (nom, code, ordre) VALUES (?, ?, ?)');
  $stmt->execute([trim($nom), trim($code) ?: null, (int) $ordre]);
  return (int) $pdo->lastInsertId();
}

function updateDepot($id, $nom, $code, $ordre) {
  $pdo = getPdo();
  $stmt = $pdo->prepare('UPDATE depots SET nom = ?, code = ?, ordre = ? WHERE id = ?');
  $stmt->execute([trim($nom), trim($code) ?: null, (int) $ordre, (int) $id]);
}

function deleteDepot($id) {
  $pdo = getPdo();
  $stmt = $pdo->prepare('DELETE FROM depots WHERE id = ?');
  $stmt->execute([(int) $id]);
}

function createRayon($depotId, $nom, $code, $ordre) {
  $pdo = getPdo();
  $stmt = $pdo->prepare('INSERT INTO rayons (depot_id, nom, code, ordre) VALUES (?, ?, ?, ?)');
  $stmt->execute([(int) $depotId, trim($nom), trim($code) ?: null, (int) $ordre]);
  return (int) $pdo->lastInsertId();
}

function updateRayon($id, $depotId, $nom, $code, $ordre) {
  $pdo = getPdo();
  $stmt = $pdo->prepare('UPDATE rayons SET depot_id = ?, nom = ?, code = ?, ordre = ? WHERE id = ?');
  $stmt->execute([(int) $depotId, trim($nom), trim($code) ?: null, (int) $ordre, (int) $id]);
}

function deleteRayon($id) {
  $pdo = getPdo();
  $stmt = $pdo->prepare('DELETE FROM rayons WHERE id = ?');
  $stmt->execute([(int) $id]);
}

// ——— Stock Transfert & Ajustement ———

function transferStock($produitId, $sourceRayonId, $destRayonId, $quantite, $commentaire, $userId) {
  $quantite = (int) $quantite;
  if ($quantite <= 0) throw new Exception('La quantité doit être positive.');
  if ((int) $sourceRayonId === (int) $destRayonId) throw new Exception('Le rayon source et destination doivent être différents.');

  $pdo = getPdo();
  $p = getProduitById((int) $produitId);
  if (!$p) throw new Exception('Produit introuvable');
  if ((int) $p['gestion_stock'] !== 1) throw new Exception('Ce produit n\'est pas géré en stock.');

  $pdo->beginTransaction();
  try {
    // Check source stock
    $stmt = $pdo->prepare('SELECT quantite FROM stock_niveau WHERE produit_id = ? AND rayon_id = ? FOR UPDATE');
    $stmt->execute([(int) $produitId, (int) $sourceRayonId]);
    $row = $stmt->fetch();
    $current = $row ? (int) $row['quantite'] : 0;
    if ($current < $quantite) throw new Exception('Stock insuffisant dans le rayon source.');

    // Debit source
    $newSource = $current - $quantite;
    $upsert = $pdo->prepare('INSERT INTO stock_niveau (produit_id, rayon_id, quantite) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantite = VALUES(quantite)');
    $upsert->execute([(int) $produitId, (int) $sourceRayonId, $newSource]);

    // Credit dest
    $stmt2 = $pdo->prepare('SELECT quantite FROM stock_niveau WHERE produit_id = ? AND rayon_id = ? FOR UPDATE');
    $stmt2->execute([(int) $produitId, (int) $destRayonId]);
    $row2 = $stmt2->fetch();
    $destCurrent = $row2 ? (int) $row2['quantite'] : 0;
    $newDest = $destCurrent + $quantite;
    $upsert->execute([(int) $produitId, (int) $destRayonId, $newDest]);

    // Log movement (sortie from source)
    $log = $pdo->prepare('INSERT INTO stock_mouvements (produit_id, rayon_id, rayon_dest_id, type, quantite, commentaire, user_id) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $log->execute([(int) $produitId, (int) $sourceRayonId, (int) $destRayonId, 'transfert', $quantite, $commentaire !== '' ? $commentaire : null, $userId ? (int) $userId : null]);

    syncVisiblePartenaireForProduit((int) $produitId);
    $pdo->commit();
  } catch (Exception $e) {
    $pdo->rollBack();
    throw $e;
  }
}

function adjustStock($produitId, $rayonId, $newQty, $commentaire, $userId) {
  $newQty = (int) $newQty;
  if ($newQty < 0) throw new Exception('La quantité ne peut pas être négative.');

  $pdo = getPdo();
  $p = getProduitById((int) $produitId);
  if (!$p) throw new Exception('Produit introuvable');
  if ((int) $p['gestion_stock'] !== 1) throw new Exception('Ce produit n\'est pas géré en stock.');

  $pdo->beginTransaction();
  try {
    $stmt = $pdo->prepare('SELECT quantite FROM stock_niveau WHERE produit_id = ? AND rayon_id = ? FOR UPDATE');
    $stmt->execute([(int) $produitId, (int) $rayonId]);
    $row = $stmt->fetch();
    $oldQty = $row ? (int) $row['quantite'] : 0;
    $delta = abs($newQty - $oldQty);

    $upsert = $pdo->prepare('INSERT INTO stock_niveau (produit_id, rayon_id, quantite) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantite = VALUES(quantite)');
    $upsert->execute([(int) $produitId, (int) $rayonId, $newQty]);

    $log = $pdo->prepare('INSERT INTO stock_mouvements (produit_id, rayon_id, type, quantite, commentaire, user_id) VALUES (?, ?, ?, ?, ?, ?)');
    $log->execute([(int) $produitId, (int) $rayonId, 'ajustement', $delta, ($commentaire !== '' ? $commentaire : 'Ajustement de ' . $oldQty . ' à ' . $newQty), $userId ? (int) $userId : null]);

    syncVisiblePartenaireForProduit((int) $produitId);
    $pdo->commit();
  } catch (Exception $e) {
    $pdo->rollBack();
    throw $e;
  }
}

function getStockAlertes() {
  $pdo = getPdo();
  $sql = '
    SELECT p.id AS produit_id, p.nom AS produit_nom, p.stock_min,
           COALESCE(SUM(sn.quantite), 0) AS total
    FROM produits p
    LEFT JOIN stock_niveau sn ON sn.produit_id = p.id
    WHERE p.gestion_stock = 1 AND p.actif = 1 AND p.stock_min > 0
    GROUP BY p.id, p.nom, p.stock_min
    HAVING total <= p.stock_min
    ORDER BY total ASC, p.nom
  ';
  return $pdo->query($sql)->fetchAll();
}

// ——— Factures ———

function getFactures($filters = []) {
  $pdo = getPdo();
  $sql = 'SELECT f.*, u.nom AS client_nom, u.login AS client_login
          FROM factures f
          INNER JOIN utilisateurs u ON u.id = f.client_id
          WHERE 1=1';
  $params = [];
  if (!empty($filters['statut'])) {
    $sql .= ' AND f.statut = ?';
    $params[] = $filters['statut'];
  }
  if (!empty($filters['client_id'])) {
    $sql .= ' AND f.client_id = ?';
    $params[] = (int) $filters['client_id'];
  }
  if (!empty($filters['date_from'])) {
    $sql .= ' AND f.date_facture >= ?';
    $params[] = $filters['date_from'];
  }
  if (!empty($filters['date_to'])) {
    $sql .= ' AND f.date_facture <= ?';
    $params[] = $filters['date_to'];
  }
  $sql .= ' ORDER BY f.date_facture DESC, f.id DESC';
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  return $stmt->fetchAll();
}

function getFactureById($id) {
  $pdo = getPdo();
  $stmt = $pdo->prepare('SELECT f.*, u.nom AS client_nom, u.login AS client_login, u.email AS client_email
                          FROM factures f
                          INNER JOIN utilisateurs u ON u.id = f.client_id
                          WHERE f.id = ?');
  $stmt->execute([(int) $id]);
  $facture = $stmt->fetch();
  if (!$facture) return null;

  $stmt2 = $pdo->prepare('SELECT lf.*, p.nom AS produit_nom FROM lignes_facture lf LEFT JOIN produits p ON p.id = lf.produit_id WHERE lf.facture_id = ? ORDER BY lf.ordre, lf.id');
  $stmt2->execute([(int) $id]);
  $facture['lignes'] = $stmt2->fetchAll();

  $stmt3 = $pdo->prepare('SELECT * FROM paiements WHERE facture_id = ? ORDER BY date_paiement, id');
  $stmt3->execute([(int) $id]);
  $facture['paiements'] = $stmt3->fetchAll();

  $stmt4 = $pdo->prepare('SELECT * FROM relances WHERE facture_id = ? ORDER BY date_relance, id');
  $stmt4->execute([(int) $id]);
  $facture['relances'] = $stmt4->fetchAll();

  return $facture;
}

function getFactureStats() {
  $pdo = getPdo();
  $monthStart = date('Y-m-01');
  $stmt = $pdo->prepare('SELECT COUNT(*) AS nb, COALESCE(SUM(montant_ttc), 0) AS total FROM factures WHERE date_facture >= ? AND statut != ?');
  $stmt->execute([$monthStart, 'annulee']);
  $month = $stmt->fetch();

  $stmt2 = $pdo->query("SELECT COALESCE(SUM(montant_ttc - montant_paye), 0) AS impaye FROM factures WHERE statut IN ('envoyee','payee_partiellement')");
  $impaye = $stmt2->fetch();

  $stmt3 = $pdo->query("SELECT COALESCE(SUM(montant_paye), 0) AS paye FROM factures WHERE statut != 'annulee'");
  $paye = $stmt3->fetch();

  $stmt4 = $pdo->query("SELECT COALESCE(SUM(montant_ttc - montant_paye), 0) AS reste FROM factures WHERE statut != 'annulee'");
  $reste = $stmt4->fetch();

  return [
    'factures_mois' => (int) $month['nb'],
    'montant_mois' => (float) $month['total'],
    'impaye' => (float) $impaye['impaye'],
    'paye' => (float) $paye['paye'],
    'reste' => (float) $reste['reste'],
  ];
}

function getNextFactureNumero() {
  $pdo = getPdo();
  $prefix = getParametre('facture_prefixe', 'FAC');
  $nextNum = (int) getParametre('facture_prochain_num', '1');
  return $prefix . '-' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
}

function createFacture($data) {
  $pdo = getPdo();
  $pdo->beginTransaction();
  try {
    $numero = getNextFactureNumero();
    $nextNum = (int) getParametre('facture_prochain_num', '1');

    $montantPaye = (float) ($data['montant_paye'] ?? 0);
    $montantTtc = (float) ($data['montant_ttc'] ?? 0);
    $statut = $data['statut'] ?? 'brouillon';
    // Auto-adjust statut based on montant_paye
    if ($statut !== 'annulee') {
      if ($montantPaye >= $montantTtc && $montantTtc > 0) {
        $statut = 'payee';
      } elseif ($montantPaye > 0) {
        $statut = 'payee_partiellement';
      }
    }

    $stmt = $pdo->prepare('INSERT INTO factures (numero, client_id, date_facture, echeance, statut, montant_ht, tva, montant_ttc, montant_paye, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
      $numero,
      (int) $data['client_id'],
      $data['date_facture'] ?? date('Y-m-d'),
      $data['echeance'] ?? null,
      $statut,
      (float) ($data['montant_ht'] ?? 0),
      (float) ($data['tva'] ?? 0),
      $montantTtc,
      $montantPaye,
      $data['notes'] ?? null,
    ]);
    $factureId = (int) $pdo->lastInsertId();

    // Insert lignes
    if (!empty($data['lignes']) && is_array($data['lignes'])) {
      $stmtLigne = $pdo->prepare('INSERT INTO lignes_facture (facture_id, produit_id, designation, quantite, prix_unitaire, total_ligne, ordre) VALUES (?, ?, ?, ?, ?, ?, ?)');
      $ordre = 0;
      foreach ($data['lignes'] as $ligne) {
        $stmtLigne->execute([
          $factureId,
          !empty($ligne['produit_id']) ? (int) $ligne['produit_id'] : null,
          $ligne['designation'] ?? '',
          (float) ($ligne['quantite'] ?? 1),
          (float) ($ligne['prix_unitaire'] ?? 0),
          (float) ($ligne['total_ligne'] ?? 0),
          $ordre++,
        ]);
      }
    }

    // Increment next number
    setParametres(['facture_prochain_num' => (string) ($nextNum + 1)]);

    $pdo->commit();
    return $factureId;
  } catch (Exception $e) {
    $pdo->rollBack();
    throw $e;
  }
}

function updateFacture($id, $data) {
  $pdo = getPdo();
  $pdo->beginTransaction();
  try {
    $montantPaye = (float) ($data['montant_paye'] ?? 0);
    $montantTtc = (float) ($data['montant_ttc'] ?? 0);
    $statut = $data['statut'] ?? 'brouillon';
    // Auto-adjust statut based on montant_paye
    if ($statut !== 'annulee') {
      if ($montantPaye >= $montantTtc && $montantTtc > 0) {
        $statut = 'payee';
      } elseif ($montantPaye > 0) {
        $statut = 'payee_partiellement';
      }
    }

    $stmt = $pdo->prepare('UPDATE factures SET client_id = ?, date_facture = ?, echeance = ?, statut = ?, montant_ht = ?, tva = ?, montant_ttc = ?, montant_paye = ?, notes = ? WHERE id = ?');
    $stmt->execute([
      (int) ($data['client_id'] ?? 0),
      $data['date_facture'] ?? date('Y-m-d'),
      $data['echeance'] ?? null,
      $statut,
      (float) ($data['montant_ht'] ?? 0),
      (float) ($data['tva'] ?? 0),
      $montantTtc,
      $montantPaye,
      $data['notes'] ?? null,
      (int) $id,
    ]);

    // Replace lignes
    $pdo->prepare('DELETE FROM lignes_facture WHERE facture_id = ?')->execute([(int) $id]);
    if (!empty($data['lignes']) && is_array($data['lignes'])) {
      $stmtLigne = $pdo->prepare('INSERT INTO lignes_facture (facture_id, produit_id, designation, quantite, prix_unitaire, total_ligne, ordre) VALUES (?, ?, ?, ?, ?, ?, ?)');
      $ordre = 0;
      foreach ($data['lignes'] as $ligne) {
        $stmtLigne->execute([
          (int) $id,
          !empty($ligne['produit_id']) ? (int) $ligne['produit_id'] : null,
          $ligne['designation'] ?? '',
          (float) ($ligne['quantite'] ?? 1),
          (float) ($ligne['prix_unitaire'] ?? 0),
          (float) ($ligne['total_ligne'] ?? 0),
          $ordre++,
        ]);
      }
    }

    $pdo->commit();
  } catch (Exception $e) {
    $pdo->rollBack();
    throw $e;
  }
}

function deleteFacture($id) {
  $pdo = getPdo();
  $stmt = $pdo->prepare('DELETE FROM factures WHERE id = ?');
  $stmt->execute([(int) $id]);
}

function createPaiement($factureId, $montant, $datePaiement, $mode, $reference, $notes) {
  $pdo = getPdo();
  $pdo->beginTransaction();
  try {
    $stmt = $pdo->prepare('INSERT INTO paiements (facture_id, montant, date_paiement, mode, reference, notes) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([(int) $factureId, (float) $montant, $datePaiement, $mode, $reference ?: null, $notes ?: null]);

    // Update facture montant_paye and statut
    $stmt2 = $pdo->prepare('SELECT montant_ttc, COALESCE(SUM(p.montant), 0) AS paye FROM factures f LEFT JOIN paiements p ON p.facture_id = f.id WHERE f.id = ? GROUP BY f.id');
    $stmt2->execute([(int) $factureId]);
    $row = $stmt2->fetch();
    $ttc = (float) $row['montant_ttc'];
    $totalPaye = (float) $row['paye'];
    $newStatut = 'envoyee';
    if ($totalPaye >= $ttc && $ttc > 0) {
      $newStatut = 'payee';
    } elseif ($totalPaye > 0) {
      $newStatut = 'payee_partiellement';
    }
    $pdo->prepare('UPDATE factures SET montant_paye = ?, statut = ? WHERE id = ?')->execute([$totalPaye, $newStatut, (int) $factureId]);

    $pdo->commit();
  } catch (Exception $e) {
    $pdo->rollBack();
    throw $e;
  }
}

function getPaiementsByFacture($factureId) {
  $pdo = getPdo();
  $stmt = $pdo->prepare('SELECT * FROM paiements WHERE facture_id = ? ORDER BY date_paiement, id');
  $stmt->execute([(int) $factureId]);
  return $stmt->fetchAll();
}

function getFacturesEnRetard() {
  $pdo = getPdo();
  $sql = "SELECT f.*, u.nom AS client_nom, u.login AS client_login
          FROM factures f
          INNER JOIN utilisateurs u ON u.id = f.client_id
          WHERE f.echeance IS NOT NULL AND f.echeance < CURDATE()
          AND f.statut NOT IN ('payee', 'annulee')
          ORDER BY f.echeance ASC";
  return $pdo->query($sql)->fetchAll();
}

function createRelance($factureId, $type, $dateRelance, $commentaire) {
  $pdo = getPdo();
  $stmt = $pdo->prepare('INSERT INTO relances (facture_id, type, date_relance, commentaire) VALUES (?, ?, ?, ?)');
  $stmt->execute([(int) $factureId, $type, $dateRelance, $commentaire ?: null]);
  return (int) $pdo->lastInsertId();
}

function getRelancesByFacture($factureId) {
  $pdo = getPdo();
  $stmt = $pdo->prepare('SELECT * FROM relances WHERE facture_id = ? ORDER BY date_relance, id');
  $stmt->execute([(int) $factureId]);
  return $stmt->fetchAll();
}
