<?php
/**
 * @author    Antoine Hedgecock <antoine.hedgecock@gmail.com>
 *
 * @copyright Interactive Solutions AB
 */

namespace InteractiveSolutions\ZfBehat\ServiceContainer;

use Behat\Behat\Context\ServiceContainer\ContextExtension;
use Behat\Testwork\ServiceContainer\Extension;
use Behat\Testwork\ServiceContainer\ExtensionManager;
use InteractiveSolutions\ZfBehat\Context\Initializer\ApiClientInitializer;
use InteractiveSolutions\ZfBehat\Context\Initializer\MailcatcherClientInitializer;
use InteractiveSolutions\ZfBehat\Context\Initializer\ServiceManagerInitializer;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class ZfBehatExtension implements Extension
{
    /**
     * You can modify the container here before it is dumped to PHP code.
     *
     * @param ContainerBuilder $container
     *
     * @api
     */
    public function process(ContainerBuilder $container)
    {
        // TODO: Implement process() method.
    }

    /**
     * Returns the extension config key.
     *
     * @return string
     */
    public function getConfigKey()
    {
        return 'zf';
    }

    /**
     * Initializes other extensions.
     *
     * This method is called immediately after all extensions are activated but
     * before any extension `configure()` method is called. This allows extensions
     * to hook into the configuration of other extensions providing such an
     * extension point.
     *
     * @param ExtensionManager $extensionManager
     */
    public function initialize(ExtensionManager $extensionManager)
    {
        // TODO: Implement initialize() method.
    }

    /**
     * Setups configuration for the extension.
     *
     * @param ArrayNodeDefinition $builder
     */
    public function configure(ArrayNodeDefinition $builder)
    {
        $builder
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('config_file')->defaultValue('config/application.config.php')->end()
                ->scalarNode('api_uri')->defaultValue('http://localhost')->end()
                ->scalarNode('mailcatcher_url')->defaultValue('http://mailcatcher:1080')->end()
            ->end();
    }

    /**
     * Loads extension services into temporary container.
     *
     * @param ContainerBuilder $container
     * @param array            $config
     */
    public function load(ContainerBuilder $container, array $config)
    {
        // Service manager
        $definition = new Definition(ServiceManagerInitializer::class, [$config['config_file']]);
        $definition->addTag(ContextExtension::INITIALIZER_TAG);

        $container->setDefinition('zf.service_manager.initializer', $definition);

        // Api client
        $definition = new Definition(ApiClientInitializer::class, [$config['api_uri']]);
        $definition->addTag(ContextExtension::INITIALIZER_TAG);

        $container->setDefinition('zf.api_client', $definition);        
        
        // Mailcatcher client
        $definition = new Definition(MailcatcherClientInitializer::class, [$config['mailcatcher_url']]);
        $definition->addTag(ContextExtension::INITIALIZER_TAG);

        $container->setDefinition('zf.mailcatcher_client', $definition);
    }
}
