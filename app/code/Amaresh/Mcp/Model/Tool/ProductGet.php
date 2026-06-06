<?php
declare(strict_types=1);

namespace Amaresh\Mcp\Model\Tool;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Amaresh\Mcp\Api\ToolInterface;

class ProductGet implements ToolInterface
{
    public function __construct(private readonly ProductRepositoryInterface $productRepository)
    {
    }

    public function getName(): string
    {
        return 'catalog_product_get';
    }

    public function getDescription(): string
    {
        return 'Returns product details by SKU.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'sku' => ['type' => 'string'],
                'store_id' => ['type' => 'integer', 'minimum' => 0],
            ],
            'required' => ['sku'],
            'additionalProperties' => false,
        ];
    }

    public function execute(array $arguments): array
    {
        $sku = trim((string)($arguments['sku'] ?? ''));
        if ($sku === '') {
            throw new LocalizedException(__('SKU is required.'));
        }

        try {
            $storeId = isset($arguments['store_id']) ? (int)$arguments['store_id'] : null;
            $product = $this->productRepository->get($sku, false, $storeId);
        } catch (NoSuchEntityException) {
            throw new LocalizedException(__('Product with SKU "%1" was not found.', $sku));
        }

        return [
            'id' => (int)$product->getId(),
            'sku' => $product->getSku(),
            'name' => $product->getName(),
            'type_id' => $product->getTypeId(),
            'attribute_set_id' => (int)$product->getAttributeSetId(),
            'price' => (float)$product->getPrice(),
            'special_price' => $product->getSpecialPrice() !== null ? (float)$product->getSpecialPrice() : null,
            'status' => (int)$product->getStatus(),
            'visibility' => (int)$product->getVisibility(),
            'url_key' => (string)$product->getUrlKey(),
            'created_at' => (string)$product->getCreatedAt(),
            'updated_at' => (string)$product->getUpdatedAt(),
        ];
    }
}
