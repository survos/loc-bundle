<?php

declare(strict_types=1);

namespace Survos\LocBundle\Exception;

class RateLimitException extends LocApiException
{
    public function __construct(
        string $message,
        ?string $url = null,
        public readonly ?int $retryAfter = null,
    ) {
        parent::__construct($message, $url, 429);
    }
}
