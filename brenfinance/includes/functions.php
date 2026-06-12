<?php
session_start();

// Fuseau horaire du Cameroun (UTC+1)
date_default_timezone_set('Africa/Douala');

define('APP_NAME', 'BrenFinance Suite Pro');
define('APP_VERSION', '1.0.0');
define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', '/brenfinance');
define('REMEMBER_COOKIE', 'brenfinance_remember');
define('REMEMBER_DAYS', 30);

require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/includes/export.php';

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        tryAutoLogin();
        if (!isLoggedIn()) {
            header('Location: ' . BASE_URL . '/index.php');
            exit;
        }
    }
    // Refresh permissions from DB on each page load
    $db = getDB();
    $stmt = $db->prepare("SELECT r.nom as role_nom, r.permissions FROM roles r JOIN utilisateurs u ON u.role_id = r.id WHERE u.id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $roleData = $stmt->fetch();
    if ($roleData) {
        $_SESSION['user']['role_nom'] = $roleData['role_nom'];
        $_SESSION['user']['permissions'] = json_decode($roleData['permissions'], true);
    }
}

function currentUser(): ?array {
    if (!isLoggedIn()) return null;
    return $_SESSION['user'] ?? null;
}

function hasPermission(string $module, string $action = 'all'): bool {
    $user = currentUser();
    if (!$user) return false;
    $perms = $user['permissions'] ?? [];
    if (isset($perms['all']) && $perms['all']) return true;
    if (isset($perms[$module]['all']) && $perms[$module]['all']) return true;
    if (isset($perms[$module][$action]) && $perms[$module][$action]) return true;
    return false;
}

function canSeeModule(string $module): bool {
    if (hasPermission('all', 'all')) return true;
    if (hasPermission($module, 'all')) return true;
    $actions = [
        'caisse'            => ['consulter', 'saisir'],
        'operations_caisse' => ['consulter', 'saisir', 'annuler'],
        'tresorerie'        => ['consulter', 'saisir', 'valider_operations', 'rapprocher', 'importer_releve'],
        'engagements'       => ['creer', 'consulter', 'consulter_propres', 'valider_hierarchie', 'valider_comptable', 'valider_daf', 'executer'],
        'comptabilite'      => ['consulter', 'saisir'],
        'budget'            => ['consulter', 'creer', 'valider'],
        'reporting'         => ['consulter'],
        'audit'             => ['consulter'],
        'admin'             => ['all'],
        'referentiels'      => ['consulter', 'saisir'],
        'ordre_mission'     => ['creer', 'consulter', 'consulter_propres', 'valider_hierarchie', 'valider_daf', 'executer'],
        'decharge'          => ['creer', 'consulter', 'valider', 'executer'],
        'radnex'            => ['consulter'],
        'paie'              => ['consulter', 'gerer_rubriques', 'gerer_bulletins', 'valider_paie', 'declarer'],
        'rh'                => ['consulter', 'importer', 'modifier'],
        'bons_commande'     => ['consulter', 'creer', 'valider', 'recevoir'],
        'cloture'           => ['consulter', 'gerer_exercice', 'ecritures_inventaire', 'cloturer'],
        'compta_analytique' => ['consulter', 'configurer', 'affecter'],
        'exercices'         => ['consulter'],
    ];
    if (isset($actions[$module])) {
        foreach ($actions[$module] as $action) {
            if (hasPermission($module, $action)) return true;
        }
    }
    return false;
}

function requireModuleAccess(string $module): void {
    if (!canSeeModule($module)) {
        flash('danger', "Vous n'avez pas accès à ce module.");
        $pageTitle = 'Accès refusé';
        include __DIR__ . '/header.php';
        echo '<div style="text-align:center;padding:80px 20px"><i class="fa-solid fa-lock" style="font-size:48px;color:var(--danger,#e74c3c);margin-bottom:16px;display:block"></i><h2>Accès refusé</h2><p style="color:var(--text3);margin-bottom:24px">Vous n\'avez pas les permissions nécessaires pour accéder à ce module.</p><a href="' . BASE_URL . '/dashboard.php" class="btn btn-primary"><i class="fa-solid fa-arrow-left"></i> Retour au tableau de bord</a></div>';
        include __DIR__ . '/footer.php';
        exit;
    }
}

