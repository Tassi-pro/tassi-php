<?php

declare(strict_types=1);

namespace Tassi\Tests\Unit;

use Tassi\TassiClient;

final class ClientTest extends TestCase
{
    public function testRejectsNonSecretKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new TassiClient('tassi_pub_123');
    }

    public function testRejectsHttpUrl(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->client(['base_url' => 'http://sandbox-api.tassi.pro']);
    }

    public function testRejectsUnknownOption(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->client(['timout' => 5]); // faute de frappe volontaire
    }

    public function testUserAgent(): void
    {
        $this->http->respond(200, ['wallet' => []]);

        $this->client(['user_agent_suffix' => 'tassi-woocommerce/1.0.0'])->wallet->get();

        $ua = $this->http->last()->getHeaderLine('User-Agent');
        self::assertStringStartsWith('tassi-php/' . TassiClient::VERSION, $ua);
        self::assertStringEndsWith(' tassi-woocommerce/1.0.0', $ua);
    }

    public function testTwoClientsShareNothing(): void
    {
        $other = new MockHttpClient();
        $other->respond(200, ['wallet' => []]);
        $this->http->respond(200, ['wallet' => []]);

        $this->client()->wallet->get();
        $this->client(['http_client' => $other])->wallet->get();

        self::assertCount(1, $this->http->requests);
        self::assertCount(1, $other->requests);
    }
}