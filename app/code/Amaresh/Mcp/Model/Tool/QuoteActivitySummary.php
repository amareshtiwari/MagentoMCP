<?php
declare(strict_types=1);

namespace Amaresh\Mcp\Model\Tool;

use Amaresh\Mcp\Api\ToolInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;

class QuoteActivitySummary implements ToolInterface
{
    public function __construct(private readonly ResourceConnection $resourceConnection)
    {
    }

    public function getName(): string
    {
        return 'quote_activity_summary';
    }

    public function getDescription(): string
    {
        return 'Summarizes quote/cart activity for a date range, useful for questions like add-to-carts this week.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'from' => ['type' => 'string', 'description' => 'Start date or datetime, for example 2026-05-01.'],
                'to' => ['type' => 'string', 'description' => 'End date or datetime, for example 2026-05-29.'],
                'date_field' => ['type' => 'string', 'enum' => ['created_at', 'updated_at'], 'description' => 'Use updated_at for add-to-cart activity.'],
                'store_id' => ['type' => 'integer', 'minimum' => 0],
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

        $dateField = (string)($arguments['date_field'] ?? 'updated_at');
        if (!in_array($dateField, ['created_at', 'updated_at'], true)) {
            throw new LocalizedException(__('date_field must be created_at or updated_at.'));
        }

        $connection = $this->resourceConnection->getConnection();
        $quoteTable = $this->resourceConnection->getTableName('quote');

        $where = [
            $connection->quoteIdentifier($dateField) . ' >= ?' => $this->normalizeDateBoundary($from, false),
            $connection->quoteIdentifier($dateField) . ' <= ?' => $this->normalizeDateBoundary($to, true),
        ];

        if (array_key_exists('store_id', $arguments)) {
            $where['store_id = ?'] = (int)$arguments['store_id'];
        }

        $select = $connection->select()
            ->from(
                $quoteTable,
                [
                    'total_quotes' => new \Zend_Db_Expr('COUNT(*)'),
                    'quotes_with_items' => new \Zend_Db_Expr('SUM(CASE WHEN items_count > 0 THEN 1 ELSE 0 END)'),
                    'active_quotes' => new \Zend_Db_Expr('SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END)'),
                    'inactive_quotes' => new \Zend_Db_Expr('SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END)'),
                    'customer_quotes' => new \Zend_Db_Expr('SUM(CASE WHEN customer_id IS NOT NULL THEN 1 ELSE 0 END)'),
                    'guest_quotes' => new \Zend_Db_Expr('SUM(CASE WHEN customer_id IS NULL THEN 1 ELSE 0 END)'),
                    'items_qty_sum' => new \Zend_Db_Expr('COALESCE(SUM(items_qty), 0)'),
                    'subtotal_sum' => new \Zend_Db_Expr('COALESCE(SUM(subtotal), 0)'),
                    'grand_total_sum' => new \Zend_Db_Expr('COALESCE(SUM(grand_total), 0)'),
                ]
            );

        foreach ($where as $condition => $value) {
            $select->where($condition, $value);
        }

        $row = (array)$connection->fetchRow($select);

        return [
            'from' => $this->normalizeDateBoundary($from, false),
            'to' => $this->normalizeDateBoundary($to, true),
            'date_field' => $dateField,
            'store_id' => array_key_exists('store_id', $arguments) ? (int)$arguments['store_id'] : null,
            'total_quotes' => (int)($row['total_quotes'] ?? 0),
            'quotes_with_items' => (int)($row['quotes_with_items'] ?? 0),
            'active_quotes' => (int)($row['active_quotes'] ?? 0),
            'inactive_quotes' => (int)($row['inactive_quotes'] ?? 0),
            'customer_quotes' => (int)($row['customer_quotes'] ?? 0),
            'guest_quotes' => (int)($row['guest_quotes'] ?? 0),
            'items_qty_sum' => (float)($row['items_qty_sum'] ?? 0),
            'subtotal_sum' => (float)($row['subtotal_sum'] ?? 0),
            'grand_total_sum' => (float)($row['grand_total_sum'] ?? 0),
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
