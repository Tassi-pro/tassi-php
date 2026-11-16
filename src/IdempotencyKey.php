<?php

declare(strict_types=1);

namespace Tassi;

/**
 * Idempotency-Key pour les requêtes POST, PUT et PATCH.
 * réponse pendant 24 h, y compris une erreur 4xx. Une 5xx n'est pas mémorisée.
 */
final class IdempotencyKey
{
    public static function random(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Clé stable pour une étape d'une commande
     * Après une erreur 4xx corrigée, passer $attempt = 2 pour obtenir une nouvelle clé.
     */
    public static function forOrder(string $orderReference, string $step, int $attempt = 1): string
    {
        $safe = trim(preg_replace('/[^A-Za-z0-9._-]+/', '-', $orderReference) ?? '', '-');
        $key = sprintf('order-%s-%s', $safe, $step) . ($attempt > 1 ? '-' . $attempt : '');

        return self::check($key);
    }

    public static function check(string $key): string
    {
        if ($key === '' || strlen($key) > 255) {
            throw new \InvalidArgumentException('Une Idempotency-Key doit faire entre 1 et 255 caractères.');
        }

        return $key;
    }
}