<?php

declare(strict_types=1);

namespace Tassi\Tests\Unit;

use Tassi\Exception\InvalidParametersException;
use Tassi\Service\Rates;
use Tassi\Service\Shipments;

final class ShipmentsTest extends TestCase
{
    /** @return array<string, mixed> */
    public static function shipment(): array
    {
        return [
            'pickup_point_id' => 109,
            'customer' => [
                'firstname' => 'Awa', 'lastname' => 'Diallo', 'email' => 'awa@example.com',
                'phone_prefix' => '+229', 'phone_number' => '97000000',
                'address' => 'Carrefour Banikanni', 'city' => 'Parakou', 'country_code' => 'BJ',
            ],
            'package' => [
                'weight' => 1.2,
                'currency' => 'EUR',
                'items' => [['description' => 'Pagne', 'quantity' => 2, 'unit_value' => 15]],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public static function rates(): array
    {
        return [
            'pickup_point_id' => 109,
            'destination' => ['latitude' => 9.3514, 'longitude' => 2.6305, 'city' => 'Parakou', 'country_code' => 'BJ'],
            'package' => ['weight' => 1.2, 'dimensions' => '20x15x10'],
        ];
    }

    public function testCreate(): void
    {
        $this->http->respond(201, ['shipment' => ['id' => 328], 'delivery_options' => []]);

        $result = $this->client()->shipments->create(self::shipment(), 'order-1042-create');

        self::assertSame(328, $result['shipment']['id']);
        self::assertSame('/shipments', $this->http->last()->getUri()->getPath());
        self::assertSame('order-1042-create', $this->http->last()->getHeaderLine('Idempotency-Key'));
        self::assertSame(self::shipment(), $this->http->lastJson());
    }

    public function testSameKeyOnEveryRetry(): void
    {
        // Le test promis à l'étape 6 : sans clé fournie, la clé tirée au hasard
        // doit être la même à chaque essai, sinon Tassi créerait un doublon.
        $this->http->failNetwork()->respond(503)->respond(201, ['shipment' => ['id' => 1]]);

        $this->client()->shipments->create(self::shipment());

        $keys = array_map(fn ($r) => $r->getHeaderLine('Idempotency-Key'), $this->http->requests);
        self::assertCount(3, $keys);
        self::assertNotSame('', $keys[0]);
        self::assertCount(1, array_unique($keys), 'Les 3 essais doivent porter la même clé');
    }

    public function testRequiresAtLeastOneItem(): void
    {
        $params = self::shipment();
        $params['package']['items'] = [];

        $problems = $this->problems(fn () => $this->client()->shipments->create($params));

        self::assertContains('Le colis doit contenir au moins un article.', $problems);
        self::assertCount(0, $this->http->requests, 'Aucun appel réseau si la validation échoue');
    }

    public function testRequiresAddressEvenWithCoordinates(): void
    {
        $params = self::shipment();
        unset($params['customer']['address']);
        $params['customer']['latitude'] = 9.35;
        $params['customer']['longitude'] = 2.63;

        $problems = $this->problems(fn () => $this->client()->shipments->create($params));

        self::assertContains('Destinataire : l\'adresse est obligatoire, même avec des coordonnées GPS.', $problems);
    }

    public function testInternationalRequiresHsCode(): void
    {
        $params = self::shipment();
        $params['package']['items'][] = ['description' => 'Café', 'quantity' => 1, 'unit_value' => 8, 'hs_code' => '090111'];

        $problems = $this->problems(fn () => $this->client()->shipments->create($params, international: true));

        self::assertSame(['Article 1 : le code SH est obligatoire pour un envoi international.'], $problems);
    }

    public function testRejectsOtherCurrencies(): void
    {
        $params = self::shipment();
        $params['package']['currency'] = 'XOF';

        $problems = $this->problems(fn () => $this->client()->shipments->create($params));

        self::assertSame(['Seul l\'euro (EUR) est accepté.'], $problems);
    }

    public function testReportsAllProblemsAtOnce(): void
    {
        $params = self::shipment();
        unset($params['customer']['email'], $params['customer']['phone_number']);
        $params['package']['weight'] = 0;

        $problems = $this->problems(fn () => $this->client()->shipments->create($params));

        self::assertCount(3, $problems);
    }

    public function testConfirm(): void
    {
        $this->http->respond(200, ['message' => 'ok', 'data' => []]);

        $this->client()->shipments->confirm(614, 'order-1042-confirm');

        self::assertSame('/shipments/confirm', $this->http->last()->getUri()->getPath());
        self::assertSame(['route_id' => 614], $this->http->lastJson());
    }

    public function testCancelWithoutReasonSendsNoBody(): void
    {
        $this->http->respond(200, ['message' => 'Shipment cancelled successfully']);

        $this->client()->shipments->cancel(329);

        self::assertSame('/shipments/329/cancel', $this->http->last()->getUri()->getPath());
        self::assertNull($this->http->lastJson());
    }

    public function testGetReadsPackage(): void
    {
        $this->http->respond(200, ['package' => ['id' => 329, 'status' => 'cancelled']]);

        $result = $this->client()->shipments->get(329);

        self::assertSame('/packages/329', $this->http->last()->getUri()->getPath());
        self::assertSame('cancelled', $result['package']['status']);
    }

    public function testPriceToShowIsTotalPriceNotCost(): void
    {
        // Les vraies valeurs de nos tests sur la sandbox.
        $rates = ['rates' => [['option_type' => 'economy', 'total_price' => '11.35', 'carrier_cost' => '11.13']]];
        $created = ['delivery_options' => [['option_type' => 'economy', 'cost' => '11.13', 'route' => ['id' => 611]]]];

        self::assertSame('11.35', Rates::find($rates, 'economy')['total_price']);
        self::assertSame(611, Shipments::findOption($created, 'economy')['route']['id']);
        self::assertNull(Shipments::findOption($created, 'express'));
    }

    public function testRatesValidation(): void
    {
        $params = self::rates();
        $params['package']['weight'] = 0;
        unset($params['destination']['country_code']);

        $problems = $this->problems(fn () => $this->client()->rates->get($params));

        self::assertSame(['Le pays de destination est obligatoire.', 'Le poids du colis doit être supérieur à 0 kg.'], $problems);
    }

    public function testRatesCacheKeyRoundsWeight(): void
    {
        $a = self::rates();
        $b = $a;
        $b['package']['weight'] = 1.24; // arrondi à 1,2 → même clé
        $c = $a;
        $c['package']['weight'] = 1.26; // arrondi à 1,3 → autre clé

        self::assertSame(Rates::cacheKey($a), Rates::cacheKey($b));
        self::assertNotSame(Rates::cacheKey($a), Rates::cacheKey($c));
    }

    public function testTrackingWithoutKey(): void
    {
        $this->http->respond(200, ['shipment' => []]);

        $this->client()->tracking->get('TRK_D28A6BEF46281A67');

        self::assertFalse($this->http->last()->hasHeader('Authorization'));
        self::assertSame('/tracking/TRK_D28A6BEF46281A67', $this->http->last()->getUri()->getPath());
    }

    public function testLatestLabelSkipsCancelled(): void
    {
        $this->http->respond(200, ['shipping_labels' => [
            ['id' => 3, 'status' => 'cancelled'],
            ['id' => 2, 'status' => 'generated', 'download_url' => 'https://x/2.pdf'],
        ]]);

        self::assertSame(2, $this->client()->labels->latest(22)['id']);
    }

    /** @return list<string> */
    private function problems(callable $call): array
    {
        try {
            $call();
        } catch (InvalidParametersException $e) {
            return $e->getProblems();
        }
        self::fail('InvalidParametersException attendue');
    }
}