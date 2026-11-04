<?php

declare(strict_types=1);

/**
 * An error that maps directly to an HTTP status code.
 * The front controller turns it into a JSON error response.
 */
class HttpException extends RuntimeException
{
    private int $status;

    public function __construct(int $status, string $message)
    {
        parent::__construct($message);
        $this->status = $status;
    }

    public function getStatus(): int
    {
        return $this->status;
    }
}
