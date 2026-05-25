<?php
// Generate password hash for admin
$hash = password_hash('admin123', PASSWORD_DEFAULT);
echo "Hash: " . $hash . "\n";
echo "SQL: INSERT INTO utilisateurs (login, mot_de_passe, nom_utilisateur, prenom_utilisateur, role, statut) VALUES ('admin', '$hash', 'Administrateur', 'Systraco', 'Administrateur', 'Actif');\n";