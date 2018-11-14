<?php

namespace Uay\YEntityGeneratorBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class UayEntitiesExtension extends Extension
{
    public const PARAMETER_CONFIG = 'uay_entities.config';

    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader (
            $container,
            new FileLocator(__DIR__ . '/../Resources/config')
        );
        try {
            $loader->load('services.yaml');
        } catch (\Exception $ex) {
            throw new \RuntimeException($ex);
        }

        $config = array_reduce($configs, 'array_merge', []);

        if (!isset($config['entities']) || !\is_array($config['entities'])) {
            throw new \RuntimeException('Configuration parameter `entities` is missing or invalid!');
        }

        $container->setParameter(
            static::PARAMETER_CONFIG,
            $config
        );
    }
}
