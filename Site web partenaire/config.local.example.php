<?php
/**
 * Développement local en utilisant la base chez l’hébergeur (ex. Hostinger).
 *
 * Copier ce fichier en : config.local.php
 *
 * IMPORTANT — db_host :
 * - Activer « Remote MySQL » + autoriser votre IP ne suffit PAS si PHP tourne sur votre PC
 *   et que db_host reste « localhost » : localhost = MySQL sur VOTRE machine.
 * - Mettre l’hôte indiqué pour les connexions EXTERNES (hPanel → Bases MySQL, onglet / détail
 *   « Accès distant »). Souvent du type srvXXXX.hstgr.io ou une adresse IP, pas « localhost ».
 *
 * Si la connexion échoue encore : revérifier votre IP publique (elle peut changer), le port 3306
 * sortant, et le message d’erreur complet renvoyé par api.php.
 *
 * config.local.php est ignoré par Git (.gitignore).
 */
return [
  'db_host'         => 'srvXXXX.hstgr.io',
  'db_port'         => 3306,
  'db_name'         => 'u526259173_partenaire',
  'db_user'         => 'u526259173_partenaire',
  'db_pass'         => 'votre_mot_de_passe',
  'db_charset'      => 'utf8mb4',
  'whatsapp_phone'  => '',
];
