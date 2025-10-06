<?php

namespace Tassi;

class Tassi
{
    const VERSION = '1.0.0';

    public static $apiKey = null;
    public static $apiBase = null;
    public static $environment = 'sandbox';
    public static $verifySslCerts = true;

    public static function getApiKey(): ?string
    {
        return self::$apiKey;
    }

    public static function setApiKey(string $apiKey): void
    {
        self::$apiKey = $apiKey;
    }

    public static function getApiBase(): ?string
    {
        return self::$apiBase;
    }

    public static function setApiBase(string $apiBase): void
    {
        self::$apiBase = $apiBase;
    }

    public static function getEnvironment(): string
    {
        return self::$environment;
    }

    public static function setEnvironment(string $environment): void
    {
        self::$environment = $environment;
    }

    public static function getVerifySslCerts(): bool
    {
        return self::$verifySslCerts;
    }

    public static function setVerifySslCerts(bool $verify): void
    {
        self::$verifySslCerts = $verify;
    }
}