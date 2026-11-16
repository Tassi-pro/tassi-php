<?php

declare(strict_types=1);

namespace Tassi\Service;
final class PickupPoints extends AbstractService
{
    /** @return array<mixed> {pickup_points: [...], meta: {...}} */
    public function list(?int $page = null, bool $serviceableOnly = false): array
    {
        return $this->transport->request('GET', '/marketplace_module/pickup_points', [
            'page' => $page,
            'serviceable' => $serviceableOnly ? '1' : null,
        ]);
    }

    /** @return array<mixed> {pickup_point: {...}} */
    public function get(int $id): array
    {
        return $this->transport->request('GET', '/marketplace_module/pickup_points/' . self::id($id));
    }

    /**
     * @param array<string, mixed> $pickupPoint name, address, city, country_id obligatoires.
     *
     * @return array<mixed> {pickup_point: {...}}
     */
    public function create(array $pickupPoint, ?string $idempotencyKey = null): array
    {
        return $this->transport->request(
            'POST',
            '/marketplace_module/pickup_points',
            body: ['pickup_point' => $pickupPoint],
            idempotencyKey: $this->key($idempotencyKey),
        );
    }

    /**
     * @param array<string, mixed> $pickupPoint
     *
     * @return array<mixed> {pickup_point: {...}}
     */
    public function update(int $id, array $pickupPoint): array
    {
        return $this->transport->request(
            'PUT',
            '/marketplace_module/pickup_points/' . self::id($id),
            body: ['pickup_point' => $pickupPoint],
            retryable: true, // un PUT remet la même valeur : le rejouer est sans risque
        );
    }

    /** @return array<mixed> */
    public function delete(int $id): array
    {
        return $this->transport->request(
            'DELETE',
            '/marketplace_module/pickup_points/' . self::id($id),
            retryable: true,
        );
    }
}