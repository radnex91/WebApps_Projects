<?php
class Housekeeping extends Model
{
    protected string $table = 'housekeeping';

    public function allWithDetails(): array
    {
        return $this->query(
            "SELECT h.*, r.numero, u.nom as user_nom, u.prenom as user_prenom
             FROM housekeeping h
             JOIN rooms r ON h.room_id = r.id
             LEFT JOIN users u ON h.user_id = u.id
             ORDER BY h.date_nettoyage DESC, r.numero"
        );
    }
}
