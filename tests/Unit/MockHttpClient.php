<?php

declare(strict_types=1);

namespace Tassi\Tests\Unit;

use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class MockHttpClient implements ClientInterface
{
    /** @var list<ResponseInterface|\Throwable> */
    private array $queue = [];

    /** @var list<RequestInterface> */
    public array $requests = [];

    /** Prépare une réponse. Un tableau est encodé en JSON. */
    public function respond(int $status, array|string|null $body = null, array $headers = []): self
    {
        $raw = is_array($body) ? json_encode($body, JSON_THROW_ON_ERROR) : (string) $body;
        $this->queue[] = new Response($status, $headers + ['Content-Type' => 'application/json'], $raw);

        return $this;
    }

    /** Prépare une panne réseau. */
    public function failNetwork(string $message = 'Connection timed out'): self
    {
        $this->queue[] = new class ($message) extends \RuntimeException implements NetworkExceptionInterface {
            public function getRequest(): RequestInterface
            {
                throw new \LogicException('non utilisé');
            }
        };

        return $this;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;
        $next = array_shift($this->queue) ?? throw new \LogicException('Aucune réponse préparée');
        if ($next instanceof \Throwable) {
            throw $next;
        }

        return $next;
    }

    public function last(): RequestInterface
    {
        return $this->requests[array_key_last($this->requests)];
    }

    /** Corps JSON de la dernière requête, décodé. */
    public function lastJson(): ?array
    {
        $body = (string) $this->last()->getBody();

        return $body === '' ? null : json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    }
}