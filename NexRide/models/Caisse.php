<?php
namespace Models;

use Core\Model;

class Caisse extends Model
{
    protected static string $table = 'caisses';
    protected array $fillable = ['libelle', 'solde_initial', 'solde_actuel', 'type', 'actif'];
    protected array $casts = ['id' => 'int', 'solde_initial' => 'float', 'solde_actuel' => 'float', 'actif' => 'bool'];

    public static function getActives(): array
    {
        return self::raw("SELECT * FROM caisses WHERE actif = 1 ORDER BY libelle ASC");
    }

    public function getMouvements(int $limit = 50): array
    {
        return self::db()->fetchAll(
            "SELECT m.*, u.nom as effectue_par_nom FROM mouvements_caisse m
            LEFT JOIN utilisateurs u ON m.effectue_par = u.id
            WHERE m.caisse_id = ? ORDER BY m.date_operation DESC LIMIT ?",
            [$this->id, $limit]
        );
    }

    public function ajouterMouvement(string $type, float $montant, string $libelle, ?int $bordereauId = null, ?int $billetId = null, ?int $userId = null, ?string $offlineId = null): int
    {
        $db = self::db();
        $db->beginTransaction();
        try {
            $mouvementId = $db->insert('mouvements_caisse', [
                'caisse_id' => $this->id, 'type' => $type, 'montant' => $montant,
                'libelle' => $libelle, 'bordereau_id' => $bordereauId,
                'billet_id' => $billetId, 'effectue_par' => $userId,
                'offline_id' => $offlineId, 'synced' => $offlineId ? 0 : 1,
            ]);
            $newSolde = $type === 'ENTREE' ? $this->solde_actuel + $montant : $this->solde_actuel - $montant;
            $db->update('caisses', ['solde_actuel' => $newSolde], 'id = ?', [$this->id]);
            $this->attributes['solde_actuel'] = $newSolde;
            $db->commit();
            return $mouvementId;
        } catch (\Exception $e) {
            $db->rollback();
            throw $e;
        }
    }

    public static function getSoldeGlobal(): float
    {
        $r = self::db()->fetch("SELECT COALESCE(SUM(solde_actuel), 0) as total FROM caisses WHERE actif = 1");
        return (float) $r->total;
    }
}