-- Migration v2 : Réorganisation des permissions
-- Ajout : radnex, decharge, engagements.executer, operations_caisse pour daf
-- Ajout : budget.consulter pour comptable (dc)
-- Ajout : engagements.consulter pour caissier
-- Mise à jour des rôles personnalisés existants (dc, Directeur Hierarchique, dg, dsl, de)
-- Date : 2026-04-29

-- Rôles standards
UPDATE roles SET permissions = '{"engagements": {"valider_daf": true, "consulter": true, "executer": true}, "budget": {"all": true}, "reporting": {"all": true}, "tresorerie": {"consulter": true}, "comptabilite": {"consulter": true}, "audit": {"consulter": true}, "ordre_mission": {"valider_daf": true, "consulter": true}, "operations_caisse": {"consulter": true}, "radnex": {"consulter": true}, "decharge": {"all": true}}' WHERE nom = 'daf';

UPDATE roles SET permissions = '{"caisse": {"saisir": true, "consulter": true}, "operations_caisse": {"saisir": true, "consulter": true, "annuler": true}, "ordre_mission": {"executer": true}, "radnex": {"consulter": true}, "decharge": {"executer": true, "consulter": true}, "engagements": {"consulter": true}}' WHERE nom = 'caissier';

UPDATE roles SET permissions = '{"engagements": {"creer": true, "consulter_propres": true}, "ordre_mission": {"creer": true, "consulter_propres": true}, "radnex": {"consulter": true}, "decharge": {"creer": true, "consulter": true}}' WHERE nom = 'demandeur';

-- Rôles personnalisés existants (ajout radnex + decharge)
UPDATE roles SET permissions = '{"caisse":{"consulter":true},"engagements":{"valider_hierarchie":true,"valider_comptable":true},"comptabilite":{"all":true},"budget":{"consulter":true},"radnex":{"consulter":true},"decharge":{"valider":true,"consulter":true}}' WHERE nom = 'dc';

UPDATE roles SET permissions = '{"engagements":{"valider_hierarchie":true},"radnex":{"consulter":true},"decharge":{"creer":true,"consulter":true}}' WHERE nom = 'Directeur Hierarchique';

UPDATE roles SET permissions = '{"caisse":{"consulter":true},"operations_caisse":{"consulter":true},"tresorerie":{"consulter":true},"engagements":{"consulter":true,"valider_hierarchie":true},"comptabilite":{"consulter":true},"budget":{"consulter":true,"valider":true},"reporting":{"consulter":true},"audit":{"consulter":true},"radnex":{"consulter":true},"decharge":{"all":true}}' WHERE nom = 'dg';

UPDATE roles SET permissions = '{"engagements":{"consulter":true,"valider_hierarchie":true},"radnex":{"consulter":true},"decharge":{"creer":true,"consulter":true}}' WHERE nom = 'dsl';

UPDATE roles SET permissions = '{"engagements":{"creer":true,"consulter":true,"consulter_propres":true,"valider_hierarchie":true},"radnex":{"consulter":true},"decharge":{"creer":true,"consulter":true}}' WHERE nom = 'de';