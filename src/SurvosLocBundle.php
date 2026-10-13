<?php

declare(strict_types=1);

namespace Survos\LocBundle;

use Survos\FetchBundle\Http\CachingHttpClientFactory;
use Survos\Kit\AbstractSurvosBundle;
use Survos\Kit\SurvosKitBundle;
use Survos\LocBundle\Client\LocClient;
use Survos\LocBundle\Client\LocClientInterface;
use Survos\LocBundle\Client\UrlNormalizer;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\RequiredBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

#[RequiredBundle(SurvosKitBundle::class)]
// Symfony\Component\HttpKernel\Bundle\Bundle <-- Flex auto-registration marker (see Survos\Kit\AbstractSurvosBundle)
final class SurvosLocBundle extends AbstractSurvosBundle
{
    private const DEFAULT_BASE_URL = 'https://www.loc.gov';
    private const DEFAULT_USER_AGENT = 'Survos LocBundle/0.1 (+https://github.com/survos/mono)';
    private const DEFAULT_TIMEOUT = 30;
    private const DEFAULT_CACHE_MAX_TTL = 86400;

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('base_url')
                    ->defaultValue(self::DEFAULT_BASE_URL)
                    ->info('Root of the LOC JSON API. No API key is required for read access.')
                ->end()
                ->scalarNode('user_agent')
                    ->defaultValue(self::DEFAULT_USER_AGENT)
                    ->info('Sent as the User-Agent header on every request, per LOC\'s etiquette guidance.')
                ->end()
                ->integerNode('timeout')
                    ->defaultValue(self::DEFAULT_TIMEOUT)
                    ->min(1)
                    ->info('HTTP timeout, in seconds, for the scoped loc.client.')
                ->end()
                ->booleanNode('cache_enabled')
                    ->defaultTrue()
                    ->info('Wrap the client in Symfony\'s RFC 9111 CachingHttpClient. LOC already sends real Cache-Control/ETag headers (24h on item/collection JSON, up to 1 year on tile.loc.gov static files), so this is a correctness-preserving speedup, not a staleness risk — set false only to debug raw request/response behavior.')
                ->end()
                ->integerNode('cache_max_ttl')
                    ->defaultValue(self::DEFAULT_CACHE_MAX_TTL)
                    ->min(1)
                    ->info('Upper bound on how long a response is cached, even if LOC sends a longer max-age.')
                ->end()
            ->end();
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        parent::loadExtension($config, $container, $builder);

        $services = $container->services()
            ->defaults()
                ->autowire()
                ->autoconfigure();

        $services->set(UrlNormalizer::class);

        $clientId = 'loc.client';
        if ($config['cache_enabled']) {
            $clientId = CachingHttpClientFactory::register(
                container: $container,
                idPrefix: 'survos_loc',
                decoratedClientId: 'loc.client',
                cacheDir: '%kernel.cache_dir%/loc_http',
                maxTtl: $config['cache_max_ttl'],
                baseUri: $config['base_url'] ?? self::DEFAULT_BASE_URL,
            );
        }

        $services->set(LocClient::class)
            ->arg('$httpClient', service($clientId));
        $services->alias(LocClientInterface::class, LocClient::class)->public();
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        parent::prependExtension($container, $builder);

        $config = self::rawConfig($builder);

        $builder->prependExtensionConfig('framework', [
            'http_client' => [
                'scoped_clients' => [
                    'loc.client' => [
                        'base_uri' => $config['base_url'] ?? self::DEFAULT_BASE_URL,
                        'headers' => [
                            'Accept' => 'application/json',
                            'User-Agent' => $config['user_agent'] ?? self::DEFAULT_USER_AGENT,
                        ],
                        'timeout' => $config['timeout'] ?? self::DEFAULT_TIMEOUT,
                        // 429 is deliberately NOT retried here -- it's surfaced as RateLimitException
                        // with the real Retry-After value so a caller (e.g. a future Messenger
                        // consumer) can reschedule itself. Auto-retrying it here would swallow that.
                        'retry_failed' => [
                            'enabled' => true,
                            'http_codes' => [500, 502, 503, 504],
                        ],
                    ],
                ],
            ],
        ]);
    }

    /** Reads `survos_loc.*` before the config tree is processed — the standard way to peek a bundle's own config in prependExtension. */
    private static function rawConfig(ContainerBuilder $builder): array
    {
        $merged = [];
        foreach ($builder->getExtensionConfig('survos_loc') as $config) {
            $merged = array_merge($merged, $config);
        }

        return $merged;
    }
}
