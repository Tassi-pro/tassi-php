<?php

declare(strict_types=1);

namespace Tassi;

use Tassi\Exception\SignatureVerificationException;

 
final class Webhook
{
    public const DEFAULT_TOLERANCE = 300; // in seconds, 5 minutes

    /**
     *
     * @param string|list<string> $secrets Un secret, ou deux pendant une rotation.
     * @param int|null $now Heure actuelle (pour les tests).
     *
     * @return array<mixed> {id, type, created_at, data: {object: {...}}}
     */
    public static function constructEvent(
        string $payload,
        ?string $signatureHeader,
        string|array $secrets,
        int $tolerance = self::DEFAULT_TOLERANCE,
        ?int $now = null,
    ): array {
        self::verify($payload, $signatureHeader, $secrets, $tolerance, $now);

        try {
            $event = json_decode($payload, true, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (\JsonException $e) {
            throw new SignatureVerificationException('Corps du webhook illisible : ' . $e->getMessage(), 0, $e);
        }
        if (!is_array($event) || !isset($event['id'], $event['type'])) {
            throw new SignatureVerificationException('Le corps du webhook n\'est pas un événement Tassi.');
        }

        return $event;
    }

    /** @param string|list<string> $secrets */
    public static function verify(
        string $payload,
        ?string $signatureHeader,
        string|array $secrets,
        int $tolerance = self::DEFAULT_TOLERANCE,
        ?int $now = null,
    ): void {
        $secrets = array_values(array_filter((array) $secrets, static fn ($s) => is_string($s) && $s !== ''));
        if ($secrets === []) {
            throw new \InvalidArgumentException('Aucun secret de webhook fourni.');
        }

        [$timestamp, $signatures] = self::parseHeader($signatureHeader ?? '');

        if (abs(($now ?? time()) - $timestamp) > $tolerance) {
            throw new SignatureVerificationException(
                'Horodatage hors tolérance : webhook trop ancien, rejoué, ou horloge du serveur décalée.'
            );
        }

        foreach ($secrets as $secret) {
            $expected = self::sign($payload, $timestamp, $secret);
            foreach ($signatures as $signature) {
                if (hash_equals($expected, $signature)) {
                    return; // signature valide
                }
            }
        }

        throw new SignatureVerificationException(
            'Signature invalide : mauvais secret, ou corps modifié avant la vérification (il faut le corps brut).'
        );
    }

    /** Calcule la signature attendue. Utile aussi pour écrire des tests. */
    public static function sign(string $payload, int $timestamp, string $secret): string
    {
        return hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
    }

    /** Construit un en-tête complet. Pour les tests. */
    public static function signatureHeader(string $payload, int $timestamp, string $secret): string
    {
        return sprintf('t=%d,v1=%s', $timestamp, self::sign($payload, $timestamp, $secret));
    }

    /** @return array{0: int, 1: list<string>} */
    private static function parseHeader(string $header): array
    {
        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $header) as $part) {
            $pair = explode('=', trim($part), 2);
            if (count($pair) !== 2) {
                continue;
            }
            [$key, $value] = [trim($pair[0]), trim($pair[1])];
            if ($key === 't' && ctype_digit($value)) {
                $timestamp = (int) $value;
            } elseif ($key === 'v1' && $value !== '') {
                $signatures[] = strtolower($value);
            }
        }

        if ($timestamp === null || $signatures === []) {
            throw new SignatureVerificationException('En-tête Tassi-Signature absent ou mal formé.');
        }

        return [$timestamp, $signatures];
    }
}