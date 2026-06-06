<?php
declare(strict_types=1);

namespace Amaresh\Mcp\Controller;

use Magento\Framework\App\Action\Forward;
use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\RouterInterface;

class Router implements RouterInterface
{
    public function __construct(
        private readonly ActionFactory $actionFactory
    ) {
    }

    public function match(RequestInterface $request)
    {
        $pathInfo = trim((string)$request->getPathInfo(), '/');

        if ($pathInfo === 'mcp') {
            return $this->forward($request, 'index', 'index');
        }

        if (in_array($pathInfo, $this->authorizationMetadataPaths(), true)) {
            return $this->forward($request, 'oauth', 'index');
        }

        if (in_array($pathInfo, $this->protectedResourceMetadataPaths(), true)) {
            return $this->forward($request, 'oauth', 'resource');
        }

        return null;
    }

    /**
     * @return string[]
     */
    private function authorizationMetadataPaths(): array
    {
        return [
            '.well-known/oauth-authorization-server',
            '.well-known/oauth-authorization-server/mcp/jsonrpc',
            '.well-known/openid-configuration',
            '.well-known/openid-configuration/mcp/jsonrpc',
            'mcp/jsonrpc/.well-known/oauth-authorization-server',
            'mcp/jsonrpc/.well-known/openid-configuration',
        ];
    }

    /**
     * @return string[]
     */
    private function protectedResourceMetadataPaths(): array
    {
        return [
            '.well-known/oauth-protected-resource',
            '.well-known/oauth-protected-resource/mcp/jsonrpc',
            'mcp/jsonrpc/.well-known/oauth-protected-resource',
        ];
    }

    private function forward(RequestInterface $request, string $controller, string $action): Forward
    {
        $request->setModuleName('mcp');
        $request->setControllerName($controller);
        $request->setActionName($action);
        $request->setPathInfo('/mcp/' . $controller . '/' . $action);

        return $this->actionFactory->create(Forward::class);
    }
}
