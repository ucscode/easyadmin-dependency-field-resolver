<?php

namespace Ucscode\EasyAdmin\DependencyFieldResolver;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class DependencyFieldResolverBundle extends AbstractBundle
{
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $services = $container->services()
            ->defaults()
                ->autowire()
                ->autoconfigure()
        ;

        $services
            ->load(sprintf('%s\\', $this->getNamespace()), '../src/')
            ->exclude(sprintf('../src/{DependencyInjection,Entity,%s.php}', $this->getName()))
        ;
    }

}
