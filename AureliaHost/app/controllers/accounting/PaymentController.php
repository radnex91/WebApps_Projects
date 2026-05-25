<?php
class PaymentController extends Controller
{
    public function __construct() { $this->requireAuth(); }

    public function index(): void
    {
        $payments = (new Payment())->allWithInvoice();
        $this->render('accounting/payments/index', ['payments' => $payments]);
    }

    public function create(): void
    {
        $invoices = (new Invoice())->all("statut IN ('envoyee','brouillon')", [], 'date_emission DESC');
        $this->render('accounting/payments/create', ['invoices' => $invoices]);
    }

    public function store(): void
    {
        $errors = $this->validateRequired($_POST, ['invoice_id', 'montant', 'date_paiement']);
        if (!empty($errors)) {
            Session::setFlash('error', 'Facture, montant et date obligatoires.');
            $this->redirectBack();
            return;
        }

        (new Payment())->create([
            'invoice_id'    => $_POST['invoice_id'],
            'montant'       => $_POST['montant'],
            'mode_paiement' => $_POST['mode_paiement'] ?? 'especes',
            'reference'     => $_POST['reference'] ?? '',
            'date_paiement' => $_POST['date_paiement'],
        ]);

        // Check if fully paid
        $invoice = (new Invoice())->find($_POST['invoice_id']);
        $payments = (new Invoice())->getPayments($_POST['invoice_id']);
        $totalPaye = array_sum(array_column($payments, 'montant'));
        if ($totalPaye >= $invoice['montant_ttc']) {
            (new Invoice())->update($_POST['invoice_id'], ['statut' => 'payee']);
        } else {
            (new Invoice())->update($_POST['invoice_id'], ['statut' => 'envoyee']);
        }

        Session::setFlash('success', 'Paiement enregistré.');
        $this->redirect('/accounting/payments');
    }

    public function delete(int $id): void
    {
        (new Payment())->delete($id);
        Session::setFlash('success', 'Paiement supprimé.');
        $this->redirect('/accounting/payments');
    }
}
