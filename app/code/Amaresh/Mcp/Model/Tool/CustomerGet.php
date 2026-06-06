<?php
declare(strict_types=1);

namespace Amaresh\Mcp\Model\Tool;

use Amaresh\Mcp\Api\ToolInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\AddressInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

class CustomerGet implements ToolInterface
{
    public function __construct(private readonly CustomerRepositoryInterface $customerRepository)
    {
    }

    public function getName(): string
    {
        return 'customer_get';
    }

    public function getDescription(): string
    {
        return 'Returns customer account details by Magento customer ID or email.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'customer_id' => ['type' => 'integer', 'minimum' => 1],
                'email' => ['type' => 'string'],
                'website_id' => ['type' => 'integer', 'minimum' => 0],
            ],
            'additionalProperties' => false,
        ];
    }

    public function execute(array $arguments): array
    {
        $customerId = (int)($arguments['customer_id'] ?? 0);
        $email = trim((string)($arguments['email'] ?? ''));
        $websiteId = isset($arguments['website_id']) ? (int)$arguments['website_id'] : null;

        if ($customerId <= 0 && $email === '') {
            throw new LocalizedException(__('A valid customer_id or email is required.'));
        }

        try {
            $customer = $customerId > 0
                ? $this->customerRepository->getById($customerId)
                : $this->customerRepository->get($email, $websiteId);
        } catch (NoSuchEntityException) {
            throw new LocalizedException(__('Customer was not found.'));
        }

        return $this->normalizeCustomer($customer);
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeCustomer(CustomerInterface $customer): array
    {
        $addresses = [];
        foreach ($customer->getAddresses() as $address) {
            $addresses[] = $this->normalizeAddress($address);
        }

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
            'default_billing' => $customer->getDefaultBilling(),
            'default_shipping' => $customer->getDefaultShipping(),
            'addresses' => $addresses,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeAddress(AddressInterface $address): array
    {
        return [
            'id' => (int)$address->getId(),
            'firstname' => $address->getFirstname(),
            'lastname' => $address->getLastname(),
            'company' => $address->getCompany(),
            'street' => $address->getStreet(),
            'city' => $address->getCity(),
            'region' => $address->getRegion() ? $address->getRegion()->getRegion() : null,
            'region_id' => $address->getRegionId(),
            'postcode' => $address->getPostcode(),
            'country_id' => $address->getCountryId(),
            'telephone' => $address->getTelephone(),
        ];
    }
}
