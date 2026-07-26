<?php
declare(strict_types=1);
/**
 * PharmaCare — Sauvegarde / Restauration BDD : fonctions métier.
 *
 * Bibliothèque sans effet de bord à l'inclusion (pas d'auth, pas d'I/O HTTP).
 * Le contrôleur/vue est dans modules/sauvegarde.php.
 *
 * Backups dans pharmacare/backups/ (protégé du web). Outil : mysqldump / mysql.
 * Identifiants passés via un fichier temporaire --defaults-extra-file (jamais
 * en clair sur la ligne de commande / liste des processus).
 */
require_once __DIR__ . '/../config/settings.php';

// ── Dossier des sauvegardes ─────────────────────────────────
function sauv_dir(): string {
    $dir = dirname(__DIR__) . '/backups';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
        // Ceinture+bretelles : bloquer l'accès web direct au dossier (.htaccess
        // global bloque aussi *.sql — ceci couvre tout fichier du dossier).
        @file_put_contents($dir . '/.htaccess', "Require all denied\nDeny from all\nOptions -Indexes\n");
        @file_put_contents($dir . '/index.php', "<?php http_response_code(403); exit;\n");
    }
    return $dir;
}

/** Valide qu'un nom de fichier est une sauvegarde réelle dans backups/. */
function sauv_resolve(?string $name): ?string {
    if ($name === null || $name === '') return null;
    // N'accepter que nos noms canoniques : pharmacare[_prerestore]_YYYYMMDD_HHMMSS.sql
    if (!preg_match('/^pharmacare(_prerestore)?_\d{8}_\d{6}\.sql$/', $name)) return null;
    $path = sauv_dir() . '/' . $name;
    return (is_file($path) && is_readable($path)) ? realpath($path) : null;
}

/** Liste les sauvegardes (nom, taille, date). */
function sauv_list(): array {
    $dir = sauv_dir();
    $files = glob($dir . '/pharmacare*.sql');
    if (!$files) return [];
    $out = [];
    foreach ($files as $f) {
        $out[] = [
            'name' => basename($f),
            'size' => filesize($f),
            'mtime' => filemtime($f),
            'prerestore' => str_contains(basename($f), '_prerestore_'),
        ];
    }
    usort($out, fn($a, $b) => $b['mtime'] <=> $a['mtime']);
    return $out;
}

// ── Détection du bin MySQL (XAMPP Windows / LAMP Linux) ─────
function sauv_bin(): ?string {
    static $bin = null;
    if ($bin !== null) return $bin ?: null;
    $isWin = PHP_OS_FAMILY === 'Windows';
    $probe = $isWin ? 'mysqldump.exe' : 'mysqldump';
    $candidates = [];
    if (getenv('MYSQL_BIN')) $candidates[] = getenv('MYSQL_BIN');
    if ($isWin) {
        $candidates[] = 'C:\xampp\mysql\bin';
    } else {
        $candidates[] = '/usr/bin';
        $candidates[] = '/usr/local/bin';
        $candidates[] = '/opt/lampp/bin';        // XAMPP Linux
        $candidates[] = '/opt/bitnami/mysql/bin';
    }
    foreach ($candidates as $c) {
        $p = rtrim(str_replace('\\', '/', $c), '/') . '/' . $probe;
        if (is_file($p)) { $bin = rtrim(str_replace('\\', '/', $c), '/'); return $bin; }
    }
    // Recherche dans le PATH (where / which)
    $where = $isWin ? 'where mysqldump' : 'which mysqldump';
    $out = [];
    @exec($where, $out, $rc);
    if ($rc === 0 && !empty($out[0]) && is_file($out[0])) {
        $bin = dirname(str_replace('\\', '/', $out[0]));
        return $bin;
    }
    $bin = '';
    return null;
}

/** Chemin complet d'un binaire MySQL (suffixe .exe sur Windows). */
function sauv_exe(string $prog): string {
    $bin = sauv_bin();
    $suffix = (PHP_OS_FAMILY === 'Windows') ? '.exe' : '';
    return ($bin ?? '') . '/' . $prog . $suffix;
}

/** Fichier temporaire d'identifiants (format [client]) — à supprimer après usage. */
function sauv_defaults_file(): string {
    $tmp = tempnam(sys_get_temp_dir(), 'pc_');
    $host = defined('DB_HOST') ? DB_HOST : '127.0.0.1';
    $user = defined('DB_USER') ? DB_USER : 'root';
    $pass = defined('DB_PASS') ? DB_PASS : '';
    $ini = "[client]\nhost=" . sauv_q($host) . "\nuser=" . sauv_q($user) . "\npassword=" . sauv_q($pass) . "\n";
    file_put_contents($tmp, $ini);
    @chmod($tmp, 0600);
    return $tmp;
}
function sauv_q(string $v): string { return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $v) . '"'; }