/* ─── NOTIFICATIONS ──────────────────────────────────────────── */
function notify(int $userId, ?int $engagementId, string $type, string $titre, string $message, ?int $ordreMissionId = null): void {
    $db = getDB();
    $db->prepare("INSERT INTO notifications (utilisateur_id, engagement_id, ordre_mission_id, type, titre, message) VALUES (?,?,?,?,?,?)")
       ->execute([$userId, $engagementId, $ordreMissionId, $type, $titre, $message]);
}

function notifyUsersWithPermission(string $module, string $action, int $engagementId, string $type, string $titre, string $message, array $excludeIds = []): void {
    $db = getDB();
    $rows = $db->query("SELECT u.id, r.permissions FROM utilisateurs u JOIN roles r ON u.role_id=r.id WHERE u.statut='actif'")->fetchAll();
    foreach ($rows as $row) {
        if (in_array($row['id'], $excludeIds)) continue;
        $perms = json_decode($row['permissions'], true);
        if (isset($perms['all']) && $perms['all']) { notify($row['id'], $engagementId, $type, $titre, $message); continue; }
        if (isset($perms[$module]['all']) && $perms[$module]['all']) { notify($row['id'], $engagementId, $type, $titre, $message); continue; }
        if (isset($perms[$module][$action]) && $perms[$module][$action]) { notify($row['id'], $engagementId, $type, $titre, $message); }
    }
}

function timeAgo(string $datetime): string {
    $now = time();
    $ts = strtotime($datetime);
    $diff = $now - $ts;
    if ($diff < 60) return 'À l\'instant';
    if ($diff < 3600) return floor($diff/60) . ' min';
    if ($diff < 86400) return floor($diff/3600) . ' h';
    if ($diff < 604800) return floor($diff/86400) . ' j';
    return date('d/m/Y', $ts);
}

/* ─── ENTREPRISE ──────────────────────────────────────────────── */
function getEntreprise(): array {
    static $cache = null;
    if ($cache === null) {
        $db = getDB();
        $cache = $db->query("SELECT * FROM entreprises WHERE id=1")->fetch() ?: [];
    }
    return $cache;
}

/* ─── REMEMBER ME ─────────────────────────────────────────────── */
function generateSecureToken(): string {
    return bin2hex(random_bytes(32));
}

