<?php
namespace App\Models;

class Breakdown extends BaseModel
{
    protected string $table = 'breakdowns';
    protected array $fillable = [
        'reference', 'source_product_id', 'source_batch_id', 'source_quantity',
        'source_unit_cost', 'total_output_weight', 'waste_weight', 'notes', 'created_by',
    ];

    /**
     * Get a breakdown with its items and product names
     */
    public function withItems(int $id): ?array
    {
        $breakdown = $this->find($id);
        if (!$breakdown) return null;

        $items = $this->repo->query(
            "SELECT bi.*, p.name AS output_product_name, p.sku AS output_product_sku,
                    sb.batch_reference AS output_batch_reference
             FROM breakdown_items bi
             INNER JOIN products p ON p.id = bi.output_product_id
             LEFT JOIN stock_batches sb ON sb.id = bi.output_batch_id
             WHERE bi.breakdown_id = :bid
             ORDER BY bi.id ASC",
            ['bid' => $id]
        );

        $breakdown['items'] = $items;

        // Add source product name
        $source = $this->repo->query(
            "SELECT p.name AS source_product_name, p.sku AS source_product_sku,
                    sb.batch_reference AS source_batch_ref, sb.quantity_remaining AS source_batch_remaining
             FROM products p
             INNER JOIN stock_batches sb ON sb.id = :batch_id
             WHERE p.id = :product_id",
            ['product_id' => $breakdown['source_product_id'], 'batch_id' => $breakdown['source_batch_id']]
        );
        if (!empty($source)) {
            $breakdown['source_product_name'] = $source[0]['source_product_name'];
            $breakdown['source_product_sku'] = $source[0]['source_product_sku'];
            $breakdown['source_batch_ref'] = $source[0]['source_batch_ref'];
            $breakdown['source_batch_remaining'] = $source[0]['source_batch_remaining'];
        }

        return $breakdown;
    }

    /**
     * Get recent breakdowns with source product info
     */
    public function getRecentBreakdowns(int $limit = 50): array
    {
        return $this->repo->query(
            "SELECT bd.*, p.name AS source_product_name, p.sku AS source_product_sku,
                    sb.batch_reference AS source_batch_ref, u.full_name AS created_by_name
             FROM breakdowns bd
             INNER JOIN products p ON p.id = bd.source_product_id
             INNER JOIN stock_batches sb ON sb.id = bd.source_batch_id
             LEFT JOIN users u ON u.id = bd.created_by
             ORDER BY bd.created_at DESC
             LIMIT :lim",
            ['lim' => $limit]
        );
    }
}