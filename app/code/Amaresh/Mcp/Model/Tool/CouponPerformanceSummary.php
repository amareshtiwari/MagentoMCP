<?php
declare(strict_types=1);

namespace Amaresh\Mcp\Model\Tool;

use Amaresh\Mcp\Api\ToolInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;

class CouponPerformanceSummary implements ToolInterface
{
    public function __construct(private readonly ResourceConnection $resourceConnection)
    {
    }

    public function getName(): string
    {
        return 'coupon_performance_summary';
    }

    public function getDescription(): string
    {
        return 'Summarizes coupon usage, order count, revenue, and discount amount for marketing analysis.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'from' => ['type' => 'string', 'description' => 'Order created_at start date or datetime.'],
                'to' => ['type' => 'string', 'description' => 'Order created_at end date or datetime.'],
                'coupon_code' => ['type' => 'string', 'description' => 'Optional exact coupon code.'],
                'store_id' => ['type' => 'integer', 'minimum' => 0],
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
        $orderTable = $this->resourceConnection->getTableName('sales_order');
        $limit = min(100, max(1, (int)($arguments['limit'] ?? 20)));

        $select = $connection->select()
            ->from(
                $orderTable,
                [
                    'coupon_code',
                    'orders_count' => new \Zend_Db_Expr('COUNT(*)'),
                    'unique_customers' => new \Zend_Db_Expr('COUNT(DISTINCT customer_id)'),
                    'subtotal_sum' => new \Zend_Db_Expr('COALESCE(SUM(subtotal), 0)'),
                    'grand_total_sum' => new \Zend_Db_Expr('COALESCE(SUM(grand_total), 0)'),
                    'discount_amount_sum' => new \Zend_Db_Expr('COALESCE(SUM(ABS(discount_amount)), 0)'),
                    'average_order_value' => new \Zend_Db_Expr('COALESCE(AVG(grand_total), 0)'),
                ]
            )
            ->where('created_at >= ?', $this->normalizeDateBoundary($from, false))
            ->where('created_at <= ?', $this->normalizeDateBoundary($to, true))
            ->where('coupon_code IS NOT NULL')
            ->where('coupon_code != ?', '')
            ->group('coupon_code')
            ->order('orders_count DESC')
            ->limit($limit);

        if (!empty($arguments['coupon_code'])) {
            $select->where('coupon_code = ?', trim((string)$arguments['coupon_code']));
        }

        if (array_key_exists('store_id', $arguments)) {
            $select->where('store_id = ?', (int)$arguments['store_id']);
        }

        $items = [];
        foreach ($connection->fetchAll($select) as $row) {
            $items[] = [
                'coupon_code' => (string)$row['coupon_code'],
                'orders_count' => (int)$row['orders_count'],
                'unique_customers' => (int)$row['unique_customers'],
                'subtotal_sum' => (float)$row['subtotal_sum'],
                'grand_total_sum' => (float)$row['grand_total_sum'],
                'discount_amount_sum' => (float)$row['discount_amount_sum'],
                'average_order_value' => (float)$row['average_order_value'],
            ];
        }

        return [
            'from' => $this->normalizeDateBoundary($from, false),
            'to' => $this->normalizeDateBoundary($to, true),
            'store_id' => array_key_exists('store_id', $arguments) ? (int)$arguments['store_id'] : null,
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
