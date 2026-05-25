<?php
/**
 * API REST — authentification requise. resource=produits|utilisateurs, q=recherche.
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';

$method = $_SERVER['REQUEST_METHOD'];
$resourceEarly = isset($_GET['resource']) ? trim($_GET['resource']) : '';

if ($method === 'GET' && $resourceEarly === 'produits' && isset($_GET['public']) && (string) $_GET['public'] === '1') {
  try {
    $q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
    echo json_encode(getAllProduitsPublic($q));
  } catch (Throwable $e) {
    http_response_code(500);
    $host = defined('DB_HOST') ? DB_HOST : '';
    $isLocalhost = ($host === 'localhost' || $host === '127.0.0.1' || $host === '::1');
    if ($isLocalhost) {
      $hint = 'MySQL refuse la connexion avec l’hôte « localhost » : depuis votre PC, cela cible MySQL sur votre machine, pas le serveur de l’hébergeur. '
        . 'Même avec « Remote MySQL » activé, il faut un fichier config.local.php avec db_host = nom d’hôte distant exact (celui du panel, souvent du type srv….hstgr.io ou une IP), pas localhost.';
    } else {
      $hint = 'Connexion MySQL impossible vers « ' . $host . ' ». À vérifier : '
        . '(1) votre adresse IP publique est bien autorisée dans Remote MySQL (elle change parfois avec la box) ; '
        . '(2) l’hôte est bien celui prévu pour l’accès distant, pas l’ancien « localhost » du phpMyAdmin côté serveur ; '
        . '(3) pare-feu / box n’interdit pas le port 3306 sortant.';
    }
    $detail = $e->getMessage();
    if (getenv('ESADISS_DEBUG') === '1') {
      $hint .= ' [ESADISS_DEBUG] ' . $detail;
    } else {
      $hint .= ' Détail : ' . $detail;
    }
    echo json_encode(['error' => $hint]);
  }
  exit;
}

function getCurrentUser() {
  if (empty($_SESSION['user_id'])) return null;
  $u = getUtilisateurById($_SESSION['user_id']);
  return ($u && $u['actif']) ? $u : null;
}

function apiError($code, $message) {
  http_response_code($code);
  echo json_encode(['error' => $message]);
  exit;
}

$user = getCurrentUser();
if (!$user) {
  apiError(401, 'Connexion requise');
}

$resource = isset($_GET['resource']) ? trim($_GET['resource']) : '';
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$search = isset($_GET['q']) ? trim($_GET['q']) : '';

function getInput() {
  $raw = file_get_contents('php://input');
  if ($raw === '') return [];
  $decoded = json_decode($raw, true);
  return is_array($decoded) ? $decoded : [];
}

try {
  if ($resource === 'produits') {
    // Partenaire ou admin peut lister (avec ou sans admin=1)
    $admin = isset($_GET['admin']) && $_GET['admin'] === '1';
    if ($admin && $user['role'] !== 'admin') {
      apiError(403, 'Accès réservé à l\'administrateur');
    }

    switch ($method) {
      case 'GET':
        if ($id) {
          $p = getProduitById($id);
          if (!$p) apiError(404, 'Produit introuvable');
          echo json_encode($p);
        } else {
          if ($user['role'] === 'partenaire') {
            $produits = getProduitsPourPartenaire($search);
          } else {
            $produits = getAllProduits(!$admin, $search);
          }
          // Enrich with stock total for admin requests
          if ($admin && $user['role'] === 'admin') {
            $stockMap = getStockResumeParProduit();
            $map = [];
            foreach ($stockMap as $row) {
              $map[(int) $row['produit_id']] = (int) $row['total'];
            }
            foreach ($produits as &$p) {
              $p['stock_total'] = isset($map[(int) $p['id']]) ? $map[(int) $p['id']] : 0;
            }
            unset($p);
          }
          echo json_encode($produits);
        }
        break;

      case 'POST':
      case 'PUT':
      case 'DELETE':
        if ($user['role'] !== 'admin') {
          apiError(403, 'Accès réservé à l\'administrateur');
        }
        if ($method === 'POST') {
          $data = getInput();
          $newId = createProduit($data);
          http_response_code(201);
          echo json_encode(['id' => $newId, 'message' => 'Produit créé']);
        } elseif ($method === 'PUT') {
          if (!$id) apiError(400, 'ID requis');
          if (!getProduitById($id)) apiError(404, 'Produit introuvable');
          updateProduit($id, getInput());
          echo json_encode(['message' => 'Produit mis à jour']);
        } else {
          if (!$id) apiError(400, 'ID requis');
          if (!getProduitById($id)) apiError(404, 'Produit introuvable');
          deleteProduit($id);
          echo json_encode(['message' => 'Produit supprimé']);
        }
        break;

      default:
        apiError(405, 'Méthode non autorisée');
    }
    exit;
  }

  if ($resource === 'utilisateurs') {
    if ($user['role'] !== 'admin') {
      apiError(403, 'Accès réservé à l\'administrateur');
    }
    if ($method === 'GET') {
      $roleFilter = isset($_GET['role']) ? trim((string) $_GET['role']) : 'partenaire';
      if (!in_array($roleFilter, ['partenaire', 'client', 'all'], true)) {
        $roleFilter = 'partenaire';
      }
      echo json_encode(getUtilisateursByRoleFilter($roleFilter));
    } elseif ($method === 'PUT') {
      $putId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
      if ($putId <= 0) {
        apiError(400, 'ID requis');
      }
      $data = getInput();
      if (empty($data['role']) || !in_array($data['role'], ['partenaire', 'client'], true)) {
        apiError(400, 'Rôle partenaire ou client requis');
      }
      if ($putId === (int) $user['id']) {
        apiError(400, 'Vous ne pouvez pas modifier votre propre rôle ici');
      }
      updateUtilisateurRole($putId, $data['role']);
      echo json_encode(['message' => 'Rôle mis à jour']);
    } elseif ($method === 'POST') {
      $data = getInput();
      if (empty($data) && !empty($_POST)) {
        $data = [
          'login' => $_POST['login'] ?? '',
          'password' => $_POST['password'] ?? '',
          'nom' => $_POST['nom'] ?? '',
          'email' => $_POST['email'] ?? '',
        ];
      }
      if (empty($data) || !isset($data['login'])) {
        apiError(400, 'Données manquantes. Envoyez login et mot de passe (JSON ou formulaire).');
      }
      $login = isset($data['login']) ? trim((string) $data['login']) : '';
      $password = isset($data['password']) ? (string) $data['password'] : '';
      if ($login === '') {
        apiError(400, 'Identifiant requis');
      }
      if ($password === '') {
        apiError(400, 'Mot de passe requis');
      }
      $data['login'] = $login;
      $data['password'] = $password;
      $data['nom'] = isset($data['nom']) ? trim((string) $data['nom']) : '';
      $data['email'] = isset($data['email']) ? trim((string) $data['email']) : '';
      $reqRole = isset($data['role']) ? (string) $data['role'] : 'partenaire';
      if ($reqRole === 'admin') {
        $data['role'] = 'admin';
      } elseif ($reqRole === 'client') {
        $data['role'] = 'client';
      } else {
        $data['role'] = 'partenaire';
      }
      $data['actif'] = 1;
      $newId = createUtilisateur($data);
      http_response_code(201);
      echo json_encode(['id' => $newId, 'message' => 'Compte créé']);
    } else {
      apiError(405, 'Méthode non autorisée');
    }
    exit;
  }

  if ($resource === 'stock') {
    if ($user['role'] !== 'admin') {
      apiError(403, 'Accès réservé à l\'administrateur');
    }
    if ($method === 'GET') {
      $resume = getStockResumeParProduit();
      $totalUnits = 0;
      $rupture = 0;
      $disponibles = 0;
      $alerteBas = 0;
      foreach ($resume as $row) {
        $t = (int) $row['total'];
        $totalUnits += $t;
        if ($t <= 0) {
          $rupture++;
        } else {
          $disponibles++;
          if ((int) $row['stock_min'] > 0 && $t <= (int) $row['stock_min']) {
            $alerteBas++;
          }
        }
      }
      echo json_encode([
        'depots' => getDepotsAvecRayons(),
        'lignes' => getStockMatrixForAdmin(),
        'mouvements' => getStockMouvementsRecents(50),
        'produits_select' => getProduitsForStockSelect(),
        'resume' => $resume,
        'statistiques' => [
          'articles_gestion_stock' => count($resume),
          'articles_en_stock' => $disponibles,
          'articles_rupture' => $rupture,
          'articles_alerte' => $alerteBas,
          'unites_total' => $totalUnits,
        ],
        'alertes' => getStockAlertes(),
      ]);
    } elseif ($method === 'POST') {
      $data = getInput();
      $action = isset($data['action']) ? trim((string) $data['action']) : '';

      if ($action === 'create-depot') {
        $nom = trim((string) ($data['nom'] ?? ''));
        if ($nom === '') apiError(400, 'Nom du dépôt requis');
        $code = trim((string) ($data['code'] ?? ''));
        $ordre = (int) ($data['ordre'] ?? 0);
        $newId = createDepot($nom, $code, $ordre);
        echo json_encode(['id' => $newId, 'message' => 'Dépôt créé']);
      } elseif ($action === 'update-depot') {
        $did = (int) ($data['id'] ?? 0);
        if ($did <= 0) apiError(400, 'ID dépôt requis');
        $nom = trim((string) ($data['nom'] ?? ''));
        if ($nom === '') apiError(400, 'Nom du dépôt requis');
        $code = trim((string) ($data['code'] ?? ''));
        $ordre = (int) ($data['ordre'] ?? 0);
        updateDepot($did, $nom, $code, $ordre);
        echo json_encode(['message' => 'Dépôt mis à jour']);
      } elseif ($action === 'delete-depot') {
        $did = (int) ($data['id'] ?? 0);
        if ($did <= 0) apiError(400, 'ID dépôt requis');
        deleteDepot($did);
        echo json_encode(['message' => 'Dépôt supprimé']);
      } elseif ($action === 'create-rayon') {
        $depotId = (int) ($data['depot_id'] ?? 0);
        $nom = trim((string) ($data['nom'] ?? ''));
        if ($depotId <= 0) apiError(400, 'depot_id requis');
        if ($nom === '') apiError(400, 'Nom du rayon requis');
        $code = trim((string) ($data['code'] ?? ''));
        $ordre = (int) ($data['ordre'] ?? 0);
        $newId = createRayon($depotId, $nom, $code, $ordre);
        echo json_encode(['id' => $newId, 'message' => 'Rayon créé']);
      } elseif ($action === 'update-rayon') {
        $rid = (int) ($data['id'] ?? 0);
        if ($rid <= 0) apiError(400, 'ID rayon requis');
        $depotId = (int) ($data['depot_id'] ?? 0);
        $nom = trim((string) ($data['nom'] ?? ''));
        if ($nom === '') apiError(400, 'Nom du rayon requis');
        $code = trim((string) ($data['code'] ?? ''));
        $ordre = (int) ($data['ordre'] ?? 0);
        updateRayon($rid, $depotId, $nom, $code, $ordre);
        echo json_encode(['message' => 'Rayon mis à jour']);
      } elseif ($action === 'delete-rayon') {
        $rid = (int) ($data['id'] ?? 0);
        if ($rid <= 0) apiError(400, 'ID rayon requis');
        deleteRayon($rid);
        echo json_encode(['message' => 'Rayon supprimé']);
      } elseif ($action === 'transfer') {
        $pid = (int) ($data['produit_id'] ?? 0);
        $srcRid = (int) ($data['rayon_id'] ?? 0);
        $dstRid = (int) ($data['rayon_dest_id'] ?? 0);
        $qty = (int) ($data['quantite'] ?? 0);
        $comment = trim((string) ($data['commentaire'] ?? ''));
        if ($pid <= 0 || $srcRid <= 0 || $dstRid <= 0) apiError(400, 'produit_id, rayon_id et rayon_dest_id requis');
        transferStock($pid, $srcRid, $dstRid, $qty, $comment, (int) $user['id']);
        echo json_encode(['message' => 'Transfert effectué']);
      } elseif ($action === 'adjust') {
        $pid = (int) ($data['produit_id'] ?? 0);
        $rid = (int) ($data['rayon_id'] ?? 0);
        $newQty = (int) ($data['quantite'] ?? -1);
        $comment = trim((string) ($data['commentaire'] ?? ''));
        if ($pid <= 0 || $rid <= 0) apiError(400, 'produit_id et rayon_id requis');
        if ($newQty < 0) apiError(400, 'Quantité requise');
        adjustStock($pid, $rid, $newQty, $comment, (int) $user['id']);
        echo json_encode(['message' => 'Stock ajusté']);
      } else {
        $pid = (int) ($data['produit_id'] ?? 0);
        $rid = (int) ($data['rayon_id'] ?? 0);
        $type = isset($data['type']) ? trim((string) $data['type']) : '';
        $qty = isset($data['quantite']) ? (int) $data['quantite'] : 0;
        $comment = isset($data['commentaire']) ? trim((string) $data['commentaire']) : '';
        if ($pid <= 0 || $rid <= 0) {
          apiError(400, 'produit_id et rayon_id requis');
        }
        applyStockMovement($pid, $rid, $type, $qty, $comment, (int) $user['id']);
        echo json_encode(['message' => 'Stock mis à jour']);
      }
    } else {
      apiError(405, 'Méthode non autorisée');
    }
    exit;
  }

  if ($resource === 'parametres') {
    if ($user['role'] !== 'admin') {
      apiError(403, 'Accès réservé à l\'administrateur');
    }
    if ($method === 'GET') {
      echo json_encode(getParametres());
    } elseif ($method === 'POST') {
      $data = getInput();
      if (isset($data['action']) && $data['action'] === 'upload-logo') {
        if (empty($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
          apiError(400, 'Fichier logo manquant ou erreur d\'upload');
        }
        $file = $_FILES['logo'];
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file['type'], $allowed, true)) {
          apiError(400, 'Type de fichier non autorisé (JPG, PNG, GIF, WebP)');
        }
        if ($file['size'] > 2 * 1024 * 1024) {
          apiError(400, 'Fichier trop volumineux (2 Mo max)');
        }
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'logo_' . time() . '.' . $ext;
        $dir = __DIR__ . '/uploads/logo';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $dest = $dir . '/' . $filename;
        move_uploaded_file($file['tmp_name'], $dest);
        setParametres(['entreprise_logo' => 'uploads/logo/' . $filename]);
        echo json_encode(['message' => 'Logo mis à jour', 'path' => 'uploads/logo/' . $filename]);
      } else {
        unset($data['action']);
        if (empty($data)) apiError(400, 'Aucune donnée');
        setParametres($data);
        echo json_encode(['message' => 'Paramètres mis à jour']);
      }
    } else {
      apiError(405, 'Méthode non autorisée');
    }
    exit;
  }

  if ($resource === 'factures') {
    if ($user['role'] !== 'admin') {
      apiError(403, 'Accès réservé à l\'administrateur');
    }
    $action = isset($_GET['action']) ? trim((string) $_GET['action']) : '';

    if ($method === 'GET') {
      if ($action === 'stats') {
        echo json_encode(getFactureStats());
      } elseif ($action === 'overdue') {
        echo json_encode(getFacturesEnRetard());
      } elseif ($action === 'next-numero') {
        echo json_encode(['numero' => getNextFactureNumero()]);
      } elseif ($id) {
        $f = getFactureById($id);
        if (!$f) apiError(404, 'Facture introuvable');
        echo json_encode($f);
      } else {
        $filters = [];
        if (!empty($_GET['statut'])) $filters['statut'] = $_GET['statut'];
        if (!empty($_GET['client_id'])) $filters['client_id'] = (int) $_GET['client_id'];
        if (!empty($_GET['date_from'])) $filters['date_from'] = $_GET['date_from'];
        if (!empty($_GET['date_to'])) $filters['date_to'] = $_GET['date_to'];
        echo json_encode(getFactures($filters));
      }
    } elseif ($method === 'POST') {
      $data = getInput();
      $postAction = isset($data['action']) ? trim((string) $data['action']) : '';

      if ($postAction === 'record-payment') {
        $factId = (int) ($data['facture_id'] ?? 0);
        if ($factId <= 0) apiError(400, 'facture_id requis');
        createPaiement(
          $factId,
          (float) ($data['montant'] ?? 0),
          $data['date_paiement'] ?? date('Y-m-d'),
          $data['mode'] ?? 'especes',
          trim((string) ($data['reference'] ?? '')),
          trim((string) ($data['notes'] ?? ''))
        );
        echo json_encode(['message' => 'Paiement enregistré']);
      } elseif ($postAction === 'add-relance') {
        $factId = (int) ($data['facture_id'] ?? 0);
        if ($factId <= 0) apiError(400, 'facture_id requis');
        createRelance(
          $factId,
          $data['type'] ?? 'rappel',
          $data['date_relance'] ?? date('Y-m-d'),
          trim((string) ($data['commentaire'] ?? ''))
        );
        echo json_encode(['message' => 'Relance ajoutée']);
      } else {
        // Check for out-of-stock products
        if (!empty($data['lignes']) && is_array($data['lignes'])) {
          $stockMap = getStockResumeParProduit();
          $sMap = [];
          foreach ($stockMap as $row) {
            $sMap[(int) $row['produit_id']] = (int) $row['total'];
          }
          foreach ($data['lignes'] as $ligne) {
            $pid = !empty($ligne['produit_id']) ? (int) $ligne['produit_id'] : 0;
            if ($pid > 0) {
              $p = getProduitById($pid);
              if ($p && (int) $p['gestion_stock'] === 1 && isset($sMap[$pid]) && $sMap[$pid] <= 0) {
                apiError(400, 'Le produit "' . $p['nom'] . '" est en rupture de stock et ne peut pas être facturé.');
              }
            }
          }
        }
        $newId = createFacture($data);
        http_response_code(201);
        echo json_encode(['id' => $newId, 'message' => 'Facture créée']);
      }
    } elseif ($method === 'PUT') {
      if (!$id) apiError(400, 'ID requis');
      $f = getFactureById($id);
      if (!$f) apiError(404, 'Facture introuvable');
      $data = getInput();
      // Check for out-of-stock products
      if (!empty($data['lignes']) && is_array($data['lignes'])) {
        $stockMap = getStockResumeParProduit();
        $sMap = [];
        foreach ($stockMap as $row) {
          $sMap[(int) $row['produit_id']] = (int) $row['total'];
        }
        foreach ($data['lignes'] as $ligne) {
          $pid = !empty($ligne['produit_id']) ? (int) $ligne['produit_id'] : 0;
          if ($pid > 0) {
            $p = getProduitById($pid);
            if ($p && (int) $p['gestion_stock'] === 1 && isset($sMap[$pid]) && $sMap[$pid] <= 0) {
              apiError(400, 'Le produit "' . $p['nom'] . '" est en rupture de stock et ne peut pas être facturé.');
            }
          }
        }
      }
      updateFacture($id, $data);
      echo json_encode(['message' => 'Facture mise à jour']);
    } elseif ($method === 'DELETE') {
      if (!$id) apiError(400, 'ID requis');
      if (!getFactureById($id)) apiError(404, 'Facture introuvable');
      deleteFacture($id);
      echo json_encode(['message' => 'Facture supprimée']);
    } else {
      apiError(405, 'Méthode non autorisée');
    }
    exit;
  }

  apiError(404, 'Ressource non trouvée');

} catch (PDOException $e) {
  http_response_code(500);
  $msg = 'Erreur base de données. Vérifiez que la table utilisateurs existe.';
  if (strpos($e->getMessage(), 'Duplicate') !== false || strpos($e->getMessage(), '1062') !== false) {
    $msg = 'Cet identifiant est déjà utilisé.';
    http_response_code(400);
  }
  echo json_encode(['error' => $msg]);
} catch (Exception $e) {
  $msg = $e->getMessage();
  $clientErr = strpos($msg, 'déjà utilisé') !== false
    || strpos($msg, 'Stock insuffisant') !== false
    || strpos($msg, 'Quantité') !== false
    || strpos($msg, 'invalide') !== false
    || strpos($msg, 'introuvable') !== false
    || strpos($msg, 'non modifiable') !== false
    || strpos($msg, 'n’est pas géré') !== false;
  http_response_code($clientErr ? 400 : 500);
  echo json_encode(['error' => $msg]);
}
