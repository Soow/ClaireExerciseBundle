<?php

namespace SimpleIT\ClaireAppBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

use SimpleIT\ClaireAppBundle\DependencyInjection\Compiler\TransportCompilerPass;

/**
 * This is the class that loads and manages your bundle configuration
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class SimpleITClaireAppExtension extends Extension
{
    /**
     * {@inheritDoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.xml');

        $container->addCompilerPass(new TransportCompilerPass);

        $listener = $container->getDefinition('simple_it.claire.http.token.transport.listener');

        $listener->addArgument($config['client_id']);
        $listener->addArgument($config['client_secret']);
    }
}