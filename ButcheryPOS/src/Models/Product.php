<?php
namespace App\Models;

class Product extends BaseModel
{
    protected string $table = 'products';
    protected array $fillable = ['name', 'sku', 'category_id', 'unit_id', 'cost_price', 'sale_price', 'quantity_in_stock', 'reorder_level', 'track_expiry', 'default_shelf_life_days', 'is_active', 'is_carcass', 'image'];

    public function withCategory(int $id): ?array
    {
        return $this->repo->query(
            "SELECT p.*, c.name AS category_name, u.name AS unit_name, u.abbreviation AS unit_abbr
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             LEFT JOIN units u ON u.id = p.unit_id
             WHERE p.id = :id",
            ['id' => $id]
        )[0] ?? null;
    }

    public function allWithDetails(int $categoryId = null, bool $activeOnly = true): array
    {
        $params = [];
        $sql = "SELECT p.*, c.name AS category_name, u.name AS unit_name, u.abbreviation AS unit_abbr
                FROM products p
                LEFT JOIN categories c ON c.id = p.category_id
                LEFT JOIN units u ON u.id = p.unit_id";

        $where = [];
        if ($categoryId) {
            $where[] = "p.category_id = :cat_id";
            $params['cat_id'] = $categoryId;
        }
        if ($activeOnly) {
            $where[] = "p.is_active = 1";
        }
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= " ORDER BY p.name ASC";

        return $this->repo->query($sql, $params);
    }
}