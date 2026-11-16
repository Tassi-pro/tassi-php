<?php

declare(strict_types=1);

namespace Tassi\Service;

/** Suivi public par numéro de suivi. Aucune clé n'est envoyée. */
final class Tracking extends AbstractService
{
    /** @return array<mixed> {shipment, segments, tracking_events, progress} */
    public function get(string $trackingNumber): array
    {
        $trackingNumber = trim($trackingNumber);
        if ($trackingNumber === '') {
            throw new \InvalidArgumentException('Le numéro de suivi est vide.');
        }

        return $this->transport->request(
            'GET',
            '/tracking/' . rawurlencode($trackingNumber),
            authenticated: false,
        );
    }
}