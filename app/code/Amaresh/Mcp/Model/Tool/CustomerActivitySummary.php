<?php
declare(strict_types=1);

namespace Amaresh\Mcp\Model\Tool;

use Amaresh\Mcp\Api\ToolInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;

class CustomerActivitySummary implements ToolInterface
{
    public function __construct(private readonly ResourceConnection $resourceConnection)
    {
    }

    public function getName(): string
    {
        return 'customer_activity_summary';
    }

    public function getDescription(): string
    {
        return 'Summarizes new customers and customer/guest order activity for a marketing date range.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'from' => ['type' => 'string', 'description' => 'Start date or datetime.'],
                'to' => ['type' => 'string', 'description' => 'End date or datetime.'],
                'website_id' => ['type' => 'integer', 'minimum' => 0],
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

        $connection = $this->resourceConnection->getConnection();
        $customerTable = $this->resourceConnection->getTableName('customer_entity');
        $orderTable = $this->resourceConnection->getTableName('sales_order');
        $fromDate = $this->normalizeDateBoundary($from, false);
        $toDate = $this->normalizeDateBoundary($to, true);

        $newCustomersSelect = $connection->select()
            ->from($customerTable, ['new_customers' => new \Zend_Db_Expr('COUNT(*)')])
            ->where('created_at >= ?', $fromDate)
            ->where('created_at <= ?', $toDate);

        if (array_key_exists('website_id', $arguments)) {
            $newCustomersSelect->where('website_id = ?', (int)$arguments['website_id']);
        }

        $ordersSelect = $connection->select()
            ->from(
                $orderTable,
                [
                    'orders_count' => new \Zend_Db_Expr('COUNT(*)'),
                    'registered_customer_orders' => new \Zend_Db_Expr('SUM(CASE WHEN customer_id IS NOT NULL THEN 1 ELSE 0 END)'),
                    'guest_orders' => new \Zend_Db_Expr('SUM(CASE WHEN customer_id IS NULL THEN 1 ELSE 0 END)'),
                    'unique_ordering_customers' => new \Zend_Db_Expr('COUNT(DISTINCT customer_id)'),
                    'revenue_sum' => new \Zend_Db_Expr('COALESCE(SUM(grand_total), 0)'),
                    'average_order_value' => new \Zend_Db_Expr('COALESCE(AVG(grand_total), 0)'),
                ]
            )
            ->where('created_at >= ?', $fromDate)
            ->where('created_at <= ?', $toDate);

        if (array_key_exists('store_id', $arguments)) {
            $ordersSelect->where('store_id = ?', (int)$arguments['store_id']);
        }

        $newCustomers = (int)$connection->fetchOne($newCustomersSelect);
        $orderRow = (array)$connection->fetchRow($ordersSelect);

        return [
            'from' => $fromDate,
            'to' => $toDate,
            'website_id' => array_key_exists('website_id', $arguments) ? (int)$arguments['website_id'] : null,
            'store_id' => array_key_exists('store_id', $arguments) ? (int)$arguments['store_id'] : null,
            'new_customers' => $newCustomers,
            'orders_count' => (int)($orderRow['orders_count'] ?? 0),
            'registered_customer_orders' => (int)($orderRow['registered_customer_orders'] ?? 0),
            'guest_orders' => (int)($orderRow['guest_orders'] ?? 0),
            'unique_ordering_customers' => (int)($orderRow['unique_ordering_customers'] ?? 0),
            'revenue_sum' => (float)($orderRow['revenue_sum'] ?? 0),
            'average_order_value' => (float)($orderRow['average_order_value'] ?? 0),
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
