<?php
declare(strict_types=1);

namespace Amaresh\Mcp\Model\Tool;

use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Amaresh\Mcp\Api\ToolInterface;

class OrderGet implements ToolInterface
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly FilterBuilder $filterBuilder
    ) {
    }

    public function getName(): string
    {
        return 'sales_order_get';
    }

    public function getDescription(): string
    {
        return 'Returns operational order details by Magento order entity ID or order number/increment ID.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'order_id' => ['type' => 'integer', 'minimum' => 1],
                'increment_id' => ['type' => 'string', 'description' => 'Magento order number, for example 000000010.'],
            ],
            'additionalProperties' => false,
        ];
    }

    public function execute(array $arguments): array
    {
        $orderId = (int)($arguments['order_id'] ?? 0);
        $incrementId = trim((string)($arguments['increment_id'] ?? ''));
        if ($orderId <= 0 && $incrementId === '') {
            throw new LocalizedException(__('A valid order_id or increment_id is required.'));
        }

        try {
            $order = $orderId > 0
                ? $this->orderRepository->get($orderId)
                : $this->getByIncrementId($incrementId);
        } catch (NoSuchEntityException) {
            throw new LocalizedException(__('Order "%1" was not found.', $orderId > 0 ? (string)$orderId : $incrementId));
        }

        return $this->normalizeOrder($order);
    }

    private function getByIncrementId(string $incrementId): OrderInterface
    {
        $criteria = $this->searchCriteriaBuilder
            ->addFilters([
                $this->filterBuilder
                    ->setField('increment_id')
                    ->setValue($incrementId)
                    ->setConditionType('eq')
                    ->create(),
            ])
            ->setPageSize(1)
            ->create();

        $items = $this->orderRepository->getList($criteria)->getItems();
        $order = reset($items);
        if (!$order) {
            throw new NoSuchEntityException(__('Order with increment ID "%1" does not exist.', $incrementId));
        }

        return $order;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeOrder(Order $order): array
    {
        $items = [];
        foreach ($order->getAllVisibleItems() as $item) {
            $items[] = [
                'item_id' => (int)$item->getItemId(),
                'sku' => $item->getSku(),
                'name' => $item->getName(),
                'qty_ordered' => (float)$item->getQtyOrdered(),
                'row_total' => (float)$item->getRowTotal(),
            ];
        }

        return [
            'entity_id' => (int)$order->getEntityId(),
            'increment_id' => $order->getIncrementId(),
            'state' => $order->getState(),
            'status' => $order->getStatus(),
            'store_id' => (int)$order->getStoreId(),
            'created_at' => $order->getCreatedAt(),
            'updated_at' => $order->getUpdatedAt(),
            'customer_email' => $order->getCustomerEmail(),
            'customer_name' => trim((string)$order->getCustomerFirstname() . ' ' . (string)$order->getCustomerLastname()),
            'currency' => $order->getOrderCurrencyCode(),
            'grand_total' => (float)$order->getGrandTotal(),
            'subtotal' => (float)$order->getSubtotal(),
            'shipping_amount' => (float)$order->getShippingAmount(),
            'payment_method' => $order->getPayment() ? $order->getPayment()->getMethod() : null,
            'items' => $items,
        ];
    }
}
