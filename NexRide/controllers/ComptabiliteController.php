<?php
namespace Controllers;

use Core\Controller;
use Core\Helpers;
use Models\EcritureComptable;
use Models\Caisse;
use Models\AuditLog;
use Core\Database;

class ComptabiliteController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();
        $debut = $this->get('debut') ?: date('Y-m-01');
        $fin = $this->get('fin') ?: date('Y-m-t');

        $journal = EcritureComptable::getJournalByDate($debut, $fin);
        $totaux = EcritureComptable::getTotaux($debut, $fin);
        $balance = EcritureComptable::getBalance();
        $caisses = Caisse::getActives();
        $soldeGlobal = Caisse::getSoldeGlobal();

        $this->render('comptabilite.index', [
            'journal' => $journal, 'totaux' => $totaux, 'balance' => $balance,
            'caisses' => $caisses, 'soldeGlobal' => $soldeGlobal,
            'debut' => $debut, 'fin' => $fin,
            'formatMoney' => [Helpers::class, 'formatMoney'],
        ]);
    }

    public function create(): void
    {
        $this->requireAuth();
        $this->requireRole('comptable');
        $caisses = Caisse::getActives();
        $this->render('comptabilite.create', ['caisses' => $caisses, 'csrf' => $this->csrf]);
    }

    public function store(): void
    {
        $this->requireAuth();
        $this->requireRole('comptable');
        if (!$this->csrf->validate($this->post('_csrf_token'))) {
            $this->setFlash('error', 'Token CSRF invalide');
            $this->redirect(BASE_URL . '/comptabilite/create');
        }

        $data = [
            'reference' => EcritureComptable::generateReference(),
            'date_ecriture' => $this->post('date_ecriture') ?: date('Y-m-d'),
            'libelle' => $this->post('libelle'),
            'compte' => $this->post('compte'),
            'compte_label' => $this->post('compte_label'),
            'debit' => (float) ($this->post('debit') ?? 0),
            'credit' => (float) ($this->post('credit') ?? 0),
            'type_operation' => $this->post('type_operation'),
            'notes' => $this->post('notes'),
            'statut' => 'BROUILLON',
            'created_by' => $this->session->get('user_id'),
            'synced' => 1,
        ];

        $ecriture = new EcritureComptable($data);
        $ecriture->save();

        // Also handle caisse movement if specified
        $caisseId = $this->post('caisse_id');
        if ($caisseId) {
            $caisse = Caisse::find((int) $caisseId);
            if ($caisse && $data['debit'] > 0) {
                $caisse->ajouterMouvement('SORTIE', $data['debit'],
                    $data['libelle'] . ' - ' . $data['reference'],
                    null, null, $this->session->get('user_id'));
            }
            if ($caisse && $data['credit'] > 0) {
                $caisse->ajouterMouvement('ENTREE', $data['credit'],
                    $data['libelle'] . ' - ' . $data['reference'],
                    null, null, $this->session->get('user_id'));
            }
        }

        AuditLog::log($this->session->get('user_id'), 'CREATE', 'ecriture_comptable', $ecriture->id);
        $this->setFlash('success', 'Écriture comptable créée - Réf: ' . $data['reference']);
        $this->redirect(BASE_URL . '/comptabilite');
    }

    public function valider(int $id): void
    {
        $this->requireAuth();
        $this->requireRole('comptable');
        $ecriture = EcritureComptable::find($id);
        if (!$ecriture) { $this->setFlash('error', 'Écriture introuvable'); $this->redirect(BASE_URL . '/comptabilite'); }

        $ecriture->statut = 'VALIDE';
        $ecriture->save();
        AuditLog::log($this->session->get('user_id'), 'VALIDATE', 'ecriture_comptable', $id);
        $this->setFlash('success', 'Écriture validée');
        $this->redirect(BASE_URL . '/comptabilite');
    }

    public function rapport(): void
    {
        $this->requireAuth();
        $this->requireRole('comptable');
        $annee = (int) ($this->get('annee') ?: date('Y'));

        $rapportMensuel = Database::getInstance()->fetchAll(
            "SELECT DATE_FORMAT(date_ecriture, '%Y-%m') as mois,
                    COALESCE(SUM(debit), 0) as total_debit,
                    COALESCE(SUM(credit), 0) as total_credit
            FROM ecritures_comptables
            WHERE YEAR(date_ecriture) = ? AND statut = 'VALIDE'
            GROUP BY mois ORDER BY mois ASC",
            [$annee]
        );

        $this->render('comptabilite.rapport', [
            'rapportMensuel' => $rapportMensuel, 'annee' => $annee,
            'formatMoney' => [Helpers::class, 'formatMoney'],
        ]);
    }
}