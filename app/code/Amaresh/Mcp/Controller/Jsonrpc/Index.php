<?php
declare(strict_types=1);

namespace Amaresh\Mcp\Controller\Jsonrpc;

use Amaresh\Mcp\Controller\CorsHeadersTrait;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpOptionsActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Amaresh\Mcp\Model\Config;
use Amaresh\Mcp\Model\OAuth\Service as OAuthService;
use Amaresh\Mcp\Service\JsonRpcService;

class Index implements HttpGetActionInterface, HttpPostActionInterface, HttpOptionsActionInterface, CsrfAwareActionInterface
{
    use CorsHeadersTrait;

    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $jsonFactory,
        private readonly RawFactory $rawFactory,
        private readonly JsonSerializer $jsonSerializer,
        private readonly Config $config,
        private readonly OAuthService $oauthService,
        private readonly JsonRpcService $jsonRpcService
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

        if (!$this->config->isEnabled()) {
            return $result->setHttpResponseCode(503)->setData([
                'jsonrpc' => '2.0',
                'id' => null,
                'error' => ['code' => -32000, 'message' => 'MCP endpoint is disabled.'],
            ]);
        }

        if (!$this->isAuthorized()) {
            $this->addAuthenticateHeader($result);

            return $result->setHttpResponseCode(401)->setData([
                'jsonrpc' => '2.0',
                'id' => null,
                'error' => ['code' => -32001, 'message' => 'Unauthorized MCP request.'],
            ]);
        }

        if (!$this->request->isPost()) {
            return $result->setHttpResponseCode(405)->setData([
                'jsonrpc' => '2.0',
                'id' => null,
                'error' => ['code' => -32600, 'message' => 'MCP JSON-RPC requests must use POST.'],
            ]);
        }

        try {
            $payload = $this->jsonSerializer->unserialize($this->request->getContent());
        } catch (\InvalidArgumentException) {
            return $result->setHttpResponseCode(400)->setData([
                'jsonrpc' => '2.0',
                'id' => null,
                'error' => ['code' => -32700, 'message' => 'Invalid JSON payload.'],
            ]);
        }

        if (!is_array($payload)) {
            return $result->setHttpResponseCode(400)->setData([
                'jsonrpc' => '2.0',
                'id' => null,
                'error' => ['code' => -32600, 'message' => 'JSON payload must be an object.'],
            ]);
        }

        if (!array_key_exists('id', $payload) && $this->isAcceptedClientMessage($payload)) {
            return $this->accepted();
        }

        return $result->setData($this->jsonRpcService->handle($payload));
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    private function isAuthorized(): bool
    {
        $header = (string)$this->request->getHeader('Authorization');
        if (!preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return false;
        }

        return $this->oauthService->validateAccessToken(trim($matches[1]));
    }

    private function addAuthenticateHeader(Json $result): void
    {
        $result->setHeader(
            'WWW-Authenticate',
                sprintf(
                    'Bearer resource_metadata="%s", scope="mcp"',
                    $this->getBaseUrl() . 'mcp/oauth/resource'
                ),
            true
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function isAcceptedClientMessage(array $payload): bool
    {
        return isset($payload['method']) || array_key_exists('result', $payload) || array_key_exists('error', $payload);
    }

    private function accepted(): Raw
    {
        $result = $this->rawFactory->create();
        $result->setHttpResponseCode(202);
        $result->setHeader('Cache-Control', 'no-store', true);
        return $result->setContents('');
    }

    private function getBaseUrl(): string
    {
        $scheme = $this->request->isSecure() ? 'https' : 'http';
        $host = (string)$this->request->getHeader('Host');
        return $scheme . '://' . $host . '/';
    }
}
