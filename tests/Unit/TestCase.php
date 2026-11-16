<?php

declare(strict_types=1);

namespace Tassi\Tests\Unit;

use Nyholm\Psr7\Factory\Psr17Factory;
use Tassi\TassiClient;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    protected const KEY = 'tassi_sec_mkp_test';

    protected MockHttpClient $http;

    /** @var list<float> Durées d'attente demandées, au lieu d'attendre vraiment. */
    protected array $sleeps = [];

    protected function setUp(): void
    {
        $this->http = new MockHttpClient();
        $this->sleeps = [];
    }

    /** @param array<string, mixed> $options */
    protected function client(array $options = []): TassiClient
    {
        $factory = new Psr17Factory();

        return new TassiClient(self::KEY, $options + [
            'http_client' => $this->http,
            'request_factory' => $factory,
            'stream_factory' => $factory,
            'sleeper' => function (float $seconds): void {
                $this->sleeps[] = $seconds;
            },
        ]);
    }
}