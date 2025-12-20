<?php

namespace Tassi;

class Shipment extends Resource
{
    public static function create(?array $params = null, ?array $headers = null)
    {
        return self::_create($params ?? [], $headers);
    }

    public static function confirm($routeId, ?array $headers = null)
    {
        $url = self::classPath() . '/confirm';
        $params = ['route_id' => $routeId];

        $response = self::staticRequest('post', $url, $params, $headers);
        return Util::arrayToTassiObject($response['data'], $response['options']);
    }
}