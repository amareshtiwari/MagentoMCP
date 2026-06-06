<?php
declare(strict_types=1);

namespace Amaresh\Mcp\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    private const XML_PATH_ENABLED = 'amaresh_mcp/general/enabled';
    private const XML_PATH_SERVER_NAME = 'amaresh_mcp/general/server_name';
    private const XML_PATH_PROTOCOL_VERSION = 'amaresh_mcp/general/protocol_version';
    private const XML_PATH_API_TOKEN = 'amaresh_mcp/security/api_token';
    private const XML_PATH_DEFAULT_PAGE_SIZE = 'amaresh_mcp/limits/default_page_size';
    private const XML_PATH_MAX_PAGE_SIZE = 'amaresh_mcp/limits/max_page_size';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EncryptorInterface $encryptor
    ) {
    }

    public function isEnabled(?int $websiteId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED, ScopeInterface::SCOPE_WEBSITE, $websiteId);
    }

    public function getServerName(?int $websiteId = null): string
    {
        return (string)$this->scopeConfig->getValue(self::XML_PATH_SERVER_NAME, ScopeInterface::SCOPE_WEBSITE, $websiteId);
    }

    public function getProtocolVersion(?int $websiteId = null): string
    {
        return (string)$this->scopeConfig->getValue(self::XML_PATH_PROTOCOL_VERSION, ScopeInterface::SCOPE_WEBSITE, $websiteId);
    }

    public function validateBearerToken(string $token, ?int $websiteId = null): bool
    {
        $configured = (string)$this->scopeConfig->getValue(self::XML_PATH_API_TOKEN, ScopeInterface::SCOPE_WEBSITE, $websiteId);
        $decrypted = $this->encryptor->decrypt($configured);
        $configured = $decrypted !== '' ? $decrypted : $configured;

        if ($configured === '' || $token === '') {
            return false;
        }

        return hash_equals($configured, $token);
    }

    public function getDefaultPageSize(?int $websiteId = null): int
    {
        $value = (int)$this->scopeConfig->getValue(self::XML_PATH_DEFAULT_PAGE_SIZE, ScopeInterface::SCOPE_WEBSITE, $websiteId);
        return max(1, $value ?: 10);
    }

    public function getMaxPageSize(?int $websiteId = null): int
    {
        $value = (int)$this->scopeConfig->getValue(self::XML_PATH_MAX_PAGE_SIZE, ScopeInterface::SCOPE_WEBSITE, $websiteId);
        return max(1, $value ?: 50);
    }

    public function normalizePageSize(?int $pageSize = null, ?int $websiteId = null): int
    {
        $pageSize = $pageSize ?: $this->getDefaultPageSize($websiteId);
        return min(max(1, $pageSize), $this->getMaxPageSize($websiteId));
    }
}
