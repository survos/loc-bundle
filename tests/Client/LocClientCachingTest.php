<?php

declare(strict_types=1);

namespace Survos\LocBundle\Tests\Client;

use PHPUnit\Framework\TestCase;
use Survos\LocBundle\Client\LocClient;
use Symfony\Component\Cache\Adapter\FilesystemTagAwareAdapter;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\CachingHttpClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/** Mirrors how SurvosLocBundle wires CachingHttpClient — verifies the wiring, not just the class in isolation. */
final class LocClientCachingTest extends TestCase
{
    private string $cacheDir;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir().'/loc-bundle-cache-test-'.bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->cacheDir);
    }

    public function testRepeatedRequestIsServedFromCacheNotTheNetwork(): void
    {
        $calls = 0;
        $mock = new MockHttpClient(function () use (&$calls): MockResponse {
            ++$calls;

            return new MockResponse(
                json_encode(['item' => ['title' => 'Cached Item'], 'resources' => []]),
                ['response_headers' => ['Cache-Control' => 'max-age=86400']],
            );
        }, 'https://www.loc.gov');

        $cache = new FilesystemTagAwareAdapter('loc_http_test', 86400, $this->cacheDir);
        $caching = new CachingHttpClient($mock, $cache, ['base_uri' => 'https://www.loc.gov'], true, 86400);
        $client = new LocClient($caching);

        $first = $client->getItemDto('afc1941016_afs05499a');
        $second = $client->getItemDto('afc1941016_afs05499a');

        self::assertSame('Cached Item', $first->title);
        self::assertSame('Cached Item', $second->title);
        self::assertSame(1, $calls, 'The second identical request should be served from cache, not hit the network again.');
    }
}
