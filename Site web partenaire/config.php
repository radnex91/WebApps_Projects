<?php
/**
 * Configuration MySQL et options.
 *
 * ─── Pourquoi le catalogue est vide en local ? ───
 * Ici, DB_HOST vaut souvent « localhost » : c’est le MySQL de la MACHINE À EXÉCUTER PHP.
 * - Sur l’hébergement : correct (MySQL du serveur).
 * - Sur votre PC : « localhost » = MySQL de XAMPP (ou rien) — PAS la base chez l’hébergeur.
 * Les identifiants u5… ne fonctionnent en général qu’avec le serveur MySQL concerné.
 *
 * Pour utiliser votre base EN LIGNE depuis le PC :
 * 1. Activer « MySQL distant » / « Remote MySQL » dans le panel hébergeur et autoriser votre IP.
 * 2. Copier config.local.example.php en config.local.php (ignoré par Git).
 * 3. Créer config.local.php avec le nom d’hôte indiqué pour les connexions DISTANTES
 *    dans le panel (souvent ≠ « localhost » affiché pour le site). Même user / base / mot de passe.
 */
$ESADISS_CONFIG = [
  'db_host'        => 'localhost',
  'db_port'        => 3306,
  'db_name'        => 'u526259173_partenaire',
  'db_user'        => 'u526259173_partenaire',
  'db_pass'        => 'MailPass96@@',
  'db_charset'     => 'utf8mb4',
  'whatsapp_phone' => '00237699951961',
];

if (is_readable(__DIR__ . '/config.local.php')) {
  $local = require __DIR__ . '/config.local.php';
  if (is_array($local)) {
    $ESADISS_CONFIG = array_replace($ESADISS_CONFIG, $local);
  }
}

define('DB_HOST', $ESADISS_CONFIG['db_host']);
define('DB_PORT', (int) ($ESADISS_CONFIG['db_port'] ?? 3306));
define('DB_NAME', $ESADISS_CONFIG['db_name']);
define('DB_USER', $ESADISS_CONFIG['db_user']);
define('DB_PASS', (string) $ESADISS_CONFIG['db_pass']);
define('DB_CHARSET', $ESADISS_CONFIG['db_charset']);
define('WHATSAPP_PHONE', (string) $ESADISS_CONFIG['whatsapp_phone']);
