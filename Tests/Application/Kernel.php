<?php

declare(strict_types=1);

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\HeadlessBundle\Tests\Application;

use Sulu\Bundle\HeadlessBundle\SuluHeadlessBundle;
use Sulu\Bundle\HeadlessBundle\Tests\Application\Testing\HeadlessBundleKernelBrowser;
use Sulu\Bundle\TestBundle\Kernel\SuluTestKernel;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

class Kernel extends SuluTestKernel implements CompilerPassInterface
{
    /**
     * @return BundleInterface[]
     */
    public function registerBundles(): array
    {
        /** @var BundleInterface[] $bundles */
        $bundles = parent::registerBundles();
        $bundles[] = new SuluHeadlessBundle();

        return $bundles;
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        parent::registerContainerConfiguration($loader);
        $loader->load(__DIR__ . '/config/config.yml');
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getKernelParameters()
    {
        $parameters = parent::getKernelParameters();

        $gedmoFileName = (new \ReflectionClass(\Gedmo\Exception::class))->getFileName();
        if ($gedmoFileName) {
            $parameters['gedmo_directory'] = \dirname($gedmoFileName);
        }

        return $parameters;
    }

    public function process(ContainerBuilder $container): void
    {
        // Will be removed, as soon as the min-requirement of sulu/sulu is high enough for the `SuluKernelBrowser` to be always available.
        if ($container->hasDefinition('test.client')) {
            $definition = $container->getDefinition('test.client');

            if (\Sulu\Bundle\TestBundle\Kernel\SuluKernelBrowser::class !== $definition->getClass()) {
                $definition->setClass(HeadlessBundleKernelBrowser::class);
            }
        }
    }
}
