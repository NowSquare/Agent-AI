<?php

namespace App\Mcp\Tools;

use App\Services\UrlGuard;
use Illuminate\Support\Facades\Http;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ExtractMetadataTool extends Tool
{
    protected string $description = 'From HTML, extract <title> and <meta name="description"> with minimal fetch.';

    public function handle(Request $request): Response
    {
        $url = (string) $request->string('url');
        UrlGuard::assertSafeUrl($url);

        $resp = Http::timeout(10)->get($url);
        $html = (string) $resp->body();
        $snippet = mb_substr($html, 0, 10000); // inspect first 10KB

        $title = null;
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $snippet, $m)) {
            $title = trim(html_entity_decode($m[1]));
        }

        $description = null;
        if (preg_match('/<meta[^>]+name=["\']description["\'][^>]*>/i', $snippet, $m)) {
            if (preg_match('/content=["\']([^"\']+)["\']/', $m[0], $m2)) {
                $description = trim(html_entity_decode($m2[1]));
            }
        }

        return Response::json([
            'status' => $resp->status(),
            'title' => $title,
            'description' => $description,
        ]);
    }

    public function schema(\Illuminate\JsonSchema\JsonSchema $schema): array
    {
        return [
            'url' => $schema->string()->format('uri')->description('HTTP/HTTPS URL to parse')->required(),
        ];
    }
}
