<?php
declare(strict_types=1);

namespace Amaresh\Mcp\Model\Tool;

use Amaresh\Mcp\Api\ToolInterface;
use Amaresh\Mcp\Model\Config;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\ResourceModel\Quote\CollectionFactory;

class QuoteSearch implements ToolInterface
{
    public function __construct(
        private readonly CollectionFactory $quoteCollectionFactory,
        private readonly Config $config
    ) {
    }

    public function getName(): string
    {
        return 'quote_search';
    }

    public function getDescription(): string
    {
        return 'Searches Magento quotes/carts by customer, date, active status, and cart item state.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'quote_id' => ['type' => 'integer', 'minimum' => 1],
                'customer_id' => ['type' => 'integer', 'minimum' => 1],
                'customer_email' => ['type' => 'string'],
                'is_active' => ['type' => 'boolean'],
                'has_items' => ['type' => 'boolean'],
                'store_id' => ['type' => 'integer', 'minimum' => 0],
                'created_from' => ['type' => 'string'],
                'created_to' => ['type' => 'string'],
                'updated_from' => ['type' => 'string'],
                'updated_to' => ['type' => 'string'],
                'page' => ['type' => 'integer', 'minimum' => 1],
                'page_size' => ['type' => 'integer', 'minimum' => 1, 'maximum' => $this->config->getMaxPageSize()],
            ],
            'additionalProperties' => false,
        ];
    }

    public function execute(array $arguments): array
    {
        $page = max(1, (int)($arguments['page'] ?? 1));
        $pageSize = $this->config->normalizePageSize(isset($arguments['page_size']) ? (int)$arguments['page_size'] : null);

        $collection = $this->quoteCollectionFactory->create();
        $collection->setCurPage($page);
        $collection->setPageSize($pageSize);
        $collection->setOrder('updated_at', 'DESC');

        $hasFilters = false;

        foreach (['quote_id' => 'entity_id', 'customer_id' => 'customer_id', 'store_id' => 'store_id'] as $argument => $field) {
            if (array_key_exists($argument, $arguments) && $arguments[$argument] !== '') {
                $collection->addFieldToFilter($field, (int)$arguments[$argument]);
                $hasFilters = true;
            }
        }

        if (!empty($arguments['customer_email'])) {
            $collection->addFieldToFilter('customer_email', ['like' => '%' . trim((string)$arguments['customer_email']) . '%']);
            $hasFilters = true;
        }

        if (array_key_exists('is_active', $arguments)) {
            $collection->addFieldToFilter('is_active', (bool)$arguments['is_active'] ? 1 : 0);
            $hasFilters = true;
        }

        if (array_key_exists('has_items', $arguments)) {
            $collection->addFieldToFilter('items_count', (bool)$arguments['has_items'] ? ['gt' => 0] : 0);
            $hasFilters = true;
        }

        foreach (['created_from' => ['created_at', false], 'created_to' => ['created_at', true], 'updated_from' => ['updated_at', false], 'updated_to' => ['updated_at', true]] as $argument => $config) {
            if (!empty($arguments[$argument])) {
                $collection->addFieldToFilter($config[0], [($config[1] ? 'lteq' : 'gteq') => $this->normalizeDateBoundary((string)$arguments[$argument], (bool)$config[1])]);
                $hasFilters = true;
            }
        }

        if (!$hasFilters) {
            throw new LocalizedException(__('At least one quote search filter is required.'));
        }

        $items = [];
        foreach ($collection as $quote) {
            $items[] = [
                'quote_id' => (int)$quote->getId(),
                'is_active' => (bool)$quote->getIsActive(),
                'store_id' => (int)$quote->getStoreId(),
                'customer_id' => $quote->getCustomerId() ? (int)$quote->getCustomerId() : null,
                'customer_email' => $quote->getCustomerEmail(),
                'items_count' => (int)$quote->getItemsCount(),
                'items_qty' => (float)$quote->getItemsQty(),
                'subtotal' => (float)$quote->getSubtotal(),
                'grand_total' => (float)$quote->getGrandTotal(),
                'coupon_code' => $quote->getCouponCode() ?: null,
                'reserved_order_id' => $quote->getReservedOrderId() ?: null,
                'created_at' => $quote->getCreatedAt(),
                'updated_at' => $quote->getUpdatedAt(),
            ];
        }

        return [
            'page' => $page,
            'page_size' => $pageSize,
            'total_count' => (int)$collection->getSize(),
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
