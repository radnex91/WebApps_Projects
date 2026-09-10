<?php
declare(strict_types=1);
date_default_timezone_set('Africa/Douala');

/**
 * Chargement des paramètres depuis la BDD
 * Utilisé partout via getParam() / getAllParams()
 */

function getAllParams(bool $refresh = false): array {
    static $params = null;
    if ($params !== null && !$refresh) {
        return $params;
    }
    if ($refresh) {
        $params = null;
    }
    if ($params === null) {
        try {
            $db = getDB();
            $rows = $db->query("SELECT cle, valeur FROM parametres")->fetchAll();
            $params = array_column($rows, 'valeur', 'cle');
        } catch (Exception $e) {
            // Valeurs par défaut si la table n'existe pas encore
            $params = [
                'devise'            => 'XAF',
                'devise_symbole'    => 'FCFA',
                'devise_pos'        => 'after',
                'tva'               => '19.25',
                'app_nom'           => 'PharmaCare',
                'theme'             => 'dark-navy',
                'police'            => 'Manrope',
                'police_titre'      => 'Manrope',
                'pharmacie_adresse' => '',
                'pharmacie_telephone'=> '',
                'pharmacie_nif'     => '',
                'ticket_sous_titre' => 'Gestion Pharmacie',
                'ticket_pied'      => 'Merci pour votre achat !',
                'prefix_vente'       => 'VNT',
                'caisse_fermeture_mode'    => 'manuel',
                'caisse_heure_fermeture'   => '22:00',
                'assistant_active'         => '1',
            ];
        }
    }
    return $params;
}

function getParam(string $key, string $default = ''): string {
    return getAllParams()[$key] ?? $default;
}

/**
 * Logo de la PHARMACIE (propre au client, uploadé via Paramètres).
 * Distinct du logo de l'application PharmaCare (qui est propriétaire et fixe).
 * Retourne l'URL publique (cache-bustée) si un logo est défini et présent sur disque,
 * sinon chaîne vide. Utilisé sur les tickets imprimés et les bons.
 */
function pharmacieLogoUrl(): string {
    $rel = getParam('pharmacie_logo', '');
    if ($rel === '') return '';
    $file = __DIR__ . '/../' . $rel;
    if (!is_file($file)) return '';
    $base = defined('APP_URL') ? APP_URL : '';
    $v    = defined('APP_VERSION') ? APP_VERSION : '1';
    return $base . '/' . $rel . '?v=' . rawurlencode($v);
}

/**
 * Chemin absolu du logo pharmacie sur disque (pour validation upload / suppression).
 */
function pharmacieLogoPath(): string {
    $rel = getParam('pharmacie_logo', '');
    return $rel === '' ? '' : __DIR__ . '/../' . $rel;
}

/**
 * Invalide le cache statique de getAllParams().
 * À appeler après une écriture directe dans parametres (ex: licence_apply_code)
 * pour que getParam() reflète la nouvelle valeur dans la même requête.
 */
function paramCacheClear(): void {
    getAllParams(true);
}

/**
 * Module fidélité / « client fidèle » — activé via le paramètre 'fidelite_active'.
 * Désactivé par défaut. Pour réactiver partout dans l'app :
 *   INSERT INTO parametres (cle, valeur) VALUES ('fidelite_active', '1')
 *     ON DUPLICATE KEY UPDATE valeur='1';
 */
function fideliteActive(): bool {
    return getParam('fidelite_active', '0') === '1';
}

/**
 * Formatage monétaire selon la devise configurée
 */
function fmtMoney(float $n): string {
    $p   = getAllParams();
    $sym = $p['devise_symbole'] ?? 'FCFA';
    $pos = $p['devise_pos']     ?? 'after';
    $formatted = number_format($n, 0, ',', ' ');   // FCFA = 0 décimales
    return $pos === 'before' ? "$sym $formatted" : "$formatted $sym";
}

/**
 * Thèmes disponibles : [id => [label, --teal, --gold, --red, --blue, --bg]]
 */
function getThemes(): array {
    return [
        'dark-navy'    => ['Bleu Marine',   '#06d6a0','#f59e0b','#ef4444','#818cf8','#080c15'],
        'dark-rose'    => ['Rose Poudré',    '#cc6f7f','#a1ae9d','#ef4444','#d4a574','#0f0c0a'],
    ];
}

/**
 * Polices disponibles
 */
function getPolices(): array {
    return [
        'Manrope' => 'Manrope',
    ];
}

function getPolicesTitres(): array {
    return [
        'Manrope' => 'Manrope',
    ];
}

/**
 * Devises Afrique Centrale et courantes
 */
function getDevises(): array {
    return [
        // Afrique Centrale & CEMAC
        'XAF' => ['FCFA',  'after',  'Franc CFA BEAC (FCFA) — Cameroun, Gabon, Congo, RCA, Tchad, Guinée Équatoriale'],
        'CDF' => ['FC',    'after',  'Franc Congolais (FC) — RD Congo'],
        'AOA' => ['Kz',    'after',  'Kwanza (Kz) — Angola'],
        'XOF' => ['FCFA',  'after',  'Franc CFA BCEAO (FCFA) — Sénégal, Côte d\'Ivoire, Mali…'],
        'GNF' => ['FG',    'after',  'Franc Guinéen (FG)'],
        // Autres courantes
        'DZD' => ['DA',    'after',  'Dinar Algérien (DA)'],
        'MAD' => ['DH',    'after',  'Dirham Marocain (DH)'],
        'TND' => ['DT',    'after',  'Dinar Tunisien (DT)'],
        'EUR' => ['€',     'after',  'Euro (€)'],
        'USD' => ['$',     'before', 'Dollar US ($)'],
    ];
}