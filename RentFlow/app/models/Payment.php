<?php
class Payment extends Model {
    protected $table = 'payments';
    protected $allowedFields = ['batch_id', 'amount', 'due_date', 'paid_date', 'status'];

    public function getAllPaginated($limit, $offset) {
        $sql = "SELECT p.*, b.name as batch_name, l.name as landlord_name, a.name as agency_name,
                CASE
                    WHEN p.paid_date < p.due_date THEN 'EARLY'
                    WHEN p.paid_date = p.due_date THEN 'ON_TIME'
                    WHEN p.paid_date > p.due_date THEN 'LATE'
                    ELSE 'PENDING'
                END as payment_status
                FROM payments p
                LEFT JOIN batches b ON p.batch_id = b.id
                LEFT JOIN landlords l ON b.landlord_id = l.id
                LEFT JOIN agencies a ON b.agency_id = a.id
                ORDER BY p.id DESC
                LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
        return $this->db->query($sql)->fetchAll();
    }

    public function getAllWithDetails() {
        $sql = "SELECT p.*, b.name as batch_name, l.name as landlord_name, a.name as agency_name,
                CASE
                    WHEN p.paid_date < p.due_date THEN 'EARLY'
                    WHEN p.paid_date = p.due_date THEN 'ON_TIME'
                    WHEN p.paid_date > p.due_date THEN 'LATE'
                    ELSE 'PENDING'
                END as payment_status
                FROM payments p
                LEFT JOIN batches b ON p.batch_id = b.id
                LEFT JOIN landlords l ON b.landlord_id = l.id
                LEFT JOIN agencies a ON b.agency_id = a.id
                ORDER BY p.id DESC";
        return $this->db->query($sql)->fetchAll();
    }

