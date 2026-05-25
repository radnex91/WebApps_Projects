<?php
namespace Controllers;

use Core\Controller;
use Core\Helpers;
use Models\Bordereau;
use Models\Billet;
use Models\AuditLog;

class BordereauController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();
        $page = (int) ($this->get('page') ?? 1);
        $type = $this->get('type');
        $where = '';
        $params = [];

        if ($type && in_array($type, ['RECETTE', 'DEPENSE', 'VIREMENT'])) {
            $where = 'type = ?';
            $params = [$type];
        }

        $bordereaux = Bordereau::paginate($page, 20, $where, $params);
        $stats = Bordereau::getStats();

        $this->render('bordereaux.index', [
            'bordereaux' => $bordereaux, 'stats' => $stats, 'type' => $type,
            'formatMoney' => [Helpers::class, 'formatMoney'],
        ]);
    }

    public function create(): void
    {
        $this->requireAuth();
        $billetsJour = Billet::getTodayBillets();

        $this->render('bordereaux.create', [
            'billetsJour' => $billetsJour, 'csrf' => $this->csrf,
        ]);
    }

    public function store(): void
    {
        $this->requireAuth();
        if (!$this->csrf->validate($this->post('_csrf_token'))) {
            $this->setFlash('error', 'Token CSRF invalide');
            $this->redirect(BASE_URL . '/bordereaux/create');
        }

        $data = [
            'reference' => Bordereau::generateReference(),
            'date_bordereau' => $this->post('date_bordereau') ?: date('Y-m-d'),
            'type' => $this->post('type'),
            'description' => $this->post('description'),
            'statut' => 'BROUILLON',
            'created_by' => $this->session->get('user_id'),
            'synced' => 1,
        ];

        $lignes = $this->post('lignes') ?? [];
        $montantTotal = 0;

        $db = \Core\Database::getInstance();
        $db->beginTransaction();
        try {
            $bordereau = new Bordereau($data);
            $bordereau->save();

            foreach ($lignes as $ligne) {
                if (empty($ligne['libelle']) || empty($ligne['montant'])) continue;
                $montant = (float) $ligne['montant'];
                $montantTotal += $montant;

                $db->insert('bordereau_lignes', [
                    'bordereau_id' => $bordereau->id,
                    'billet_id' => !empty($ligne['billet_id']) ? (int) $ligne['billet_id'] : null,
                    'libelle' => $ligne['libelle'],
                    'montant' => $montant,
                    'type' => $ligne['type'] ?? 'RECETTE',
                    'categorie' => $ligne['categorie'] ?? null,
                    'notes' => $ligne['notes'] ?? null,
                ]);
            }

            $bordereau->montant_total = $montantTotal;
            $bordereau->save();

            $db->commit();
            AuditLog::log($this->session->get('user_id'), 'CREATE', 'bordereau', $bordereau->id);
            $this->setFlash('success', 'Bordereau créé - Réf: ' . $data['reference']);
            $this->redirect(BASE_URL . '/bordereaux/' . $bordereau->id);
        } catch (\Exception $e) {
            $db->rollback();
            $this->setFlash('error', 'Erreur: ' . $e->getMessage());
            $this->redirect(BASE_URL . '/bordereaux/create');
        }
    }

    public function show(int $id): void
    {
        $this->requireAuth();
        $bordereau = Bordereau::find($id);
        if (!$bordereau) {
            $this->setFlash('error', 'Bordereau introuvable');
            $this->redirect(BASE_URL . '/bordereaux');
        }
        $lignes = $bordereau->lignes();

        $this->render('bordereaux.show', [
            'bordereau' => $bordereau, 'lignes' => $lignes,
            'formatMoney' => [Helpers::class, 'formatMoney'],
        ]);
    }

    public function valider(int $id): void
    {
        $this->requireAuth();
        $this->requireRole('admin');
        $bordereau = Bordereau::find($id);
        if (!$bordereau) { $this->setFlash('error', 'Bordereau introuvable'); $this->redirect(BASE_URL . '/bordereaux'); }

        $bordereau->statut = 'VALIDE';
        $bordereau->valide_par = $this->session->get('user_id');
        $bordereau->date_validation = date('Y-m-d H:i:s');
        $bordereau->save();

        AuditLog::log($this->session->get('user_id'), 'VALIDATE', 'bordereau', $id);
        $this->setFlash('success', 'Bordereau validé');
        $this->redirect(BASE_URL . '/bordereaux/' . $id);
    }

    public function cloturer(int $id): void
    {
        $this->requireAuth();
        $this->requireRole('admin');
        $bordereau = Bordereau::find($id);
        if (!$bordereau) { $this->setFlash('error', 'Bordereau introuvable'); $this->redirect(BASE_URL . '/bordereaux'); }

        $bordereau->statut = 'CLOTURE';
        $bordereau->save();

        AuditLog::log($this->session->get('user_id'), 'CLOSE', 'bordereau', $id);
        $this->setFlash('success', 'Bordereau clôturé');
        $this->redirect(BASE_URL . '/bordereaux/' . $id);
    }
}