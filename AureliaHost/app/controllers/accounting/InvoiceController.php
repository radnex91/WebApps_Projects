<?php
class InvoiceController extends Controller
{
    public function __construct() { $this->requireAuth(); }

    public function index(): void
    {
        $model = new Invoice();
        $invoices = $model->allWithDetails();
        $this->render('accounting/invoices/index', ['invoices' => $invoices]);
    }

    public function create(): void
    {
        $clients = (new Client())->all('', [], 'nom, prenom');
        $reservations = (new Reservation())->all("statut IN ('confirmee','en_cours','terminee')", [], 'date_checkin DESC');
        $taxes = (new Tax())->all("actif = 1", [], 'nom');
        $this->render('accounting/invoices/create', ['clients' => $clients, 'reservations' => $reservations, 'taxes' => $taxes]);
    }

    public function store(): void
    {
        $errors = $this->validateRequired($_POST, ['client_id', 'date_emission']);
        if (!empty($errors)) {
            Session::setFlash('error', 'Client et date d\'émission obligatoires.');
            $this->redirectBack();
            return;
        }

        $model = new Invoice();
        $numero = $model->generateNumber();
        $tvaRate = 0;

        if (!empty($_POST['tax_id'])) {
            $tax = (new Tax())->find($_POST['tax_id']);
            if ($tax) $tvaRate = $tax['taux'];
        }

        $descriptions = $_POST['descriptions'] ?? [];
        $quantities = $_POST['quantites'] ?? [];
        $prices = $_POST['prix_unitaires'] ?? [];

        $totalHT = 0;
        $items = [];
        foreach ($descriptions as $i => $desc) {
            if (empty($desc)) continue;
            $qty = max(1, (int)($quantities[$i] ?? 1));
            $price = (float)($prices[$i] ?? 0);
            $lineTotal = $qty * $price;
            $totalHT += $lineTotal;
            $items[] = ['desc' => $desc, 'qty' => $qty, 'price' => $price, 'total' => $lineTotal];
        }

        $montantTVA = $totalHT * ($tvaRate / 100);
        $montantTTC = $totalHT + $montantTVA;

        $model->beginTransaction();
        try {
            $invoiceId = $model->create([
                'reservation_id' => $_POST['reservation_id'] ?: null,
                'client_id'      => $_POST['client_id'],
                'numero_facture' => $numero,
                'date_emission'  => $_POST['date_emission'],
                'date_echeance'  => $_POST['date_echeance'] ?: null,
                'montant_ht'     => $totalHT,
                'montant_tva'    => $montantTVA,
                'montant_ttc'    => $montantTTC,
                'statut'         => 'brouillon',
                'notes'          => $_POST['notes'] ?? '',
            ]);

            foreach ($items as $item) {
                $model->query(
                    "INSERT INTO invoice_items (invoice_id, description, quantite, prix_unitaire, total) VALUES (:iid, :d, :q, :p, :t)",
                    ['iid' => $invoiceId, 'd' => $item['desc'], 'q' => $item['qty'], 'p' => $item['price'], 't' => $item['total']]
                );
            }

            $model->commit();
            Session::setFlash('success', "Facture $numero créée.");
        } catch (Exception $e) {
            $model->rollback();
            Session::setFlash('error', 'Erreur: ' . $e->getMessage());
        }

        $this->redirect('/accounting/invoices');
    }

    public function show(int $id): void
    {
        $model = new Invoice();
        $invoice = $model->findWithDetails($id);
        if (!$invoice) { Session::setFlash('error', 'Introuvable.'); $this->redirect('/accounting/invoices'); return; }
        $items = $model->getItems($id);
        $payments = $model->getPayments($id);
        $totalPaye = array_sum(array_column($payments, 'montant'));

        $this->render('accounting/invoices/show', [
            'invoice' => $invoice,
            'items' => $items,
            'payments' => $payments,
            'totalPaye' => $totalPaye,
        ]);
    }

    public function edit(int $id): void
    {
        $invoice = (new Invoice())->find($id);
        if (!$invoice) { Session::setFlash('error', 'Introuvable.'); $this->redirect('/accounting/invoices'); return; }
        $this->render('accounting/invoices/edit', ['invoice' => $invoice]);
    }

    public function update(int $id): void
    {
        (new Invoice())->update($id, [
            'statut' => $_POST['statut'],
            'notes' => $_POST['notes'] ?? '',
        ]);
        Session::setFlash('success', 'Facture mise à jour.');
        $this->redirect('/accounting/invoices');
    }

    public function delete(int $id): void
    {
        (new Invoice())->delete($id);
        Session::setFlash('success', 'Facture supprimée.');
        $this->redirect('/accounting/invoices');
    }

    public function print(int $id): void
    {
        $model = new Invoice();
        $invoice = $model->findWithDetails($id);
        if (!$invoice) { Session::setFlash('error', 'Introuvable.'); $this->redirect('/accounting/invoices'); return; }
        $items = $model->getItems($id);
        $this->render('accounting/invoices/print', ['invoice' => $invoice, 'items' => $items]);
    }
}
