<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\Response;

class WhatsAppCloudApiService
{
    protected string $baseUrl = 'https://graph.facebook.com';
    protected string $apiVersion = 'v22.0';
    protected ?string $accessToken;
    protected ?string $phoneNumberId;
    protected ?string $wabaId;

    public function __construct()
    {
        $this->accessToken   = config('services.whatsapp_cloud.token');
        $this->phoneNumberId = config('services.whatsapp_cloud.phone_number_id');
        $this->wabaId        = config('services.whatsapp_cloud.waba_id');
        $this->apiVersion    = config('services.whatsapp_cloud.api_version', 'v22.0');
    }

    public function isConfigured(): bool
    {
        return !empty($this->accessToken) && !empty($this->phoneNumberId);
    }

    public function getAccessToken(): ?string  { return $this->accessToken; }
    public function getPhoneNumberId(): ?string { return $this->phoneNumberId; }
    public function getWabaId(): ?string        { return $this->wabaId; }

    public function sendTextMessage(string $to, string $text, ?string $accessToken = null, ?string $phoneNumberId = null): array
    {
        $accessToken   = $accessToken   ?? $this->accessToken;
        $phoneNumberId = $phoneNumberId ?? $this->phoneNumberId;
        Log::info('WhatsAppCloudApiService: Access token: ' . $accessToken);

        if (!$accessToken || !$phoneNumberId) {
            Log::error('WhatsAppCloudApiService: Service not configured.');
            return ['success' => false, 'error' => 'WhatsApp Cloud API service not configured.', 'data' => null];
        }

        if ($phoneNumberId == "1010322575491077") {
            $accessToken = 'EAAW6NIGs3xcBQp4qbUGEHol4WYmRYpbKbjWY8ZBxIalBV0psJoZA1evagLRnPKPwVIWaDZBjZCwFaFAUKcGnZBhoFQosZByzChm12UIeXQ94UVIojEXxGZCVFYVzx7Gbd6ZCYc4M18OIJwSg5idf9b2e5HVEXr7FFNuhduxOTBsTqQwmZA9ZBEYLubrAZAboVZB8rhGTR52WcZB4pSt39TLXr4X5xdZCQaSMRYtkey2oBc';
        }

        $to       = ltrim($to, '+');
        $endpoint = "{$this->baseUrl}/{$this->apiVersion}/{$phoneNumberId}/messages";

        try {
            $response = Http::withToken($accessToken)
                ->asJson()
                ->post($endpoint, [
                    'messaging_product' => 'whatsapp',
                    'to'   => $to,
                    'type' => 'text',
                    'text' => ['body' => $text],
                ]);

            return $this->handleResponse($response, 'Text message');
        } catch (\Exception $e) {
            Log::error("WhatsAppCloudApiService sendTextMessage Exception: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage(), 'data' => null];
        }
    }

    public function sendTemplateMessage(
        string $to,
        string $templateName,
        string $languageCode = 'en_US',
        array $components = [],
        ?string $accessToken = null,
        ?string $phoneNumberId = null
    ): array {
        $accessToken   = $accessToken   ?? $this->accessToken;
        $phoneNumberId = $phoneNumberId ?? $this->phoneNumberId;

        if (!$accessToken || !$phoneNumberId) {
            Log::error('WhatsAppCloudApiService: Service not configured.');
            return ['success' => false, 'error' => 'WhatsApp Cloud API service not configured.', 'data' => null];
        }

        $to       = ltrim($to, '+');
        $endpoint = "{$this->baseUrl}/{$this->apiVersion}/{$phoneNumberId}/messages";

        $payload = [
            'messaging_product' => 'whatsapp',
            'to'       => $to,
            'type'     => 'template',
            'template' => [
                'name'     => $templateName,
                'language' => ['code' => $languageCode],
            ],
        ];

        if (!empty($components)) {
            $payload['template']['components'] = $components;
        }

        try {
            $response = Http::withOptions([
                    'curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4],
                ])
                ->timeout(30)
                ->withToken($accessToken)
                ->asJson()
                ->post($endpoint, $payload);

            return $this->handleResponse($response, 'Template message');
        } catch (\Exception $e) {
            Log::error("WhatsAppCloudApiService sendTemplateMessage Exception: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage(), 'data' => null];
        }
    }

    public function sendDocument(
        string $to,
        string $documentUrl,
        ?string $filename = null,
        ?string $caption = null,
        ?string $accessToken = null,
        ?string $phoneNumberId = null
    ): array {
        $accessToken   = $accessToken   ?? $this->accessToken;
        $phoneNumberId = $phoneNumberId ?? $this->phoneNumberId;

        if (!$accessToken || !$phoneNumberId) {
            return ['success' => false, 'error' => 'WhatsApp Cloud API service not configured.', 'data' => null];
        }

        $to       = ltrim($to, '+');
        $endpoint = "{$this->baseUrl}/{$this->apiVersion}/{$phoneNumberId}/messages";

        $payload = [
            'messaging_product' => 'whatsapp',
            'to'       => $to,
            'type'     => 'document',
            'document' => ['link' => $documentUrl],
        ];

        if ($filename) $payload['document']['filename'] = $filename;
        if ($caption)  $payload['document']['caption']  = $caption;

        try {
            $response = Http::withToken($accessToken)->asJson()->post($endpoint, $payload);
            return $this->handleResponse($response, 'Document');
        } catch (\Exception $e) {
            Log::error("WhatsAppCloudApiService sendDocument Exception: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage(), 'data' => null];
        }
    }

    public function sendImage(string $to, string $imageUrl, ?string $caption = null, ?string $accessToken = null, ?string $phoneNumberId = null): array
    {
        $accessToken   = $accessToken   ?? $this->accessToken;
        $phoneNumberId = $phoneNumberId ?? $this->phoneNumberId;

        if (!$accessToken || !$phoneNumberId) {
            return ['success' => false, 'error' => 'WhatsApp Cloud API service not configured.', 'data' => null];
        }

        $to       = ltrim($to, '+');
        $endpoint = "{$this->baseUrl}/{$this->apiVersion}/{$phoneNumberId}/messages";

        $payload = [
            'messaging_product' => 'whatsapp',
            'to'    => $to,
            'type'  => 'image',
            'image' => ['link' => $imageUrl],
        ];

        if ($caption) $payload['image']['caption'] = $caption;

        try {
            $response = Http::withToken($accessToken)->asJson()->post($endpoint, $payload);
            return $this->handleResponse($response, 'Image');
        } catch (\Exception $e) {
            Log::error("WhatsAppCloudApiService sendImage Exception: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage(), 'data' => null];
        }
    }

    public function getTemplates(?string $wabaId = null, ?string $accessToken = null): array
    {
        $accessToken = $accessToken ?? $this->accessToken;
        $wabaId      = $wabaId      ?? $this->wabaId;

        if (!$accessToken || !$wabaId) {
            return ['success' => false, 'error' => 'WhatsApp Cloud API service not configured.', 'data' => null];
        }

        $endpoint = "{$this->baseUrl}/{$this->apiVersion}/{$wabaId}/message_templates";

        try {
            $response     = Http::withToken($accessToken)->get($endpoint);
            $responseData = $response->json();

            if ($response->successful()) {
                return ['success' => true, 'data' => $responseData];
            }

            return ['success' => false, 'error' => $responseData['error']['message'] ?? 'Failed to get templates.', 'data' => $responseData];
        } catch (\Exception $e) {
            Log::error("WhatsAppCloudApiService getTemplates Exception: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage(), 'data' => null];
        }
    }

    protected function handleResponse(Response $response, string $actionDescription): array
    {
        $responseData = $response->json();

        if ($response->successful() && isset($responseData['messages']) && !empty($responseData['messages'])) {
            $messageId = $responseData['messages'][0]['id'] ?? null;
            Log::info("WhatsAppCloudApiService: {$actionDescription} sent successfully.", ['message_id' => $messageId]);
            return ['success' => true, 'data' => $responseData, 'message_id' => $messageId];
        }

        $errorMessage = "Failed to send {$actionDescription}.";
        if (isset($responseData['error']['message'])) {
            $errorMessage .= " Error: " . $responseData['error']['message'];
        } elseif (!$response->successful()) {
            $errorMessage .= " HTTP Status: " . $response->status();
        }

        Log::error("WhatsAppCloudApiService: {$errorMessage}", ['response' => $responseData]);
        return ['success' => false, 'error' => $errorMessage, 'data' => $responseData];
    }

    public static function formatPhoneNumber(string $phoneNumber, string $defaultCountryCode = '968'): ?string
    {
        if (empty(trim($phoneNumber))) {
            return null;
        }

        $cleanedNumber = preg_replace('/[^\d]/', '', $phoneNumber);

        if (str_starts_with($cleanedNumber, '0')) {
            $cleanedNumber = substr($cleanedNumber, 1);
        }

        if (!str_starts_with($cleanedNumber, $defaultCountryCode)) {
            $cleanedNumber = $defaultCountryCode . $cleanedNumber;
        }

        return $cleanedNumber;
    }
}
