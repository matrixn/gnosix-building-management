<?php

namespace App\Evidence;

use App\Models\Evidence\Version;
use Illuminate\Support\Facades\Http;

class OpenRouterExtractor implements Extractor
{
    public function extract(Version $version): array
    {
        $bytes = ProviderInput::bytes($version, 'openrouter');
        $s = app(Settings::class)->all();
        $response = Http::withToken($s['api_key'])->acceptJson()->timeout(90)->withOptions(['allow_redirects' => false])->post('https://openrouter.ai/api/v1/chat/completions', [
            'model' => $s['model'], 'stream' => false, 'max_tokens' => 12000,
            'provider' => ['require_parameters' => true, 'allow_fallbacks' => false],
            'messages' => [['role' => 'system', 'content' => ExtractionContract::instructions()], ['role' => 'user', 'content' => [['type' => 'text', 'text' => 'Extract candidate facts with exact source quotes.'], ['type' => 'image_url', 'image_url' => ['url' => 'data:'.$version->mime.';base64,'.$bytes]]]]],
            'response_format' => ['type' => 'json_schema', 'json_schema' => ['name' => 'building_evidence', 'strict' => true, 'schema' => ExtractionContract::schema()]],
        ]);
        $data = ProviderInput::response($response);
        $choice = $data['choices'][0] ?? [];
        if (($choice['finish_reason'] ?? null) !== 'stop' || ! empty($choice['message']['tool_calls'])) {
            throw new \RuntimeException('provider_incomplete');
        }

        return ExtractionContract::decode($choice['message']['content'] ?? '');
    }
}
