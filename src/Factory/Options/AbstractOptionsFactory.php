<?php
/**
 * @author Erik Norgren <erik.norgren@interactivesolutions.se>
 * @copyright Interactive Solutions
 */

namespace InteractiveSolutions\ZfBehat\Factory\Options;

use Zend\ServiceManager\AbstractFactoryInterface;
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
     * Determine if we can create a service with name
     *
     * @param ServiceLocatorInterface $serviceLocator
     * @param $name
     * @param $requestedName
     * @return bool
     */
    public function canCreateServiceWithName(ServiceLocatorInterface $serviceLocator, $name, $requestedName)
    {
        foreach ($this->validPrefixes as $prefix) {
            if (strpos($requestedName, $prefix) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Create service with name
     *
     * @param ServiceLocatorInterface $serviceLocator
     * @param $name
     * @param $requestedName
     * @return mixed
     */
    public function createServiceWithName(ServiceLocatorInterface $serviceLocator, $name, $requestedName)
    {
        $config = $serviceLocator->get('Config')[static::APP_CONFIG_KEY]['options'];
        $config = isset($config[$requestedName]) ? $config[$requestedName] : [];

        return new $requestedName($config);
    }
}
