<?php

declare(strict_types=1);

use App\Kernel;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\PhpConfigReferenceDumpPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

require dirname(__DIR__) . '/vendor/autoload.php';

$container = new ContainerBuilder();
$container->getCompilerPassConfig()->addPass(new PhpConfigReferenceDumpPass('/var/www/html/config/reference.php', []));

$kernel = new Kernel('dev', true);
$build = new ReflectionMethod($kernel, 'build');
$build->setAccessible(true);
$build->invoke($kernel, $container);

foreach ($container->getCompilerPassConfig()->getBeforeOptimizationPasses() as $pass) {
    if ($pass instanceof PhpConfigReferenceDumpPass) {
        fwrite(STDERR, "Kernel should remove Symfony's config/reference.php dump pass at runtime.\n");
        exit(1);
    }
}

fwrite(STDOUT, "OK\n");
