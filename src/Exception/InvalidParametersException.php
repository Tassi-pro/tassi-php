<?php

declare(strict_types=1);

namespace Tassi\Exception;

class InvalidParametersException extends TassiException
{
    /** @param list<string> $problems */
    public function __construct(private readonly array $problems)
    {
        parent::__construct(implode(' ', $problems));
    }

    /** @return list<string> */
    public function getProblems(): array
    {
        return $this->problems;
    }
}