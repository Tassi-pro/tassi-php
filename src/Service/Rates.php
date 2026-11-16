<?php

declare(strict_types=1);

namespace Tassi\Service;

use Tassi\Exception\InvalidParametersException;

final class Rates extends AbstractService
{
    /**
     * @param array{pickup_point_id: int, destination: array<string, mixed>, package: array<string, mixed>} $params
     *
     * @return array<mixed> {rates: [...], weights: {...}, international: bool}
     */
    public function get(array $params): array
    {
        $problems = [];
        if (!isset($params['pickup_point_id'])) {
            $problems[] = 'Le point de collecte est obligatoire.';
        }
        if (empty($params['destination']['country_code'])) {
            $problems[] = 'Le pays de destination est obligatoire.';
        }
        $weight = $params['package']['weight'] ?? null;
        if (!is_numeric($weight) || (float) $weight <= 0) {
            $problems[] = 'Le poids du colis doit être supérieur à 0 kg.';
        }
        if ($problems !== []) {
            throw new InvalidParametersException($problems);
        }

        // Ne crée rien : on peut réessayer sans clé.
        return $this->transport->request('POST', '/rates', body: $params, retryable: true);
    }

    /**
     * Retrouve l'option express, economy ou standard dans une réponse de get().
     *
     * @param array<mixed> $ratesResponse
     *
     * @return array<string, mixed>|null
     */
    public static function find(array $ratesResponse, string $optionType): ?array
    {
        foreach ($ratesResponse['rates'] ?? [] as $rate) {
            if (is_array($rate) && ($rate['option_type'] ?? null) === $optionType) {
                return $rate;
            }
        }

        return null;
    }

    /**
     * Clé de cache : même point, même destination, poids arrondi à 0,1 kg.
     *
     * @param array<mixed> $params Les mêmes paramètres que get()
     */
    public static function cacheKey(array $params): string
    {
        $d = is_array($params['destination'] ?? null) ? $params['destination'] : [];
        $place = isset($d['latitude'], $d['longitude'])
            ? sprintf('%.3f,%.3f', (float) $d['latitude'], (float) $d['longitude'])
            : strtolower(trim(($d['postal_code'] ?? '') . '|' . ($d['city'] ?? '') . '|' . ($d['address'] ?? '')));

        return 'tassi_rates_' . hash('sha256', implode('#', [
            $params['pickup_point_id'] ?? '',
            strtoupper((string) ($d['country_code'] ?? '')),
            $place,
            sprintf('%.1f', round((float) ($params['package']['weight'] ?? 0), 1)),
            $params['package']['dimensions'] ?? '',
        ]));
    }
}