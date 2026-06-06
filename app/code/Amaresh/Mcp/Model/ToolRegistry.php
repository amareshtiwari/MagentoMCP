<?php
declare(strict_types=1);

namespace Amaresh\Mcp\Model;

use InvalidArgumentException;
use Amaresh\Mcp\Api\ToolInterface;

class ToolRegistry
{
    /**
     * @param array<string, ToolInterface> $tools
     */
    public function __construct(private readonly array $tools = [])
    {
    }

    /**
     * @return ToolInterface[]
     */
    public function all(): array
    {
        return array_values($this->tools);
    }

    public function get(string $name): ToolInterface
    {
        if (!isset($this->tools[$name])) {
            throw new InvalidArgumentException(sprintf('Unknown tool "%s".', $name));
        }

        return $this->tools[$name];
    }
}
