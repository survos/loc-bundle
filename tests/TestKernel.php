<?php

declare(strict_types=1);

namespace Survos\LocBundle\Tests;

use Survos\Kit\SurvosKitBundle;
use Survos\LocBundle\SurvosLocBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;

final class TestKernel extends Kernel
{
    public function registerBundles(): array
    {
        return [
            new FrameworkBundle(),
            new SurvosKitBundle(),
            new SurvosLocBundle(),
        ];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(function (ContainerBuilder $container): void {
            $container->loadFromExtension('framework', [
                'secret' => 'test',
                'test' => true,
                'http_client' => [],
            ]);
        });
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/loc-bundle-tests/cache/'.spl_object_hash($this);
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/loc-bundle-tests/log';
    }
}
