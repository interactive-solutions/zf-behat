<?php
/**
 * @author    Antoine Hedgecock <antoine.hedgecock@gmail.com>
 *
 * @copyright Interactive Solutions AB
 */

namespace InteractiveSolutions\ZfBehat\Context;

use Behat\Behat\Context\SnippetAcceptingContext;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\SchemaTool;
use Zend\ServiceManager\ServiceManager;
use Zend\ServiceManager\ServiceManagerAwareInterface;

class DatabaseContext implements SnippetAcceptingContext, ServiceManagerAwareInterface
{
    /**
     * @var string
     */
    private $objectManagerRef = EntityManager::class;

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

    /**
     * @return EntityManager
     */
    public function getEntityManager()
    {
        return $this->serviceManager->get($this->objectManagerRef);
    }

    /**
     * @AfterScenario
     */
    public function cleanup()
    {
        $this
            ->getEntityManager()
            ->getConnection()
            ->close();
    }

    /**
     * @Given a clean database
     */
    public function aCleanDatabase()
    {
        $entityManager = $this->getEntityManager();
        $entityManager->clear();

        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();

        $schemaTool = new SchemaTool($entityManager);
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }
}
