<?php
namespace App\Models;

class Sale extends BaseModel
{
    protected string $table = 'sales';
    protected array $fillable = ['pos_session_id', 'customer_id', 'reference', 'subtotal', 'discount_amount', 'tax_amount', 'total_amount', 'payment_method', 'payment_status', 'notes', 'created_by'];

    public function withItems(int $id): ?array
    {
        $sale = $this->find($id);
        if (!$sale) return null;
        $sale['items'] = $this->repo->query(
            "SELECT si.*, p.name AS product_name, p.sku, u.abbreviation AS unit_abbr
             FROM sale_items si
             INNER JOIN products p ON p.id = si.product_id
             LEFT JOIN units u ON u.id = (SELECT unit_id FROM products WHERE id = si.product_id)
             WHERE si.sale_id = :sid",
            ['sid' => $id]
        );
        $sale['payments'] = $this->repo->query(
            "SELECT * FROM payments WHERE sale_id = :sid",
            ['sid' => $id]
        );
        return $sale;
    }
}