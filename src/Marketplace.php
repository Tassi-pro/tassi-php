<?php

namespace Tassi;

class Marketplace extends Resource
{
    public static function retrieve($id, ?array $headers = null)
    {
        return self::_retrieve($id, $headers);
    }

    public static function update($id, ?array $params = null, ?array $headers = null)
    {
        return self::_update($id, $params ?? [], $headers);
    }

    public function getWalletHistory(?array $params = null, ?array $headers = null)
    {
        $url = $this->instanceUrl() . '/wallet_history';

        $response = static::staticRequest('get', $url, $params ?? [], $headers);
        return Util::arrayToTassiObject($response['data'], $response['options']);
    }
}