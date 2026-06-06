<?php
declare(strict_types=1);

namespace Amaresh\Mcp\Model\Tool;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\Exception\LocalizedException;
use Amaresh\Mcp\Api\ToolInterface;
use Amaresh\Mcp\Model\Config;

class CatalogSearch implements ToolInterface
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly FilterBuilder $filterBuilder,
        private readonly SortOrderBuilder $sortOrderBuilder,
        private readonly Config $config
    ) {
    }

    public function getName(): string
    {
        return 'catalog_search';
    }

    public function getDescription(): string
    {
        return 'Searches enabled, catalog-visible products by SKU or product name.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string', 'description' => 'SKU or product name fragment.'],
                'page' => ['type' => 'integer', 'minimum' => 1],
                'page_size' => ['type' => 'integer', 'minimum' => 1, 'maximum' => $this->config->getMaxPageSize()],
            ],
            'required' => ['query'],
            'additionalProperties' => false,
        ];
    }

    public function execute(array $arguments): array
    {
        $query = trim((string)($arguments['query'] ?? ''));
        if ($query === '' || strlen($query) < 2) {
            throw new LocalizedException(__('A search query with at least 2 characters is required.'));
        }

        $page = max(1, (int)($arguments['page'] ?? 1));
        $pageSize = $this->config->normalizePageSize(isset($arguments['page_size']) ? (int)$arguments['page_size'] : null);

        $statusFilter = $this->filterBuilder->setField('status')->setValue(1)->setConditionType('eq')->create();
        $visibilityFilter = $this->filterBuilder
            ->setField('visibility')
            ->setValue([Visibility::VISIBILITY_IN_CATALOG, Visibility::VISIBILITY_IN_SEARCH, Visibility::VISIBILITY_BOTH])
            ->setConditionType('in')
            ->create();
        $nameFilter = $this->filterBuilder->setField('name')->setValue('%' . $query . '%')->setConditionType('like')->create();
        $skuFilter = $this->filterBuilder->setField('sku')->setValue('%' . $query . '%')->setConditionType('like')->create();
        $sortOrder = $this->sortOrderBuilder->setField('entity_id')->setDirection(SortOrder::SORT_DESC)->create();

        $criteria = $this->searchCriteriaBuilder
            ->addFilters([$statusFilter])
            ->addFilters([$visibilityFilter])
            ->addFilters([$nameFilter, $skuFilter])
            ->setCurrentPage($page)
            ->setPageSize($pageSize)
            ->setSortOrders([$sortOrder])
            ->create();

        $result = $this->productRepository->getList($criteria);
        $items = [];
        foreach ($result->getItems() as $product) {
            $items[] = [
                'id' => (int)$product->getId(),
                'sku' => $product->getSku(),
                'name' => $product->getName(),
                'type_id' => $product->getTypeId(),
                'price' => (float)$product->getPrice(),
                'status' => (int)$product->getStatus(),
                'visibility' => (int)$product->getVisibility(),
            ];
        }

        return [
            'query' => $query,
            'page' => $page,
            'page_size' => $pageSize,
            'total_count' => (int)$result->getTotalCount(),
            'items' => $items,
        ];
    }
}
