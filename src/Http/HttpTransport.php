<?php

declare(strict_types=1);

namespace Tassi\Http;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Tassi\Exception\ApiException;
use Tassi\Exception\NetworkException;
use Tassi\Exception\TassiException;

/**
 * Envoie les requêtes à l'API Tassi 
 *
 * @internal
 */
final class HttpTransport
{
    private const RETRYABLE_STATUSES = [409, 429, 500, 502, 503, 504];

    /** @var callable(float): void */
    private $sleeper;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl,
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly string $userAgent,
        private readonly int $maxRetries = 0,
        ?callable $sleeper = null,
    ) {
        $this->sleeper = $sleeper ?? static function (float $seconds): void {
            usleep((int) ($seconds * 1_000_000));
        };
    }

    /**
     * @param array<string, scalar|null> $query
     * @param array<mixed>|null          $body
     * @param bool|null $retryable Vrai si l'appel peut être rejoué sans risque.
     *                             Par défaut : GET, ou écriture avec Idempotency-Key.
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
        ?bool $retryable = null,                 // NOUVEAU
    ): array {
        $retryable ??= $method === 'GET' || $idempotencyKey !== null;
        $request = $this->buildRequest($method, $path, $query, $body, $idempotencyKey, $authenticated);

        // NOUVEAU : la boucle des essais.
        $attempt = 0;
        while (true) {
            $attempt++;
            $canRetry = $retryable && $attempt <= $this->maxRetries;

            try {
                $response = $this->httpClient->sendRequest($request);
            } catch (ClientExceptionInterface $e) {
                if ($canRetry) {
                    $this->wait($attempt, null);
                    continue;
                }
                throw new NetworkException('Impossible de joindre l\'API Tassi : ' . $e->getMessage(), 0, $e);
            }

            $status = $response->getStatusCode();
            if ($status >= 200 && $status < 300) {
                return $this->decode($response);
            }

            if ($canRetry && in_array($status, self::RETRYABLE_STATUSES, true)) {
                $this->wait($attempt, $response);
                continue;
            }

            throw ApiException::fromResponse($status, (string) $response->getBody());
        }
    }

    /**
     * @param array<string, scalar|null> $query
     * @param array<mixed>|null          $body
     */
    private function buildRequest(
        string $method,
        string $path,
        array $query,
        ?array $body,
        ?string $idempotencyKey,
        bool $authenticated,
    ): RequestInterface {
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

        return $request;
    }

    /** @return array<mixed> */
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

    private function wait(int $attempt, ?ResponseInterface $response): void
    {
        $delay = null;
        if ($response !== null && ctype_digit($response->getHeaderLine('Retry-After'))) {
            $delay = (float) $response->getHeaderLine('Retry-After');
        }
        $delay ??= min(8.0, 0.5 * 2 ** ($attempt - 1)) * (0.8 + mt_rand() / mt_getrandmax() * 0.4);

        ($this->sleeper)(min($delay, 30.0));
    }
}