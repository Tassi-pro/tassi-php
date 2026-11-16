<?php

declare(strict_types=1);

namespace Tassi\Service;

/**
 * Étiquettes. Le download_url n'exige pas de clé et expire au bout de 2 heures :
 * le relire au moment de l'afficher, ne pas le stocker.
 */
final class Labels extends AbstractService
{
    /** @return array<mixed> {shipping_labels: [...]}, la plus récente en premier */
    public function list(int $packageId): array
    {
        return $this->transport->request('GET', '/packages/' . self::id($packageId) . '/shipping_labels');
    }

    /** @return array<mixed> {shipping_label: {...}} */
    public function get(int $packageId, int $labelId): array
    {
        return $this->transport->request(
            'GET',
            '/packages/' . self::id($packageId) . '/shipping_labels/' . self::id($labelId),
        );
    }

    /**
     * Étiquette la plus récente prête à imprimer, ou null.
     *
     * @return array<string, mixed>|null
     */
    public function latest(int $packageId): ?array
    {
        foreach ($this->list($packageId)['shipping_labels'] ?? [] as $label) {
            if (is_array($label) && in_array($label['status'] ?? null, ['generated', 'printed'], true)) {
                return $label;
            }
        }

        return null;
    }
}