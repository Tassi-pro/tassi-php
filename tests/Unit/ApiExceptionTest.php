<?php

declare(strict_types=1);

namespace Tassi\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tassi\Exception\ApiException;
use Tassi\Exception\AuthenticationException;
use Tassi\Exception\InvalidRequestException;
use Tassi\Exception\NotFoundException;
use Tassi\Exception\ServerException;
use Tassi\Exception\TassiException;
use Tassi\Exception\UnprocessableEntityException;

final class ApiExceptionTest extends TestCase
{
    public function testStringMessage(): void
    {
        $e = ApiException::fromResponse(404, '{"message":"Shipment not found","errors":{},"model":null}');

        self::assertInstanceOf(NotFoundException::class, $e);
        self::assertSame(404, $e->getStatusCode());
        self::assertSame('Shipment not found', $e->getMessage());
        self::assertSame(['Shipment not found'], $e->getMessages());
    }

    public function testListMessage(): void
    {
        $e = ApiException::fromResponse(422, '{"message":["Name doit être rempli(e)","City doit être rempli(e)"],"errors":{},"model":null}');

        self::assertInstanceOf(UnprocessableEntityException::class, $e);
        self::assertSame(['Name doit être rempli(e)', 'City doit être rempli(e)'], $e->getMessages());
        self::assertSame('Name doit être rempli(e) ; City doit être rempli(e)', $e->getMessage());
    }

    public function testFieldErrorsAndModel(): void
    {
        $e = ApiException::fromResponse(400, '{"message":"Package invalid","errors":{"base":["Un colis doit contenir au moins un article."]},"model":"Package"}');

        self::assertInstanceOf(InvalidRequestException::class, $e);
        self::assertSame(['base' => ['Un colis doit contenir au moins un article.']], $e->getErrors());
        self::assertSame('Package', $e->getModel());
    }

    public function testBodyThatIsNotJson(): void
    {
        $e = ApiException::fromResponse(502, '<html>Bad gateway</html>');

        self::assertInstanceOf(ServerException::class, $e);
        self::assertSame('Erreur HTTP 502 renvoyée par l\'API Tassi', $e->getMessage());
        self::assertSame([], $e->getMessages());
        self::assertSame('<html>Bad gateway</html>', $e->getResponseBody());
    }

    public static function statusCodes(): iterable
    {
        yield [401, AuthenticationException::class];
        yield [500, ServerException::class];
        yield [503, ServerException::class];
        yield [418, ApiException::class];
    }

    #[DataProvider('statusCodes')]
    public function testClassDependsOnStatus(int $status, string $class): void
    {
        $e = ApiException::fromResponse($status, '{"message":"x","errors":{}}');

        self::assertSame($class, $e::class);
        self::assertInstanceOf(TassiException::class, $e);
    }
}