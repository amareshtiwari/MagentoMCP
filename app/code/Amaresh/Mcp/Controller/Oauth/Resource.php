<?php
declare(strict_types=1);

namespace Amaresh\Mcp\Controller\Oauth;

use Amaresh\Mcp\Controller\CorsHeadersTrait;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpOptionsActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Controller\ResultInterface;

class Resource implements HttpGetActionInterface, HttpOptionsActionInterface
{
    use CorsHeadersTrait;

    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $jsonFactory,
        private readonly RawFactory $rawFactory
    ) {
    }

    public function execute(): ResultInterface
    {
        if (strtoupper((string)$this->request->getMethod()) === 'OPTIONS') {
            return $this->preflight($this->rawFactory);
        }

        $baseUrl = $this->getBaseUrl();
        $result = $this->jsonFactory->create();
        $result->setHeader('Content-Type', 'application/json', true);
        $result->setHeader('Cache-Control', 'no-store', true);
        $this->addCorsHeaders($result);

        return $result->setData([
            'resource' => $baseUrl . 'mcp/jsonrpc',
            'authorization_servers' => [
                rtrim($baseUrl, '/'),
            ],
            'scopes_supported' => ['mcp'],
            'bearer_methods_supported' => ['header'],
            'resource_documentation' => $baseUrl . 'mcp/oauth/help',
        ]);
    }

    private function getBaseUrl(): string
    {
        $scheme = $this->request->isSecure() ? 'https' : 'http';
        return $scheme . '://' . (string)$this->request->getHeader('Host') . '/';
    }
}
