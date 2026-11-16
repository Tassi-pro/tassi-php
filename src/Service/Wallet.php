<?php

declare(strict_types=1);

namespace Tassi\Service;

use Tassi\Amount;

final class Wallet extends AbstractService
{
    private const AMOUNT_FIELDS = ['balance', 'held_amount', 'available_balance'];

    /**
     * L'API renvoie ces montants en nombres JSON : on les convertit en chaînes ("988.65").
     *
     * @return array<mixed> {wallet: {balance, held_amount, available_balance, currency, last_move_date}}
     */
    public function get(): array
    {
        $data = $this->transport->request('GET', '/marketplace_module/wallet');

        if (isset($data['wallet']) && is_array($data['wallet'])) {
            foreach (self::AMOUNT_FIELDS as $field) {
                if (array_key_exists($field, $data['wallet'])) {
                    $data['wallet'][$field] = Amount::normalize($data['wallet'][$field]);
                }
            }
        }

        return $data;
    }

    /**
     * @param 'credit'|'debit'|null $type
     *
     * @return array<mixed> {wallet_movements: [...], meta: {...}}
     */
    public function history(?string $type = null, ?int $page = null, ?int $perPage = null): array
    {
        if ($type !== null && !in_array($type, ['credit', 'debit'], true)) {
            throw new \InvalidArgumentException('type doit valoir "credit" ou "debit".');
        }

        return $this->transport->request('GET', '/marketplace_module/wallet/history', [
            'type' => $type,
            'page' => $page,
            'per_page' => $perPage,
        ]);
    }
}