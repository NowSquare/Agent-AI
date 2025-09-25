<?php

namespace App\Mcp\Tools;

use App\Services\UrlGuard;
use Illuminate\Support\Facades\Http;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class FetchUrlTool extends Tool
{
    protected string $description = 'Fetch up to 2048 bytes from a URL (GET), returning status, content-type, and truncated body.';

    public function handle(Request $request): Response
    {
        $url = (string) $request->string('url');
        $max = (int) $request->integer('max_bytes', 2048);

        UrlGuard::assertSafeUrl($url);

        $resp = Http::timeout(10)->get($url);
        $body = (string) $resp->body();
        $truncated = mb_substr($body, 0, max(1, min($max, 2048)));

        return Response::json([
            'ok' => $resp->successful(),
            'status' => $resp->status(),
            'content_type' => $resp->header('Content-Type'),
            'content' => $truncated,
            'bytes_returned' => strlen($truncated),
        ]);
    }

    public function schema(\Illuminate\JsonSchema\JsonSchema $schema): array
    {
        return [
            'url' => $schema->string()->format('uri')->description('HTTP/HTTPS URL to fetch')->required(),
            'max_bytes' => $schema->integer()->minimum(1)->maximum(2048)->description('Maximum bytes to return (≤2048)'),
        ];
    }
}
