<?php

namespace App\Services;

use App\Support\FaceEmbedding;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class PythonMicroservice
{
    /**
     * The URL of the Python face embedding microservice.
     */
    private string $baseUrl;

    /**
     * Request timeout in seconds. The dlib HOG model can take ~20s on first
     * cold boot, so we give it a generous window.
     */
    private int $timeout;

    public function __construct()
    {
        $this->baseUrl = config('services.python_microservice.url', 'http://127.0.0.1:8001');
        $this->timeout = (int) config('services.python_microservice.timeout', 60);
    }

    /**
     * Send an uploaded image to the Python face embedding microservice and
     * return the raw 128-element embedding array.
     *
     * @param  UploadedFile  $imageFile  The uploaded image from the request.
     * @return float[]                   128-element face embedding vector.
     *
     * @throws ValidationException  When no face is found or the service is unreachable.
     */
    public function extractEmbedding(UploadedFile $imageFile): array
    {
        $response = Http::timeout($this->timeout)
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