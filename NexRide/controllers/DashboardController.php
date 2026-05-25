<?php
namespace Controllers;

use Core\Controller;
use Core\Helpers;
use Models\Billet;
use Models\Voyage;
use Models\Bordereau;
use Models\Caisse;
use Models\EcritureComptable;

class DashboardController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();

        $billetStats = Billet::getStats();
        $voyageStats = Voyage::getStats();
        $bordereauStats = Bordereau::getStats();
        $soldeGlobal = Caisse::getSoldeGlobal();
        $recetteJour = Billet::getRecetteJour();
        $voyagesDuJour = Voyage::getVoyagesDuJour();
        $billetsRecents = Billet::getRecent(5);

        // Monthly revenue chart data
        $monthlyRevenue = \Core\Database::getInstance()->fetchAll(
            "SELECT DATE_FORMAT(date_emission, '%Y-%m') as mois,
                    COALESCE(SUM(CASE WHEN statut = 'CONFIRME' THEN montant_total ELSE 0 END), 0) as recette
            FROM billets
            WHERE date_emission >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            GROUP BY mois ORDER BY mois ASC"
        );

        $this->render('dashboard.index', [
            'billetStats' => $billetStats,
            'voyageStats' => $voyageStats,
            'bordereauStats' => $bordereauStats,
            'soldeGlobal' => $soldeGlobal,
            'recetteJour' => $recetteJour,
            'voyagesDuJour' => $voyagesDuJour,
            'billetsRecents' => $billetsRecents,
            'monthlyRevenue' => $monthlyRevenue,
            'formatMoney' => [Helpers::class, 'formatMoney'],
        ]);
    }
}