function createRememberToken(int $userId): void {
    $db = getDB();
    $selector = bin2hex(random_bytes(16));
    $token = generateSecureToken();
    $tokenHash = hash('sha256', $token);
    $expires = date('Y-m-d H:i:s', time() + REMEMBER_DAYS * 86400);

    // Delete any existing token for this user
    $db->prepare("DELETE FROM remember_tokens WHERE utilisateur_id = ?")->execute([$userId]);

    $db->prepare("INSERT INTO remember_tokens (utilisateur_id, selector, token_hash, expires_at) VALUES (?,?,?,?)")
       ->execute([$userId, $selector, $tokenHash, $expires]);

    $cookieValue = $selector . ':' . $token;
    setcookie(REMEMBER_COOKIE, $cookieValue, [
        'expires'  => time() + REMEMBER_DAYS * 86400,
        'path'     => BASE_URL . '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function tryAutoLogin(): void {
    if (isLoggedIn()) return;
    if (empty($_COOKIE[REMEMBER_COOKIE])) return;

    $parts = explode(':', $_COOKIE[REMEMBER_COOKIE], 2);
    if (count($parts) !== 2) { clearRememberCookie(); return; }

    [$selector, $token] = $parts;
    $db = getDB();

    $stmt = $db->prepare("SELECT rt.*, u.statut FROM remember_tokens rt JOIN utilisateurs u ON rt.utilisateur_id = u.id WHERE rt.selector = ? AND rt.expires_at > NOW()");
    $stmt->execute([$selector]);
    $row = $stmt->fetch();

    if (!$row || !hash_equals($row['token_hash'], hash('sha256', $token))) {
        clearRememberCookie();
        return;
    }

    if ($row['statut'] !== 'actif') {
        clearRememberCookie();
        return;
    }

    // Valid token — restore session
    $userStmt = $db->prepare("SELECT u.*, r.nom as role_nom, r.permissions FROM utilisateurs u JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
    $userStmt->execute([$row['utilisateur_id']]);
    $user = $userStmt->fetch();

    if (!$user) { clearRememberCookie(); return; }

    $user['permissions'] = json_decode($user['permissions'], true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user'] = $user;
    $db->prepare("UPDATE utilisateurs SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
    auditLog('auto_login', 'auth');

    // Rotate token for security
    createRememberToken($user['id']);
}

function clearRememberCookie(): void {
    if (!empty($_COOKIE[REMEMBER_COOKIE])) {
        $parts = explode(':', $_COOKIE[REMEMBER_COOKIE], 2);
        if (count($parts) === 2) {
            try {
                getDB()->prepare("DELETE FROM remember_tokens WHERE selector = ?")->execute([$parts[0]]);
            } catch (Exception $e) {}
        }
    }
    setcookie(REMEMBER_COOKIE, '', [
        'expires'  => time() - 3600,
        'path'     => BASE_URL . '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function auditLog(string $action, string $module, ?string $table = null, ?int $recordId = null, $old = null, $new = null): void {
    try {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO journal_audit (utilisateur_id, action, module, table_cible, enregistrement_id, anciennes_valeurs, nouvelles_valeurs, ip_address, user_agent) VALUES (?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            $_SESSION['user_id'] ?? null,
            $action, $module, $table, $recordId,
            $old ? json_encode($old) : null,
            $new ? json_encode($new) : null,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (Exception $e) {}
}

function formatMontant(float $amount, string $devise = 'FCFA'): string {
    return number_format($amount, 0, ',', ' ') . ' ' . $devise;
}

function montantEnLettres(float $nombre, string $devise = 'francs', string $sous = 'centimes'): string {
    $unites = ['', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf',
               'dix', 'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize', 'dix-sept', 'dix-huit', 'dix-neuf'];
    $dizaines = ['', 'dix', 'vingt', 'trente', 'quarante', 'cinquante', 'soixante', 'soixante', 'quatre-vingt', 'quatre-vingt'];

    $entier = (int)floor($nombre);
    $centimes = (int)round(($nombre - $entier) * 100);

    $groupes = [
        [1000000000, 'milliard'],
        [1000000, 'million'],
        [1000, 'mille'],
        [100, 'cent'],
    ];

    $resultat = '';
    $reste = $entier;

    foreach ($groupes as [$div, $mot]) {
        if ($reste >= $div) {
            $q = (int)($reste / $div);
            $reste = $reste % $div;
            if ($div === 100) {
                if ($q > 1) $resultat .= $unites[$q] . ' ';
                $resultat .= 'cent';
                if ($reste === 0 && $entier > 100) $resultat .= 's';
                $resultat .= ' ';
            } elseif ($div === 1000 && $q === 1) {
                $resultat .= 'mille ';
            } else {
                $sousResultat = convertirCentaines($q, $unites, $dizaines);
                $resultat .= $sousResultat . ' ' . $mot;
                if ($q > 1) $resultat .= 's';
                $resultat .= ' ';
            }
        }
    }

    if ($reste > 0 || $entier === 0) {
        if ($entier === 0) {
            $resultat = 'zéro ';
        } else {
            $resultat .= convertirCentaines($reste, $unites, $dizaines) . ' ';
        }
    }

    $resultat = trim($resultat);
    if ($entier === 1) {
        $resultat .= ' ' . $devise;
    } else {
        $resultat .= ' ' . $devise;
    }

    if ($centimes > 0) {
        $resultat .= ' et ' . convertirCentaines($centimes, $unites, $dizaines) . ' ' . $sous;
    }

    return $resultat;
}

function convertirCentaines(int $n, array $unites, array $dizaines): string {
    if ($n === 0) return '';
    if ($n < 20) return $unites[$n];

    $resultat = '';
    if ($n >= 100) {
        $c = (int)($n / 100);
        $n = $n % 100;
        if ($c > 1) $resultat .= $unites[$c] . ' ';
        $resultat .= 'cent';
        if ($n === 0) $resultat .= 's';
        if ($n > 0) $resultat .= ' ';
    }

    if ($n === 0) return $resultat;

    if ($n < 20) {
        return $resultat . $unites[$n];
    }

    $d = (int)($n / 10);
    $u = $n % 10;

    if ($d === 7 || $d === 9) {
        $base = $dizaines[$d];
        $reste = $n - ($d === 7 ? 60 : 80);
        if ($d === 7) {
            if ($reste === 1) $resultat .= 'soixante-et-onze';
            elseif ($reste < 20) $resultat .= 'soixante-' . $unites[$reste];
            else $resultat .= 'soixante-' . $unites[$reste];
        } else {
            if ($u === 0) $resultat .= 'quatre-vingts';
            elseif ($u === 1) $resultat .= 'quatre-vingt-un';
            else $resultat .= 'quatre-vingt-' . $unites[$u];
        }
        return $resultat;
    }

    if ($d === 8) {
        if ($u === 0) return $resultat . 'quatre-vingts';
        return $resultat . 'quatre-vingt-' . $unites[$u];
    }

    $resultat .= $dizaines[$d];
    if ($u === 0) {
        if ($d === 1) $resultat .= '';
        elseif ($u === 1 && $d !== 8) $resultat .= '-et-un';
    } elseif ($u === 1 && $d !== 8) {
        $resultat .= '-et-un';
    } else {
        $resultat .= '-' . $unites[$u];
    }

    return $resultat;
}

function generateNumero(string $prefix): string {
    return $prefix . '-' . date('Y') . '-' . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
}

function flash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

function sanitize(string $str): string {
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}

function str_getcsv_all(string $content, string $separator = ','): array {
    $lines = [];
    $temp = fopen('php://memory', 'r+');
    fwrite($temp, $content);
    rewind($temp);
    while (($row = fgetcsv($temp, 0, $separator)) !== false) {
        $lines[] = $row;
    }
    fclose($temp);
    return $lines;
}

define('UPLOAD_ALLOWED_MIME', [
    'application/pdf',
    'image/jpeg', 'image/png', 'image/gif',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.ms-powerpoint',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'application/vnd.oasis.opendocument.text',
    'application/vnd.oasis.opendocument.spreadsheet',
]);
define('UPLOAD_MAX_SIZE', 10 * 1024 * 1024); // 10 Mo

function handleUploads(string $inputName, string $numero, string $subDir = 'engagements'): array {
    if (empty($_FILES[$inputName]) || empty($_FILES[$inputName]['name'])) {
        return [];
    }

    $files = $_FILES[$inputName];
    $uploaded = [];
    $destDir = BASE_PATH . '/uploads/' . $subDir . '/';

    if (!is_dir($destDir)) {
        mkdir($destDir, 0755, true);
    }

    $count = is_array($files['name']) ? count($files['name']) : 1;
    if (!is_array($files['name'])) {
        $files = [
            'name'     => [$files['name']],
            'type'     => [$files['type']],
            'tmp_name' => [$files['tmp_name']],
            'error'    => [$files['error']],
            'size'     => [$files['size']],
        ];
    }

    for ($i = 0; $i < $count; $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) {
            if ($files['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
            flash('warning', 'Erreur lors du téléchargement de ' . sanitize($files['name'][$i]) . '.');
            continue;
        }

        if ($files['size'][$i] > UPLOAD_MAX_SIZE) {
            flash('warning', sanitize($files['name'][$i]) . ' dépasse 10 Mo et n\'a pas été ajouté.');
            continue;
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($files['tmp_name'][$i]);

        if (!in_array($mime, UPLOAD_ALLOWED_MIME, true)) {
            flash('warning', sanitize($files['name'][$i]) . ' : type de fichier non autorisé.');
            continue;
        }

        $ext = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
        $safeName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $numero) . '_' . time() . '_' . $i . '.' . $ext;

        if (move_uploaded_file($files['tmp_name'][$i], $destDir . $safeName)) {
            $uploaded[] = [
                'nom'     => basename($files['name'][$i]),
                'fichier' => $safeName,
                'taille'  => (int)$files['size'][$i],
                'type'    => $mime,
            ];
        } else {
            flash('warning', 'Impossible d\'enregistrer ' . sanitize($files['name'][$i]) . '.');
        }
    }

    return $uploaded;
}

function formatFileSize(int $bytes): string {
    if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' Mo';
    if ($bytes >= 1024) return round($bytes / 1024, 0) . ' Ko';
    return $bytes . ' o';
}

function fileIcon(string $mime): string {
    if (strpos($mime, 'pdf') !== false) return '<i class="fa-solid fa-file-pdf"></i>';
    if (strpos($mime, 'image') !== false) return '<i class="fa-solid fa-file-image"></i>';
    if (strpos($mime, 'word') !== false || strpos($mime, 'opendocument.text') !== false) return '<i class="fa-solid fa-file-word"></i>';
    if (strpos($mime, 'excel') !== false || strpos($mime, 'spreadsheet') !== false) return '<i class="fa-solid fa-file-excel"></i>';
    if (strpos($mime, 'powerpoint') !== false || strpos($mime, 'presentation') !== false) return '<i class="fa-solid fa-file-powerpoint"></i>';
    return '<i class="fa-solid fa-paperclip"></i>';
}

function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/* ─── EXERCICES ────────────────────────────────────────────────── */
function getExerciceCourant(): ?array {
    $db = getDB();
    return $db->query("SELECT * FROM exercices WHERE statut='ouvert' OR statut='cloture_provisoire' ORDER BY statut='ouvert' DESC, date_debut DESC LIMIT 1")->fetch() ?: null;
}

function getExercices(): array {
    return getDB()->query("SELECT * FROM exercices ORDER BY date_debut DESC")->fetchAll();
}

function exerciceEstVerrouille(?int $exerciceId = null): bool {
    if (!$exerciceId) {
        $ex = getExerciceCourant();
        $exerciceId = $ex ? (int)$ex['id'] : 0;
    }
    if (!$exerciceId) return false;
    $ex = getDB()->prepare("SELECT statut FROM exercices WHERE id=?");
    $ex->execute([$exerciceId]);
    $ex = $ex->fetch();
    return $ex && in_array($ex['statut'], ['cloture_definitive', 'archive']);
}

function verifierVerrouillageExercice(?int $exerciceId = null): void {
    if (exerciceEstVerrouille($exerciceId)) {
        flash('danger', "Cet exercice est clôturé. Aucune saisie n'est possible.");
        header('Location: ' . BASE_URL . '/dashboard.php');
        exit;
    }
}

/* ─── AMORTISSEMENTS ───────────────────────────────────────────── */
function calculerAmortissementAnnuel(array $immo): float {
    $valeur = (float)($immo['valeur_acquisition'] ?? 0);
    $cumul = (float)($immo['cumul_amortissement'] ?? 0);
    $duree = (int)($immo['duree_amortissement'] ?? 5);
    $methode = $immo['mode_amortissement'] ?? 'lineaire';
    $vnc = max(0, $valeur - $cumul);
    if ($vnc <= 0 || $duree <= 0) return 0;

    if ($methode === 'degressif') {
        $tauxLineaire = 1 / $duree;
        $coef = (float)($immo['coefficient_degressif'] ?? 1.75);
        $tauxDegressif = $tauxLineaire * $coef;
        $annuite = $vnc * $tauxDegressif;
        $anneesRestantes = $duree - (($cumul > 0) ? floor($cumul / ($valeur * $tauxLineaire)) : 0);
        if ($anneesRestantes > 0 && $tauxDegressif < (1 / $anneesRestantes)) {
            $annuite = $vnc / max(1, $anneesRestantes);
        }
        return $annuite;
    }
    return $valeur / max(1, $duree);
}

/* ─── SIG & RATIOS ─────────────────────────────────────────────── */
function calculerSIG(int $exerciceId): array {
    $db = getDB();
    $query = function(string $comptes, string $sens = 'credit') use ($db, $exerciceId) {
        $cls = is_array($comptes) ? $comptes : explode(',', $comptes);
        $conditions = implode(' OR ', array_fill(0, count($cls), 'compte LIKE ?'));
        $sign = $sens === 'credit' ? 'credit - debit' : 'debit - credit';
        $params = [];
        foreach ($cls as $c) $params[] = trim($c) . '%';
        $params[] = $exerciceId;
        $stmt = $db->prepare("SELECT COALESCE(SUM($sign),0) FROM ecritures_comptables WHERE ($conditions) AND exercice_id=?");
        $stmt->execute($params);
        return (float)$stmt->fetchColumn();
    };

    $margeCommerciale = $query('70%', 'credit') - $query('60%', 'debit');
    $prodExercice = $query('70%', 'credit') + $query('71%') + $query('72%') + $query('73%') + $query('75%');
    $consoEx = $query('60%', 'debit') + $query('61%') + $query('62%');
    $valAjoutee = $margeCommerciale + $prodExercice - $consoEx;
    $ebe = $valAjoutee + $query('71%') - $query('63%') - $query('64%') - $query('65%');
    $resExploit = $ebe + $query('75%') + $query('78%') - $query('65%') - $query('68%');
    $resFinancier = ($query('76%') + $query('796%')) - ($query('66%') + $query('696%'));
    $resOrdinaire = $resExploit + $resFinancier;
    $resHAO = $query('77%') + $query('797%') - $query('67%') - $query('697%');
    $resNet = $resOrdinaire + $resHAO - $query('69%');

    return [
        ['code'=>'MC','libelle'=>'Marge commerciale','montant'=>$margeCommerciale],
        ['code'=>'PE','libelle'=>'Production de l\'exercice','montant'=>$prodExercice],
        ['code'=>'CE','libelle'=>'Consommation de l\'exercice','montant'=>$consoEx],
        ['code'=>'VA','libelle'=>'Valeur ajoutée','montant'=>$valAjoutee],
        ['code'=>'EBE','libelle'=>'Excédent brut d\'exploitation','montant'=>$ebe],
        ['code'=>'RE','libelle'=>'Résultat d\'exploitation','montant'=>$resExploit],
        ['code'=>'RF','libelle'=>'Résultat financier','montant'=>$resFinancier],
        ['code'=>'RO','libelle'=>'Résultat ordinaire','montant'=>$resOrdinaire],
        ['code'=>'RHAO','libelle'=>'Résultat HAO','montant'=>$resHAO],
        ['code'=>'RN','libelle'=>'Résultat net de l\'exercice','montant'=>$resNet],
    ];
}

function calculerRatios(int $exerciceId): array {
    $sig = calculerSIG($exerciceId);
    $rn = $sig[9]['montant'] ?? 0;
    $ca = $sig[1]['montant'] ?? 0;
    $va = $sig[3]['montant'] ?? 0;
    $ebe = $sig[4]['montant'] ?? 0;
    $re = $sig[5]['montant'] ?? 0;

    $db = getDB();
    $capitaux = $db->prepare("SELECT COALESCE(SUM(credit-debit),0) FROM ecritures_comptables WHERE compte LIKE '1%' AND exercice_id=?");
    $capitaux->execute([$exerciceId]);
    $capitaux = (float)$capitaux->fetchColumn();
    $totalActif = $db->prepare("SELECT COALESCE(SUM(debit-credit),0) FROM ecritures_comptables WHERE (compte LIKE '1%' OR compte LIKE '2%' OR compte LIKE '3%' OR compte LIKE '4%' OR compte LIKE '5%') AND exercice_id=?");
    $totalActif->execute([$exerciceId]);
    $totalActif = (float)$totalActif->fetchColumn();
    $dettesFin = $db->prepare("SELECT COALESCE(SUM(credit-debit),0) FROM ecritures_comptables WHERE (compte LIKE '16%' OR compte LIKE '17%') AND exercice_id=?");
    $dettesFin->execute([$exerciceId]);
    $dettesFin = (float)$dettesFin->fetchColumn();

    $ratios = [];
    if ($ca != 0) {
        $ratios[] = ['code'=>'RN/CA','libelle'=>'Marge nette','valeur'=>round(($rn/$ca)*100,2),'norme'=>'5-15%','interpretation'=>'Part du résultat net dans le chiffre d\'affaires','ok'=>($rn/$ca)>=0.05];
    }
    if ($va != 0) {
        $ratios[] = ['code'=>'EBE/VA','libelle'=>'Taux de marge brute','valeur'=>round(($ebe/$va)*100,2),'norme'=>'30-50%','interpretation'=>'Richesse restant après frais de personnel','ok'=>($ebe/$va)>=0.25];
    }
    if ($capitaux != 0) {
        $ratios[] = ['code'=>'RN/CP','libelle'=>'Rentabilité financière (ROE)','valeur'=>round(($rn/$capitaux)*100,2),'norme'=>'>10%','interpretation'=>'Rendement des capitaux propres investis','ok'=>($rn/$capitaux)>=0.10];
    }
    if ($ebe != 0) {
        $ratios[] = ['code'=>'DF/EBE','libelle'=>'Capacité de remboursement','valeur'=>round(($dettesFin/max(1,$ebe)),1),'norme'=>'<3','interpretation'=>'Années nécessaires pour rembourser les dettes avec l\'EBE','ok'=>($dettesFin/$ebe)<3];
    }
    $ratios[] = ['code'=>'RE/CA','libelle'=>'Marge opérationnelle','valeur'=>$ca!=0?round(($re/$ca)*100,2):0,'norme'=>'10-25%','interpretation'=>'Performance de l\'activité principale','ok'=>$ca!=0 && ($re/$ca)>=0.10];

    return $ratios;
}

/* ─── COMPTA ANALYTIQUE ────────────────────────────────────────── */
function getAxesAnalytiques(): array {
    return getDB()->query("SELECT * FROM axes_analytiques ORDER BY type, code")->fetchAll();
}

function getSectionsAnalytiques(): array {
    return getDB()->query("SELECT * FROM sections_analytiques ORDER BY code")->fetchAll();
}

function affecterAnalytique(string $sourceType, int $sourceId, int $axeId, float $montant, ?float $pourcentage = null, ?int $ecritureId = null): void {
    $db = getDB();
    $db->prepare("INSERT INTO affectations_analytiques (source_type, source_id, axe_id, montant, pourcentage, ecriture_id) VALUES (?,?,?,?,?,?)")
       ->execute([$sourceType, $sourceId, $axeId, $montant, $pourcentage, $ecritureId]);
}

/* ─── PAIE ──────────────────────────────────────────────────────── */
function getRubriquesActives(): array {
    return getDB()->query("SELECT * FROM rubriques_paie WHERE actif=1 ORDER BY ordre_affichage")->fetchAll();
}

function calculerAnciennete(string $dateEmbauche): array {
    $embauche = new DateTime($dateEmbauche);
    $now = new DateTime();
    $diff = $embauche->diff($now);
    return ['annees' => $diff->y, 'mois' => $diff->m];
}

function calculerIRPP(float $baseImposable): float {
    $tranches = [
        [0, 250000, 0, 0],
        [250001, 416667, 0.10, 250000],
        [416668, 583333, 0.15, 416667],
        [583334, 750000, 0.20, 583334],
        [750001, 1000000, 0.25, 750001],
        [1000001, 1500000, 0.30, 1000001],
        [1500001, 2000000, 0.35, 1500001],
        [2000001, PHP_INT_MAX, 0.40, 2000001],
    ];
    $impot = 0;
    foreach ($tranches as [$min, $max, $taux, $cumul]) {
        if ($baseImposable > $min) {
            $imposable = min($baseImposable, $max) - $cumul;
            $impot += $imposable * $taux;
        }
    }
    return round($impot, 0);
}

function calculerMontantRubrique(array $rubrique, float $salaireBase): float {
    $mode = $rubrique['calcul'] ?? 'montant_fixe';
    if ($mode === 'montant_fixe') {
        return (float)($rubrique['valeur'] ?? 0);
    }
    $valeur = (float)($rubrique['valeur'] ?? 0);
    if ($mode === 'pourcentage_base' || $mode === 'pourcentage_brut') {
        return round($salaireBase * $valeur / 100, 0);
    }
    return $valeur;
}

function calculerBulletin(int $utilisateurId, int $periodeId): array {
    $db = getDB();
    $contrat = $db->prepare("SELECT ce.*, e.utilisateur_id FROM contrats_employes ce JOIN employes e ON ce.employe_id=e.id WHERE e.utilisateur_id=? AND ce.statut='actif' ORDER BY date_embauche DESC LIMIT 1");
    $contrat->execute([$utilisateurId]);
    $contrat = $contrat->fetch();
    if (!$contrat) throw new Exception("Aucun contrat actif pour l'utilisateur #$utilisateurId");

    $salaireBase = (float)$contrat['salaire_base'];
    $anciennete = calculerAnciennete($contrat['date_embauche']);
    $rubriques = getRubriquesActives();

    $lignes = [];
    $totalGains = 0;
    $totalRetenues = 0;
    $totalPatronal = 0;
    $baseImposable = $salaireBase;

    foreach ($rubriques as $r) {
        $montant = calculerMontantRubrique($r, $salaireBase);
        if ($montant <= 0 && $r['calcul'] !== 'montant_fixe') continue;
        $montant = abs($montant);

        switch ($r['type']) {
            case 'gain': $totalGains += $montant; break;
            case 'retenue': $totalRetenues += $montant; break;
            case 'cotisation_patronale': $totalPatronal += $montant; break;
            case 'indemnite': $totalGains += $montant; break;
        }
        $lignes[] = ['rubrique_id' => $r['id'], 'libelle' => $r['libelle'], 'type' => $r['type'], 'montant' => $montant, 'ordre' => (int)($r['ordre_affichage'] ?? 0)];
    }

    // IRPP calculation
    $irpp = calculerIRPP($salaireBase);
    if ($irpp > 0) {
        $totalRetenues += $irpp;
        $lignes[] = ['rubrique_id' => null, 'libelle' => 'IRPP (Impôt sur le revenu)', 'type' => 'retenue', 'montant' => $irpp, 'ordre' => 999];
    }

    $netAPayer = $salaireBase + $totalGains - $totalRetenues;
    $coutTotal = $salaireBase + $totalGains + $totalPatronal;

    return [
        'salaire_base' => $salaireBase,
        'total_gains' => $totalGains,
        'total_retenues' => $totalRetenues,
        'net_a_payer' => $netAPayer,
        'charges_patronales' => $totalPatronal,
        'cout_total' => $coutTotal,
        'lignes' => $lignes,
    ];
}

/* ─── NUMÉRO SEQUENTIEL ────────────────────────────────────────── */
function generateNumeroSequentiel(string $prefix, string $table): string {
    $db = getDB();
    $r = $db->query("SELECT MAX(numero) FROM $table WHERE numero LIKE '$prefix-%'")->fetchColumn();
    if ($r) {
        $parts = explode('-', $r);
        $seq = (int)(end($parts)) + 1;
    } else {
        $seq = 1;
    }
    return $prefix . '-' . date('Y') . '-' . str_pad($seq, 5, '0', STR_PAD_LEFT);
}

/* ─── RAPPROCHEMENT BANCAIRE ────────────────────────────────────── */
function matcherOperationsRapprochement(array $operations): array {
    $matches = [];
    foreach ($operations as $op) {
        $candidates = getDB()->prepare("SELECT * FROM operations_bancaires WHERE ABS(montant - ?) < 1 AND ABS(DATEDIFF(date_operation, ?)) <= 5 ORDER BY ABS(DATEDIFF(date_operation, ?)) LIMIT 1");
        $candidates->execute([(float)($op['montant'] ?? 0), $op['date'] ?? date('Y-m-d'), $op['date'] ?? date('Y-m-d')]);
        $match = $candidates->fetch();
        $matches[] = ['operation' => $op, 'match' => $match ?: null, 'matched' => (bool)$match];
    }
    return $matches;
}

/* ─── BONS DE COMMANDE ──────────────────────────────────────────── */
function getBonsCommande(?string $statut = null): array {
    $db = getDB();
    $sql = "SELECT bc.*, de.numero as eng_numero, f.nom as fournisseur_nom FROM bons_commande bc JOIN demandes_engagement de ON bc.engagement_id=de.id JOIN fournisseurs f ON bc.fournisseur_id=f.id";
    $params = [];
    if ($statut) {
        $sql .= " WHERE bc.statut=?";
        $params[] = $statut;
    }
    $sql .= " ORDER BY bc.created_at DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}
