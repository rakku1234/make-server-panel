<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class TranslatorAPI
{
    public function translate(string $text, string $sourceLang, ?string $targetLang): string
    {
        if (empty($text)) {
            return '';
        }
        $key = config('services.translator.key');
        $region = config('services.translator.region');
        if (empty($key) || empty($region) || empty($targetLang)) {
            return $text;
        }
        $cacheKey = "translation_{$sourceLang}_{$targetLang}_".hash('md5', $text);
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }
        $url = "https://api.cognitive.microsofttranslator.com/translate?api-version=3.0&from={$sourceLang}&to={$targetLang}";
        $response = Http::withHeaders([
            'Ocp-Apim-Subscription-Key' => $key,
            'Ocp-Apim-Subscription-Region' => $region,
            'Content-Type' => 'application/json; charset=UTF-8',
            'X-ClientTraceId' => Str::uuid()->toString(),
        ])->post($url, [
            ['text' => $text],
        ]);
        if ($response->successful()) {
            $result = $response->json();
            $translatedText = $result[0]['translations'][0]['text'];
            Cache::put($cacheKey, $translatedText, now()->addWeek());
            return $translatedText;
        }
        return $text;
    }
}
