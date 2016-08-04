<?php
/**
 * @author Erik Norgren <erik.norgren@interactivesolutions.se>
 * @copyright Interactive Solutions
 */

namespace InteractiveSolutions\ZfBehat\Context;

use Behat\Behat\Context\SnippetAcceptingContext;
use Zend\ServiceManager\ServiceManager;
use Zend\ServiceManager\ServiceManagerAwareInterface;

/**
 * Class ServiceManagerContext
 *
 * This class is intended to be used for easy access
 * to the ZF service manager when we are testing
 * the service layer directly. Hopefully Antoine won't keel me.
 */
class ServiceManagerContext implements SnippetAcceptingContext, ServiceManagerAwareInterface
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
    public function setServiceManager(ServiceManager $serviceManager)
    {
        $this->serviceManager = $serviceManager;
    }

    public function get($class)
    {
        return $this->serviceManager->get($class);
    }
}
