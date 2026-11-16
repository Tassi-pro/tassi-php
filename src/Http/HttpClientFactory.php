<?php

declare(strict_types=1);

namespace Tassi\Http;

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Trouve un client PSR-18 installé et lui applique le délai d'attente.
 * PSR-18 ne prévoit aucun réglage de délai : on le règle nous-mêmes pour les
 * clients connus. Pour un client fourni par l'appelant, c'est à lui de le faire.
 *
 * @internal
 */
final class HttpClientFactory
{
    public static function createClient(float $timeout): ClientInterface
    {
        if (class_exists(\Symfony\Component\HttpClient\Psr18Client::class)) {
            return new \Symfony\Component\HttpClient\Psr18Client(
                \Symfony\Component\HttpClient\HttpClient::create([
                    'timeout' => $timeout,
                    'max_duration' => $timeout,
                ]),
            );
        }

        $guzzle = 'GuzzleHttp\\Client';
        if (class_exists($guzzle)) {
            /** @var ClientInterface $client Guzzle 7 implémente PSR-18. */
            $client = new $guzzle([
                'timeout' => $timeout,
                'connect_timeout' => min($timeout, 5.0),
                'http_errors' => false,
            ]);

            return $client;
        }

        return Psr18ClientDiscovery::find();
    }

    public static function createRequestFactory(): RequestFactoryInterface
    {
        return Psr17FactoryDiscovery::findRequestFactory();
    }

    public static function createStreamFactory(): StreamFactoryInterface
    {
        return Psr17FactoryDiscovery::findStreamFactory();
    }
}
