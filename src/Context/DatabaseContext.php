<?php
/**
 * @author    Antoine Hedgecock <antoine.hedgecock@gmail.com>
 *
 * @copyright Interactive Solutions AB
 */

namespace InteractiveSolutions\ZfBehat\Context;

use Behat\Behat\Context\Context;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\SchemaTool;
use Interop\Container\ContainerInterface;

class DatabaseContext implements Context
{
    /**
     * @var string
     */
    private $objectManagerRef = EntityManager::class;

    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * DatabaseContext constructor.
     *
     * @param ContainerInterface $container
     */
    public function setContainer(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     *
     * @return EntityManager
     */
    public function getEntityManager(): EntityManager
    {
        return $this->container->get($this->objectManagerRef);
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
