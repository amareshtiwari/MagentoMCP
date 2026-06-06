<?php
declare(strict_types=1);

namespace Amaresh\Mcp\Model\Tool;

use Amaresh\Mcp\Api\ToolInterface;
use Amaresh\Mcp\Model\Config;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\Exception\LocalizedException;

class CustomerSearch implements ToolInterface
{
    public function __construct(
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly FilterBuilder $filterBuilder,
        private readonly SortOrderBuilder $sortOrderBuilder,
        private readonly Config $config
    ) {
    }

    public function getName(): string
    {
        return 'customer_search';
    }

    public function getDescription(): string
    {
        return 'Searches customers by email, name, customer ID, website, or created date.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string', 'description' => 'Email, first name, or last name fragment.'],
                'customer_id' => ['type' => 'integer', 'minimum' => 1],
                'email' => ['type' => 'string'],
                'firstname' => ['type' => 'string'],
                'lastname' => ['type' => 'string'],
                'website_id' => ['type' => 'integer', 'minimum' => 0],
                'created_from' => ['type' => 'string', 'description' => 'Customer created_at start date or datetime, for example 2026-04-20.'],
                'created_to' => ['type' => 'string', 'description' => 'Customer created_at end date or datetime, for example 2026-05-20.'],
                'page' => ['type' => 'integer', 'minimum' => 1],
                'page_size' => ['type' => 'integer', 'minimum' => 1, 'maximum' => $this->config->getMaxPageSize()],
            ],
            'additionalProperties' => false,
        ];
    }

    public function execute(array $arguments): array
    {
        $criteriaBuilder = $this->searchCriteriaBuilder;
        $hasFilters = false;

        $query = trim((string)($arguments['query'] ?? ''));
        if ($query !== '') {
            if (strlen($query) < 2) {
                throw new LocalizedException(__('A search query with at least 2 characters is required.'));
            }

            $criteriaBuilder->addFilters([
                $this->likeFilter('email', $query),
                $this->likeFilter('firstname', $query),
                $this->likeFilter('lastname', $query),
            ]);
            $hasFilters = true;
        }

        foreach (['email', 'firstname', 'lastname'] as $field) {
            if (!empty($arguments[$field])) {
                $criteriaBuilder->addFilters([$this->likeFilter($field, trim((string)$arguments[$field]))]);
                $hasFilters = true;
            }
        }

        if (!empty($arguments['customer_id'])) {
            $criteriaBuilder->addFilters([
                $this->filterBuilder
                    ->setField('entity_id')
                    ->setValue((int)$arguments['customer_id'])
                    ->setConditionType('eq')
                    ->create(),
            ]);
            $hasFilters = true;
        }

        if (array_key_exists('website_id', $arguments)) {
            $criteriaBuilder->addFilters([
                $this->filterBuilder
                    ->setField('website_id')
                    ->setValue((int)$arguments['website_id'])
                    ->setConditionType('eq')
                    ->create(),
            ]);
            $hasFilters = true;
        }

        if (!empty($arguments['created_from'])) {
            $criteriaBuilder->addFilters([
                $this->filterBuilder
                    ->setField('created_at')
                    ->setValue($this->normalizeDateBoundary((string)$arguments['created_from'], false))
                    ->setConditionType('gteq')
                    ->create(),
            ]);
            $hasFilters = true;
        }

        if (!empty($arguments['created_to'])) {
            $criteriaBuilder->addFilters([
                $this->filterBuilder
                    ->setField('created_at')
                    ->setValue($this->normalizeDateBoundary((string)$arguments['created_to'], true))
                    ->setConditionType('lteq')
                    ->create(),
            ]);
            $hasFilters = true;
        }

        if (!$hasFilters) {
            throw new LocalizedException(__('At least one customer search filter is required.'));
        }

        $page = max(1, (int)($arguments['page'] ?? 1));
        $pageSize = $this->config->normalizePageSize(isset($arguments['page_size']) ? (int)$arguments['page_size'] : null);
        $sortOrder = $this->sortOrderBuilder->setField('created_at')->setDirection(SortOrder::SORT_DESC)->create();

        $criteria = $criteriaBuilder
            ->setCurrentPage($page)
            ->setPageSize($pageSize)
            ->setSortOrders([$sortOrder])
            ->create();

        $result = $this->customerRepository->getList($criteria);
        $items = [];
        foreach ($result->getItems() as $customer) {
            $items[] = $this->normalizeCustomerSummary($customer);
        }

        return [
            'page' => $page,
            'page_size' => $pageSize,
            'total_count' => (int)$result->getTotalCount(),
            'items' => $items,
        ];
    }

    private function likeFilter(string $field, string $value): \Magento\Framework\Api\Filter
    {
        return $this->filterBuilder
            ->setField($field)
            ->setValue('%' . $value . '%')
            ->setConditionType('like')
            ->create();
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeCustomerSummary(CustomerInterface $customer): array
    {
        return [
            'id' => (int)$customer->getId(),
            'email' => $customer->getEmail(),
            'firstname' => $customer->getFirstname(),
            'lastname' => $customer->getLastname(),
            'fullname' => trim((string)$customer->getFirstname() . ' ' . (string)$customer->getLastname()),
            'group_id' => (int)$customer->getGroupId(),
            'store_id' => (int)$customer->getStoreId(),
            'website_id' => (int)$customer->getWebsiteId(),
            'created_at' => $customer->getCreatedAt(),
            'updated_at' => $customer->getUpdatedAt(),
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
