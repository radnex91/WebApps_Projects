<?php
namespace App\Models;

class Category extends BaseModel
{
    protected string $table = 'categories';
    protected array $fillable = ['name', 'description', 'parent_id', 'sort_order', 'is_active'];

    public function allWithProductCount(): array
    {
        return $this->repo->query(
            "SELECT c.*, COUNT(p.id) AS product_count
             FROM categories c
             LEFT JOIN products p ON p.category_id = c.id AND p.is_active = 1
             WHERE c.is_active = 1
             GROUP BY c.id
             ORDER BY c.sort_order ASC, c.name ASC"
        );
    }
}