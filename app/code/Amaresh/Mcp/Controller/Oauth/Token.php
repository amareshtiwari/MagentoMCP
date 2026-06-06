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

class Token implements HttpPostActionInterface, HttpOptionsActionInterface, CsrfAwareActionInterface
{
    use CorsHeadersTrait;

    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $jsonFactory,
        private readonly RawFactory $rawFactory,
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

        if ((string)$this->request->getParam('grant_type') !== 'authorization_code') {
            return $result->setHttpResponseCode(400)->setData(['error' => 'unsupported_grant_type']);
        }

        $token = $this->oauthService->exchangeCode(
            trim((string)$this->request->getParam('code')),
            trim((string)$this->request->getParam('client_id')),
            trim((string)$this->request->getParam('redirect_uri')),
            trim((string)$this->request->getParam('code_verifier')),
            trim((string)$this->request->getParam('resource'))
        );

        if (!$token) {
            return $result->setHttpResponseCode(400)->setData(['error' => 'invalid_grant']);
        }

        return $result->setData($token);
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
