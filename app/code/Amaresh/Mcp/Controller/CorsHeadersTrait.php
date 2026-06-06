<?php
declare(strict_types=1);

namespace Amaresh\Mcp\Controller;

use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Controller\ResultInterface;

trait CorsHeadersTrait
{
    private function addCorsHeaders(ResultInterface $result): void
    {
        $origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
        $allowedOrigins = [
            'https://claude.ai',
            'https://www.claude.ai',
            'https://claude.com',
            'https://www.claude.com',
            'https://chatgpt.com',
            'https://www.chatgpt.com',
            'https://chat.openai.com',
        ];

        if (in_array($origin, $allowedOrigins, true)) {
            $result->setHeader('Access-Control-Allow-Origin', $origin, true);
            $result->setHeader('Access-Control-Allow-Credentials', 'true', true);
            $result->setHeader('Vary', 'Origin', true);
        } else {
            $result->setHeader('Access-Control-Allow-Origin', '*', true);
        }

        $result->setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS', true);
        $result->setHeader(
            'Access-Control-Allow-Headers',
            'Authorization, Content-Type, Accept, Origin, X-Requested-With, MCP-Protocol-Version, Mcp-Session-Id',
            true
        );
        $result->setHeader('Access-Control-Expose-Headers', 'WWW-Authenticate, MCP-Protocol-Version, Mcp-Session-Id', true);
        $result->setHeader('Access-Control-Max-Age', '86400', true);
    }

    private function preflight(RawFactory $rawFactory): ResultInterface
    {
        $result = $rawFactory->create();
        $result->setHttpResponseCode(204);
        $this->addCorsHeaders($result);
        return $result->setContents('');
    }
}
