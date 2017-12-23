<?php
/**
 * @author    Erik Norgren <erik.norgren@interactivesolutions.se>
 * @copyright Interactive Solutions
 */

namespace InteractiveSolutions\ZfBehat\Factory\Options;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\AbstractFactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

class AbstractOptionsFactory implements AbstractFactoryInterface
{
    const APP_CONFIG_KEY = 'interactive_solutions';

    /**
     * @var array
     */
    private $validPrefixes = [
        'InteractiveSolutions\ZfBehat\Options',
    ];

    /**
     * {@inheritdoc}
     */
    public function canCreate(ContainerInterface $container, $requestedName)
    {
        foreach ($this->validPrefixes as $prefix) {
            if (strpos($requestedName, $prefix) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function __invoke(ContainerInterface $serviceLocator, $requestedName, array $options = null)
    {
        $config = $serviceLocator->get('config')[static::APP_CONFIG_KEY]['options'];
        $config = isset($config[$requestedName]) ? $config[$requestedName] : [];

        return new $requestedName($config);
    }
}
