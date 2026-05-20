<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

final class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $configDir = $this->getConfigDir();

        $container->import($configDir . '/{packages}/*.{php,xml,yaml}');
        $container->import($configDir . '/{packages}/' . $this->environment . '/*.{php,xml,yaml}');
        $container->import($configDir . '/{services}.{php,xml,yaml}');
        $container->import($configDir . '/{services}_' . $this->environment . '.{php,xml,yaml}');
    }
}
