<?php

namespace App\Evidence;

class Providers
{
    public static function all(): array
    {
        return [
            'openai' => ['label' => 'OpenAI · Responses', 'formats' => ['application/pdf', 'image/png', 'image/jpeg'], 'key' => true, 'adapter' => OpenAiExtractor::class],
            'gemini' => ['label' => 'Google Gemini · Free tier disponibil', 'formats' => ['application/pdf', 'image/png', 'image/jpeg'], 'key' => true, 'adapter' => GeminiExtractor::class],
            'openrouter' => ['label' => 'OpenRouter · modele :free', 'formats' => ['image/png', 'image/jpeg'], 'key' => true, 'adapter' => OpenRouterExtractor::class],
            'ollama' => ['label' => 'Ollama · local, fără tarif API', 'formats' => ['image/png', 'image/jpeg'], 'key' => false, 'adapter' => OllamaExtractor::class],
        ];
    }

    public static function supports(string $provider, string $mime): bool
    {
        return in_array($mime, self::all()[$provider]['formats'] ?? [], true);
    }

    public static function validModel(string $provider, string $model, bool $freeOnly = true): bool
    {
        if (! preg_match('~^[A-Za-z0-9][A-Za-z0-9._:/-]{0,99}$~D', $model) || str_contains($model, '..')) {
            return false;
        }

        return match ($provider) {
            'openai','gemini' => ! str_contains($model, '/') && ! str_contains($model, ':'),
            'openrouter' => ! $freeOnly || $model === 'openrouter/free' || str_ends_with($model, ':free'),
            'ollama' => ! preg_match('/(?:[:\-]cloud)(?:$|[:\-])/i', $model),
            default => false,
        };
    }
}
