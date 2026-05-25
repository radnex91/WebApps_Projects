<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';

$agences = [
    ['YAG', 'Yagoua', 'Yagoua', 'Centre Ville', '+237 000 000 001'],
    ['KLF', 'Kalfou', 'Kalfou', 'Centre Ville', '+237 000 000 002'],
    ['GDG', 'Guidiguis', 'Guidiguis', 'Centre Ville', '+237 000 000 003'],
    ['KLE', 'Kaélé', 'Kaélé', 'Centre Ville', '+237 000 000 004'],
    ['MR1', 'Maroua 1', 'Maroua 1', 'Centre Ville', '+237 000 000 005'],
    ['MR2', 'Maroua 2', 'Maroua 2', 'Centre Ville', '+237 000 000 006'],
    ['MKL', 'Mokolo', 'Mokolo', 'Centre Ville', '+237 000 000 007'],
    ['GAR', 'Garoua', 'Garoua', 'Centre Ville', '+237 000 000 008'],
    ['NGA', 'Ngaoundéré', 'Ngaoundéré', 'Centre Ville', '+237 000 000 009'],
    ['MEI', 'Meiganga', 'Meiganga', 'Centre Ville', '+237 000 000 010'],
    ['GBL', 'Garoua Boulai', 'Garoua Boulai', 'Centre Ville', '+237 000 000 011'],
    ['BER', 'Bertoua', 'Bertoua', 'Centre Ville', '+237 000 000 012'],
    ['YDE', 'Yaoundé', 'Yaoundé', 'Centre Ville', '+237 000 000 013'],
    ['DLA', 'Douala', 'Douala', 'Centre Ville', '+237 000 000 014'],
];

Database::execute("SET FOREIGN_KEY_CHECKS = 0");
Database::execute("DELETE FROM agences");
Database::execute("SET FOREIGN_KEY_CHECKS = 1");

$stmt = Database::getInstance()->prepare("INSERT INTO agences (code, nom, ville, adresse, telephone, actif) VALUES (?, ?, ?, ?, ?, 1)");
foreach ($agences as $a) {
    $stmt->execute($a);
}

echo "Agences mises à jour: " . count($agences);
