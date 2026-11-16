<?php

declare(strict_types=1);

namespace Tassi\Service;

use Tassi\Http\HttpTransport;

/**
 * Les services renvoient le JSON de l'API tel quel, en tableau associatif.
 *
 * @internal
 */
abstract class AbstractService
{
    public function __construct(protected readonly HttpTransport $transport) {}

    protected static function id(int $id): string
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException('Un identifiant doit être un entier positif.');
        }

        return (string) $id;
    }

    protected function key(?string $idempotencyKey): string
    {
        return $idempotencyKey !== null
            ? \Tassi\IdempotencyKey::check($idempotencyKey)
            : \Tassi\IdempotencyKey::random();
    }
}
