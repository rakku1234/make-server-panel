<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class TranslatorAPIService
{
    private string $key;
    private string $region;
    private string $cachekey;
    public string $translatedText;

    public function __construct(string $text, string $sourceLang, string $targetLang)
    {
        $this->key = config('services.translator.key');
        $this->region = config('services.translator.region');
        $this->cachekey = "translation_{$sourceLang}_{$targetLang}_".hash('xxh3', $text);
        match (config('services.translator.service')) {
            'Microsoft' => $this->translatedText = $this->MicrosoftTranslate($text, $sourceLang, $targetLang),
            'DeepL' => $this->translatedText = $this->DeepLTranslate($text, $targetLang),
            default => $this->translatedText = $text,
        };
    }

    private function MicrosoftTranslate(string $text, string $sourceLang, ?string $targetLang): string
    {
        if (empty($this->key) || empty($this->region) || empty($targetLang)) {
            return $text;
        }
        if (Cache::has($this->cachekey)) {
            return Cache::get($this->cachekey);
        }
        $url = "https://api.cognitive.microsofttranslator.com/translate?api-version=3.0&from={$sourceLang}&to={$targetLang}";
        $response = Http::withHeaders([
            'Ocp-Apim-Subscription-Key' => $this->key,
            'Ocp-Apim-Subscription-Region' => $this->region,
            'Content-Type' => 'application/json',
            'X-ClientTraceId' => Str::uuid()->toString(),
        ])->post($url, [
            ['text' => $text],
        ]);
        if ($response->successful()) {
            $result = $response->json();
            $translatedText = $result[0]['translations'][0]['text'];
            Cache::put($this->cachekey, $translatedText, now()->addWeek());
            return $translatedText;
        }
        return $text;
    }

    private function DeepLTranslate(string $text, string $targetLang): string
    {
        if (empty($targetLang) || empty($this->key)) {
            return $text;
        }
        if (Cache::has($this->cachekey)) {
            return Cache::get($this->cachekey);
        }
        $url = "https://api-free.deepl.com/v2/translate";
        $response = Http::withHeaders([
            'Authorization' => "DeepL-Auth-Key {$this->key}",
            'Content-Type' => 'application/json',
        ])->post($url, [
            'text' => [$text],
            'target_lang' => $targetLang,
        ]);
        if ($response->successful()) {
            $result = $response->json();
            $translatedText = $result['translations'][0]['text'];
            Cache::put($this->cachekey, $translatedText, now()->addWeek());
            return $translatedText;
        }
        return $text;
    }
}
