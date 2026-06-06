<?php
declare(strict_types=1);

namespace Amaresh\Mcp\Model\Tool;

use Amaresh\Mcp\Api\ToolInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;

class ProductCartSummary implements ToolInterface
{
    public function __construct(private readonly ResourceConnection $resourceConnection)
    {
    }

    public function getName(): string
    {
        return 'product_cart_summary';
    }

    public function getDescription(): string
    {
        return 'Shows top products added to carts for a date range, based on quote items.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'from' => ['type' => 'string', 'description' => 'Quote updated_at start date or datetime.'],
                'to' => ['type' => 'string', 'description' => 'Quote updated_at end date or datetime.'],
                'store_id' => ['type' => 'integer', 'minimum' => 0],
                'active_only' => ['type' => 'boolean'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
            ],
            'required' => ['from', 'to'],
            'additionalProperties' => false,
        ];
    }

    public function execute(array $arguments): array
    {
        $from = trim((string)($arguments['from'] ?? ''));
        $to = trim((string)($arguments['to'] ?? ''));
        if ($from === '' || $to === '') {
            throw new LocalizedException(__('Both from and to dates are required.'));
        }

        $connection = $this->resourceConnection->getConnection();
        $quoteTable = $this->resourceConnection->getTableName('quote');
        $quoteItemTable = $this->resourceConnection->getTableName('quote_item');
        $limit = min(100, max(1, (int)($arguments['limit'] ?? 20)));

        $select = $connection->select()
            ->from(['qi' => $quoteItemTable], [])
            ->joinInner(['q' => $quoteTable], 'q.entity_id = qi.quote_id', [])
            ->columns(
                [
                    'product_id' => 'qi.product_id',
                    'sku' => 'qi.sku',
                    'name' => 'qi.name',
                    'quotes_count' => new \Zend_Db_Expr('COUNT(DISTINCT qi.quote_id)'),
                    'total_qty' => new \Zend_Db_Expr('COALESCE(SUM(qi.qty), 0)'),
                    'row_total_sum' => new \Zend_Db_Expr('COALESCE(SUM(qi.row_total), 0)'),
                ]
            )
            ->where('q.updated_at >= ?', $this->normalizeDateBoundary($from, false))
            ->where('q.updated_at <= ?', $this->normalizeDateBoundary($to, true))
            ->where('qi.parent_item_id IS NULL')
            ->group(['qi.product_id', 'qi.sku', 'qi.name'])
            ->order('total_qty DESC')
            ->limit($limit);

        if (array_key_exists('store_id', $arguments)) {
            $select->where('q.store_id = ?', (int)$arguments['store_id']);
        }

        if (!empty($arguments['active_only'])) {
            $select->where('q.is_active = ?', 1);
        }

        $items = [];
        foreach ($connection->fetchAll($select) as $row) {
            $items[] = [
                'product_id' => (int)$row['product_id'],
                'sku' => (string)$row['sku'],
                'name' => (string)$row['name'],
                'quotes_count' => (int)$row['quotes_count'],
                'total_qty' => (float)$row['total_qty'],
                'row_total_sum' => (float)$row['row_total_sum'],
            ];
        }

        return [
            'from' => $this->normalizeDateBoundary($from, false),
            'to' => $this->normalizeDateBoundary($to, true),
            'store_id' => array_key_exists('store_id', $arguments) ? (int)$arguments['store_id'] : null,
            'active_only' => (bool)($arguments['active_only'] ?? false),
            'limit' => $limit,
            'items' => $items,
        ];
    }

    private function normalizeDateBoundary(string $value, bool $endOfDay): string
    {
        $value = trim($value);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value . ($endOfDay ? ' 23:59:59' : ' 00:00:00');
        }

        return $value;
    }
}
