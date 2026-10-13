<?php

declare(strict_types=1);

namespace Survos\LocBundle\Tests\Client;

use PHPUnit\Framework\TestCase;
use Survos\LocBundle\Client\LocClient;
use Survos\LocBundle\Exception\InvalidJsonException;
use Survos\LocBundle\Exception\RateLimitException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class LocClientTest extends TestCase
{
    public function testSearchDecodesResponseAndForcesJsonFormat(): void
    {
        $requestedUrl = null;
        $httpClient = new MockHttpClient(function (string $method, string $url) use (&$requestedUrl): MockResponse {
            $requestedUrl = $url;

            return new MockResponse(json_encode(['results' => [['id' => 'https://www.loc.gov/item/afc1950037_afs09990a/', 'title' => 'Interview']]]));
        }, 'https://www.loc.gov');

        $client = new LocClient($httpClient);
        $response = $client->search(['q' => 'oral history']);

        self::assertSame('Interview', $response['results'][0]['title']);
        self::assertStringContainsString('fo=json', $requestedUrl);
        self::assertStringContainsString('q=oral', $requestedUrl);
    }

    public function testIterateCollectionFollowsPaginationNext(): void
    {
        $calls = 0;
        $httpClient = new MockHttpClient(function () use (&$calls): MockResponse {
            ++$calls;

            if (1 === $calls) {
                return new MockResponse(json_encode([
                    'results' => [['id' => 'https://www.loc.gov/item/one/', 'title' => 'First']],
                    'pagination' => ['next' => 'https://www.loc.gov/collections/demo/?sp=2&fo=json'],
                ]));
            }

            return new MockResponse(json_encode([
                'results' => [['id' => 'https://www.loc.gov/item/two/', 'title' => 'Second']],
                'pagination' => ['next' => null],
            ]));
        }, 'https://www.loc.gov');

        $client = new LocClient($httpClient);
        $titles = array_map(static fn ($result) => $result->title, iterator_to_array($client->iterateCollection('demo')));

        self::assertSame(['First', 'Second'], $titles);
        self::assertSame(2, $calls);
    }

    public function testGetItemAcceptsCanonicalUrl(): void
    {
        $requestedUrl = null;
        $httpClient = new MockHttpClient(function (string $method, string $url) use (&$requestedUrl): MockResponse {
            $requestedUrl = $url;

            return new MockResponse(json_encode(['item' => ['title' => 'Voices'], 'resources' => [['url' => 'https://tile.loc.gov/a.mp3']]]));
        }, 'https://www.loc.gov');

        $client = new LocClient($httpClient);
        $item = $client->getItemDto('https://www.loc.gov/item/afc1950037_afs09990a/');

        self::assertSame('afc1950037_afs09990a', $item->id);
        self::assertSame('Voices', $item->title);
        self::assertCount(1, $item->resources);
        self::assertStringContainsString('/item/afc1950037_afs09990a/', $requestedUrl);
    }

    public function testInvalidJsonRaisesInvalidJsonException(): void
    {
        $httpClient = new MockHttpClient(fn () => new MockResponse('<html>rate limited</html>'), 'https://www.loc.gov');
        $client = new LocClient($httpClient);

        $this->expectException(InvalidJsonException::class);
        $client->search(['q' => 'x']);
    }

    public function testTooManyRequestsRaisesRateLimitException(): void
    {
        $httpClient = new MockHttpClient(
            fn () => new MockResponse('', ['http_code' => 429, 'response_headers' => ['Retry-After' => '30']]),
            'https://www.loc.gov',
        );
        $client = new LocClient($httpClient);

        try {
            $client->search(['q' => 'x']);
            self::fail('Expected a RateLimitException.');
        } catch (RateLimitException $e) {
            self::assertSame(30, $e->retryAfter);
        }
    }

    public function testGetJsonUrlRejectsDisallowedHost(): void
    {
        $httpClient = new MockHttpClient(fn () => new MockResponse('{}'), 'https://www.loc.gov');
        $client = new LocClient($httpClient);

        $this->expectException(\InvalidArgumentException::class);
        $client->getJsonUrl('https://evil.example.com/item/x/?fo=json');
    }
}
