<?php
/**
 * @author    Antoine Hedgecock <antoine.hedgecock@gmail.com>
 *
 * @copyright Interactive Solutions AB
 */

namespace InteractiveSolutions\ZfBehat\Context\Initializer;

use Behat\Behat\Context\Context;
use Behat\Behat\Context\Initializer\ContextInitializer;
use Zend\Mvc\Service\ServiceManagerConfig;
use Zend\ServiceManager\ServiceManager;
use Zend\ServiceManager\ServiceManagerAwareInterface;

class ServiceManagerInitializer implements ContextInitializer
{
    /**
     * @var string
     */
    private $file;

    /**
     * @param string $file
     */
    public function __construct($file)
    {
        $this->file = $file;
    }

    /**
     * Initializes provided context.
     *
     * @param Context $context
     */
    public function initializeContext(Context $context)
    {
        if (!$context instanceof ServiceManagerAwareInterface) {
            return;
        }

        if (!file_exists($this->file)) {
            throw new \LogicException(sprintf('The file %s was not found', $this->file));
        }

        // We _should_ always be in the root of a zf2 project when executing this
        $config = include $this->file;

        $serviceManager = new ServiceManager(new ServiceManagerConfig($config));
        $serviceManager->setService('ApplicationConfig', $config);

        /* @var $moduleManager \Zend\ModuleManager\ModuleManager */
        $moduleManager = $serviceManager->get('ModuleManager');
        $moduleManager->loadModules();

        $context->setServiceManager($serviceManager);
    }
}
