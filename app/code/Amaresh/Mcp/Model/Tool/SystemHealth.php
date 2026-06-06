<?php
declare(strict_types=1);

namespace Amaresh\Mcp\Model\Tool;

use Magento\Framework\App\ProductMetadataInterface;
use Magento\Store\Model\StoreManagerInterface;
use Amaresh\Mcp\Api\ToolInterface;

class SystemHealth implements ToolInterface
{
    public function __construct(
        private readonly ProductMetadataInterface $productMetadata,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function getName(): string
    {
        return 'system_health';
    }

    public function getDescription(): string
    {
        return 'Returns Magento application, edition, and store-view health context.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => new \stdClass(),
            'additionalProperties' => false,
        ];
    }

    public function execute(array $arguments): array
    {
        $stores = [];
        foreach ($this->storeManager->getStores() as $store) {
            $stores[] = [
                'id' => (int)$store->getId(),
                'code' => $store->getCode(),
                'name' => $store->getName(),
                'base_url' => $store->getBaseUrl(),
                'is_active' => (bool)$store->getIsActive(),
            ];
        }

        return [
            'status' => 'ok',
            'magento' => [
                'name' => $this->productMetadata->getName(),
                'edition' => $this->productMetadata->getEdition(),
                'version' => $this->productMetadata->getVersion(),
            ],
            'stores' => $stores,
        ];
    }
}
