<?php
declare(strict_types=1);

namespace Amaresh\Mcp\Model\Tool;

use Amaresh\Mcp\Api\ToolInterface;
use Amaresh\Mcp\Model\Config;
use Magento\Framework\Exception\LocalizedException;
use Magento\SalesRule\Model\ResourceModel\Coupon\CollectionFactory;

class SalesRuleCouponSearch implements ToolInterface
{
    public function __construct(
        private readonly CollectionFactory $couponCollectionFactory,
        private readonly Config $config
    ) {
    }

    public function getName(): string
    {
        return 'sales_rule_coupon_search';
    }

    public function getDescription(): string
    {
        return 'Searches individual sales rule coupon codes and usage counts.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'code' => ['type' => 'string', 'description' => 'Coupon code or code fragment.'],
                'rule_id' => ['type' => 'integer', 'minimum' => 1],
                'unused_only' => ['type' => 'boolean'],
                'created_from' => ['type' => 'string', 'description' => 'Coupon created_at start date or datetime.'],
                'created_to' => ['type' => 'string', 'description' => 'Coupon created_at end date or datetime.'],
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

        $collection = $this->couponCollectionFactory->create();
        $collection->setCurPage($page);
        $collection->setPageSize($pageSize);
        $collection->setOrder('coupon_id', 'DESC');

        $hasFilters = false;

        if (!empty($arguments['code'])) {
            $code = trim((string)$arguments['code']);
            if (strlen($code) < 2) {
                throw new LocalizedException(__('A coupon code search with at least 2 characters is required.'));
            }
            $collection->addFieldToFilter('code', ['like' => '%' . $code . '%']);
            $hasFilters = true;
        }

        if (!empty($arguments['rule_id'])) {
            $collection->addFieldToFilter('rule_id', (int)$arguments['rule_id']);
            $hasFilters = true;
        }

        if (!empty($arguments['unused_only'])) {
            $collection->addFieldToFilter('times_used', 0);
            $hasFilters = true;
        }

        if (!empty($arguments['created_from'])) {
            $collection->addFieldToFilter('created_at', ['gteq' => $this->normalizeDateBoundary((string)$arguments['created_from'], false)]);
            $hasFilters = true;
        }

        if (!empty($arguments['created_to'])) {
            $collection->addFieldToFilter('created_at', ['lteq' => $this->normalizeDateBoundary((string)$arguments['created_to'], true)]);
            $hasFilters = true;
        }

        if (!$hasFilters) {
            throw new LocalizedException(__('At least one coupon search filter is required.'));
        }

        $items = [];
        foreach ($collection as $coupon) {
            $items[] = [
                'coupon_id' => (int)$coupon->getCouponId(),
                'rule_id' => (int)$coupon->getRuleId(),
                'code' => (string)$coupon->getCode(),
                'usage_limit' => $coupon->getUsageLimit() !== null ? (int)$coupon->getUsageLimit() : null,
                'usage_per_customer' => $coupon->getUsagePerCustomer() !== null ? (int)$coupon->getUsagePerCustomer() : null,
                'times_used' => (int)$coupon->getTimesUsed(),
                'expiration_date' => $coupon->getExpirationDate(),
                'is_primary' => (bool)$coupon->getIsPrimary(),
                'created_at' => $coupon->getCreatedAt(),
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
