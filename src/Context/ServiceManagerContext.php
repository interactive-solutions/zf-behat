<?php
/**
 * @author Erik Norgren <erik.norgren@interactivesolutions.se>
 * @copyright Interactive Solutions
 */

namespace InteractiveSolutions\ZfBehat\Context;

use Behat\Behat\Context\Context;
use Zend\ServiceManager\ServiceManager;

/**
 * Class ServiceManagerContext
 *
 * This class is intended to be used for easy access
 * to the ZF service manager when we are testing
 * the service layer directly. Hopefully Antoine won't keel me.
 */
class ServiceManagerContext implements Context
{
    /**
     * @var ServiceManager
     */
    private $serviceManager;

    /**
     * Set service manager
     *
     * @param ServiceManager $serviceManager
     */
    public function setContainer(ServiceManager $serviceManager)
    {
        $this->serviceManager = $serviceManager;
    }

    public function get($class)
    {
        return $this->serviceManager->get($class);
    }

    public function getServiceManager()
    {
        return $this->serviceManager;
    }
}
