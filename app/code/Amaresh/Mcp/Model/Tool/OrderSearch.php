<?php
declare(strict_types=1);

namespace Amaresh\Mcp\Model\Tool;

use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Amaresh\Mcp\Api\ToolInterface;
use Amaresh\Mcp\Model\Config;

class OrderSearch implements ToolInterface
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly FilterBuilder $filterBuilder,
        private readonly SortOrderBuilder $sortOrderBuilder,
        private readonly Config $config
    ) {
    }

    public function getName(): string
    {
        return 'sales_order_search';
    }

    public function getDescription(): string
    {
        return 'Searches orders by increment ID, customer email, or status.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'increment_id' => ['type' => 'string'],
                'customer_email' => ['type' => 'string'],
                'status' => ['type' => 'string'],
                'created_from' => ['type' => 'string', 'description' => 'Order created_at start date or datetime, for example 2026-04-20.'],
                'created_to' => ['type' => 'string', 'description' => 'Order created_at end date or datetime, for example 2026-05-20.'],
                'page' => ['type' => 'integer', 'minimum' => 1],
                'page_size' => ['type' => 'integer', 'minimum' => 1, 'maximum' => $this->config->getMaxPageSize()],
            ],
            'additionalProperties' => false,
        ];
    }

    public function execute(array $arguments): array
    {
        $filters = [];
        if (!empty($arguments['increment_id'])) {
            $filters[] = $this->filterBuilder
                ->setField('increment_id')
                ->setValue('%' . trim((string)$arguments['increment_id']) . '%')
                ->setConditionType('like')
                ->create();
        }
        if (!empty($arguments['customer_email'])) {
            $filters[] = $this->filterBuilder
                ->setField('customer_email')
                ->setValue('%' . trim((string)$arguments['customer_email']) . '%')
                ->setConditionType('like')
                ->create();
        }
        if (!empty($arguments['status'])) {
            $filters[] = $this->filterBuilder
                ->setField('status')
                ->setValue(trim((string)$arguments['status']))
                ->setConditionType('eq')
                ->create();
        }
        if (!empty($arguments['created_from'])) {
            $filters[] = $this->filterBuilder
                ->setField('created_at')
                ->setValue($this->normalizeDateBoundary((string)$arguments['created_from'], false))
                ->setConditionType('gteq')
                ->create();
        }
        if (!empty($arguments['created_to'])) {
            $filters[] = $this->filterBuilder
                ->setField('created_at')
                ->setValue($this->normalizeDateBoundary((string)$arguments['created_to'], true))
                ->setConditionType('lteq')
                ->create();
        }

        if (!$filters) {
            throw new LocalizedException(__('At least one of increment_id, customer_email, status, created_from, or created_to is required.'));
        }

        $page = max(1, (int)($arguments['page'] ?? 1));
        $pageSize = $this->config->normalizePageSize(isset($arguments['page_size']) ? (int)$arguments['page_size'] : null);
        $sortOrder = $this->sortOrderBuilder->setField('created_at')->setDirection(SortOrder::SORT_DESC)->create();

        $criteria = $this->searchCriteriaBuilder
            ->addFilters($filters)
            ->setCurrentPage($page)
            ->setPageSize($pageSize)
            ->setSortOrders([$sortOrder])
            ->create();

        $result = $this->orderRepository->getList($criteria);
        $items = [];
        foreach ($result->getItems() as $order) {
            $items[] = [
                'entity_id' => (int)$order->getEntityId(),
                'increment_id' => $order->getIncrementId(),
                'state' => $order->getState(),
                'status' => $order->getStatus(),
                'customer_email' => $order->getCustomerEmail(),
                'grand_total' => (float)$order->getGrandTotal(),
                'currency' => $order->getOrderCurrencyCode(),
                'created_at' => $order->getCreatedAt(),
            ];
        }

        return [
            'page' => $page,
            'page_size' => $pageSize,
            'total_count' => (int)$result->getTotalCount(),
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
