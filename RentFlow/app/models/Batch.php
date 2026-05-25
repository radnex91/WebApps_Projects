<?php
class Batch extends Model {
    protected $table = 'batches';
    protected $allowedFields = ['name', 'landlord_id', 'agency_id', 'monthly_price'];

    public function getAllPaginated($limit, $offset) {
        $sql = "SELECT b.*,
                l.name as landlord_name,
                a.name as agency_name
                FROM batches b
                LEFT JOIN landlords l ON b.landlord_id = l.id
                LEFT JOIN agencies a ON b.agency_id = a.id
                ORDER BY b.id DESC
                LIMIT ? OFFSET ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([(int)$limit, (int)$offset]);
        return $stmt->fetchAll();
    }

    public function getAllWithDetails() {
        $sql = "SELECT b.*, l.name as landlord_name, a.name as agency_name
                FROM batches b
                LEFT JOIN landlords l ON b.landlord_id = l.id
                LEFT JOIN agencies a ON b.agency_id = a.id
                ORDER BY b.id DESC";
        return $this->db->query($sql)->fetchAll();
    }

    public function getByLandlord($landlordId) {
        $stmt = $this->db->prepare("SELECT * FROM batches WHERE landlord_id = ?");
        $stmt->execute([$landlordId]);
        return $stmt->fetchAll();
    }

    public function countByAgency($agencyId) {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM batches WHERE agency_id = ?");
        $stmt->execute([$agencyId]);
        return (int)$stmt->fetchColumn();
    }

    public function getByAgency($agencyId) {
        $stmt = $this->db->prepare("SELECT * FROM batches WHERE agency_id = ?");
        $stmt->execute([$agencyId]);
        return $stmt->fetchAll();
    }

    public function getByAgencyAndLandlord($agencyId, $landlordId) {
        $sql = "SELECT b.*, l.name as landlord_name, a.name as agency_name
                FROM batches b
                LEFT JOIN landlords l ON b.landlord_id = l.id
                LEFT JOIN agencies a ON b.agency_id = a.id
                WHERE b.agency_id = ? AND b.landlord_id = ?
                ORDER BY b.name";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$agencyId, $landlordId]);
        return $stmt->fetchAll();
    }
}
