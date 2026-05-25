<?php
namespace App\Models;

class PosSession extends BaseModel
{
    protected string $table = 'pos_sessions';
    protected array $fillable = ['session_token', 'user_id', 'terminal_name', 'opened_at', 'opening_cash', 'closing_cash', 'status'];

    public function findOpenByUser(int $userId): ?array
    {
        return $this->repo->query(
            "SELECT * FROM pos_sessions WHERE user_id = :uid AND status = 'open' LIMIT 1",
            ['uid' => $userId]
        )[0] ?? null;
    }

    public function findByToken(string $token): ?array
    {
        return $this->repo->findBy($this->table, 'session_token', $token);
    }
}