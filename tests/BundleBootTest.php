<?php

declare(strict_types=1);

namespace Survos\LocBundle\Tests;

use Survos\LocBundle\Client\LocClientInterface;
use Survos\LocBundle\Command\LocCollectionListCommand;
use Survos\LocBundle\Command\LocItemGetCommand;
use Survos\LocBundle\Command\LocSearchCommand;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\HttpKernel\KernelInterface;

final class BundleBootTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    public function testContainerCompilesAndClientResolves(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        self::assertInstanceOf(LocClientInterface::class, $container->get(LocClientInterface::class));
    }

    public function testCommandsAreRegistered(): void
    {
        self::bootKernel();
        /** @var KernelInterface $kernel */
        $kernel = static::$kernel;

        $application = new Application($kernel);
        $names = array_keys($application->all());

        self::assertContains('loc:search', $names);
        self::assertContains('loc:collection:list', $names);
        self::assertContains('loc:item:get', $names);

        static::getContainer()->get(LocSearchCommand::class);
        static::getContainer()->get(LocCollectionListCommand::class);
        static::getContainer()->get(LocItemGetCommand::class);
    }
}
