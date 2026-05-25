<?php
namespace Models;

use Core\Model;

class AuditLog extends Model
{
    protected static string $table = 'audit_logs';
    protected array $fillable = ['user_id', 'action', 'entity_type', 'entity_id', 'old_values', 'new_values', 'ip_address', 'user_agent'];

    public static function log(int $userId, string $action, string $entityType, ?int $entityId = null, ?array $oldValues = null, ?array $newValues = null): void
    {
        self::db()->insert('audit_logs', [
            'user_id' => $userId, 'action' => $action, 'entity_type' => $entityType,
            'entity_id' => $entityId, 'old_values' => $oldValues ? json_encode($oldValues) : null,
            'new_values' => $newValues ? json_encode($newValues) : null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);
    }

    public static function getRecent(int $limit = 50): array
    {
        return self::raw(
            "SELECT a.*, u.nom as user_nom FROM audit_logs a
            LEFT JOIN utilisateurs u ON a.user_id = u.id
            ORDER BY a.created_at DESC LIMIT ?", [$limit]
        );
    }
}