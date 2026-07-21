<?php

declare(strict_types=1);

namespace Survos\LocBundle\Model;

/** Tolerant read of LOC's `pagination` object. Any field LOC omits or adds is fine — nothing here is required. */
final readonly class Pagination
{
    public function __construct(
        public ?int $total = null,
        public ?int $of = null,
        public ?int $current = null,
        public ?int $perPage = null,
        public ?string $first = null,
        public ?string $last = null,
        public ?string $previous = null,
        public ?string $next = null,
        public array $raw = [],
    ) {
    }

    public static function fromArray(array $raw): self
    {
        return new self(
            total: $raw['total'] ?? null,
            of: $raw['of'] ?? null,
            current: $raw['current'] ?? null,
            perPage: $raw['perpage'] ?? null,
            first: $raw['first'] ?? null,
            last: $raw['last'] ?? null,
            previous: $raw['previous'] ?? null,
            next: $raw['next'] ?? null,
            raw: $raw,
        );
    }

    public function hasNext(): bool
    {
        return null !== $this->next;
    }
}
