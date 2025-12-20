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

    public function testCreate()
    {
        $payload = [
            "marketplace_id" => "1",
            "customer" => [
                "first_name" => "John",
                "last_name" => "Doe",
                "email" => "john@example.com",
                "address" => "123 Main St",
                "city" => "Cotonou",
                "country_code" => "BJ"
            ],
            "pickup_point_id" => 4,
            "package" => [
                "description" => "Colis test",
                "weight" => 5,
                "dimensions" => "10x10x10",
                "declared_value" => "100",
                "currency" => "XOF",
                "insurance" => false
            ]
        ];

        $mockResponse = [
            "delivery_options" => [
                [
                    "id" => 17,
                    "option_type" => "economy",
                    "estimated_days" => 1,
                    "cost" => "14.0",
                    "route" => ["id" => 17, "origin" => "Cotonou", "destination" => "Porto-Novo"]
                ],
                [
                    "id" => 16,
                    "option_type" => "express",
                    "estimated_days" => 1,
                    "cost" => "20.0",
                    "route" => ["id" => 16]
                ]
            ]
        ];

        $mock = new MockHandler([
            new Response(200, [], json_encode($mockResponse))
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $requestor = new Requestor();
        $reflection = new \ReflectionClass($requestor);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($requestor, $client);

        Shipment::setRequestor($requestor);

        $result = Shipment::create($payload);

        $this->assertObjectHasProperty('delivery_options', $result);
        $this->assertCount(2, $result->delivery_options);
        $this->assertEquals("economy", $result->delivery_options[0]->option_type);
        $this->assertEquals(17, $result->delivery_options[0]->route->id);
    }

    public function testCreateValidationError()
    {
        $mock = new MockHandler([
            new RequestException(
                "Client error",
                new Request('POST', 'test'),
                new Response(400, [], json_encode(["error" => "Missing required fields"]))
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
        Shipment::create(["marketplace_id" => "1"]);
    }

    public function testConfirm()
    {
        $mockResponse = [
            "movement" => [
                "id" => 26,
                "action" => "debit",
                "description" => "Shipment cost for route ID 17",
                "amount" => "14.0",
                "wallet_id" => 7,
                "created_at" => "2025-12-19T15:50:50.413Z"
            ],
            "message" => "shipment confirmed successfully"
        ];

        $mock = new MockHandler([
            new Response(200, [], json_encode($mockResponse))
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $requestor = new Requestor();
        $reflection = new \ReflectionClass($requestor);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($requestor, $client);

        Shipment::setRequestor($requestor);

        $result = Shipment::confirm(17);

        $this->assertEquals("shipment confirmed successfully", $result->message);
        $this->assertObjectHasProperty('movement', $result);
        $this->assertEquals(26, $result->movement->id);
        $this->assertEquals("debit", $result->movement->action);
        $this->assertEquals("14.0", $result->movement->amount);
    }

    public function testConfirmInvalidRoute()
    {
        $mock = new MockHandler([
            new RequestException(
                "Client error",
                new Request('POST', 'test'),
                new Response(404, [], json_encode(["error" => "Route not found"]))
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
        Shipment::confirm(99999);
    }
}