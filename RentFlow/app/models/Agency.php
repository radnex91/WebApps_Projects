<?php
class Agency extends Model {
    protected $table = 'agencies';
    protected $allowedFields = ['name', 'contact'];

    public function getAllPaginated($limit, $offset) {
        $sql = "SELECT a.*,
                (SELECT COUNT(*) FROM batches b WHERE b.agency_id = a.id) as batches_count
                FROM agencies a
                ORDER BY a.id DESC
                LIMIT ? OFFSET ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([(int)$limit, (int)$offset]);
        return $stmt->fetchAll();
    }

    public function getWithBatchesCount() {
        $sql = "SELECT a.*, COUNT(b.id) as batches_count
                FROM agencies a
                LEFT JOIN batches b ON a.id = b.agency_id
                GROUP BY a.id
                ORDER BY a.id DESC";
        return $this->db->query($sql)->fetchAll();
    }

    public function getLandlordsByAgency($agencyId) {
        $sql = "SELECT DISTINCT l.* 
                FROM landlords l
                INNER JOIN batches b ON l.id = b.landlord_id
                WHERE b.agency_id = ?
                ORDER BY l.name";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$agencyId]);
        return $stmt->fetchAll();
    }

    // New method: get all agencies ordered by name
    public function getAllOrderedByName() {
        return $this->db->query("SELECT * FROM agencies ORDER BY name ASC")->fetchAll();
    }
}
