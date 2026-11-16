<?php

declare(strict_types=1);

namespace Tassi\Tests\Unit;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Tassi\Exception\NetworkException;
use Tassi\Exception\NotFoundException;
use Tassi\Exception\UnprocessableEntityException;
use Tassi\Http\HttpTransport;

final class HttpTransportTest extends TestCase
{
    private const KEY = 'tassi_sec_test';

    private MockHttpClient $http;
    private HttpTransport $transport;

    protected function setUp(): void
    {
        $this->http = new MockHttpClient();
        $factory = new Psr17Factory();
        $this->transport = new HttpTransport(
            apiKey: self::KEY,
            baseUrl: 'https://sandbox-api.tassi.pro',
            httpClient: $this->http,
            requestFactory: $factory,
            streamFactory: $factory,
            userAgent: 'tassi-php/0.1.0',
        );
    }

    public function testGetWithHeaders(): void
    {
        $this->http->respond(200, ['wallet' => ['currency' => 'EUR']]);

        $data = $this->transport->request('GET', '/marketplace_module/wallet');

        self::assertSame(['wallet' => ['currency' => 'EUR']], $data);
        $req = $this->http->last();
        self::assertSame('GET', $req->getMethod());
        self::assertSame('https://sandbox-api.tassi.pro/marketplace_module/wallet', (string) $req->getUri());
        self::assertSame('Bearer ' . self::KEY, $req->getHeaderLine('Authorization'));
        self::assertSame('application/json', $req->getHeaderLine('Accept'));
        self::assertSame('tassi-php/0.1.0', $req->getHeaderLine('User-Agent'));
        self::assertFalse($req->hasHeader('Idempotency-Key'));
        self::assertNull($this->http->lastJson());
    }

    public function testQueryIgnoresNull(): void
    {
        $this->http->respond(200, ['data' => []]);

        $this->transport->request('GET', '/shipments/search', ['q' => 'TRK_1', 'page' => null, 'per_page' => 10]);

        self::assertSame('q=TRK_1&per_page=10', $this->http->last()->getUri()->getQuery());
    }

    public function testPostWithJsonBodyAndIdempotencyKey(): void
    {
        $this->http->respond(201, ['shipment' => ['id' => 328]]);
        $body = ['pickup_point_id' => 109, 'package' => ['weight' => 1.0, 'description' => 'Pagne tissé']];

        $data = $this->transport->request('POST', '/shipments', body: $body, idempotencyKey: 'order-1042-create');

        self::assertSame(328, $data['shipment']['id']);
        $req = $this->http->last();
        self::assertSame('application/json', $req->getHeaderLine('Content-Type'));
        self::assertSame('order-1042-create', $req->getHeaderLine('Idempotency-Key'));
        self::assertSame($body, $this->http->lastJson());
        self::assertStringContainsString('"weight":1.0', (string) $req->getBody());
        self::assertStringContainsString('Pagne tissé', (string) $req->getBody());
    }

    public function testWithoutAuthentication(): void
    {
        $this->http->respond(200, ['shipment' => []]);

        $this->transport->request('GET', '/tracking/TRK_1', authenticated: false);

        self::assertFalse($this->http->last()->hasHeader('Authorization'));
    }

    public function testEmptyBodyReturnsEmptyArray(): void
    {
        $this->http->respond(204);

        self::assertSame([], $this->transport->request('DELETE', '/webhook_endpoints/5'));
    }

    public function testApiErrorBecomesException(): void
    {
        $this->http->respond(422, ['message' => 'No route available', 'errors' => [], 'model' => null]);

        $this->expectException(UnprocessableEntityException::class);
        $this->expectExceptionMessage('No route available');

        $this->transport->request('POST', '/rates', body: ['x' => 1]);
    }

    public function testNotFound(): void
    {
        $this->http->respond(404, ['message' => 'Package not found']);

        $this->expectException(NotFoundException::class);

        $this->transport->request('GET', '/packages/999');
    }

    public function testNetworkFailureBecomesNetworkException(): void
    {
        $this->http->failNetwork('Connection timed out');

        try {
            $this->transport->request('GET', '/marketplace_module/wallet');
            self::fail('NetworkException attendue');
        } catch (NetworkException $e) {
            self::assertStringContainsString('Connection timed out', $e->getMessage());
            self::assertNotNull($e->getPrevious(), 'L\'exception d\'origine doit être conservée');
        }
    }
}