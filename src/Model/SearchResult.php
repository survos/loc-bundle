<?php

declare(strict_types=1);

namespace Survos\LocBundle\Model;

/** One entry from a `results` array — shared shape between `/search/` and `/collections/{slug}/` responses. */
final readonly class SearchResult
{
    public function __construct(
        public string $id,
        public ?string $url,
        public ?string $title,
        public ?string $date,
        public array $originalFormat,
        public array $onlineFormat,
        public array $raw,
    ) {
    }

    public static function fromArray(array $raw): self
    {
        $url = $raw['id'] ?? $raw['url'] ?? null;

        return new self(
            id: self::idFromUrl($url) ?? (string) ($raw['number'] ?? $raw['shelf_id'] ?? $url ?? ''),
            url: $url,
            title: $raw['title'] ?? null,
            date: $raw['date'] ?? null,
            originalFormat: (array) ($raw['original_format'] ?? []),
            onlineFormat: (array) ($raw['online_format'] ?? []),
            raw: $raw,
        );
    }

    private static function idFromUrl(?string $url): ?string
    {
        if (null === $url) {
            return null;
        }

        $path = (string) parse_url($url, PHP_URL_PATH);
        $segments = array_values(array_filter(explode('/', $path)));

        return [] === $segments ? null : end($segments);
    }
}
