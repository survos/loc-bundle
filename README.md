# survos/loc-bundle

Symfony bundle client for the [Library of Congress JSON API](https://www.loc.gov) (`loc.gov`) —
search, collection, and item lookup, with raw responses always preserved alongside tolerant DTOs.

No API key is required for read access. LOC exposes JSON on ordinary `loc.gov` pages by adding
`fo=json` to the query string; this bundle handles that automatically.

This is a **Phase 1** implementation (see
[survos/mono#23](https://github.com/survos/mono/issues/23)): the API client, DI wiring, and basic
console commands. Deliberately **not** included yet, per the issue's own phasing:

- recursive resource/asset extraction beyond the raw `resources` array (Phase 2)
- response caching, Symfony RateLimiter integration, retries, streamed asset downloads with
  resume/checksums (Phase 3)
- Messenger-based harvesting (Phase 4)

`Item::$resources` and `Item::$raw` already carry everything a resource walker would need — adding
one is additive, not a breaking change to this phase.

## Installation

```bash
composer require survos/loc-bundle
```

## Configuration

```yaml
# config/packages/survos_loc.yaml
survos_loc:
    base_url: 'https://www.loc.gov'
    user_agent: 'Museado LOC Harvester/0.1 (+https://museado.org)'
    timeout: 30
```

All three keys are optional; the defaults above (with a generic `Survos LocBundle/0.1` user agent)
work out of the box.

## Usage

```php
use Survos\LocBundle\Client\LocClientInterface;

final readonly class ImportService
{
    public function __construct(private LocClientInterface $locClient) {}

    public function import(): void
    {
        foreach ($this->locClient->iterateCollection('voices-remembering-slavery') as $result) {
            $item = $this->locClient->getItemDto($result->id);

            foreach ($item->resources as $resource) {
                // raw LOC resource array — walk/extract per-file URLs as needed (Phase 2)
            }
        }
    }
}
```

- `search(array $query): array` / `searchPage(array $query): SearchPage` — `/search/`.
- `getCollection(string $collection, array $query = []): array` / `getCollectionPage(...)` —
  `/collections/{slug}/`.
- `iterateCollection(string $collection, array $query = []): iterable<SearchResult>` — lazily
  follows `pagination.next`, fetching one page at a time.
- `getItem(string $idOrUrl, array $query = []): array` / `getItemDto(...): Item` — accepts a bare
  item id or a canonical `loc.gov/item/{id}/` URL.
- `getJsonUrl(string $url): array` — fetches an arbitrary `fo=json` URL discovered in a previous
  response; restricted to `www.loc.gov` / `tile.loc.gov` / `memory.loc.gov`.

Every DTO (`SearchResult`, `Item`, `SearchPage`, `CollectionPage`, `Pagination`) keeps its source
array on `->raw` — unknown/future LOC fields never cause a hydration failure.

## Console commands

```bash
bin/console loc:search "oral history" --limit=10
bin/console loc:collection:list voices-remembering-slavery --limit=20
bin/console loc:item:get afc1950037_afs09990a --resources
```

## Test collections

Useful real collections for manual testing (see the issue for why each is interesting):

- <https://www.loc.gov/collections/voices-remembering-slavery/>
- <https://www.loc.gov/collections/todd-and-sonkin-migrant-workers-from-1940-to-1941/>
- <https://www.loc.gov/collections/interviews-following-the-attack-on-pearl-harbor/>

## Downloading vs. reuse

Successfully fetching or downloading an asset through this bundle says nothing about whether it is
legally reusable. Rights fields on LOC responses (`item.rights`, `item.rights_advisory`, etc.) are
preserved unchanged on `Item::$raw` — nothing in this bundle collapses them into a boolean.
