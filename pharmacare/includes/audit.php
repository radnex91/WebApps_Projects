<?php
/**
 * PharmaCare — Journal d'audit
 * 
 * Enregistre les actions critiques dans la table audit_log.
 * Inclure ce fichier dans les modules sensibles pour tracer l'activité.
 */

/**
 * Enregistre un événement d'audit.
 *
 * @param string $action     Code de l'action (ex: 'vente.create', 'stock.adjust', 'auth.login', 'caisse.close')
 * @param string $details    Description lisible (ex: 'Vente VNT-2026-0042 : 3 articles, 45000 FCFA')
 * @param string $ip         Adresse IP (null = auto-détection)
 * @param int|null $targetId ID de l'objet cible (vente, produit, utilisateur…)
 * @param string|null $ref   Référence associée (facultatif)
 */
function auditLog(string $action, string $details = '', ?int $targetId = null, ?string $ref = null, ?string $ip = null): void {
    try {
        $db = getDB();
        $uid = $_SESSION['user_id'] ?? null;
        $ip  = $ip ?? ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');

        $stmt = $db->prepare("
            INSERT INTO audit_log (utilisateur_id, action, details, ip, target_id, reference)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$uid, $action, $details, $ip, $targetId, $ref ?: null]);
    } catch (\Throwable $e) {
        // Silencieux — ne jamais bloquer l'application pour un log
        error_log('PharmaCare Audit Error: ' . $e->getMessage());
    }
}
