<?php

declare(strict_types=1);

namespace Survos\LocBundle;

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

        $services->set(LocClient::class)
            ->arg('$httpClient', service('loc.client'));
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
