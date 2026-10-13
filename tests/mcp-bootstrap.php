<?php

// Load guarded shims only when the optional MCP module or SDK is absent.
$mcpStubs = [
    'Mcp/Server/Builder.php',
    'Mcp/Schema/Result/CallToolResult.php',
    'QUI/AI/MCP/ProviderInterface.php',
    'QUI/AI/MCP/Server.php',
    'QUI/AI/MCP/ToolHelper.php'
];

foreach ($mcpStubs as $mcpStub) {
    require_once __DIR__ . '/stubs/' . $mcpStub;
}