/**
 * Lance une commande MySQL via proc_open (forme tableau = pas de shell).
 *
 * @param array  $cmd      Commande en tableau.
 * @param string $stdin    Données à écrire sur stdin ('' = rien).
 * @param string $stderr   Reçoit stderr (référence).
 * @return string          stdout.
 */
function sauv_run(array $cmd, string $stdin, string &$stderr): string {
    $stderr = '';
    $stdout = '';
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $proc = @proc_open($cmd, $descriptors, $pipes);
    if (!is_resource($proc)) { $stderr = 'proc_open échoué.'; return ''; }
    if ($stdin !== '') { fwrite($pipes[0], $stdin); }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    proc_close($proc);
    return $stdout;
}

/** Réalise un dump dans un fichier. Retourne [ok,msg,file?]. */
function sauv_make_backup(): array {
    $bin = sauv_bin();
    if (!$bin) return ['ok' => false, 'msg' => 'mysqldump introuvable. Windows : C:\xampp\mysql\bin — Linux : apt install mysql-client (ou définir MYSQL_BIN).'];
    $tmp = sauv_defaults_file();
    try {
        $name = 'pharmacare_' . date('Ymd_His') . '.sql';
        $path = sauv_dir() . '/' . $name;
        $cmd = [
            sauv_exe('mysqldump'),
            "--defaults-extra-file=" . $tmp,
            '--default-character-set=utf8mb4',
            '--single-transaction',
            '--quick',
            '--routines',
            '--triggers',
            '--events',
            '--no-tablespaces',
            defined('DB_NAME') ? DB_NAME : 'pharmacare',
        ];
        $stderr = '';
        $stdout = sauv_run($cmd, '', $stderr);
        // Un dump valide contient des CREATE TABLE ou des INSERT INTO.
        if ($stdout === '' || (!str_contains($stdout, 'CREATE TABLE') && !str_contains($stdout, 'INSERT INTO'))) {
            return ['ok' => false, 'msg' => 'Dump vide ou échoué : ' . trim($stderr)];
        }
        file_put_contents($path, $stdout);
        if (!is_file($path) || filesize($path) < 50) {
            return ['ok' => false, 'msg' => 'Échec écriture du fichier de sauvegarde.'];
        }
        return ['ok' => true, 'msg' => 'Sauvegarde créée : ' . $name . ' (' . round(filesize($path) / 1024) . ' Ko)', 'file' => $name];
    } finally {
        @unlink($tmp);
    }
}

/** Restore un fichier .sql dans la base. Retourne [ok,msg]. */
function sauv_restore(string $path): array {
    $bin = sauv_bin();
    if (!$bin) return ['ok' => false, 'msg' => 'mysql introuvable. Windows : C:\xampp\mysql\bin — Linux : apt install mysql-client.'];
    $sql = file_get_contents($path);
    if ($sql === false || $sql === '') return ['ok' => false, 'msg' => 'Fichier de sauvegarde vide ou illisible.'];
    $tmp = sauv_defaults_file();
    try {
        $cmd = [
            sauv_exe('mysql'),
            "--defaults-extra-file=" . $tmp,
            '--default-character-set=utf8mb4',
            defined('DB_NAME') ? DB_NAME : 'pharmacare',
        ];
        $stderr = '';
        sauv_run($cmd, $sql, $stderr);
        $err = trim($stderr);
        // Filtrer les avertissements non fatals (ex. "Using a password on the command line").
        $errLines = array_filter(explode("\n", $err), fn($l) => $l !== '' && !str_contains($l, 'Using a password'));
        if (!empty($errLines)) {
            return ['ok' => false, 'msg' => 'Restauration échouée : ' . implode(' | ', $errLines)];
        }
        return ['ok' => true, 'msg' => 'Base restaurée depuis ' . basename($path)];
    } finally {
        @unlink($tmp);
    }
}

/** Supprime une sauvegarde. */
function sauv_delete(string $path): array {
    if (@unlink($path)) return ['ok' => true, 'msg' => 'Sauvegarde supprimée : ' . basename($path)];
    return ['ok' => false, 'msg' => 'Suppression impossible.'];
}

/** Infos base (nb tables, taille Mo). */
function sauv_db_info(): array {
    try {
        $db = getDB();
        $name = defined('DB_NAME') ? DB_NAME : 'pharmacare';
        return $db->query("SELECT
            (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = " . $db->quote($name) . ") AS nb_tables,
            (SELECT ROUND(SUM(data_length+index_length)/1024/1024,2) FROM information_schema.tables WHERE table_schema = " . $db->quote($name) . ") AS size_mb
        ")->fetch() ?: ['nb_tables' => 0, 'size_mb' => 0];
    } catch (\Throwable $e) {
        return ['nb_tables' => '?', 'size_mb' => '?'];
    }
}