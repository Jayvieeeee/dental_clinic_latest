<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PayMongoService
{
    protected $baseUrl = 'https://api.paymongo.com/v1';
    protected $secretKey;

    public function __construct()
    {
        // Load the secret key from environment variables (e.g., in your .env file)
        $this->secretKey = env('PAYMONGO_SECRET_KEY');

        if (empty($this->secretKey)) {
            Log::error('PAYMONGO_SECRET_KEY is not set in environment. Payment functionality will fail.');
            // Note: In a production app, you might throw an exception here.
        }
    }

    /**
     * Creates an authenticated HTTP client instance.
     * PayMongo uses Basic Auth with the secret key as the username.
     *
     * @return \Illuminate\Http\Client\PendingRequest
     */
    protected function client()
    {
        return Http::withBasicAuth($this->secretKey, '')
            ->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ]);
    }

    /**
     * Creates a new PayMongo checkout session.
     *
     * @param array $data The checkout session attributes payload.
     * @return array Contains 'id' and 'checkout_url'
     * @throws \Exception
     */
    public function createCheckoutSession(array $data): array
    {
        $payload = ['data' => ['attributes' => $data]];

        Log::info('PayMongo API Request', ['endpoint' => 'checkout_sessions', 'payload' => $payload]);

        $response = $this->client()->post("{$this->baseUrl}/checkout_sessions", $payload);

        if ($response->failed()) {
            Log::error('PayMongo API response failed', ['status' => $response->status(), 'response' => $response->json()]);
            // Attempt to get a meaningful error message
            $error = $response->json('errors.0.detail', 'Unknown PayMongo error.');
            throw new \Exception("Failed to create PayMongo checkout session: {$error}");
        }

        $responseData = $response->json();
        Log::info('PayMongo API response', ['status' => $response->status(), 'response' => $responseData]);

        return [
            'id' => $responseData['data']['id'],
            'checkout_url' => $responseData['data']['attributes']['checkout_url'],
        ];
    }

    /**
     * Retrieves a PayMongo checkout session by ID for verification.
     *
     * @param string $sessionId
     * @return array The complete response JSON.
     * @throws \Exception
     */
    public function retrieveCheckoutSession(string $sessionId): array
    {
        $response = $this->client()->get("{$this->baseUrl}/checkout_sessions/{$sessionId}");

        if ($response->failed()) {
            Log::error('PayMongo API retrieval failed', ['status' => $response->status(), 'response' => $response->json()]);
            throw new \Exception('Failed to retrieve PayMongo checkout session for verification.');
        }

        return $response->json();
    }
}