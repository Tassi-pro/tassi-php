<?php

declare(strict_types=1);

namespace Tassi;

use Tassi\Http\HttpClientFactory;
use Tassi\Http\HttpTransport;
use Tassi\Service\Wallet;
use Tassi\Service\Labels;
use Tassi\Service\PickupPoints;
use Tassi\Service\Rates;
use Tassi\Service\Shipments;
use Tassi\Service\Tracking;

/**
 * Point d'entrée. Un client par compte marchand, sans état global.
 */
final class TassiClient
{
    public const VERSION = '0.1.0';
    public const SANDBOX_URL = 'https://sandbox-api.tassi.pro';

    private const DEFAULT_OPTIONS = [
        'base_url' => self::SANDBOX_URL,
        'timeout' => 10.0,
        'http_client' => null,
        'request_factory' => null,
        'stream_factory' => null,
        'user_agent_suffix' => null,
        'max_retries' => 2,
        'sleeper' => null,
    ];

    public readonly Wallet $wallet;
    public readonly PickupPoints $pickupPoints;
    public readonly Rates $rates;
    public readonly Shipments $shipments;
    public readonly Labels $labels;
    public readonly Tracking $tracking;

    private readonly HttpTransport $transport;

    /**
     * @param string $apiKey Clé secrète tassi_sec_…, à lire depuis l'environnement.
     * @param array<string, mixed> $options Voir DEFAULT_OPTIONS.
     */
    public function __construct(string $apiKey, array $options = [])
    {
        // Une faute de frappe dans une option ne doit pas passer inaperçue.
        $unknown = array_diff_key($options, self::DEFAULT_OPTIONS);
        if ($unknown !== []) {
            throw new \InvalidArgumentException('Option(s) inconnue(s) : ' . implode(', ', array_keys($unknown)));
        }
        $options += self::DEFAULT_OPTIONS;

        $apiKey = trim($apiKey);
        if (!str_starts_with($apiKey, 'tassi_sec_')) {
            throw new \InvalidArgumentException(
                'Clé API invalide : seule une clé secrète "tassi_sec_…" est acceptée.'
            );
        }

        $baseUrl = rtrim((string) $options['base_url'], '/');
        if (parse_url($baseUrl, PHP_URL_SCHEME) !== 'https' || !is_string(parse_url($baseUrl, PHP_URL_HOST))) {
            throw new \InvalidArgumentException('base_url doit être une URL https.');
        }

        $timeout = (float) $options['timeout'];
        if ($timeout <= 0) {
            throw new \InvalidArgumentException('timeout doit être positif.');
        }

        $maxRetries = (int) $options['max_retries'];
        if ($maxRetries < 0) {
            throw new \InvalidArgumentException('max_retries ne peut pas être négatif.');
        }

        $userAgent = 'tassi-php/' . self::VERSION . ' PHP/' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
        if (!empty($options['user_agent_suffix'])) {
            $userAgent .= ' ' . $options['user_agent_suffix'];
        }

        $this->transport = new HttpTransport(
            apiKey: $apiKey,
            baseUrl: $baseUrl,
            httpClient: $options['http_client'] ?? HttpClientFactory::createClient($timeout),
            requestFactory: $options['request_factory'] ?? HttpClientFactory::createRequestFactory(),
            streamFactory: $options['stream_factory'] ?? HttpClientFactory::createStreamFactory(),
            userAgent: $userAgent,
            maxRetries: $maxRetries,
            sleeper: $options['sleeper'],
        );

        $this->wallet = new Wallet($this->transport);
        $this->pickupPoints = new PickupPoints($this->transport);
        $this->rates = new Rates($this->transport);
        $this->shipments = new Shipments($this->transport);
        $this->labels = new Labels($this->transport);
        $this->tracking = new Tracking($this->transport);
    }

    /**
     * Appel direct d'une opération que la bibliothèque n'expose pas encore.
     *
     * @param array<string, scalar|null> $query
     * @param array<mixed>|null          $body
     *
     * @return array<mixed>
     */
    public function request(string $method, string $path, array $query = [], ?array $body = null): array
    {
        return $this->transport->request(strtoupper($method), '/' . ltrim($path, '/'), $query, $body);
    }
}
