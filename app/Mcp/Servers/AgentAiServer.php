<?php

namespace App\Mcp\Servers;

use App\Mcp\Prompts\DefineAgentsPrompt;
use App\Mcp\Prompts\OrchestrateComplexRequestPrompt;
use App\Mcp\Tools\ActionInterpretationTool;
use App\Mcp\Tools\AgentSelectionTool;
use App\Mcp\Tools\LanguageDetectTool;
use App\Mcp\Tools\MemoryExtractTool;
use App\Mcp\Tools\ResponseGenerationTool;
use App\Mcp\Tools\ThreadSummarizeTool;
use Laravel\Mcp\Server;

class AgentAiServer extends Server
{
    /**
     * The MCP server's name.
     */
    protected string $name = 'Agent AI Server';

    /**
     * The MCP server's version.
     */
    protected string $version = '1.0.0';

    /**
     * The MCP server's instructions for the LLM.
     */
    protected string $instructions = 'Agent AI system for intelligent email processing and automated responses. Use tools for structured operations and prompts for complex multi-agent orchestration.';

    /**
     * The tools registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Tool>>
     */
    protected array $tools = [
        ActionInterpretationTool::class,
        AgentSelectionTool::class,
        ResponseGenerationTool::class,
        LanguageDetectTool::class,
        ThreadSummarizeTool::class,
        MemoryExtractTool::class,
    ];

    /**
     * The resources registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Resource>>
     */
    protected array $resources = [
        //
    ];

    /**
     * The prompts registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Prompt>>
     */
    protected array $prompts = [
        DefineAgentsPrompt::class,
        OrchestrateComplexRequestPrompt::class,
    ];
}
