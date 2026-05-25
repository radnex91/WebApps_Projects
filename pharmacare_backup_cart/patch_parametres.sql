-- Patch : table des paramètres globaux (à importer après database.sql)
USE pharmacare;

CREATE TABLE IF NOT EXISTS parametres (
    cle     VARCHAR(60) PRIMARY KEY,
    valeur  TEXT NOT NULL,
    label   VARCHAR(120),
    groupe  VARCHAR(60) DEFAULT 'général'
);

INSERT INTO parametres (cle, valeur, label, groupe) VALUES
('devise',        'XAF',                  'Devise',               'général'),
('devise_symbole','FCFA',                 'Symbole devise',       'général'),
('devise_pos',    'after',                'Position symbole',     'général'),
('tva',           '19.25',               'Taux TVA (%)',         'général'),
('app_nom',       'PharmaCare',           'Nom de la pharmacie',  'général'),
('theme',         'dark-cyan',            'Thème couleur',        'apparence'),
('police',        'DM Sans',              'Police principale',    'apparence'),
('police_titre',  'Cormorant Garamond',   'Police titres',        'apparence')
ON DUPLICATE KEY UPDATE valeur=VALUES(valeur);
