<?php
class ExpenseController extends Controller
{
    public function __construct() { $this->requireAuth(); }

    public function index(): void
    {
        $expenses = (new Expense())->allWithUser();
        $this->render('accounting/expenses/index', ['expenses' => $expenses]);
    }

    public function create(): void
    {
        $this->render('accounting/expenses/create');
    }

    public function store(): void
    {
        $errors = $this->validateRequired($_POST, ['description', 'montant', 'date_depense']);
        if (!empty($errors)) {
            Session::setFlash('error', 'Description, montant et date obligatoires.');
            $this->redirectBack();
            return;
        }

        (new Expense())->create([
            'description'  => $_POST['description'],
            'montant'      => $_POST['montant'],
            'categorie'    => $_POST['categorie'] ?? 'autre',
            'date_depense' => $_POST['date_depense'],
            'user_id'      => Session::get('user_id'),
            'justificatif' => $_POST['justificatif'] ?? '',
        ]);

        Session::setFlash('success', 'Dépense enregistrée.');
        $this->redirect('/accounting/expenses');
    }

    public function edit(int $id): void
    {
        $expense = (new Expense())->find($id);
        if (!$expense) { Session::setFlash('error', 'Introuvable.'); $this->redirect('/accounting/expenses'); return; }
        $this->render('accounting/expenses/edit', ['expense' => $expense]);
    }

    public function update(int $id): void
    {
        (new Expense())->update($id, [
            'description'  => $_POST['description'],
            'montant'      => $_POST['montant'],
            'categorie'    => $_POST['categorie'],
            'date_depense' => $_POST['date_depense'],
        ]);
        Session::setFlash('success', 'Dépense mise à jour.');
        $this->redirect('/accounting/expenses');
    }

    public function delete(int $id): void
    {
        (new Expense())->delete($id);
        Session::setFlash('success', 'Dépense supprimée.');
        $this->redirect('/accounting/expenses');
    }
}
