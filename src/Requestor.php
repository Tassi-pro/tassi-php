<?php

namespace Tassi;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Tassi\Error\ApiConnectionError;

class Requestor
{
    const SANDBOX_BASE = 'https://tassi-api.exanora.com';
    const LIVE_BASE = 'https://tassi-api.exanora.com';

    protected $client;

    public function __construct()
    {
        $this->client = new Client();
    }

    public function request(string $method, string $path, ?array $params = null, ?array $headers = null): array
    {
        $url = $this->url($path);
        $requestHeaders = array_merge($this->defaultHeaders(), $headers ?? []);

        try {
            $options = [
                'headers' => $requestHeaders,
                'verify' => Tassi::getVerifySslCerts()
            ];

            $method = strtoupper($method);

            if (in_array($method, ['GET', 'HEAD', 'DELETE'])) {
                if ($params) {
                    $options['query'] = $params;
                }
            } else {
                if ($params) {
                    $options['json'] = $params;
                }
            }

            $response = $this->client->request($method, $url, $options);

            $body = $response->getBody()->getContents();
            $data = $body ? json_decode($body, true) : [];

            return [
                'data' => $data,
                'options' => [
                    'environment' => Tassi::getEnvironment()
                ]
            ];
        } catch (RequestException $e) {
            $this->handleRequestException($e);
        }

        // In case handleRequestException does not throw, return an empty array
        return [
            'data' => [],
            'options' => [
                'environment' => Tassi::getEnvironment()
            ]
        ];
    }

    protected function baseUrl(): string
    {
        $apiBase = Tassi::getApiBase();
        $environment = Tassi::getEnvironment();

        if ($apiBase) {
            return $apiBase;
        }

        if ($environment === 'live') {
            return self::LIVE_BASE;
        }

        return self::SANDBOX_BASE;
    }

    protected function url(string $path = ''): string
    {
        return $this->baseUrl() . $path;
    }

    protected function defaultHeaders(): array
    {
        $apiKey = Tassi::getApiKey();

        return [
            'X-Version' => Tassi::VERSION,
            'X-Source' => 'Tassi PHPLib',
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ];
    }

    protected function handleRequestException(RequestException $e): void
    {
        $message = 'Request error: ' . $e->getMessage();
        $httpStatus = null;
        $httpRequest = null;
        $httpResponse = null;

        if ($e->hasResponse()) {
            $response = $e->getResponse();
            $httpStatus = $response->getStatusCode();
            $httpResponse = $response;
        }

        if ($e->getRequest()) {
            $httpRequest = $e->getRequest();
        }

        throw new ApiConnectionError(
            $message,
            $httpStatus,
            $httpRequest,
            $httpResponse
        );
    }
}