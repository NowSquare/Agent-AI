<?php

namespace App\Mcp\Tools;

use App\Services\UrlGuard;
use Illuminate\Support\Facades\Http;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class HttpHeadTool extends Tool
{
    protected string $description = 'Perform a HEAD request for a URL and return status and headers (bounded).';

    public function handle(Request $request): Response
    {
        $url = (string) $request->string('url');
        UrlGuard::assertSafeUrl($url);

        $resp = Http::timeout(10)->head($url);

        return Response::json([
            'ok' => $resp->successful(),
            'status' => $resp->status(),
            'headers' => collect($resp->headers())
                ->map(fn ($v) => is_array($v) ? implode(', ', $v) : (string) $v)
                ->take(25)
                ->toArray(),
        ]);
    }

    public function schema(\Illuminate\JsonSchema\JsonSchema $schema): array
    {
        return [
            'url' => $schema->string()->format('uri')->description('HTTP/HTTPS URL to check')->required(),
        ];
    }
}
