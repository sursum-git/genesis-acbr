<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\PhpConfigReferenceDumpPass;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

final class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    protected function build(ContainerBuilder $container): void
    {
        $passConfig = $container->getCompilerPassConfig();
        $passConfig->setBeforeOptimizationPasses(array_values(array_filter(
            $passConfig->getBeforeOptimizationPasses(),
            static fn (CompilerPassInterface $pass): bool => !$pass instanceof PhpConfigReferenceDumpPass
        )));
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $configDir = $this->getConfigDir();

        $container->import($configDir . '/{packages}/*.{php,xml,yaml}');
        $container->import($configDir . '/{packages}/' . $this->environment . '/*.{php,xml,yaml}');
        $container->import($configDir . '/{services}.{php,xml,yaml}');
        $container->import($configDir . '/{services}_' . $this->environment . '.{php,xml,yaml}');
    }
}
