<?php
namespace Models;

use Core\Model;

class SyncQueue extends Model
{
    protected static string $table = 'sync_queue';
    protected array $fillable = ['table_name', 'action', 'record_id', 'offline_id', 'payload', 'status', 'attempts', 'error_message', 'processed_at'];
    protected array $casts = ['id' => 'int', 'record_id' => 'int', 'attempts' => 'int'];

    public static function enqueue(string $table, string $action, string $offlineId, array $payload, ?int $recordId = null): void
    {
        self::db()->insert('sync_queue', [
            'table_name' => $table, 'action' => $action, 'record_id' => $recordId,
            'offline_id' => $offlineId, 'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'status' => 'PENDING', 'attempts' => 0,
        ]);
    }

    public static function getPending(): array
    {
        return self::raw("SELECT * FROM sync_queue WHERE status = 'PENDING' ORDER BY created_at ASC LIMIT 50");
    }

    public static function getFailed(): array
    {
        return self::raw("SELECT * FROM sync_queue WHERE status = 'FAILED' ORDER BY created_at DESC LIMIT 20");
    }

    public static function countPending(): int
    {
        return (int) self::db()->fetch("SELECT COUNT(*) as cnt FROM sync_queue WHERE status = 'PENDING'")->cnt;
    }
}