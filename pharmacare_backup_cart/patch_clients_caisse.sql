-- patch_clients_caisse.sql
-- Ajoute 'crédit' au moyen de mouvements_caisse pour traçabilité des ventes à crédit
-- Exécuter avec : mysql -u root pharmacare --default-character-set=utf8mb4 < patch_clients_caisse.sql

ALTER TABLE mouvements_caisse MODIFY COLUMN moyen ENUM('espèces','carte','chèque','assurance','crédit') NOT NULL DEFAULT 'espèces';
