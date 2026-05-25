<?php
namespace Models;

use Core\Model;
use Core\Helpers;

class EcritureComptable extends Model
{
    protected static string $table = 'ecritures_comptables';
    protected array $fillable = ['reference', 'date_ecriture', 'libelle', 'debit', 'credit', 'compte', 'compte_label', 'bordereau_id', 'billet_id', 'type_operation', 'statut', 'notes', 'offline_id', 'synced', 'created_by'];
    protected array $casts = ['id' => 'int', 'debit' => 'float', 'credit' => 'float', 'synced' => 'bool'];

    public static function generateReference(): string
    {
        return Helpers::generateRef('EC');
    }

    public static function getUnsynced(): array
    {
        return self::raw("SELECT * FROM ecritures_comptables WHERE synced = 0");
    }

    public static function getJournalByDate(string $debut, string $fin): array
    {
        return self::raw(
            "SELECT e.*, u.nom as created_nom FROM ecritures_comptables e
            LEFT JOIN utilisateurs u ON e.created_by = u.id
            WHERE e.date_ecriture BETWEEN ? AND ? AND e.statut != 'BROUILLON'
            ORDER BY e.date_ecriture ASC, e.id ASC", [$debut, $fin]
        );
    }

    public static function getBalance(): array
    {
        return self::raw(
            "SELECT compte, compte_label, SUM(debit) as total_debit,
                    SUM(credit) as total_credit, (SUM(debit) - SUM(credit)) as solde
            FROM ecritures_comptables WHERE statut = 'VALIDE'
            GROUP BY compte, compte_label ORDER BY compte ASC"
        );
    }

    public static function getTotaux(string $debut, string $fin): object
    {
        return self::db()->fetch(
            "SELECT COALESCE(SUM(debit), 0) as total_debit, COALESCE(SUM(credit), 0) as total_credit
            FROM ecritures_comptables WHERE date_ecriture BETWEEN ? AND ? AND statut != 'BROUILLON'",
            [$debut, $fin]
        );
    }

    public static function getGrandLivre(string $compte, string $debut, string $fin): array
    {
        return self::raw(
            "SELECT e.*, u.nom as created_nom FROM ecritures_comptables e
            LEFT JOIN utilisateurs u ON e.created_by = u.id
            WHERE e.compte = ? AND e.date_ecriture BETWEEN ? AND ?
            ORDER BY e.date_ecriture ASC", [$compte, $debut, $fin]
        );
    }
}