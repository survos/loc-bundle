<?php

declare(strict_types=1);

namespace Survos\LocBundle\Client;

/** Normalizes the item/collection identifiers LOC accepts in several equivalent forms (bare id, http/https URL, with or without a trailing slash). */
final class UrlNormalizer
{
    /** @var string[] hosts an arbitrary `fo=json` URL is allowed to target */
    public const ALLOWED_HOSTS = ['www.loc.gov', 'loc.gov', 'tile.loc.gov', 'memory.loc.gov'];

    /** Extracts the bare item id from a bare id or a `loc.gov/item/<id>/` URL. */
    public function extractItemId(string $idOrUrl): string
    {
        if (!str_contains($idOrUrl, '/')) {
            return $idOrUrl;
        }

        $path = (string) parse_url($idOrUrl, PHP_URL_PATH);
        $segments = array_values(array_filter(explode('/', $path)));
        $index = array_search('item', $segments, true);

        if (false === $index || !isset($segments[$index + 1])) {
            throw new \InvalidArgumentException(sprintf('Could not extract an item id from "%s".', $idOrUrl));
        }

        return $segments[$index + 1];
    }

    /** Upgrades http to https, ensures a trailing slash, and validates the host is one LOC actually serves JSON from. */
    public function assertAllowedJsonUrl(string $url): string
    {
        $parts = parse_url($url);
        if (!isset($parts['host']) || !in_array($parts['host'], self::ALLOWED_HOSTS, true)) {
            throw new \InvalidArgumentException(sprintf('Refusing to fetch "%s": host is not an allowed loc.gov host.', $url));
        }

        $normalized = 'https://'.$parts['host'].($parts['path'] ?? '/');
        if (!str_ends_with($normalized, '/')) {
            $normalized .= '/';
        }

        if (isset($parts['query'])) {
            $normalized .= '?'.$parts['query'];
        }

        return $normalized;
    }
}
