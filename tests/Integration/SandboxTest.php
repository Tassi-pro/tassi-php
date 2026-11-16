<?php

declare(strict_types=1);

namespace Tassi\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Tassi\TassiClient;

final class SandboxTest extends TestCase
{
    private TassiClient $tassi;

    protected function setUp(): void
    {
        $key = getenv('TASSI_API_KEY');
        if (!is_string($key) || $key === '') {
            self::markTestSkipped('TASSI_API_KEY non définie.');
        }
        $this->tassi = new TassiClient($key);
    }

    public function testWallet(): void
    {
        $wallet = $this->tassi->wallet->get()['wallet'];

        self::assertMatchesRegularExpression('/^\d+\.\d{2}$/', $wallet['available_balance']);
        self::assertSame('EUR', $wallet['currency']);
    }
}