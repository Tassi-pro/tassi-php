<?php

namespace Tassi;

class Util
{
    public static function arrayToTassiObject($data, array $options)
    {
        if (is_array($data) && isset($data[0])) {
            return array_map(function ($item) use ($options) {
                return self::convertToTassiObject($item, $options);
            }, $data);
        }

        return self::convertToTassiObject($data, $options);
    }

    protected static function convertToTassiObject($data, array $options)
    {
        if (!is_array($data)) {
            return $data;
        }

        $obj = new TassiObject();
        $obj->refreshFrom($data, $options);

        foreach ($data as $key => $value) {
            if (is_array($value) && !isset($value[0])) {
                $obj->{$key} = self::convertToTassiObject($value, $options);
            } elseif (is_array($value) && isset($value[0])) {
                $obj->{$key} = array_map(function ($item) use ($options) {
                    return is_array($item) ? self::convertToTassiObject($item, $options) : $item;
                }, $value);
            } else {
                $obj->{$key} = $value;
            }
        }

        return $obj;
    }
}