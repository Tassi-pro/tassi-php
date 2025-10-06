<?php

namespace Tassi\Tests;

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Tassi\Tassi;
use Tassi\Package;
use Tassi\Requestor;
use Tassi\Error\InvalidRequestError;

class PackageTest extends TestCase
{
    protected function setUp(): void
    {
        Tassi::setApiKey('test_api_key');
        Tassi::setEnvironment('sandbox');
    }

    public function testAll()
    {
        $mockResponse = [
            "packages" => [
                [
                    "id" => 4,
                    "tracking_number" => "tassi_TRK_CFE667F2DB8E9578",
                    "status" => "in_transit",
                    "description" => "Colis test contenant accessoires électroniques",
                    "weight" => "5.0",
                    "dimensions" => "10x10x10",
                    "declared_value" => "100.0",
                    "currency" => "USD",
                    "insurance" => false,
                    "signature_required" => true
                ]
            ],
            "meta" => [
                "current_page" => 1,
                "total_count" => 4
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

        Package::setRequestor($requestor);

        $result = Package::all();

        $this->assertIsObject($result);
        $this->assertObjectHasProperty('packages', $result);
        $this->assertIsArray($result->packages);
        $this->assertCount(1, $result->packages);
        $this->assertEquals("tassi_TRK_CFE667F2DB8E9578", $result->packages[0]->tracking_number);
        $this->assertEquals("in_transit", $result->packages[0]->status);
        $this->assertFalse($result->packages[0]->insurance);
        $this->assertTrue($result->packages[0]->signature_required);
    }

    public function testRetrieve()
    {
        $mockResponse = [
            "package" => [
                "id" => 4,
                "tracking_number" => "tassi_TRK_CFE667F2DB8E9578",
                "status" => "in_transit",
                "description" => "Colis test contenant accessoires électroniques",
                "weight" => "5.0",
                "dimensions" => "10x10x10",
                "declared_value" => "100.0",
                "currency" => "USD",
                "insurance" => false,
                "signature_required" => true
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

        Package::setRequestor($requestor);

        $pkg = Package::retrieve(4);

        $this->assertEquals(4, $pkg->id);
        $this->assertEquals("tassi_TRK_CFE667F2DB8E9578", $pkg->tracking_number);
        $this->assertEquals("in_transit", $pkg->status);
        $this->assertEquals("5.0", $pkg->weight);
        $this->assertFalse($pkg->insurance);
        $this->assertTrue($pkg->signature_required);
    }

    public function testRetrieveInvalidId()
    {
        $this->expectException(InvalidRequestError::class);
        Package::retrieve(null);
    }

    public function testUpdate()
    {
        $mockResponse = [
            "package" => [
                "id" => 4,
                "tracking_number" => "tassi_TRK_CFE667F2DB8E9578",
                "status" => "in_transit",
                "description" => "Colis test contenant accessoires de coifure",
                "weight" => "15.0",
                "dimensions" => "10x10x10",
                "declared_value" => "100.0",
                "currency" => "USD"
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

        Package::setRequestor($requestor);

        $pkg = Package::update(4, [
            "description" => "Colis test contenant accessoires de coifure",
            "weight" => "15.0"
        ]);

        $this->assertEquals("Colis test contenant accessoires de coifure", $pkg->description);
        $this->assertEquals("15.0", $pkg->weight);
    }

    public function testTrack()
    {
        $mockResponse = [];

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

        Package::setRequestor($requestor);

        $pkg = new Package(4);
        $result = $pkg->track();

        $this->assertNotNull($result);
    }

    public function testGetShippingLabel()
    {
        $mockResponse = [
            "shipping_label" => [
                "id" => 1,
                "label_type" => "shipping_label",
                "format" => "pdf",
                "size" => "a4",
                "file_url" => null,
                "checksum" => "f36a40debd30d81fdabf9285bcf5b573c828c21a0b839f7c85be62bdf7f2a9d1",
                "version" => 1,
                "package_id" => 1,
                "filename" => "tassi_TRK_99F75AD8447EA4C0_v1.pdf"
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

        Package::setRequestor($requestor);

        $pkg = new Package(1);
        $result = $pkg->getShippingLabel(1);

        $this->assertObjectHasProperty('shipping_label', $result);
        $this->assertEquals("shipping_label", $result->shipping_label->label_type);
        $this->assertEquals("pdf", $result->shipping_label->format);
        $this->assertEquals(1, $result->shipping_label->version);
        $this->assertEquals("tassi_TRK_99F75AD8447EA4C0_v1.pdf", $result->shipping_label->filename);
    }
}