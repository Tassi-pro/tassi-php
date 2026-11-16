<?php

declare(strict_types=1);

namespace Tassi\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Tassi\TassiClient;
use Tassi\Amount;
use Tassi\Exception\UnprocessableEntityException;
use Tassi\IdempotencyKey;
use Tassi\Service\Rates;
use Tassi\Service\Shipments;

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

        public function testOutOfCoverage(): void
    {
        $this->expectException(UnprocessableEntityException::class);
        $this->tassi->rates->get([
            'pickup_point_id' => $this->pickupPointId(),
            'destination' => ['city' => 'Agadez', 'country_code' => 'NE', 'latitude' => 16.97, 'longitude' => 7.99],
            'package' => ['weight' => 1],
        ]);
    }

    /** Le parcours complet. Bloque environ 11 € fictifs, puis annule. */
    public function testFullJourney(): void
    {
        $pickupPointId = $this->pickupPointId();

        // 1. Frais de port
        $rates = $this->tassi->rates->get([
            'pickup_point_id' => $pickupPointId,
            'destination' => ['latitude' => 9.3514, 'longitude' => 2.6305, 'city' => 'Parakou', 'country_code' => 'BJ'],
            'package' => ['weight' => 1.2, 'dimensions' => '20x15x10'],
        ]);
        $economy = Rates::find($rates, 'economy');
        self::assertNotNull($economy, 'Pas de tarif economy : vérifier les coordonnées du point de collecte.');

        // 2. Création
        $order = 'it-' . bin2hex(random_bytes(4));
        $created = $this->tassi->shipments->create([
            'pickup_point_id' => $pickupPointId,
            'customer' => [
                'firstname' => 'Test', 'lastname' => 'Integration', 'email' => $order . '@example.com',
                'phone_prefix' => '+229', 'phone_number' => '97000000',
                'address' => 'Carrefour Banikanni', 'city' => 'Parakou', 'country_code' => 'BJ',
                'latitude' => 9.3514, 'longitude' => 2.6305,
            ],
            'package' => [
                'description' => 'Test', 'weight' => 1.2, 'dimensions' => '20x15x10', 'currency' => 'EUR',
                'order_reference' => $order,
                'items' => [['description' => 'Test', 'quantity' => 1, 'unit_value' => 10]],
            ],
        ], IdempotencyKey::forOrder($order, 'create'), international: $rates['international']);

        $shipmentId = $created['shipment']['id'];
        $option = Shipments::findOption($created, 'economy');

        // 3. Confirmation : le montant bloqué doit être le total_price
        $before = $this->tassi->wallet->get()['wallet'];
        try {
            $this->tassi->shipments->confirm($option['route']['id'], IdempotencyKey::forOrder($order, 'confirm'));
            $after = $this->tassi->wallet->get()['wallet'];

            self::assertSame(
                Amount::toCents($economy['total_price']),
                Amount::toCents($after['held_amount']) - Amount::toCents($before['held_amount']),
                'Le montant bloqué doit être le total_price, commission comprise',
            );
        } finally {
            // Annulé même si le test échoue : on ne laisse pas d'argent bloqué.
            $this->tassi->shipments->cancel($shipmentId, 'Test d\'intégration tassi-php');
        }

        self::assertSame('cancelled', $this->tassi->shipments->get($shipmentId)['package']['status']);
    }

    private function pickupPointId(): int
    {
        $id = (int) getenv('TASSI_PICKUP_POINT_ID');
        if ($id <= 0) {
            self::markTestSkipped('TASSI_PICKUP_POINT_ID non définie.');
        }

        return $id;
    }
}