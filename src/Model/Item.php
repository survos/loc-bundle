<?php

declare(strict_types=1);

namespace Survos\LocBundle\Model;

/**
 * A `/item/{id}/?fo=json` response. LOC nests most fields under `item`, and lists
 * derivative files under a top-level `resources` array — but that shape is not
 * consistent across collections, so `resources` and `raw` are the source of truth;
 * `title` is a convenience read, not a guarantee.
 */
final readonly class Item
{
    public function __construct(
        public string $id,
        public string $url,
        public ?string $title,
        public array $resources,
        public array $raw,
    ) {
    }

    public static function fromArray(string $id, array $raw): self
    {
        $item = (array) ($raw['item'] ?? []);

        return new self(
            id: $id,
            url: $item['url'] ?? $raw['url'] ?? '',
            title: $item['title'] ?? $raw['title'] ?? null,
            resources: (array) ($raw['resources'] ?? $item['resources'] ?? []),
            raw: $raw,
        );
    }
}
