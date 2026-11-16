<?php

declare(strict_types=1);

namespace Tassi;

/**
 * Montants : toujours exposés en chaînes décimales ("11.35"), jamais en float.
 */
final class Amount
{
    /** Convertit un montant reçu de l'API en chaîne. Les nombres sont arrondis au centime. */
    public static function normalize(mixed $value): ?string
    {
        return match (true) {
            $value === null => null,
            is_string($value) => $value,
            is_int($value) => number_format($value, 2, '.', ''),
            is_float($value) => number_format(round($value, 2), 2, '.', ''),
            default => throw new \InvalidArgumentException('Montant inattendu : ' . get_debug_type($value)),
        };
    }

    /** "11.35" → 1135. Permet de comparer ou d'additionner sans float. */
    public static function toCents(string $amount): int
    {
        if (!preg_match('/^(-?)(\d+)(?:\.(\d+))?$/', trim($amount), $m)) {
            throw new \InvalidArgumentException(sprintf('Montant invalide : "%s"', $amount));
        }
        $decimals = rtrim($m[3] ?? '', '0');
        if (strlen($decimals) > 2) {
            throw new \InvalidArgumentException(sprintf('Montant avec plus de 2 décimales : "%s"', $amount));
        }
        $cents = (int) $m[2] * 100 + (int) str_pad($decimals, 2, '0');

        return $m[1] === '-' ? -$cents : $cents;
    }

    public static function equals(string $a, string $b): bool
    {
        return self::toCents($a) === self::toCents($b);
    }
}