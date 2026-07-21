<?php

declare(strict_types=1);

namespace Survos\LocBundle\Model;

/** One page of `/collections/{slug}/?fo=json` results. */
final readonly class CollectionPage
{
    /** @param SearchResult[] $results */
    public function __construct(
        public string $collection,
        public array $results,
        public ?Pagination $pagination,
        public array $raw,
    ) {
    }

    public static function fromArray(string $collection, array $raw): self
    {
        return new self(
            collection: $collection,
            results: array_map(SearchResult::fromArray(...), $raw['results'] ?? []),
            pagination: isset($raw['pagination']) ? Pagination::fromArray($raw['pagination']) : null,
            raw: $raw,
        );
    }
}
