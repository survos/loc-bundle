<?php

declare(strict_types=1);

namespace Survos\LocBundle\Client;

use Survos\LocBundle\Model\CollectionPage;
use Survos\LocBundle\Model\Item;
use Survos\LocBundle\Model\SearchPage;

interface LocClientInterface
{
    /** Raw decoded `/search/?fo=json` response. */
    public function search(array $query): array;

    public function searchPage(array $query): SearchPage;

    /** Raw decoded `/collections/{slug}/?fo=json` response. */
    public function getCollection(string $collection, array $query = []): array;

    public function getCollectionPage(string $collection, array $query = []): CollectionPage;

    /**
     * Lazily follows `pagination.next` across an entire collection, fetching one page
     * at a time. Never buffers more than one page of results in memory.
     *
     * @return iterable<\Survos\LocBundle\Model\SearchResult>
     */
    public function iterateCollection(string $collection, array $query = []): iterable;

    /** Accepts a bare item id, a canonical `loc.gov/item/{id}/` URL, or an API result `id`. */
    public function getItem(string $idOrUrl, array $query = []): array;

    public function getItemDto(string $idOrUrl, array $query = []): Item;

    /** Fetches an arbitrary `fo=json` LOC URL discovered inside a previous response (host-restricted). */
    public function getJsonUrl(string $url): array;
}
