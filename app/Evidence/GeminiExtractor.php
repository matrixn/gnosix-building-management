<?php

namespace App\Evidence;

use App\Models\Evidence\Version;
use Illuminate\Support\Facades\Http;

class GeminiExtractor implements Extractor
{
    public function extract(Version $version): array
    {
        $bytes = ProviderInput::bytes($version, 'gemini');
        $s = app(Settings::class)->all();
        $response = Http::withHeaders(['x-goog-api-key' => $s['api_key']])->acceptJson()->timeout(90)->withOptions(['allow_redirects' => false])->post('https://generativelanguage.googleapis.com/v1beta/models/'.rawurlencode($s['model']).':generateContent', [
            'systemInstruction' => ['parts' => [['text' => ExtractionContract::instructions()]]],
            'contents' => [['role' => 'user', 'parts' => [['text' => 'Classify and extract explicitly quoted candidate facts.'], ['inlineData' => ['mimeType' => $version->mime, 'data' => $bytes]]]]],
            'generationConfig' => ['responseMimeType' => 'application/json', 'responseJsonSchema' => ExtractionContract::schema(), 'maxOutputTokens' => 12000],
        ]);
        $data = ProviderInput::response($response);
        $candidate = $data['candidates'][0] ?? [];
        if (($candidate['finishReason'] ?? null) !== 'STOP') {
            throw new \RuntimeException('provider_incomplete');
        }
        $text = '';
        foreach ($candidate['content']['parts'] ?? [] as $part) {
            if (isset($part['text']) && ! ($part['thought'] ?? false)) {
                $text .= $part['text'];
            }
        }

        return ExtractionContract::decode($text);
    }
}
