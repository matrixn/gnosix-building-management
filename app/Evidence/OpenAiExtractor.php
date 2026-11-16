<?php

namespace App\Evidence;

use App\Models\Evidence\Version;
use Illuminate\Support\Facades\Http;

class OpenAiExtractor implements Extractor
{
    public function extract(Version $version): array
    {
        $encoded = ProviderInput::bytes($version, 'openai');
        $s = app(Settings::class)->all();
        $file = $version->mime === 'application/pdf' ? ['type' => 'input_file', 'filename' => 'evidence.pdf', 'file_data' => 'data:application/pdf;base64,'.$encoded] : ['type' => 'input_image', 'image_url' => 'data:'.$version->mime.';base64,'.$encoded];
        $schema = ExtractionContract::schema();
        $response = Http::withToken($s['api_key'])->acceptJson()->timeout(90)->withOptions(['allow_redirects' => false])->post('https://api.openai.com/v1/responses', ['model' => $s['model'], 'store' => false, 'max_output_tokens' => 12000, 'instructions' => ExtractionContract::instructions(), 'input' => [['role' => 'user', 'content' => [['type' => 'input_text', 'text' => 'Classify this document and return candidate facts with their exact supporting source quotes.'], $file]]], 'text' => ['format' => ['type' => 'json_schema', 'name' => 'building_evidence', 'strict' => true, 'schema' => $schema]]]);
        if (! $response->successful()) {
            throw new \RuntimeException('provider_http_'.$response->status());
        }
        if (strlen($response->body()) > 2000000) {
            throw new \RuntimeException('provider_invalid');
        }
        $data = $response->json();
        if (($data['status'] ?? null) !== 'completed') {
            throw new \RuntimeException('provider_incomplete');
        }
        foreach ($data['output'] ?? [] as $item) {
            foreach ($item['content'] ?? [] as $content) {
                if (($content['type'] ?? null) === 'output_text') {
                    return ExtractionContract::decode($content['text']);
                }
            }
        }
        throw new \RuntimeException('provider_no_facts');
    }
}
