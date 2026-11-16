<?php

declare(strict_types=1);

namespace Tassi\Tests\Unit;

use Tassi\Exception\NetworkException;
use Tassi\Exception\ServerException;
use Tassi\Exception\UnprocessableEntityException;
use Tassi\IdempotencyKey;

final class RetryTest extends TestCase
{
    public function testGetIsRetriedAfterServerError(): void
    {
        $this->http->respond(503)->respond(500)->respond(200, ['wallet' => []]);

        $this->client()->wallet->get();

        self::assertCount(3, $this->http->requests, '2 échecs + 1 succès');
        self::assertCount(2, $this->sleeps);
    }

    public function testGivesUpAfterMaxRetries(): void
    {
        $this->http->respond(500)->respond(500)->respond(500);

        try {
            $this->client()->wallet->get();
            self::fail('ServerException attendue');
        } catch (ServerException) {
            self::assertCount(3, $this->http->requests, '1 envoi + 2 nouveaux essais');
        }
    }

    public function testNetworkErrorIsRetried(): void
    {
        $this->http->failNetwork()->respond(200, ['wallet' => []]);

        $this->client()->wallet->get();

        self::assertCount(2, $this->http->requests);
    }

    public function testNetworkErrorWithoutRetries(): void
    {
        $this->http->failNetwork();

        $this->expectException(NetworkException::class);
        $this->client(['max_retries' => 0])->wallet->get();
    }

    public function testClientErrorIsNeverRetried(): void
    {
        $this->http->respond(422, ['message' => 'Insufficient funds']);

        try {
            $this->client()->request('GET', '/marketplace_module/wallet');
            self::fail('UnprocessableEntityException attendue');
        } catch (UnprocessableEntityException) {
            self::assertCount(1, $this->http->requests, 'Une 4xx ne se réessaie pas');
        }
    }

    public function testPostWithoutKeyIsNotRetried(): void
    {
        $this->http->respond(500);

        try {
            $this->client()->request('POST', '/webhook_endpoints/3/test');
            self::fail('ServerException attendue');
        } catch (ServerException) {
            self::assertCount(1, $this->http->requests, 'Sans clé, un POST pourrait créer un doublon');
        }
    }

    public function testRetryAfterHeader(): void
    {
        $this->http->respond(429, null, ['Retry-After' => '3'])->respond(200, ['wallet' => []]);

        $this->client()->wallet->get();

        self::assertSame([3.0], $this->sleeps);
    }

    public function testDelaysGrow(): void
    {
        $this->http->respond(500)->respond(500)->respond(200, ['wallet' => []]);

        $this->client()->wallet->get();

        [$first, $second] = $this->sleeps;
        self::assertEqualsWithDelta(0.5, $first, 0.1);   // 0,5 s ± 20 %
        self::assertEqualsWithDelta(1.0, $second, 0.2);  // 1 s ± 20 %
    }

    public function testIdempotencyKeys(): void
    {
        self::assertSame('order-1042-confirm', IdempotencyKey::forOrder('1042', 'confirm'));
        self::assertSame('order-CMD-1042-create-2', IdempotencyKey::forOrder('CMD #1042', 'create', 2));
        self::assertSame(32, strlen(IdempotencyKey::random()));
        self::assertNotSame(IdempotencyKey::random(), IdempotencyKey::random());
    }
}