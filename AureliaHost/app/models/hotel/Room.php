<?php
class Room extends Model
{
    protected string $table = 'rooms';

    public function allWithType(): array
    {
        return $this->query(
            "SELECT r.*, rt.nom as type_nom, rt.prix_base
             FROM rooms r
             LEFT JOIN room_types rt ON r.room_type_id = rt.id
             ORDER BY r.etage, r.numero"
        );
    }

    public function findWithType(int $id): ?array
    {
        return $this->queryOne(
            "SELECT r.*, rt.nom as type_nom, rt.prix_base, rt.capacite
             FROM rooms r
             LEFT JOIN room_types rt ON r.room_type_id = rt.id
             WHERE r.id = :id",
            ['id' => $id]
        );
    }

    public function getDisponibles(string $checkin = null, string $checkout = null): array
    {
        if (!$checkin || !$checkout) {
            return $this->all("statut = 'disponible'");
        }
        return $this->query(
            "SELECT r.*, rt.nom as type_nom, rt.prix_base
             FROM rooms r
             LEFT JOIN room_types rt ON r.room_type_id = rt.id
             WHERE r.statut = 'disponible'
             AND r.id NOT IN (
                 SELECT rr.room_id FROM reservation_rooms rr
                 JOIN reservations res ON rr.reservation_id = res.id
                 WHERE res.statut IN ('confirmee', 'en_cours')
                 AND res.date_checkin < :checkout
                 AND res.date_checkout > :checkin
             )
             ORDER BY r.etage, r.numero",
            ['checkin' => $checkin, 'checkout' => $checkout]
        );
    }
}
