<?php
declare(strict_types=1);

namespace Amaresh\Mcp\Model\Tool;

use Amaresh\Mcp\Api\ToolInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;

class QuoteGet implements ToolInterface
{
    public function __construct(private readonly CartRepositoryInterface $quoteRepository)
    {
    }

    public function getName(): string
    {
        return 'quote_get';
    }

    public function getDescription(): string
    {
        return 'Returns detailed Magento quote/cart data by quote ID, including visible cart items.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'quote_id' => ['type' => 'integer', 'minimum' => 1],
            ],
            'required' => ['quote_id'],
            'additionalProperties' => false,
        ];
    }

    public function execute(array $arguments): array
    {
        $quoteId = (int)($arguments['quote_id'] ?? 0);
        if ($quoteId <= 0) {
            throw new LocalizedException(__('A valid quote_id is required.'));
        }

        try {
            $quote = $this->quoteRepository->get($quoteId);
        } catch (NoSuchEntityException) {
            throw new LocalizedException(__('Quote "%1" was not found.', $quoteId));
        }

        $items = [];
        foreach ($quote->getAllVisibleItems() as $item) {
            $items[] = [
                'item_id' => (int)$item->getItemId(),
                'product_id' => (int)$item->getProductId(),
                'sku' => $item->getSku(),
                'name' => $item->getName(),
                'qty' => (float)$item->getQty(),
                'price' => (float)$item->getPrice(),
                'row_total' => (float)$item->getRowTotal(),
            ];
        }

        return [
            'quote_id' => (int)$quote->getId(),
            'is_active' => (bool)$quote->getIsActive(),
            'store_id' => (int)$quote->getStoreId(),
            'customer_id' => $quote->getCustomerId() ? (int)$quote->getCustomerId() : null,
            'customer_email' => $quote->getCustomerEmail(),
            'customer_name' => trim((string)$quote->getCustomerFirstname() . ' ' . (string)$quote->getCustomerLastname()),
            'items_count' => (int)$quote->getItemsCount(),
            'items_qty' => (float)$quote->getItemsQty(),
            'subtotal' => (float)$quote->getSubtotal(),
            'grand_total' => (float)$quote->getGrandTotal(),
            'coupon_code' => $quote->getCouponCode() ?: null,
            'reserved_order_id' => $quote->getReservedOrderId() ?: null,
            'created_at' => $quote->getCreatedAt(),
            'updated_at' => $quote->getUpdatedAt(),
            'items' => $items,
        ];
    }
}
