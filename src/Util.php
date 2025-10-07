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
            if (!is_array($value)) {
                // Valeur scalaire
                $obj->{$key} = $value;
            } elseif (empty($value)) {
                // Tableau vide - doit rester un tableau
                $obj->{$key} = [];
            } elseif (array_keys($value) === range(0, count($value) - 1)) {
                // Tableau indexé (liste) - vérifier si contient des objets
                $obj->{$key} = array_map(function ($item) use ($options) {
                    return is_array($item) ? self::convertToTassiObject($item, $options) : $item;
                }, $value);
            } else {
                // Tableau associatif - convertir en objet
                $obj->{$key} = self::convertToTassiObject($value, $options);
            }
        }

        return $obj;
    }
}