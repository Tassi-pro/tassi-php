<?php

declare(strict_types=1);

namespace Tassi\Tests\Unit;

use Tassi\Amount;

final class WalletTest extends TestCase
{
    public function testAmountsBecomeStrings(): void
    {
        // Corps brut, exactement comme la sandbox l'a renvoyé lors de nos tests.
        $this->http->respond(200, '{"wallet":{"balance":1000.0,"held_amount":11.35,"available_balance":988.65,"currency":"EUR"}}');

        $wallet = $this->client()->wallet->get()['wallet'];

        self::assertSame('1000.00', $wallet['balance']);
        self::assertSame('11.35', $wallet['held_amount']);
        self::assertSame('988.65', $wallet['available_balance']);
        self::assertSame('/marketplace_module/wallet', $this->http->last()->getUri()->getPath());
    }

    public function testHistoryFilters(): void
    {
        $this->http->respond(200, ['wallet_movements' => []]);

        $this->client()->wallet->history('debit', perPage: 10);

        self::assertSame('type=debit&per_page=10', $this->http->last()->getUri()->getQuery());
    }

    public function testAmountHelpers(): void
    {
        self::assertSame('0.30', Amount::normalize(0.1 + 0.2));
        self::assertSame(1135, Amount::toCents('11.35'));
        self::assertSame(7450, Amount::toCents('74.5'));
        self::assertTrue(Amount::equals('74.5', '74.50'));
    }
}