<?php

namespace Tassi;

use Doctrine\Inflector\InflectorFactory;
use Tassi\Error\InvalidRequestError;

abstract class Resource extends TassiObject
{
    protected static $requestor = null;

    public static function setRequestor(Requestor $requestor): void
    {
        self::$requestor = $requestor;
    }

    public static function getRequestor(): Requestor
    {
        if (self::$requestor === null) {
            self::$requestor = new Requestor();
        }
        return self::$requestor;
    }

    public static function className(): string
    {
        $className = get_called_class();
        $parts = explode('\\', $className);
        return strtolower(end($parts));
    }

    public static function classPath(): string
    {
        $base = static::className();
        $inflector = InflectorFactory::create()->build();
        $plural = $inflector->pluralize($base);
        return '/' . $plural;
    }

    public static function resourcePath($id): string
    {
        if ($id === null) {
            $klass = static::className();
            throw new InvalidRequestError(
                "Could not determine which URL to request: {$klass} instance has invalid ID: {$id}"
            );
        }

        $base = static::classPath();
        return "{$base}/{$id}";
    }

    public function instanceUrl(): string
    {
        return static::resourcePath($this->id);
    }

    protected static function validateParams($params): void
    {
        if ($params !== null && !is_array($params)) {
            throw new InvalidRequestError(
                'You must pass an array as the first argument to Tassi API method calls.'
            );
        }
    }

    protected static function staticRequest(string $method, string $url, ?array $params = null, ?array $headers = null): array
    {
        return static::getRequestor()->request(
            $method,
            $url,
            $params ?? [],
            $headers ?? []
        );
    }

    protected static function _retrieve($id, ?array $headers = null)
    {
        $url = static::resourcePath($id);
        $className = static::className();

        $response = static::staticRequest('get', $url, null, $headers);
        $data = $response['data'];
        $options = $response['options'];

        $objData = $data[$className] ?? $data;

        return Util::arrayToTassiObject($objData, $options);
    }

    protected static function _all(?array $params = null, ?array $headers = null)
    {
        static::validateParams($params);
        $path = static::classPath();

        $response = static::staticRequest('get', $path, $params, $headers);
        return Util::arrayToTassiObject($response['data'], $response['options']);
    }

    protected static function _create(array $params, ?array $headers = null)
    {
        static::validateParams($params);
        $url = static::classPath();
        $className = static::className();

        $response = static::staticRequest('post', $url, $params, $headers);
        $data = $response['data'];
        $options = $response['options'];

        $objData = $data[$className] ?? $data;

        return Util::arrayToTassiObject($objData, $options);
    }

    protected static function _update($id, array $params, ?array $headers = null)
    {
        static::validateParams($params);
        $url = static::resourcePath($id);
        $className = static::className();

        $response = static::staticRequest('put', $url, $params, $headers);
        $data = $response['data'];
        $options = $response['options'];

        $objData = $data[$className] ?? $data;

        return Util::arrayToTassiObject($objData, $options);
    }

    protected function _delete(?array $headers = null)
    {
        $url = $this->instanceUrl();
        static::staticRequest('delete', $url, [], $headers);
        return $this;
    }
}