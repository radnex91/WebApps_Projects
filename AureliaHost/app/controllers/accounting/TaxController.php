<?php
class TaxController extends Controller
{
    public function __construct() { $this->requireAuth(); }

    public function index(): void
    {
        $taxes = (new Tax())->all('', [], 'nom');
        $this->render('accounting/taxes/index', ['taxes' => $taxes]);
    }

    public function create(): void { $this->render('accounting/taxes/create'); }

    public function store(): void
    {
        $errors = $this->validateRequired($_POST, ['nom', 'taux']);
        if (!empty($errors)) {
            Session::setFlash('error', 'Nom et taux obligatoires.');
            $this->redirectBack();
            return;
        }
        (new Tax())->create(['nom' => $_POST['nom'], 'taux' => $_POST['taux'], 'type' => $_POST['type'] ?? 'tva', 'description' => $_POST['description'] ?? '', 'actif' => 1]);
        Session::setFlash('success', 'Taxe créée.');
        $this->redirect('/accounting/taxes');
    }

    public function edit(int $id): void
    {
        $tax = (new Tax())->find($id);
        if (!$tax) { Session::setFlash('error', 'Introuvable.'); $this->redirect('/accounting/taxes'); return; }
        $this->render('accounting/taxes/edit', ['tax' => $tax]);
    }

    public function update(int $id): void
    {
        (new Tax())->update($id, ['nom' => $_POST['nom'], 'taux' => $_POST['taux'], 'type' => $_POST['type'], 'description' => $_POST['description'] ?? '', 'actif' => $_POST['actif'] ?? 1]);
        Session::setFlash('success', 'Taxe mise à jour.');
        $this->redirect('/accounting/taxes');
    }

    public function delete(int $id): void
    {
        (new Tax())->delete($id);
        Session::setFlash('success', 'Taxe supprimée.');
        $this->redirect('/accounting/taxes');
    }
}
