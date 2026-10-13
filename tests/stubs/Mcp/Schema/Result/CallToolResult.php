<?php

namespace Mcp\Schema\Result;

if (!class_exists(CallToolResult::class)) {
    class CallToolResult
    {
        public function __construct(public readonly bool $isError = true)
        {
        }
    }
}
