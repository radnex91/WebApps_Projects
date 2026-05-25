<?php
class Reminder extends Model {
    protected $table = 'reminders';
    protected $allowedFields = ['payment_id', 'sent_at'];

    public function createReminder($paymentId) {
        return $this->create([
            'payment_id' => $paymentId,
            'sent_at' => date('Y-m-d H:i:s')
        ]);
    }

    public function getByPayment($paymentId) {
        $stmt = $this->db->prepare("SELECT * FROM reminders WHERE payment_id = ? ORDER BY sent_at DESC");
        $stmt->execute([$paymentId]);
        return $stmt->fetchAll();
    }
}
