<?php

declare(strict_types=1);

namespace Tassi\Service;

final class WebhookEndpoints extends AbstractService
{
    /** @return array<mixed> {webhook_endpoints: [...]} */
    public function list(): array
    {
        return $this->transport->request('GET', '/webhook_endpoints');
    }

    /**
     * @param list<string> $enabledEvents Types d'événements, ou ['*'] pour tous.
     *
     * @return array<mixed> {webhook_endpoint: {..., secret}}
     */
    public function create(string $url, array $enabledEvents = ['*'], ?string $description = null, ?string $idempotencyKey = null): array
    {
        if (!str_starts_with($url, 'https://')) {
            throw new \InvalidArgumentException('L\'URL d\'un webhook doit commencer par https://');
        }

        $body = ['url' => $url, 'enabled_events' => array_values($enabledEvents)];
        if ($description !== null) {
            $body['description'] = $description;
        }

        return $this->transport->request('POST', '/webhook_endpoints', body: $body, idempotencyKey: $this->key($idempotencyKey));
    }

    /** @return array<mixed> {webhook_endpoint: {...}} */
    public function get(int $id): array
    {
        return $this->transport->request('GET', '/webhook_endpoints/' . self::id($id));
    }

    /**
     * @param array{url?: string, description?: string, enabled_events?: list<string>, status?: 'enabled'|'disabled'} $changes
     *
     * @return array<mixed>
     */
    public function update(int $id, array $changes): array
    {
        return $this->transport->request('PATCH', '/webhook_endpoints/' . self::id($id), body: $changes, retryable: true);
    }

    /** Réactive un endpoint désactivé après 3 jours d'échecs. */
    public function enable(int $id): array
    {
        return $this->update($id, ['status' => 'enabled']);
    }

    public function delete(int $id): void
    {
        $this->transport->request('DELETE', '/webhook_endpoints/' . self::id($id), retryable: true);
    }

    /**
     * Nouveau secret, actif immédiatement : passer l'ancien et le nouveau à
     * Webhook::constructEvent() pendant la bascule.
     *
     * @return array<mixed> {webhook_endpoint: {..., secret}}
     */
    public function rollSecret(int $id, ?string $idempotencyKey = null): array
    {
        return $this->transport->request('POST', '/webhook_endpoints/' . self::id($id) . '/roll_secret', idempotencyKey: $this->key($idempotencyKey));
    }

    /** Envoie un événement `ping` signé, tout de suite, sans nouvel essai. */
    public function test(int $id): array
    {
        return $this->transport->request('POST', '/webhook_endpoints/' . self::id($id) . '/test', retryable: false);
    }
}