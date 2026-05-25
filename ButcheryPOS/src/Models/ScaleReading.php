<?php
namespace App\Models;

class ScaleReading extends BaseModel
{
    protected string $table = 'scale_readings';
    protected array $fillable = ['weight_value', 'unit', 'device_code', 'raw_payload', 'pos_session_id'];

    public function getLatest(): ?array
    {
        return $this->repo->query(
            "SELECT * FROM scale_readings ORDER BY captured_at DESC LIMIT 1"
        )[0] ?? null;
    }
}