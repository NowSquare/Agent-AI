<?php

namespace App\Mcp\Tools;

use App\Services\UrlGuard;
use Illuminate\Support\Facades\Http;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ResolveRedirectTool extends Tool
{
    protected string $description = 'Follow redirects (HEAD) and return the final URL.';

    public function handle(Request $request): Response
    {
        $url = (string) $request->string('url');
        UrlGuard::assertSafeUrl($url);

        $resp = Http::timeout(10)->withOptions(['allow_redirects' => ['track_redirects' => true]])->head($url);
        $final = $resp->effectiveUri();

        return Response::json([
            'final_url' => (string) $final,
            'status' => $resp->status(),
        ]);
    }

    public function schema(\Illuminate\JsonSchema\JsonSchema $schema): array
    {
        return [
            'url' => $schema->string()->format('uri')->description('HTTP/HTTPS URL to resolve')->required(),
        ];
    }
}
