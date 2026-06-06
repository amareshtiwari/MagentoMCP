<?php
declare(strict_types=1);

namespace Amaresh\Mcp\Model\Tool;

use Amaresh\Mcp\Api\ToolInterface;
use Amaresh\Mcp\Model\Config;
use Magento\Framework\Exception\LocalizedException;
use Magento\SalesRule\Model\ResourceModel\Rule\CollectionFactory;

class CartPriceRuleSearch implements ToolInterface
{
    public function __construct(
        private readonly CollectionFactory $ruleCollectionFactory,
        private readonly Config $config
    ) {
    }

    public function getName(): string
    {
        return 'cart_price_rule_search';
    }

    public function getDescription(): string
    {
        return 'Searches Magento cart price rules, including currently active coupon and promotion rules.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string', 'description' => 'Rule name, description, or coupon code fragment.'],
                'rule_id' => ['type' => 'integer', 'minimum' => 1],
                'coupon_code' => ['type' => 'string'],
                'is_active' => ['type' => 'boolean'],
                'active_now' => ['type' => 'boolean', 'description' => 'When true, only rules active for today are returned.'],
                'website_id' => ['type' => 'integer', 'minimum' => 1],
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

        $collection = $this->ruleCollectionFactory->create();
        $collection->setCurPage($page);
        $collection->setPageSize($pageSize);
        $collection->setOrder('rule_id', 'DESC');

        $hasFilters = false;

        if (!empty($arguments['rule_id'])) {
            $collection->addFieldToFilter('rule_id', (int)$arguments['rule_id']);
            $hasFilters = true;
        }

        if (array_key_exists('is_active', $arguments)) {
            $collection->addFieldToFilter('is_active', (bool)$arguments['is_active'] ? 1 : 0);
            $hasFilters = true;
        }

        if (!empty($arguments['coupon_code'])) {
            $collection->addFieldToFilter('coupon_code', ['like' => '%' . trim((string)$arguments['coupon_code']) . '%']);
            $hasFilters = true;
        }

        $query = trim((string)($arguments['query'] ?? ''));
        if ($query !== '') {
            if (strlen($query) < 2) {
                throw new LocalizedException(__('A search query with at least 2 characters is required.'));
            }

            $collection->addFieldToFilter(
                ['name', 'description', 'coupon_code'],
                [
                    ['like' => '%' . $query . '%'],
                    ['like' => '%' . $query . '%'],
                    ['like' => '%' . $query . '%'],
                ]
            );
            $hasFilters = true;
        }

        if (!empty($arguments['website_id']) && method_exists($collection, 'addWebsiteFilter')) {
            $collection->addWebsiteFilter((int)$arguments['website_id']);
            $hasFilters = true;
        }

        if (!empty($arguments['active_now'])) {
            $today = date('Y-m-d');
            $collection->addFieldToFilter('is_active', 1);
            $collection->addFieldToFilter(['from_date', 'from_date'], [['null' => true], ['lteq' => $today]]);
            $collection->addFieldToFilter(['to_date', 'to_date'], [['null' => true], ['gteq' => $today]]);
            $hasFilters = true;
        }

        if (!$hasFilters) {
            throw new LocalizedException(__('At least one cart price rule filter is required.'));
        }

        $items = [];
        foreach ($collection as $rule) {
            $items[] = [
                'rule_id' => (int)$rule->getRuleId(),
                'name' => (string)$rule->getName(),
                'description' => (string)$rule->getDescription(),
                'is_active' => (bool)$rule->getIsActive(),
                'coupon_type' => (int)$rule->getCouponType(),
                'coupon_code' => $rule->getCouponCode() ?: null,
                'uses_per_coupon' => $rule->getUsesPerCoupon() !== null ? (int)$rule->getUsesPerCoupon() : null,
                'uses_per_customer' => $rule->getUsesPerCustomer() !== null ? (int)$rule->getUsesPerCustomer() : null,
                'from_date' => $rule->getFromDate(),
                'to_date' => $rule->getToDate(),
                'sort_order' => (int)$rule->getSortOrder(),
                'discount_amount' => (float)$rule->getDiscountAmount(),
                'simple_action' => $rule->getSimpleAction(),
                'website_ids' => array_map('intval', (array)$rule->getWebsiteIds()),
                'customer_group_ids' => array_map('intval', (array)$rule->getCustomerGroupIds()),
            ];
        }

        return [
            'page' => $page,
            'page_size' => $pageSize,
            'total_count' => (int)$collection->getSize(),
            'items' => $items,
        ];
    }
}
