<?php
class Invoice extends Model
{
    protected string $table = 'invoices';

    public function allWithDetails(): array
    {
        return $this->query(
            "SELECT i.*, c.nom as client_nom, c.prenom as client_prenom
             FROM invoices i
             LEFT JOIN clients c ON i.client_id = c.id
             ORDER BY i.date_emission DESC"
        );
    }

    public function findWithDetails(int $id): ?array
    {
        return $this->queryOne(
            "SELECT i.*, c.nom as client_nom, c.prenom as client_prenom, c.telephone as client_tel, c.email as client_email,
                    r.date_checkin, r.date_checkout
             FROM invoices i
             LEFT JOIN clients c ON i.client_id = c.id
             LEFT JOIN reservations r ON i.reservation_id = r.id
             WHERE i.id = :id",
            ['id' => $id]
        );
    }

    public function getItems(int $invoiceId): array
    {
        return $this->query(
            "SELECT * FROM invoice_items WHERE invoice_id = :iid",
            ['iid' => $invoiceId]
        );
    }

    public function getPayments(int $invoiceId): array
    {
        return $this->query(
            "SELECT * FROM payments WHERE invoice_id = :iid ORDER BY date_paiement DESC",
            ['iid' => $invoiceId]
        );
    }

    public function generateNumber(): string
    {
        $count = $this->count("YEAR(date_emission) = YEAR(CURDATE())") + 1;
        return 'FACT-' . date('Y') . '-' . str_pad($count, 5, '0', STR_PAD_LEFT);
    }
}
