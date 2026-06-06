<?php
declare(strict_types=1);

namespace Amaresh\Mcp\Controller\Oauth;

use Amaresh\Mcp\Controller\CorsHeadersTrait;
use Amaresh\Mcp\Model\OAuth\Service as OAuthService;
use Magento\Framework\App\Action\HttpOptionsActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;

class Register implements HttpPostActionInterface, HttpOptionsActionInterface, CsrfAwareActionInterface
{
    use CorsHeadersTrait;

    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $jsonFactory,
        private readonly RawFactory $rawFactory,
        private readonly JsonSerializer $jsonSerializer,
        private readonly OAuthService $oauthService
    ) {
    }

    public function execute(): ResultInterface
    {
        if (strtoupper((string)$this->request->getMethod()) === 'OPTIONS') {
            return $this->preflight($this->rawFactory);
        }

        $result = $this->jsonFactory->create();
        $result->setHeader('Content-Type', 'application/json', true);
        $result->setHeader('Cache-Control', 'no-store', true);
        $this->addCorsHeaders($result);

        try {
            $payload = $this->jsonSerializer->unserialize($this->request->getContent() ?: '{}');
        } catch (\InvalidArgumentException) {
            return $result->setHttpResponseCode(400)->setData(['error' => 'invalid_client_metadata']);
        }

        $redirectUris = is_array($payload['redirect_uris'] ?? null) ? $payload['redirect_uris'] : [];
        $client = $this->oauthService->registerClient($redirectUris, (string)($payload['client_name'] ?? 'Claude MCP Connector'));

        return $result->setHttpResponseCode(201)->setData([
            'client_id' => $client['client_id'],
            'client_name' => $client['client_name'],
            'redirect_uris' => $client['redirect_uris'],
            'grant_types' => ['authorization_code'],
            'response_types' => ['code'],
            'scope' => 'mcp',
            'token_endpoint_auth_method' => 'none',
            'client_id_issued_at' => $client['created_at'],
        ]);
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
