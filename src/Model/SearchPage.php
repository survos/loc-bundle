<?php

declare(strict_types=1);

namespace Survos\LocBundle\Model;

/** One page of `/search/?fo=json` results. */
final readonly class SearchPage
{
    /** @param SearchResult[] $results */
    public function __construct(
        public array $results,
        public ?Pagination $pagination,
        public array $raw,
    ) {
    }

    public static function fromArray(array $raw): self
    {
        return new self(
            results: array_map(SearchResult::fromArray(...), $raw['results'] ?? []),
            pagination: isset($raw['pagination']) ? Pagination::fromArray($raw['pagination']) : null,
            raw: $raw,
        );
    }
}
