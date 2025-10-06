<?php

namespace Tassi\Error;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class ApiConnectionError extends TassiError
{
    protected $httpStatus;
    protected $httpRequest;
    protected $httpResponse;

    public function __construct(
        string $message = '',
        ?int $httpStatus = null,
        ?RequestInterface $httpRequest = null,
        ?ResponseInterface $httpResponse = null) {
        parent::__construct($message);
        $this->httpStatus = $httpStatus;
        $this->httpRequest = $httpRequest;
        $this->httpResponse = $httpResponse;
    }

    public function getHttpStatus(): ?int
    {
        return $this->httpStatus;
    }

    public function getHttpRequest(): ?RequestInterface
    {
        return $this->httpRequest;
    }

    public function getHttpResponse(): ?ResponseInterface
    {
        return $this->httpResponse;
    }
}