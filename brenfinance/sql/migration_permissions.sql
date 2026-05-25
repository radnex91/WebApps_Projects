-- Migration : Mise à jour des permissions des rôles pour le contrôle d'accès
-- Corrige banque → tresorerie et ajoute les permissions manquantes pour chaque rôle

-- DAF : ajouter consulter sur engagements, tresorerie, comptabilite, audit
UPDATE roles SET permissions = '{"engagements": {"valider_daf": true, "consulter": true}, "budget": {"all": true}, "reporting": {"all": true}, "tresorerie": {"consulter": true}, "comptabilite": {"consulter": true}, "audit": {"consulter": true}}' WHERE nom = 'daf';

-- Comptable : corriger banque → tresorerie, ajouter operations_caisse, engagements.consulter, audit, referentiels
UPDATE roles SET permissions = '{"caisse": {"all": true}, "operations_caisse": {"all": true}, "tresorerie": {"all": true}, "comptabilite": {"all": true}, "engagements": {"valider_comptable": true, "consulter": true}, "audit": {"consulter": true}, "referentiels": {"consulter": true, "saisir": true}}' WHERE nom = 'comptable';

-- Caissier : ajouter operations_caisse
UPDATE roles SET permissions = '{"caisse": {"saisir": true, "consulter": true}, "operations_caisse": {"saisir": true, "consulter": true, "annuler": true}}' WHERE nom = 'caissier';

-- Valideur N1 : ajouter consulter sur engagements
UPDATE roles SET permissions = '{"engagements": {"valider_hierarchie": true, "consulter": true}}' WHERE nom = 'valideur_n1';