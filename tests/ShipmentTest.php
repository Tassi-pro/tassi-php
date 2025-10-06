<?php

namespace Tassi\Tests;

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use Tassi\Tassi;
use Tassi\Shipment;
use Tassi\Requestor;
use Tassi\Error\ApiConnectionError;

class ShipmentTest extends TestCase
{
    protected function setUp(): void
    {
        Tassi::setApiKey('test_api_key');
        Tassi::setEnvironment('sandbox');
    }

    public function testCreateComplete()
    {
        $payload = [
            "marketplace_id" => "1",
            "customer_id" => "",
            "customer" => [
                "first_name" => "Doe",
                "last_name" => "Jane",
                "email" => "doe@gmail.com",
                "address" => "Rue 123, Houéyiho, Cotonou",
                "city" => "Cotonou",
                "country_code" => "BJ"
            ],
            "pickup_point_id" => "",
            "pickup_point" => [
                "name" => "Point Relais Houéyiho",
                "address" => "Carrefour Houéyiho, Cotonou",
                "city" => "Cotonou",
                "postal_code" => "22901",
                "latitude" => 6.3703,
                "longitude" => 2.3912,
                "phone" => "+22961020304",
                "email" => "pickup.houeyiho@example.com",
                "is_active" => true
            ],
            "package" => [
                "description" => "Colis test contenant accessoires électroniques",
                "weight" => 5,
                "dimensions" => "10x10x10",
                "declared_value" => "100",
                "currency" => "USD",
                "insurance" => false
            ],
            "route" => [
                "origin" => "Cotonou",
                "destination" => "Porto-Novo",
                "stops" => [
                    [
                        "city" => "Sèmè-Kpodji",
                        "address" => "Avenue de l'Inter, Sèmè-Kpodji",
                        "latitude" => 6.3512,
                        "longitude" => 2.4987
                    ]
                ]
            ]
        ];

        $mockResponse = [
            "shipment" => [
                "id" => 1,
                "marketplace_id" => 1,
                "package_id" => 1,
                "status" => "created"
            ]
        ];

        $mock = new MockHandler([
            new Response(201, [], json_encode($mockResponse))
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $requestor = new Requestor();
        $reflection = new \ReflectionClass($requestor);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($requestor, $client);

        Shipment::setRequestor($requestor);

        $shipment = Shipment::create($payload);

        $this->assertObjectHasProperty('id', $shipment);
        $this->assertEquals("created", $shipment->status);
    }

    public function testCreateValidationError()
    {
        $invalidPayload = [
            "marketplace_id" => "1",
            "customer" => [
                "first_name" => "Doe"
            ]
        ];

        $mock = new MockHandler([
            new RequestException(
                "Client error",
                new Request('POST', 'test'),
                new Response(400, [], json_encode(["error" => "Missing required customer fields"]))
            )
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $requestor = new Requestor();
        $reflection = new \ReflectionClass($requestor);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($requestor, $client);

        Shipment::setRequestor($requestor);

        $this->expectException(ApiConnectionError::class);
        Shipment::create($invalidPayload);
    }

    public function testCreateInvalidPackageWeight()
    {
        $payload = [
            "marketplace_id" => "1",
            "customer" => [
                "first_name" => "Test",
                "last_name" => "User",
                "email" => "test@example.com"
            ],
            "package" => [
                "description" => "Test package",
                "weight" => -5,
                "dimensions" => "invalid"
            ]
        ];

        $mock = new MockHandler([
            new RequestException(
                "Client error",
                new Request('POST', 'test'),
                new Response(400, [], json_encode(["error" => "Invalid package weight or dimensions"]))
            )
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $requestor = new Requestor();
        $reflection = new \ReflectionClass($requestor);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($requestor, $client);

        Shipment::setRequestor($requestor);

        $this->expectException(ApiConnectionError::class);
        Shipment::create($payload);
    }
}