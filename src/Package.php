<?php

namespace Tassi;

class Package extends Resource
{
    public static function retrieve($id, ?array $headers = null)
    {
        return self::_retrieve($id, $headers);
    }

    public static function all(?array $params = null, ?array $headers = null)
    {
        return self::_all($params, $headers);
    }

    public static function update($id, ?array $params = null, ?array $headers = null)
    {
        return self::_update($id, $params ?? [], $headers);
    }
}