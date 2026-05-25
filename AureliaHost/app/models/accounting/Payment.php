<?php
class Payment extends Model
{
    protected string $table = 'payments';

    public function allWithInvoice(): array
    {
        return $this->query(
            "SELECT p.*, i.numero_facture, c.nom as client_nom, c.prenom as client_prenom
             FROM payments p
             JOIN invoices i ON p.invoice_id = i.id
             LEFT JOIN clients c ON i.client_id = c.id
             ORDER BY p.date_paiement DESC"
        );
    }
}
