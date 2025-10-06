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
use Tassi\Marketplace;
use Tassi\Requestor;
use Tassi\Error\ApiConnectionError;

class MarketplaceTest extends TestCase
{
    protected function setUp(): void
    {
        Tassi::setApiKey('test_api_key');
        Tassi::setEnvironment('sandbox');
    }

    public function testRetrieve()
    {
        $mockResponse = [
            "id" => 1,
            "name" => "Market1",
            "api_name" => "market1",
            "website" => "market1.com",
            "is_active" => true,
            "api_configuration" => [],
            "country_code" => "BJ",
            "phone_number" => "0162000000",
            "email" => "abc@gmail.com",
            "customers_count" => 0,
            "packages_count" => 4
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

        Marketplace::setRequestor($requestor);

        $marketplace = Marketplace::retrieve(1);

        $this->assertEquals(1, $marketplace->id);
        $this->assertEquals("Market1", $marketplace->name);
        $this->assertEquals("market1", $marketplace->api_name);
        $this->assertTrue($marketplace->is_active);
        $this->assertEquals("BJ", $marketplace->country_code);
        $this->assertEquals(4, $marketplace->packages_count);
    }

    public function testUpdate()
    {
        $mockResponse = [
            "id" => 1,
            "name" => "Market1",
            "api_name" => "market1",
            "website" => "market-app.com",
            "is_active" => true,
            "api_configuration" => [],
            "country_code" => "BJ",
            "phone_number" => "0162000000",
            "email" => "abc@gmail.com",
            "customers_count" => 0,
            "packages_count" => 4
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

        Marketplace::setRequestor($requestor);

        $marketplace = Marketplace::update(1, ["website" => "market-app.com"]);

        $this->assertEquals("market-app.com", $marketplace->website);
    }

    public function testUpdateValidationError()
    {
        $mock = new MockHandler([
            new RequestException(
                "Client error",
                new Request('PUT', 'test'),
                new Response(400, [], json_encode(["error" => "Invalid email format"]))
            )
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $requestor = new Requestor();
        $reflection = new \ReflectionClass($requestor);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($requestor, $client);

        Marketplace::setRequestor($requestor);

        $this->expectException(ApiConnectionError::class);
        Marketplace::update(1, ["email" => "invalid-email"]);
    }

    public function testGetWalletHistory()
    {
        $mockResponse = [
            "wallet_movements" => [
                [
                    "id" => 7,
                    "action" => "Credit",
                    "description" => "Test credit",
                    "amount" => "1.0",
                    "created_at" => "2025-09-27T12:43:59Z",
                    "wallet_id" => 1
                ],
                [
                    "id" => 6,
                    "action" => "Credit",
                    "description" => "Test credit",
                    "amount" => "1.0",
                    "created_at" => "2025-09-27T12:43:57Z",
                    "wallet_id" => 1
                ],
                [
                    "id" => 5,
                    "action" => "Debit",
                    "description" => "Test debit",
                    "amount" => "1.0",
                    "created_at" => "2025-09-27T12:43:46Z",
                    "wallet_id" => 1
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

        Marketplace::setRequestor($requestor);

        $marketplace = new Marketplace(1);
        $result = $marketplace->getWalletHistory();

        $this->assertObjectHasProperty('wallet_movements', $result);
        $this->assertIsArray($result->wallet_movements);
        $this->assertCount(3, $result->wallet_movements);
        $this->assertEquals("Credit", $result->wallet_movements[0]->action);
        $this->assertEquals("Debit", $result->wallet_movements[2]->action);
    }

    public function testGetWalletHistoryEmpty()
    {
        $mockResponse = [
            "wallet_movements" => []
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

        Marketplace::setRequestor($requestor);

        $marketplace = new Marketplace(1);
        $result = $marketplace->getWalletHistory();

        $this->assertObjectHasProperty('wallet_movements', $result);
        $this->assertIsArray($result->wallet_movements);
        $this->assertCount(0, $result->wallet_movements);
    }

    public function testMarketplaceStatusManagement()
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode(["id" => 1, "is_active" => false])),
            new Response(200, [], json_encode(["id" => 1, "is_active" => true]))
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $requestor = new Requestor();
        $reflection = new \ReflectionClass($requestor);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($requestor, $client);

        Marketplace::setRequestor($requestor);

        $marketplace = Marketplace::update(1, ["is_active" => false]);
        $this->assertFalse($marketplace->is_active);

        $marketplace = Marketplace::update(1, ["is_active" => true]);
        $this->assertTrue($marketplace->is_active);
    }
}