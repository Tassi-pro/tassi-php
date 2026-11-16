<?php

declare(strict_types=1);

namespace Tassi\Service;

use Tassi\Exception\InvalidParametersException;

/**
 * Expéditions. create() renvoie les options de livraison ; confirm() avec le
 * route.id de l'option choisie bloque les fonds.
 *
 * Attention : dans delivery_options, `cost` est le prix SANS la commission.
 * Le montant bloqué est le `total_price` de rates->get() pour la même option.
 */
final class Shipments extends AbstractService
{
    /**
     * @param array<string, mixed> $params
     * @param bool $international Vrai si destination et collecte sont dans des pays différents.
     *
     * @return array<mixed> {shipment: {...}, delivery_options: [...]}
     */
    public function create(array $params, ?string $idempotencyKey = null, bool $international = false): array
    {
        self::validateCreate($params, $international);

        return $this->transport->request(
            'POST',
            '/shipments',
            body: $params,
            idempotencyKey: $this->key($idempotencyKey),
        );
    }

    /**
     * Choisit une option et bloque les fonds.
     *
     * @return array<mixed> {message, data: {package, confirmed_route, transport_requests}}
     */
    public function confirm(int $routeId, ?string $idempotencyKey = null): array
    {
        return $this->transport->request(
            'POST',
            '/shipments/confirm',
            body: ['route_id' => $routeId],
            idempotencyKey: $this->key($idempotencyKey),
        );
    }

    /**
     * Annule avant le départ du colis. Les fonds bloqués sont libérés.
     *
     * @return array<mixed>
     */
    public function cancel(int $id, ?string $reason = null, ?string $idempotencyKey = null): array
    {
        return $this->transport->request(
            'POST',
            '/shipments/' . self::id($id) . '/cancel',
            body: $reason !== null ? ['reason' => $reason] : null,
            idempotencyKey: $this->key($idempotencyKey),
        );
    }

    /** @return array<mixed> {package: {...}} */
    public function get(int $id): array
    {
        return $this->transport->request('GET', '/packages/' . self::id($id));
    }

    /** @return array<mixed> {data: [...], pagination: {...}} */
    public function search(?string $query = null, ?int $page = null, ?int $perPage = null): array
    {
        return $this->transport->request('GET', '/shipments/search', [
            'q' => $query,
            'page' => $page,
            'per_page' => $perPage,
        ]);
    }

    /**
     * Retrouve une option de livraison par son type dans la réponse de create().
     *
     * @param array<mixed> $createResponse
     *
     * @return array<string, mixed>|null
     */
    public static function findOption(array $createResponse, string $optionType): ?array
    {
        foreach ($createResponse['delivery_options'] ?? [] as $option) {
            if (is_array($option) && ($option['option_type'] ?? null) === $optionType) {
                return $option;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $params */
    private static function validateCreate(array $params, bool $international): void
    {
        $problems = [];

        if (!isset($params['pickup_point_id']) && !isset($params['pickup_point'])) {
            $problems[] = 'Le point de collecte est obligatoire.';
        }

        // Un destinataire existant (customer_id) ou un nouveau (customer).
        if (!isset($params['customer_id'])) {
            $customer = $params['customer'] ?? null;
            if (!is_array($customer)) {
                $problems[] = 'Le destinataire est obligatoire.';
            } else {
                $required = [
                    'firstname' => 'le prénom',
                    'lastname' => 'le nom',
                    'email' => 'l\'e-mail',
                    'phone_number' => 'le téléphone',
                    'address' => 'l\'adresse',
                ];
                foreach ($required as $field => $label) {
                    if (trim((string) ($customer[$field] ?? '')) === '') {
                        $suffix = $field === 'address' ? ', même avec des coordonnées GPS' : '';
                        $problems[] = sprintf('Destinataire : %s est obligatoire%s.', $label, $suffix);
                    }
                }
            }
        }

        $package = $params['package'] ?? null;
        if (!is_array($package)) {
            $problems[] = 'Le colis est obligatoire.';
        } else {
            $weight = $package['weight'] ?? null;
            if (!is_numeric($weight) || (float) $weight <= 0) {
                $problems[] = 'Le poids du colis doit être supérieur à 0 kg.';
            }
            if (isset($package['currency']) && strtoupper((string) $package['currency']) !== 'EUR') {
                $problems[] = 'Seul l\'euro (EUR) est accepté.';
            }

            $items = $package['items'] ?? [];
            if (!is_array($items) || $items === []) {
                $problems[] = 'Le colis doit contenir au moins un article.';
            } else {
                foreach (array_values($items) as $i => $item) {
                    $n = $i + 1; // "Article 1", plus naturel que "Article 0"
                    if (!is_array($item)) {
                        $problems[] = sprintf('Article %d : format invalide.', $n);
                        continue;
                    }
                    if (trim((string) ($item['description'] ?? '')) === '') {
                        $problems[] = sprintf('Article %d : la description est obligatoire.', $n);
                    }
                    if (!is_int($item['quantity'] ?? null) || $item['quantity'] < 1) {
                        $problems[] = sprintf('Article %d : la quantité doit être un entier d\'au moins 1.', $n);
                    }
                    if (!is_numeric($item['unit_value'] ?? null) || (float) $item['unit_value'] < 0) {
                        $problems[] = sprintf('Article %d : la valeur unitaire est obligatoire.', $n);
                    }
                    if ($international && trim((string) ($item['hs_code'] ?? '')) === '') {
                        $problems[] = sprintf('Article %d : le code SH est obligatoire pour un envoi international.', $n);
                    }
                }
            }
        }

        if ($problems !== []) {
            throw new InvalidParametersException($problems);
        }
    }
}