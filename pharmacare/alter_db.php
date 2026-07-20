<?php
require_once 'config/database.php';
try {
    $db = getDB();
    $db->exec("ALTER TABLE ventes ADD COLUMN est_annulee TINYINT(1) DEFAULT 0");
    echo "Column est_annulee added successfully.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

// ── Paramètres de fermeture caisse ──────────────────────────
try {
    $db->exec("INSERT IGNORE INTO parametres (cle, valeur, label, groupe) VALUES
        ('caisse_fermeture_mode', 'manuel', 'Mode fermeture caisse', 'caisse'),
        ('caisse_heure_fermeture', '22:00', 'Heure fermeture auto', 'caisse')");
    echo "<br>Paramètres caisse ajoutés.";
} catch (Exception $e) {
    echo "<br>Paramètres caisse: " . $e->getMessage();
}

// ── Module Retours caisse : tables + permission ─────────────
try {
    $db->exec("CREATE TABLE IF NOT EXISTS retours_vente (
        id                INT AUTO_INCREMENT PRIMARY KEY,
        reference         VARCHAR(20) UNIQUE NOT NULL,
        vente_id          INT NOT NULL,
        utilisateur_id    INT,
        date_retour       DATE NOT NULL,
        montant_ht        DECIMAL(10,2) DEFAULT 0,
        montant_tva       DECIMAL(10,2) DEFAULT 0,
        montant_total     DECIMAL(10,2) DEFAULT 0,
        cout_achat_total  DECIMAL(10,2) DEFAULT 0,
        mode_remboursement ENUM('espèces','carte','chèque','assurance','crédit') DEFAULT 'espèces',
        note              VARCHAR(255) DEFAULT NULL,
        created_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (vente_id) REFERENCES ventes(id),
        FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE SET NULL
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS retour_vente_lignes (
        id              INT AUTO_INCREMENT PRIMARY KEY,
        retour_id       INT NOT NULL,
        vente_ligne_id  INT,
        produit_id      INT,
        produit_nom     VARCHAR(200),
        quantite        INT NOT NULL,
        prix_unitaire   DECIMAL(10,2) NOT NULL,
        tva             DECIMAL(5,2) DEFAULT 0,
        total_ligne     DECIMAL(10,2) NOT NULL,
        cout_achat      DECIMAL(10,2) DEFAULT 0,
        FOREIGN KEY (retour_id) REFERENCES retours_vente(id) ON DELETE CASCADE,
        FOREIGN KEY (produit_id) REFERENCES produits(id) ON DELETE SET NULL
    )");
    $db->exec("INSERT IGNORE INTO permissions (code, libelle, module)
               VALUES ('retours.gerer','Gérer les retours de ventes','retours')");
    // Admin hérite automatiquement (toutes permissions) ; Pharmacien (2) + Caissier (3)
    $db->exec("INSERT IGNORE INTO role_permissions (role_id, permission_id)
               SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code='retours.gerer'
               WHERE r.id IN (2,3)");
    echo "<br>Module Retours caisse : tables + permission ajoutées.";
} catch (Exception $e) {
    echo "<br>Module Retours caisse: " . $e->getMessage();
}

// ── Module Réduction (remise %) : colonnes ventes + table codes_remise + 7119 ─
// Colonnes ventes (try/catch — non idempotent nativement, doublon ignoré)
foreach ([
    "remise_pct     DECIMAL(5,2) DEFAULT 0"   => 'remise_pct',
    "remise_montant DECIMAL(10,2) DEFAULT 0"  => 'remise_montant',
    "autorise_par   INT NULL"                 => 'autorise_par',
] as $ddl => $col) {
    try {
        $db->exec("ALTER TABLE ventes ADD COLUMN $ddl");
        echo "<br>Colonne ventes.$col ajoutée.";
    } catch (Exception $e) {
        echo "<br>Colonne ventes.$col: " . $e->getMessage();
    }
}
// FK autorise_par → utilisateurs (ignoré si déjà existe)
try {
    $db->exec("ALTER TABLE ventes ADD CONSTRAINT fk_ventes_autorise_par
               FOREIGN KEY (autorise_par) REFERENCES utilisateurs(id) ON DELETE SET NULL");
    echo "<br>FK ventes.autorise_par créé.";
} catch (Exception $e) {
    echo "<br>FK ventes.autorise_par: " . $e->getMessage();
}

try {
    $db->exec("CREATE TABLE IF NOT EXISTS codes_remise (
        id              INT AUTO_INCREMENT PRIMARY KEY,
        code            VARCHAR(10) UNIQUE NOT NULL,
        created_by      INT NOT NULL,
        created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
        expires_at      DATETIME NOT NULL,
        used            TINYINT(1) DEFAULT 0,
        used_at         DATETIME NULL,
        used_vente_id   INT NULL,
        used_remise_pct DECIMAL(5,2) NULL,
        FOREIGN KEY (created_by) REFERENCES utilisateurs(id),
        FOREIGN KEY (used_vente_id) REFERENCES ventes(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->exec("INSERT IGNORE INTO plan_comptable (compte, intitule, classe, nature)
               VALUES ('7119','Rabais, remises et ristournes accordés',7,'debit')");
    $db->exec("INSERT IGNORE INTO permissions (code, libelle, module)
               VALUES ('remise.approuver','Approuver une remise (générer un code)','vente')");
    // Approuveurs : Admin (1), Pharmacien (2) et Manager (4) — le caissier (3) saisit les codes, ne les génère pas
    $db->exec("INSERT IGNORE INTO role_permissions (role_id, permission_id)
               SELECT r.id, p.id FROM permissions p
               JOIN roles r ON r.id IN (1,2,4) WHERE p.code='remise.approuver'");
    $db->exec("INSERT IGNORE INTO parametres (cle, valeur, label, groupe) VALUES
        ('remise_code_ttl_min', '15', 'Validité code remise (min)', 'ventes'),
        ('remise_max_pct', '100', 'Remise max (%)', 'ventes')");
    echo "<br>Module Réduction : colonnes + codes_remise + 7119 + permission ajoutés.";
} catch (Exception $e) {
    echo "<br>Module Réduction: " . $e->getMessage();
}

// ── Approbateurs de remise gérés par l'admin ─────────────────
try {
    $db->exec("CREATE TABLE IF NOT EXISTS remise_approbateurs (
        id             INT AUTO_INCREMENT PRIMARY KEY,
        utilisateur_id INT NOT NULL,
        actif          TINYINT(1) DEFAULT 1,
        added_by       INT NULL,
        created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_utilisateur (utilisateur_id),
        FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->exec("INSERT IGNORE INTO permissions (code, libelle, module)
               VALUES ('remise.approbateurs.gerer','Gérer la liste des approbateurs de remise','remise')");
    // Gestion de la liste : Admin (1) et Manager (4)
    $db->exec("INSERT IGNORE INTO role_permissions (role_id, permission_id)
               SELECT r.id, p.id FROM permissions p
               JOIN roles r ON r.id IN (1,4) WHERE p.code='remise.approbateurs.gerer'");
    // Seed initial : les détenteurs actuels de remise.approuver deviennent approbateurs (comportement préservé)
    $db->exec("INSERT IGNORE INTO remise_approbateurs (utilisateur_id, added_by)
               SELECT u.id, 1 FROM utilisateurs u
               JOIN role_permissions rp ON rp.role_id = u.role_id
               JOIN permissions p ON p.id = rp.permission_id
               WHERE p.code = 'remise.approuver' AND u.actif = 1");
    echo "<br>Module Approbateurs de remise : table + permission + seed initial ajoutés.";
} catch (Exception $e) {
    echo "<br>Module Approbateurs de remise: " . $e->getMessage();
}

// ── Noms de clients en MAJUSCULE (règle : jamais de minuscule) ──
try {
    $rows = $db->query("SELECT id, nom FROM clients")->fetchAll();
    $up = $db->prepare("UPDATE clients SET nom = ? WHERE id = ?");
    $n = 0;
    foreach ($rows as $r) {
        $maj = mb_strtoupper(trim((string)$r['nom']), 'UTF-8');
        if ($maj !== (string)$r['nom']) {
            $up->execute([$maj, (int)$r['id']]);
            $n++;
        }
    }
    echo "<br>Noms de clients mis en majuscule : $n ligne(s) normalisée(s).";
} catch (Exception $e) {
    echo "<br>Noms clients majuscule: " . $e->getMessage();
}

// ── Compteurs de numérotation (séquence atomique) ───────────
try {
    $db->exec("CREATE TABLE IF NOT EXISTS compteurs_ref (
        prefix   VARCHAR(8) NOT NULL,
        annee    SMALLINT NOT NULL,
        compteur INT NOT NULL DEFAULT 0,
        PRIMARY KEY (prefix, annee)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Backfill : initialise les compteurs de l'année courante à partir du MAX
    // existant, pour ne pas recommencer à 0001. (prefix => table source)
    foreach (['VNT' => 'ventes', 'CMD' => 'commandes', 'TRF' => 'transferts_magasin'] as $pfx => $tbl) {
        $db->exec("INSERT INTO compteurs_ref (prefix, annee, compteur)
                   SELECT '$pfx', YEAR(NOW()),
                          COALESCE(MAX(CAST(SUBSTRING_INDEX(reference,'-',-1) AS UNSIGNED)), 0)
                   FROM `$tbl`
                   WHERE reference LIKE CONCAT('$pfx','-',YEAR(NOW()),'-%')
                   ON DUPLICATE KEY UPDATE compteur = GREATEST(compteur, VALUES(compteur))");
    }
    echo "<br>Compteurs de numérotation : table créée + backfill effectué.";
} catch (Exception $e) {
    echo "<br>Compteurs de numérotation: " . $e->getMessage();
}
?>