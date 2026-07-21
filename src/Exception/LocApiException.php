<?php

declare(strict_types=1);

namespace Survos\LocBundle\Exception;

class LocApiException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?string $url = null,
        public readonly ?int $statusCode = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
