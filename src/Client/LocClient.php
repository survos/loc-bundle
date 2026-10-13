<?php

declare(strict_types=1);

namespace Survos\LocBundle\Client;

use Survos\LocBundle\Exception\InvalidJsonException;
use Survos\LocBundle\Exception\LocApiException;
use Survos\LocBundle\Exception\RateLimitException;
use Survos\LocBundle\Model\CollectionPage;
use Survos\LocBundle\Model\Item;
use Survos\LocBundle\Model\SearchPage;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class LocClient implements LocClientInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private UrlNormalizer $urlNormalizer = new UrlNormalizer(),
    ) {
    }

    public function search(array $query): array
    {
        return $this->request('/search/', $query);
    }

    public function searchPage(array $query): SearchPage
    {
        return SearchPage::fromArray($this->search($query));
    }

    public function getCollection(string $collection, array $query = []): array
    {
        return $this->request(sprintf('/collections/%s/', $collection), $query);
    }

    public function getCollectionPage(string $collection, array $query = []): CollectionPage
    {
        return CollectionPage::fromArray($collection, $this->getCollection($collection, $query));
    }

    public function iterateCollection(string $collection, array $query = []): iterable
    {
        $page = $this->getCollectionPage($collection, $query);

        while (true) {
            // Not `yield from $page->results` — each page's array restarts its keys at 0,
            // which would collide across pages and drop entries under iterator_to_array().
            foreach ($page->results as $result) {
                yield $result;
            }

            if (!$page->pagination?->hasNext()) {
                return;
            }

            $page = CollectionPage::fromArray($collection, $this->getJsonUrl($page->pagination->next));
        }
    }

    public function getItem(string $idOrUrl, array $query = []): array
    {
        $id = str_contains($idOrUrl, '/') ? $this->urlNormalizer->extractItemId($idOrUrl) : $idOrUrl;

        return $this->request(sprintf('/item/%s/', $id), $query);
    }

    public function getItemDto(string $idOrUrl, array $query = []): Item
    {
        $id = str_contains($idOrUrl, '/') ? $this->urlNormalizer->extractItemId($idOrUrl) : $idOrUrl;

        return Item::fromArray($id, $this->getItem($idOrUrl, $query));
    }

    public function getJsonUrl(string $url): array
    {
        $normalized = $this->urlNormalizer->assertAllowedJsonUrl($url);
        $parts = parse_url($normalized);
        parse_str($parts['query'] ?? '', $query);

        return $this->request($parts['path'] ?? '/', $query, $normalized);
    }

    private function request(string $path, array $query, ?string $absoluteUrl = null): array
    {
        $query['fo'] = 'json';
        $url = $absoluteUrl ?? $path;

        try {
            $response = $this->httpClient->request('GET', $url, ['query' => $query]);
            $statusCode = $response->getStatusCode();

            if (429 === $statusCode) {
                $retryAfter = $response->getHeaders(false)['retry-after'][0] ?? null;
                throw new RateLimitException(sprintf('LOC rate limit hit for %s.', $url), $url, null !== $retryAfter ? (int) $retryAfter : null);
            }

            if ($statusCode >= 300) {
                throw new LocApiException(sprintf('LOC returned HTTP %d for %s.', $statusCode, $url), $url, $statusCode);
            }

            $content = $response->getContent();
        } catch (HttpExceptionInterface $e) {
            throw new LocApiException(sprintf('LOC request to %s failed: %s', $url, $e->getMessage()), $url, null, $e);
        }

        try {
            /** @var array $decoded */
            $decoded = json_decode($content, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new InvalidJsonException(sprintf('LOC response for %s was not valid JSON (got %s).', $url, self::sniff($content)), $url, null, $e);
        }

        return $decoded;
    }

    private static function sniff(string $content): string
    {
        $snippet = trim(substr($content, 0, 40));

        return str_starts_with($snippet, '<') ? 'HTML' : ('' === $snippet ? 'an empty body' : $snippet);
    }
}
