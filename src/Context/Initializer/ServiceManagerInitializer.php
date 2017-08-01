<?php
/**
 * @author    Antoine Hedgecock <antoine.hedgecock@gmail.com>
 *
 * @copyright Interactive Solutions AB
 */

namespace InteractiveSolutions\ZfBehat\Context\Initializer;

use Behat\Behat\Context\Context;
use Behat\Behat\Context\Initializer\ContextInitializer;
use Doctrine\ORM\EntityManager;
use Zend\Mvc\Service\ServiceManagerConfig;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceManager;
use Zend\ServiceManager\ServiceManagerAwareInterface;

class ServiceManagerInitializer implements ContextInitializer
{
    /**
     * @var string
     */
    private $file;

    /**
     * @var ServiceManager
     */
    private $serviceManager;

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

        if (!$this->serviceManager) {
            // We _should_ always be in the root of a zf2 project when executing this
            $config = include $this->file;

            $this->serviceManager = new ServiceManager(new ServiceManagerConfig($config));
            $this->serviceManager->setService('ApplicationConfig', $config);

            /* @var $moduleManager \Zend\ModuleManager\ModuleManager */
            $moduleManager = $this->serviceManager->get('ModuleManager');
            $moduleManager->loadModules();

            $this->clearDoctrineCacheAndGenerateProxies();
        }

        /* @var EntityManager $entityManager */
        $entityManager = $this->serviceManager->get(EntityManager::class);

        if (!$entityManager->isOpen()) {
            $this->serviceManager->setAllowOverride(true);
            $this->serviceManager->setService(
                EntityManager::class,
                EntityManager::create(
                    $entityManager->getConnection(),
                    $entityManager->getConfiguration()
                )
            );
        }

        $context->setServiceManager($this->serviceManager);
    }

    /**
     * Clear the doctrine cache and generate doctrine proxies
     */
    private function clearDoctrineCacheAndGenerateProxies()
    {
        /* @var EntityManager $entityManager */
        $entityManager = $this->serviceManager->get(EntityManager::class);

        // Clear doctrine cache
        $cacheDriver = $entityManager->getConfiguration()->getMetadataCacheImpl();
        $cacheDriver->deleteAll();

        $cacheDriver = $entityManager->getConfiguration()->getQueryCacheImpl();
        $cacheDriver->deleteAll();

        $cacheDriver = $entityManager->getConfiguration()->getResultCacheImpl();
        $cacheDriver->deleteAll();

        // Generate proxies
        $destPath  = $entityManager->getConfiguration()->getProxyDir();
        $metadatas = $entityManager->getMetadataFactory()->getAllMetadata();
        $entityManager->getProxyFactory()->generateProxyClasses($metadatas, $destPath);

        $this->hasGeneratedProxiesAndClearedDoctrineCache = true;
    }
}
