<?php

declare(strict_types=1);

namespace Tassi\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tassi\Exception\SignatureVerificationException;
use Tassi\Webhook;

final class WebhookTest extends TestCase
{
    private const SECRET = 'whsec_5b0d6c0f2a7e4e1c9d8b3a2f6e7c1d0a9b8c7d6e5f4a3b2c';
    private const NOW = 1762871525;
    private const PAYLOAD = '{"id":4812,"type":"package.delivered","created_at":"2026-11-11T14:32:05Z","data":{"object":{"object":"package","id":22,"status":"delivered","updated_at":"2026-11-11T14:32:04.000Z"}}}';

    public function testKnownSignature(): void
    {
        self::assertSame(
            '87265452db7b1de5e4f0610ebe5566ecd017ea4e596a824f3b1c79f29f1e41ef',
            Webhook::sign(self::PAYLOAD, self::NOW, self::SECRET),
        );
    }

    public function testValidEvent(): void
    {
        $header = Webhook::signatureHeader(self::PAYLOAD, self::NOW, self::SECRET);

        $event = Webhook::constructEvent(self::PAYLOAD, $header, self::SECRET, now: self::NOW + 10);

        self::assertSame(4812, $event['id']);
        self::assertSame('package.delivered', $event['type']);
        self::assertSame('2026-11-11T14:32:04.000Z', $event['data']['object']['updated_at']);
    }

    public function testToleratesSpacesAndUppercase(): void
    {
        $sig = strtoupper(Webhook::sign(self::PAYLOAD, self::NOW, self::SECRET));

        Webhook::verify(self::PAYLOAD, 't=' . self::NOW . ', v1=' . $sig, self::SECRET, now: self::NOW);
        $this->addToAssertionCount(1); // pas d'exception = succès
    }

    public function testWrongSecret(): void
    {
        $header = Webhook::signatureHeader(self::PAYLOAD, self::NOW, self::SECRET);

        $this->expectException(SignatureVerificationException::class);
        Webhook::verify(self::PAYLOAD, $header, 'whsec_autre', now: self::NOW);
    }

    public function testReencodedBodyFails(): void
    {
        // L'erreur classique : décoder puis ré-encoder le JSON avant de vérifier.
        $header = Webhook::signatureHeader(self::PAYLOAD, self::NOW, self::SECRET);
        $reencoded = (string) json_encode(json_decode(self::PAYLOAD, true), JSON_PRETTY_PRINT);

        $this->expectException(SignatureVerificationException::class);
        Webhook::verify($reencoded, $header, self::SECRET, now: self::NOW);
    }

    public function testTooOld(): void
    {
        $header = Webhook::signatureHeader(self::PAYLOAD, self::NOW, self::SECRET);

        $this->expectException(SignatureVerificationException::class);
        Webhook::verify(self::PAYLOAD, $header, self::SECRET, now: self::NOW + 301); // 5 min + 1 s
    }

    public function testRotationAcceptsBothSecrets(): void
    {
        $new = 'whsec_nouveau';
        $header = Webhook::signatureHeader(self::PAYLOAD, self::NOW, $new);

        Webhook::verify(self::PAYLOAD, $header, [self::SECRET, $new], now: self::NOW);
        $this->addToAssertionCount(1);
    }

    public static function malformedHeaders(): iterable
    {
        yield 'absent' => [null];
        yield 'vide' => [''];
        yield 'sans v1' => ['t=1762871525'];
        yield 'sans t' => ['v1=abc'];
        yield 't non numérique' => ['t=abc,v1=abc'];
    }

    #[DataProvider('malformedHeaders')]
    public function testMalformedHeader(?string $header): void
    {
        $this->expectException(SignatureVerificationException::class);
        Webhook::verify(self::PAYLOAD, $header, self::SECRET, now: self::NOW);
    }

    public function testWebhookEndpointRequiresHttps(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $factory = new \Nyholm\Psr7\Factory\Psr17Factory();
        (new \Tassi\TassiClient('tassi_sec_x', [
            'http_client' => new MockHttpClient(),
            'request_factory' => $factory,
            'stream_factory' => $factory,
        ]))->webhookEndpoints->create('http://boutique.example/webhook');
    }
}