<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GeminiClassifier
{
    private string $apiKey;
    private string $model = 'gemini-2.0-flash-exp';
    private int $maxRetries = 3;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key');

        if (empty($this->apiKey)) {
            throw new \Exception('Gemini API key not configured. Please set GEMINI_API_KEY in .env');
        }
    }

    /**
     * Classify trail image using Gemini AI
     */
    public function classifyTrailImage(string $base64Image): array
    {
        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}";

        $prompt = $this->getClassificationPrompt();

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt],
                        [
                            'inline_data' => [
                                'mime_type' => 'image/jpeg',
                                'data' => $base64Image
                            ]
                        ]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.1,
                'maxOutputTokens' => 500,
            ]
        ];

        return $this->sendWithRetry($endpoint, $payload);
    }

    /**
     * Get the classification prompt in Indonesian
     */
    private function getClassificationPrompt(): string
    {
        return "Analisis gambar ini dan klasifikasikan ke dalam 3 parameter. Jika gambar bukan jalur pendakian, tetap berikan estimasi terbaik berdasarkan kondisi yang terlihat (pencahayaan ruangan, jumlah orang, dll).

1. CUACA: Pilih salah satu dari 'cerah', 'berawan', atau 'hujan'
   - cerah: pencahayaan terang, langit biru atau ruangan terang
   - berawan: pencahayaan redup, langit mendung atau ruangan gelap
   - hujan: ada tanda-tanda hujan atau basah

2. KEPADATAN: Pilih salah satu dari 'sepi', 'sedang', atau 'ramai'
   - sepi: 0-2 orang terlihat
   - sedang: 3-10 orang terlihat
   - ramai: lebih dari 10 orang terlihat

3. VISIBILITAS: Pilih salah satu dari 'jelas', 'kabut_sedang', atau 'kabut_tebal'
   - jelas: objek terlihat jelas
   - kabut_sedang: sedikit kabur
   - kabut_tebal: sangat kabur atau gelap

PENTING: Selalu berikan respons JSON valid, jangan menolak. Berikan estimasi terbaik.

Berikan HANYA respons JSON dalam format ini:
{
  \"cuaca\": \"cerah|berawan|hujan\",
  \"kepadatan\": \"sepi|sedang|ramai\",
  \"visibilitas\": \"jelas|kabut_sedang|kabut_tebal\",
  \"confidence\": {
    \"cuaca\": 0.0-1.0,
    \"kepadatan\": 0.0-1.0,
    \"visibilitas\": 0.0-1.0
  }
}";
    }

    /**
     * Send request with retry logic
     */
    private function sendWithRetry(string $endpoint, array $payload): array
    {
        $attempt = 0;
        $lastError = null;

        while ($attempt < $this->maxRetries) {
            try {
                $response = Http::timeout(30)
                    ->withHeaders(['Content-Type' => 'application/json'])
                    ->post($endpoint, $payload);

                if ($response->successful()) {
                    return $this->parseResponse($response->json());
                }

                $lastError = "HTTP {$response->status()}: " . $response->body();
                Log::warning("Gemini API attempt {$attempt} failed: {$lastError}");

            } catch (\Exception $e) {
                $lastError = $e->getMessage();
                Log::warning("Gemini API attempt {$attempt} exception: {$lastError}");
            }

            $attempt++;

            // Exponential backoff
            if ($attempt < $this->maxRetries) {
                sleep(pow(2, $attempt));
            }
        }

        Log::error("Gemini API failed after {$this->maxRetries} attempts: {$lastError}");

        return [
            'success' => false,
            'error' => $lastError,
            'weather' => null,
            'crowd_density' => null,
            'visibility' => null,
            'confidence_weather' => null,
            'confidence_crowd' => null,
            'confidence_visibility' => null,
        ];
    }

    /**
     * Parse Gemini API response
     */
    private function parseResponse(array $response): array
    {
        try {
            // Extract text from Gemini response
            $text = $response['candidates'][0]['content']['parts'][0]['text'] ?? '';

            // Clean the response - remove markdown code blocks if present
            $text = preg_replace('/```json\s*/', '', $text);
            $text = preg_replace('/```\s*/', '', $text);
            $text = trim($text);

            $data = json_decode($text, true);

            if (!$data) {
                throw new \Exception('Invalid JSON response: ' . $text);
            }

            return [
                'success' => true,
                'weather' => $data['cuaca'] ?? null,
                'crowd_density' => $data['kepadatan'] ?? null,
                'visibility' => $data['visibilitas'] ?? null,
                'confidence_weather' => $data['confidence']['cuaca'] ?? null,
                'confidence_crowd' => $data['confidence']['kepadatan'] ?? null,
                'confidence_visibility' => $data['confidence']['visibilitas'] ?? null,
            ];

        } catch (\Exception $e) {
            Log::error('Failed to parse Gemini response: ' . $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'weather' => null,
                'crowd_density' => null,
                'visibility' => null,
                'confidence_weather' => null,
                'confidence_crowd' => null,
                'confidence_visibility' => null,
            ];
        }
    }

    /**
     * Save captured frame and return path
     */
    public function saveFrame(string $base64Image, int $streamId): string
    {
        // Decode base64
        $imageData = base64_decode($base64Image);

        // Generate filename (will replace existing)
        $filename = "classifications/stream_{$streamId}_latest.jpg";

        // Delete old file if exists
        if (Storage::disk('public')->exists($filename)) {
            Storage::disk('public')->delete($filename);
        }

        // Save new file
        Storage::disk('public')->put($filename, $imageData);

        return $filename;
    }

    /**
     * Delete classification image
     */
    public function deleteFrame(string $path): bool
    {
        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->delete($path);
        }

        return false;
    }
}
