<?php
declare(strict_types=1);

namespace Amaresh\Mcp\Service;

use InvalidArgumentException;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;
use Amaresh\Mcp\Model\Config;
use Amaresh\Mcp\Model\ToolRegistry;
use Throwable;

class JsonRpcService
{
    public function __construct(
        private readonly Config $config,
        private readonly ToolRegistry $toolRegistry,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param array<string, mixed> $request
     * @return array<string, mixed>
     */
    public function handle(array $request): array
    {
        $id = $request['id'] ?? null;

        if (($request['jsonrpc'] ?? null) !== '2.0' || !isset($request['method'])) {
            return $this->error($id, -32600, 'Invalid JSON-RPC request.');
        }

        try {
            return [
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => $this->dispatch((string)$request['method'], (array)($request['params'] ?? [])),
            ];
        } catch (InvalidArgumentException $exception) {
            return $this->error($id, -32602, $exception->getMessage());
        } catch (LocalizedException $exception) {
            return $this->error($id, -32000, $exception->getMessage());
        } catch (Throwable $exception) {
            $this->logger->critical($exception);
            return $this->error($id, -32603, 'Internal MCP server error.');
        }
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function dispatch(string $method, array $params): array
    {
        return match ($method) {
            'initialize' => $this->initialize(),
            'tools/list' => $this->listTools(),
            'tools/call' => $this->callTool($params),
            'resources/list' => ['resources' => []],
            'prompts/list' => ['prompts' => []],
            default => throw new InvalidArgumentException(sprintf('Method "%s" is not supported.', $method)),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function initialize(): array
    {
        return [
            'protocolVersion' => $this->config->getProtocolVersion(),
            'serverInfo' => [
                'name' => $this->config->getServerName(),
                'version' => '1.0.0',
            ],
            'capabilities' => [
                'tools' => ['listChanged' => false],
                'resources' => ['subscribe' => false, 'listChanged' => false],
                'prompts' => ['listChanged' => false],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function listTools(): array
    {
        $tools = [];
        foreach ($this->toolRegistry->all() as $tool) {
            $securitySchemes = [
                [
                    'type' => 'oauth2',
                    'scopes' => ['mcp'],
                ],
            ];

            $tools[] = [
                'name' => $tool->getName(),
                'title' => $this->titleFromName($tool->getName()),
                'description' => $tool->getDescription(),
                'inputSchema' => $tool->getInputSchema(),
                'securitySchemes' => $securitySchemes,
                'annotations' => [
                    'readOnlyHint' => true,
                ],
                '_meta' => [
                    'securitySchemes' => $securitySchemes,
                ],
            ];
        }

        return ['tools' => $tools];
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function callTool(array $params): array
    {
        $name = (string)($params['name'] ?? '');
        $arguments = (array)($params['arguments'] ?? []);

        if ($name === '') {
            throw new InvalidArgumentException('Tool name is required.');
        }

        $result = $this->toolRegistry->get($name)->execute($arguments);

        return [
            'structuredContent' => $result,
            'content' => [
                [
                    'type' => 'text',
                    'text' => json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                ],
            ],
        ];
    }

    private function titleFromName(string $name): string
    {
        return ucwords(str_replace('_', ' ', $name));
    }

    /**
     * @return array<string, mixed>
     */
    private function error(mixed $id, int $code, string $message): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];
    }
}
