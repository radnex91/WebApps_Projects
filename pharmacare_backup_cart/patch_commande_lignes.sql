-- Patch: ajout de la table commande_lignes pour les lignes de commande
ALTER TABLE commandes DROP COLUMN montant_total;

CREATE TABLE commande_lignes (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    commande_id   INT NOT NULL,
    produit_id    INT,
    designation   VARCHAR(200) NOT NULL,
    quantite      INT DEFAULT 1,
    prix_unitaire DECIMAL(10,2) DEFAULT 0,
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (commande_id) REFERENCES commandes(id) ON DELETE CASCADE,
    FOREIGN KEY (produit_id)  REFERENCES produits(id) ON DELETE SET NULL
);

-- Mettre à jour la vue: le montant total est calculé dynamiquement depuis les lignes
-- Les commandes existantes sans lignes auront un montant de 0