# MagentoMCP - Magento 2 Model Context Protocol (MCP) Server Module for AI Assistants

MagentoMCP is a Magento 2 MCP server module that exposes selected Magento catalog, sales, customer, cart, quote, coupon, and system data through a token-protected JSON-RPC endpoint.

This Magento MCP extension is designed for Magento 2 and Adobe Commerce stores that need a Model Context Protocol style integration layer for AI assistants, commerce automation tools, analytics agents, support copilots, or custom MCP clients that need structured access to Magento commerce data.

Use MagentoMCP when you want an AI-ready Magento integration that can help external tools search products, inspect orders, review customers, analyze quotes, report coupon performance, and summarize commerce activity through controlled API access.

## Keywords

`Magento 2 MCP`, `Magento MCP`, `Magento MCP server`, `Magento Model Context Protocol`, `Magento AI assistant`, `Magento AI integration`, `Magento 2 AI module`, `Magento 2 AI connector`, `Magento commerce AI`, `Adobe Commerce MCP`, `MCP server for Magento`, `Model Context Protocol Magento`, `Magento JSON-RPC API`, `Magento catalog MCP`, `Magento sales MCP`, `Magento order MCP`, `Magento customer MCP`, `Magento quote MCP`, `Magento coupon MCP`, `Magento automation module`, `Amaresh MCP`, `Amaresh_Mcp`

## Search-Friendly Description

MagentoMCP is an open-source Magento 2 module for connecting Magento commerce data with MCP-compatible AI tools and automation systems. It provides a Magento Model Context Protocol server-style endpoint for product search, order lookup, customer lookup, quote search, coupon reporting, cart price rule search, and system health checks.

If you are searching for a Magento MCP server, Magento 2 AI connector, Adobe Commerce MCP module, Magento Model Context Protocol integration, or a way to connect Magento data to AI assistants, this repository is built for that use case.

## Module

- Module name: `Amaresh_Mcp`
- Magento path: `app/code/Amaresh/Mcp`
- Frontend route: `/mcp`
- JSON-RPC endpoint: `/mcp/jsonrpc`
- OAuth resource metadata: `/mcp/oauth/resource`
- Package name: `amareshtiwari/magento-mcp`

## Features

- MCP JSON-RPC endpoint for Magento data access
- Bearer token authorization for MCP requests
- Admin configuration under `Stores > Configuration > Amaresh > Amaresh MCP`
- CORS support for browser-based MCP clients
- Configurable default and maximum page sizes
- OAuth discovery/resource metadata endpoints
- Commerce-focused tools for catalog, sales, customers, quotes, coupons, and system health
- Useful for AI assistants, internal support copilots, reporting agents, and Magento automation workflows

## Use Cases

- Connect Magento 2 data to MCP-compatible AI assistants and clients
- Build a Magento AI assistant that can search catalog, order, customer, quote, and coupon data
- Search products, orders, customers, quotes, coupons, and cart price rules
- Build internal commerce support tools using structured JSON-RPC responses
- Expose controlled Magento store data through token-protected endpoints
- Create reporting or automation workflows around Magento sales and customer activity
- Create commerce analytics agents for coupon performance, customer activity, product cart activity, and quote activity
- Provide a structured Magento data bridge for AI workflows without exposing unrestricted admin access

## Available Tools

The module currently registers these MCP tools:

- `system_health`
- `catalog_search`
- `catalog_product_get`
- `sales_order_get`
- `sales_order_search`
- `customer_get`
- `customer_search`
- `cart_price_rule_search`
- `sales_rule_coupon_search`
- `quote_search`
- `quote_get`
- `quote_activity_summary`
- `coupon_performance_summary`
- `customer_activity_summary`
- `product_cart_summary`

## Installation

### Manual Installation

Copy the module into your Magento installation:

```bash
app/code/Amaresh/Mcp
```

Then run:

```bash
php bin/magento module:enable Amaresh_Mcp
php bin/magento setup:upgrade
php bin/magento cache:flush
```

For production mode, also run the required dependency injection compilation and static content deployment commands for your Magento environment.

### Composer Installation

If this repository is added to Packagist or configured as a VCS repository in your Magento project's `composer.json`, install it with:

```bash
composer require amareshtiwari/magento-mcp
php bin/magento module:enable Amaresh_Mcp
php bin/magento setup:upgrade
php bin/magento cache:flush
```

## Composer VCS Repository Example

Until the package is published on Packagist, you can install it from GitHub by adding it as a VCS repository in your Magento project's root `composer.json`:

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/amareshtiwari/MagentoMCP"
    }
  ]
}
```

Then run:

```bash
composer require amareshtiwari/magento-mcp
```

## Configuration

In the Magento admin panel, go to:

```text
Stores > Configuration > Amaresh > Amaresh MCP
```

Configure:

- Enable MCP Endpoint
- Server Name
- MCP Protocol Version
- Bearer Token
- Default Page Size
- Maximum Page Size

The endpoint is disabled by default. Enable it and set a bearer token before using the JSON-RPC API.

## Authentication

Send the configured bearer token with each MCP JSON-RPC request:

```http
Authorization: Bearer <token>
```

Unauthorized requests return a JSON-RPC error response.

## Example Request

```bash
curl -X POST "https://example.com/mcp/jsonrpc" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <token>" \
  -d '{
    "jsonrpc": "2.0",
    "id": 1,
    "method": "tools/list",
    "params": {}
  }'
```

## Search-Friendly Summary

MagentoMCP is a Magento 2 extension for exposing Magento commerce data through an MCP server endpoint. It can be used as a Magento MCP bridge for AI tools, MCP clients, JSON-RPC integrations, and automation systems that need access to product, order, customer, quote, coupon, cart price rule, and system health data.

Common search terms for this project include Magento MCP server, Magento 2 MCP module, Magento Model Context Protocol server, Adobe Commerce MCP integration, Magento AI assistant module, Magento AI connector, Magento JSON-RPC commerce API, and MCP server for Magento stores.

## FAQ

### What is MagentoMCP?

MagentoMCP is a Magento 2 module that provides a Model Context Protocol style JSON-RPC endpoint for secure, structured access to Magento commerce data.

### Is MagentoMCP an MCP server for Magento 2?

Yes. MagentoMCP is built to expose Magento 2 data through an MCP-style server endpoint so MCP-compatible clients, AI assistants, and automation tools can call defined commerce tools.

### Can MagentoMCP be used with AI assistants?

Yes. MagentoMCP is designed for AI assistant workflows that need controlled access to Magento catalog, order, customer, quote, coupon, and reporting data. The exact client integration depends on the AI platform or MCP client you connect to it.

### Does MagentoMCP support Adobe Commerce?

MagentoMCP targets Magento 2 module architecture and can be evaluated for Adobe Commerce installations that support compatible Magento 2 modules. Always test in a staging environment before production use.

### What Magento data can AI tools access through this module?

The module includes tools for catalog search, product lookup, order lookup, order search, customer lookup, customer search, quote lookup, quote search, coupon search, cart price rule search, quote activity summaries, coupon performance summaries, customer activity summaries, product cart summaries, and system health checks.

## Dependencies

The module declares sequence dependencies on:

- `Magento_Catalog`
- `Magento_Sales`
- `Magento_Store`

## Repository

GitHub: `https://github.com/amareshtiwari/MagentoMCP`

Clone with HTTPS:

```bash
git clone https://github.com/amareshtiwari/MagentoMCP.git
```

Clone with SSH:

```bash
git clone git@github.com:amareshtiwari/MagentoMCP.git
```
