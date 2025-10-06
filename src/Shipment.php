<?php

namespace Tassi;

class Shipment extends Resource
{
    public static function create(?array $params = null, ?array $headers = null)
    {
        return self::_create($params ?? [], $headers);
    }
}