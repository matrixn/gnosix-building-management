<?php

namespace App\Evidence;

use App\Models\Evidence\Version;
use Illuminate\Support\Facades\Http;

class OllamaExtractor implements Extractor
{
    public function extract(Version $version): array
    {
        $bytes = ProviderInput::bytes($version, 'ollama');
        $s = app(Settings::class)->all();
        // Fixed loopback only. No arbitrary endpoints, redirects, cloud tags or automatic model downloads.
        $metadata = ProviderInput::response(Http::acceptJson()->timeout(10)->withOptions(['allow_redirects' => false, 'proxy' => ''])->post('http://127.0.0.1:11434/api/show', ['model' => $s['model']]));
        if (! empty($metadata['remote_host']) || ! empty($metadata['remote_model']) || empty($metadata['model_info']) || ! in_array('vision', $metadata['capabilities'] ?? [], true)) {
            throw new \RuntimeException('provider_model_incompatible');
        }
        $response = Http::acceptJson()->timeout(90)->withOptions(['allow_redirects' => false, 'proxy' => ''])->post('http://127.0.0.1:11434/api/chat', [
            'model' => $s['model'], 'stream' => false, 'format' => ExtractionContract::schema(), 'options' => ['num_predict' => 12000, 'temperature' => 0],
            'messages' => [['role' => 'system', 'content' => ExtractionContract::instructions()], ['role' => 'user', 'content' => 'Extract quoted candidate facts from the image.', 'images' => [$bytes]]],
        ]);
        $data = ProviderInput::response($response);
        if (($data['done'] ?? false) !== true || ($data['done_reason'] ?? 'stop') !== 'stop' || ! empty($data['message']['tool_calls'])) {
            throw new \RuntimeException('provider_incomplete');
        }

        return ExtractionContract::decode($data['message']['content'] ?? '');
    }
}
