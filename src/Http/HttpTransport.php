<?php

declare(strict_types=1);

namespace Tassi\Http;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Tassi\Exception\ApiException;
use Tassi\Exception\NetworkException;
use Tassi\Exception\TassiException;

/**
 * Envoie les requêtes à l'API Tassi et convertit les erreurs en exceptions.
 */
final class HttpTransport
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl,
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly string $userAgent,
    ) {
    }

    /**
     * @param array<string, scalar|null> $query
     * @param array<mixed>|null          $body
     *
     * @return array<mixed>
     */
    public function request(
        string $method,
        string $path,
        array $query = [],
        ?array $body = null,
        ?string $idempotencyKey = null,
        bool $authenticated = true,
    ): array {

        $query = array_filter($query, static fn ($value) => $value !== null);
        $url = $this->baseUrl . $path;
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        $request = $this->requestFactory->createRequest($method, $url)
            ->withHeader('Accept', 'application/json')
            ->withHeader('User-Agent', $this->userAgent);

        if ($authenticated) {
            $request = $request->withHeader('Authorization', 'Bearer ' . $this->apiKey);
        }
        if ($idempotencyKey !== null) {
            $request = $request->withHeader('Idempotency-Key', $idempotencyKey);
        }

        if ($body !== null) {
            try {
                $json = json_encode(
                    $body,
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION,
                );
            } catch (\JsonException $e) {
                throw new TassiException('Corps de requête impossible à encoder en JSON : ' . $e->getMessage(), 0, $e);
            }
            $request = $request
                ->withHeader('Content-Type', 'application/json')
                ->withBody($this->streamFactory->createStream($json));
        }

        try {
            $response = $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw new NetworkException('Impossible de joindre l\'API Tassi : ' . $e->getMessage(), 0, $e);
        }

        $status = $response->getStatusCode();
        if ($status >= 200 && $status < 300) {
            return $this->decode($response);
        }

        throw ApiException::fromResponse($status, (string) $response->getBody());
    }

    /**
     *
     * @return array<mixed>
     */
    private function decode(ResponseInterface $response): array
    {
        $raw = (string) $response->getBody();
        if (trim($raw) === '') {
            return [];
        }

        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (\JsonException $e) {
            throw new TassiException('Réponse de l\'API illisible (JSON invalide) : ' . $e->getMessage(), 0, $e);
        }

        return is_array($data) ? $data : ['value' => $data];
    }
}