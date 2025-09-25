<?php

namespace App\Mcp\Tools;

use Carbon\CarbonImmutable;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetDatetimeTool extends Tool
{
    protected string $description = 'Return current date/time in a given timezone and format.';

    public function handle(Request $request): Response
    {
        $tz = (string) $request->string('timezone', 'UTC');
        $fmt = (string) $request->string('format', CarbonImmutable::ISO8601);
        try {
            $now = CarbonImmutable::now($tz);
        } catch (\Throwable $e) {
            $now = CarbonImmutable::now('UTC');
        }

        return Response::json([
            'timezone' => $now->timezoneName,
            'iso' => $now->toIso8601String(),
            'formatted' => $now->format($fmt),
            'epoch' => $now->getTimestamp(),
        ]);
    }

    public function schema(\Illuminate\JsonSchema\JsonSchema $schema): array
    {
        return [
            'timezone' => $schema->string()->description('IANA timezone like Europe/Amsterdam').
                pattern('[A-Za-z_\/]+'),
            'format' => $schema->string()->description('PHP date() format or ISO8601'),
        ];
    }
}