    public function createWithStatus($data) {
        $data['status'] = $this->calculateStatus($data['due_date'] ?? null, $data['paid_date'] ?? null);
        $data = $this->filterAllowedFields($data);

        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO {$this->table} ($columns) VALUES ($placeholders)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_values($data));
        return $this->db->lastInsertId();
    }

    public function updateWithStatus($id, $data) {
        if (isset($data['due_date']) && isset($data['paid_date'])) {
            $data['status'] = $this->calculateStatus($data['due_date'], $data['paid_date']);
        }
        return $this->update($id, $data);
    }

    private function calculateStatus($dueDate, $paidDate) {
        if (empty($paidDate)) return 'PENDING';
        if ($paidDate < $dueDate) return 'EARLY';
        if ($paidDate == $dueDate) return 'ON_TIME';
        return 'LATE';
    }

    public function getLatePayments() {
        $sql = "SELECT p.*, b.name as batch_name, l.name as landlord_name, a.name as agency_name
                FROM payments p
                LEFT JOIN batches b ON p.batch_id = b.id
                LEFT JOIN landlords l ON b.landlord_id = l.id
                LEFT JOIN agencies a ON b.agency_id = a.id
                WHERE p.paid_date > p.due_date OR (p.paid_date IS NULL AND p.due_date < CURDATE())
                ORDER BY p.due_date ASC";
        return $this->db->query($sql)->fetchAll();
    }

    public function getLatePaymentsPaginated($limit, $offset) {
        $sql = "SELECT p.*, b.name as batch_name, l.name as landlord_name, a.name as agency_name
                FROM payments p
                LEFT JOIN batches b ON p.batch_id = b.id
                LEFT JOIN landlords l ON b.landlord_id = l.id
                LEFT JOIN agencies a ON b.agency_id = a.id
                WHERE p.paid_date > p.due_date OR (p.paid_date IS NULL AND p.due_date < CURDATE())
                ORDER BY p.due_date ASC
                LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
        return $this->db->query($sql)->fetchAll();
    }

    public function getEarlyPayments() {
        $sql = "SELECT p.*, b.name as batch_name, l.name as landlord_name, a.name as agency_name
                FROM payments p
                LEFT JOIN batches b ON p.batch_id = b.id
                LEFT JOIN landlords l ON b.landlord_id = l.id
                LEFT JOIN agencies a ON b.agency_id = a.id
                WHERE p.paid_date < p.due_date
                ORDER BY p.paid_date DESC";
        return $this->db->query($sql)->fetchAll();
    }

    public function getEarlyPaymentsPaginated($limit, $offset) {
        $sql = "SELECT p.*, b.name as batch_name, l.name as landlord_name, a.name as agency_name
                FROM payments p
                LEFT JOIN batches b ON p.batch_id = b.id
                LEFT JOIN landlords l ON b.landlord_id = l.id
                LEFT JOIN agencies a ON b.agency_id = a.id
                WHERE p.paid_date < p.due_date
                ORDER BY p.paid_date DESC
                LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
        return $this->db->query($sql)->fetchAll();
    }

    public function getStatsByAgency() {
        $sql = "SELECT a.id, a.name as agency, COUNT(p.id) as total_payments, SUM(p.amount) as total_amount
                FROM payments p
                LEFT JOIN batches b ON p.batch_id = b.id
                LEFT JOIN agencies a ON b.agency_id = a.id
                GROUP BY a.id
                ORDER BY total_amount DESC";
        return $this->db->query($sql)->fetchAll();
    }

    public function getStatsByLandlord() {
        $sql = "SELECT l.name as landlord, COUNT(p.id) as total_payments, SUM(p.amount) as total_amount
                FROM payments p
                LEFT JOIN batches b ON p.batch_id = b.id
                LEFT JOIN landlords l ON b.landlord_id = l.id
                GROUP BY l.id
                ORDER BY total_amount DESC";
        return $this->db->query($sql)->fetchAll();
    }

    public function getTotalRent() {
        $stmt = $this->db->query("SELECT SUM(amount) as total FROM payments");
        $result = $stmt->fetch();
        return $result['total'] ?? 0;
    }

    public function getMonthlyStats($year = null) {
        $year = $year ?? date('Y');
        $sql = "SELECT MONTH(paid_date) as month, COUNT(*) as count, SUM(amount) as total
                FROM payments p
                WHERE YEAR(paid_date) = ? AND p.paid_date IS NOT NULL
                GROUP BY MONTH(paid_date)
                ORDER BY month";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$year]);
        return $stmt->fetchAll();
    }

    public function getYearlyStats() {
        $sql = "SELECT YEAR(paid_date) as year, COUNT(*) as count, SUM(amount) as total
                FROM payments p
                WHERE p.paid_date IS NOT NULL
                GROUP BY YEAR(paid_date)
                ORDER BY year DESC";
        return $this->db->query($sql)->fetchAll();
    }

    public function getPaymentsByStatus() {
        $sql = "SELECT
                    CASE
                        WHEN p.paid_date < p.due_date THEN 'EARLY'
                        WHEN p.paid_date = p.due_date THEN 'ON_TIME'
                        WHEN p.paid_date > p.due_date THEN 'LATE'
                        ELSE 'PENDING'
                    END as status,
                    COUNT(*) as count,
                    SUM(amount) as total
                FROM payments p
                GROUP BY
                    CASE
                        WHEN p.paid_date < p.due_date THEN 'EARLY'
                        WHEN p.paid_date = p.due_date THEN 'ON_TIME'
                        WHEN p.paid_date > p.due_date THEN 'LATE'
                        ELSE 'PENDING'
                    END";
        return $this->db->query($sql)->fetchAll();
    }

    public function getTopLandlords($limit = 10) {
        $sql = "SELECT l.name as landlord, l.contact, COUNT(p.id) as payments_count, SUM(p.amount) as total_amount
                FROM payments p
                LEFT JOIN batches b ON p.batch_id = b.id
                LEFT JOIN landlords l ON b.landlord_id = l.id
                WHERE p.paid_date IS NOT NULL
                GROUP BY l.id
                ORDER BY total_amount DESC
                LIMIT " . (int)$limit;
        return $this->db->query($sql)->fetchAll();
    }

    public function getTopAgencies($limit = 10) {
        $sql = "SELECT a.name as agency, a.contact, COUNT(p.id) as payments_count, SUM(p.amount) as total_amount
                FROM payments p
                LEFT JOIN batches b ON p.batch_id = b.id
                LEFT JOIN agencies a ON b.agency_id = a.id
                WHERE p.paid_date IS NOT NULL
                GROUP BY a.id
                ORDER BY total_amount DESC
                LIMIT " . (int)$limit;
        return $this->db->query($sql)->fetchAll();
    }

    public function getPaymentsByMonthForAgency($agencyId, $year = null) {
        $year = $year ?? date('Y');
        $sql = "SELECT MONTH(p.paid_date) as month, SUM(p.amount) as total
                FROM payments p
                LEFT JOIN batches b ON p.batch_id = b.id
                WHERE b.agency_id = ? AND YEAR(p.paid_date) = ? AND p.paid_date IS NOT NULL
                GROUP BY MONTH(p.paid_date)
                ORDER BY month";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$agencyId, $year]);
        return $stmt->fetchAll();
    }

    public function exportReport($startDate, $endDate) {
        $sql = "SELECT p.*, b.name as batch_name, l.name as landlord_name, a.name as agency_name,
                CASE
                    WHEN p.paid_date < p.due_date THEN 'Anticipé'
                    WHEN p.paid_date = p.due_date THEN 'À temps'
                    WHEN p.paid_date > p.due_date THEN 'En retard'
                    ELSE 'En attente'
                END as status_label
                FROM payments p
                LEFT JOIN batches b ON p.batch_id = b.id
                LEFT JOIN landlords l ON b.landlord_id = l.id
                LEFT JOIN agencies a ON b.agency_id = a.id
                WHERE p.due_date BETWEEN ? AND ?
                ORDER BY p.due_date DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$startDate, $endDate]);
        return $stmt->fetchAll();
    }
}
