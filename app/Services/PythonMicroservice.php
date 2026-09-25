<?php

namespace App\Services;

use App\Support\FaceEmbedding;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class PythonMicroservice
{
    private string $baseUrl;
    private int $timeout;
    private ?string $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('services.python_microservice.url', 'http://127.0.0.1:8001');
        $this->timeout = (int) config('services.python_microservice.timeout', 60);
        $this->apiKey  = config('services.python_microservice.key');
    }

    public function extractEmbedding(UploadedFile $imageFile): array
    {
        $response = Http::timeout($this->timeout)
            ->withHeaders([
                'X-API-Key' => $this->apiKey,
                'ngrok-skip-browser-warning' => 'true',
            ])
            ->attach(
                'image',
                file_get_contents($imageFile->getRealPath()),
                $imageFile->getClientOriginalName()
            )
            ->post("{$this->baseUrl}/extract-embedding/");

        if ($response->failed()) {
            $detail = $response->json('detail') ?? 'Failed to extract a face embedding from the image.';
            throw ValidationException::withMessages(['image' => [$detail]]);
        }

        $embedding = $response->json('embedding');

        if (! is_array($embedding) || count($embedding) !== FaceEmbedding::LENGTH) {
            throw ValidationException::withMessages([
                'image' => ['Invalid embedding format received from the face recognition service.'],
            ]);
        }

        return $embedding;
    }
}