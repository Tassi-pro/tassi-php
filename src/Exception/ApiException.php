<?php

declare(strict_types=1);

namespace Tassi\Exception;

class ApiException extends TassiException
{

    public function __construct(

        string $message,
        private readonly int $statusCode,
        private readonly array $messages = [],
        private readonly array $errors = [],
        private readonly ?string $model = null,
        private readonly string $responseBody = "",
    ) {
        parent::__construct($message, $statusCode);
    }

    static public function fromResponse(int $statusCode, string $body): self
    {
        $messages = [];
        $errors = [];
        $model = null;

        try {
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $data = null;
        }

        if (is_array($data)) {
            $raw = $data['message'] ?? null;
            if (is_string($raw)) {
                $messages = [$raw];
            } elseif (is_array($raw)) {
                $messages = array_values(array_map('strval', array_filter($raw, 'is_scalar')));
            }
            $errors = is_array($data['errors'] ?? null) ? $data['errors'] : [];
            $model = is_string($data['model'] ?? null) ? $data['model'] : null;
        }

        $message = $messages !== []
            ? implode(' ; ', $messages)
            : sprintf('Erreur HTTP %d renvoyée par l\'API Tassi', $statusCode);

        $class = match (true) {
            $statusCode === 400 => InvalidRequestException::class,
            $statusCode === 401 => AuthenticationException::class,
            $statusCode === 403 => PermissionException::class,
            $statusCode === 404 => NotFoundException::class,
            $statusCode === 409 => IdempotencyException::class,
            $statusCode === 422 => UnprocessableEntityException::class,
            $statusCode === 429 => RateLimitException::class,
            $statusCode >= 500 => ServerException::class,
            default => self::class,
        };

        return new $class(
            message: $message,
            statusCode: $statusCode,
            messages: $messages,
            errors: $errors,
            model: $model,
            responseBody: $body,
        );
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getMessages(): array
    {
        return $this->messages;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function getResponseBody(): string
    {
        return $this->responseBody;
    }
}
