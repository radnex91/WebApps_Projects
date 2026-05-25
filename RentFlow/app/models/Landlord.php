<?php
class Landlord extends Model {
    protected $table = 'landlords';
    protected $allowedFields = ['name', 'contact', 'contract_details'];

    public function getAllPaginated($limit, $offset) {
        $sql = "SELECT l.*,
                COUNT(DISTINCT b.id) as batches_count,
                GROUP_CONCAT(DISTINCT a.name ORDER BY a.name ASC SEPARATOR ', ') as agency_names
                FROM landlords l
                LEFT JOIN batches b ON l.id = b.landlord_id
                LEFT JOIN agencies a ON b.agency_id = a.id
                GROUP BY l.id
                ORDER BY l.id DESC
                LIMIT ? OFFSET ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([(int)$limit, (int)$offset]);
        return $stmt->fetchAll();
    }

    public function search($keyword) {
        $stmt = $this->db->prepare("SELECT * FROM landlords WHERE name LIKE ? OR contact LIKE ? ORDER BY id DESC");
        $keyword = "%$keyword%";
        $stmt->execute([$keyword, $keyword]);
        return $stmt->fetchAll();
    }

    public function getWithStats() {
        $sql = "SELECT l.*,
                COUNT(DISTINCT b.id) as batches_count,
                COUNT(DISTINCT p.id) as payments_count,
                COALESCE(SUM(p.amount), 0) as total_rent
                FROM landlords l
                LEFT JOIN batches b ON l.id = b.landlord_id
                LEFT JOIN payments p ON b.id = p.batch_id
                GROUP BY l.id
                ORDER BY l.id DESC";
        return $this->db->query($sql)->fetchAll();
    }
}
