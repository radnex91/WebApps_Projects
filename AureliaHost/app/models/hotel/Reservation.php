<?php
class Reservation extends Model
{
    protected string $table = 'reservations';

    public function allWithDetails(): array
    {
        return $this->query(
            "SELECT r.*, c.nom as client_nom, c.prenom as client_prenom,
                    u.nom as user_nom, u.prenom as user_prenom
             FROM reservations r
             JOIN clients c ON r.client_id = c.id
             LEFT JOIN users u ON r.user_id = u.id
             ORDER BY r.date_checkin DESC"
        );
    }

    public function findWithDetails(int $id): ?array
    {
        return $this->queryOne(
            "SELECT r.*, c.nom as client_nom, c.prenom as client_prenom, c.telephone as client_tel, c.email as client_email,
                    u.nom as user_nom, u.prenom as user_prenom
             FROM reservations r
             JOIN clients c ON r.client_id = c.id
             LEFT JOIN users u ON r.user_id = u.id
             WHERE r.id = :id",
            ['id' => $id]
        );
    }

    public function getRooms(int $reservationId): array
    {
        return $this->query(
            "SELECT rr.*, r.numero, rt.nom as type_nom
             FROM reservation_rooms rr
             JOIN rooms r ON rr.room_id = r.id
             LEFT JOIN room_types rt ON r.room_type_id = rt.id
             WHERE rr.reservation_id = :rid",
            ['rid' => $reservationId]
        );
    }

    public function getServices(int $reservationId): array
    {
        return $this->query(
            "SELECT rs.*, s.nom, s.categorie
             FROM reservation_services rs
             JOIN services s ON rs.service_id = s.id
             WHERE rs.reservation_id = :rid",
            ['rid' => $reservationId]
        );
    }

    public function getForCalendar(string $month = null): array
    {
        $month = $month ?? date('Y-m');
        return $this->query(
            "SELECT r.*, c.nom as client_nom, c.prenom as client_prenom
             FROM reservations r
             JOIN clients c ON r.client_id = c.id
             WHERE r.statut IN ('confirmee', 'en_cours')
             AND DATE_FORMAT(r.date_checkin, '%Y-%m') = :m
             ORDER BY r.date_checkin",
            ['m' => $month]
        );
    }
}
