<?php
/**
 * @author    Erik Norgren <erik.norgren@interactivesolutions.se>
 * @copyright Interactive Solutions
 */

namespace InteractiveSolutions\ZfBehat\Context;

use Behat\Behat\Context\SnippetAcceptingContext;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\TableNode;
use DateTime;
use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use InteractiveSolutions\ZfBehat\Options\EntityOptions;
use RuntimeException;
use Zend\ServiceManager\ServiceManager;
use Zend\ServiceManager\ServiceManagerAwareInterface;

class EntityFixtureContext implements SnippetAcceptingContext, ServiceManagerAwareInterface
{
    use EntityHydratorTrait;

    /**
     * @var EntityManager
     */
    private $entityManager;

    /**
     * @var ServiceManager
     */
    private $serviceManager;

    /**
     * @var EntityOptions
     */
    private $options;

    /**
     * Inject the entity manager
     *
     * @BeforeScenario
     *
     * @param BeforeScenarioScope $scope
     *
     * @return void
     */
    public function bootstrap(BeforeScenarioScope $scope)
    {
        $this->entityManager = $scope->getEnvironment()->getContext(DatabaseContext::class)->getEntityManager();
    }

    /**
     * Set service manager
     *
     * @param ServiceManager $serviceManager
     */
    public function setServiceManager(ServiceManager $serviceManager)
    {
        $this->serviceManager = $serviceManager;
        $this->options        = $serviceManager->get(EntityOptions::class);
    }

    /**
     * Retrieve the repository for the given type
     *
     * @param string $type
     *
     * @return \Doctrine\ORM\EntityRepository
     */
    public function getRepository($type)
    {
        return $this->entityManager->getRepository($this->getEntityClass($type));
    }

    /**
     * Retrieve the default entity properties
     *
     * Is used by merging new properties with the defaults ones, this stops us from rewriting a shit load of properties
     * on each time we update or modify a performer.
     *
     * @param $type
     *
     * @return array
     */
    public function getDefaultEntityProperties($type)
    {
        $entityOptions = $this->getEntityOptions($type);

        if (!$entityOptions) {
            throw new RuntimeException('No options set for this entity type');
        }

        $defaultProperties = $entityOptions['defaultProperties'];

        if (!$defaultProperties) {
            throw new RuntimeException('No default properties set for ' . $type);
        }

        return $defaultProperties;
    }

    /**
     * Retrieve the configuration for the given entity
     *
     * @param string $type
     *
     * @return array
     */
    public function getEntityOptions($type)
    {
        $hasKey = array_key_exists($type, $this->options->getEntities());

        if (!$hasKey) {
            foreach ($this->options->getEntities() as $entity) {
                if (in_array($type, $entity['aliases'])) {
                    return $entity;
                }
            }

            throw new RuntimeException(sprintf('No options set for "%s" entity type ', $type));
        }

        return $this->options->getEntities()[$type];
    }

    /**
     * Retrieve the entity class for the given type
     *
     * @param string $type
     *
     * @return string
     */
    public function getEntityClass($type)
    {
        $entityOptions = $this->getEntityOptions($type);
        $entityClass   = $entityOptions['entity'];

        if (!$entityClass) {
            throw new RuntimeException('No entity class set for this type');
        }

        return $entityClass;
    }

    /**
     * Get the route
     *
     * @param string $type
     *
     * @return string
     */
    public function getEntityRoute($type)
    {
        return $this->getEntityOptions($type)['route'];
    }

    /**
     * Add a entity of the given type with the default values
     *
     * @Given an existing :type
     *
     * @param string $type
     *
     * @return void
     */
    public function anExisting($type)
    {
        $this->anExistingWithValues($type, new TableNode([]));
    }

    /**
     * Add a entity of the given type and merging the default values with the new ones
     *
     * @Given an existing :type with values:
     *
     * @param string    $type
     * @param TableNode $values
     *
     * @return void
     */
    public function anExistingWithValues($type, TableNode $values)
    {
        $entityClass = $this->getEntityClass($type);

        $properties = $this->getDefaultEntityProperties($type);
        $metadata   = $this->entityManager->getClassMetadata($entityClass);
        $hydrator   = $this->createEntityHydrator($metadata);

        foreach ($values->getRows() as list ($key, $value)) {
            $properties[$key] = $value;
        }

        $entity = $hydrator->hydrate($properties, new $entityClass());

        $this->ensureEntityTimestamps($entity);

        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    /**
     * Add an entity with a association to a parent entity
     *
     * @Given an existing :type on :parentType with id :parentId
     *
     * @param string $type
     * @param string $parentType
     * @param string $parentId
     *
     * @return void
     */
    public function anExistingWithParent($type, $parentType, $parentId)
    {
        $this->anExistingWithParentAndValues($type, $parentType, $parentId, new TableNode([]));
    }

    /**
     * Add an entity with a association to a parent entity with updated values
     *
     * @Given an existing :type on :parentType with id :parentId and the values:
     *
     * @param string    $type
     * @param string    $parentType
     * @param string    $parentId
     * @param TableNode $values
     *
     * @return void
     */
    public function anExistingWithParentAndValues($type, $parentType, $parentId, TableNode $values)
    {
        $parentClass = $this->getEntityClass($parentType);
        $targetClass = $this->getEntityClass($type);

        $properties = $this->getDefaultEntityProperties($type);
        $metadata   = $this->entityManager->getClassMetadata($targetClass);
        $hydrator   = $this->createEntityHydrator($metadata);

        foreach ($values->getRows() as list ($key, $value)) {
            $properties[$key] = $value;
        }

        $targetEntity = $hydrator->hydrate($properties, new $targetClass());
        $parentEntity = $this->entityManager->find($parentClass, $parentId);

        $this->ensureEntityTimestamps($targetEntity);

        $parentMetadata = $this->entityManager->getClassMetadata($parentClass);
        $targetMetadata = $this->entityManager->getClassMetadata($targetClass);

        $this->setAssociation($targetEntity, $parentEntity, $targetMetadata, $parentMetadata);

        $this->entityManager->persist($targetEntity);
        $this->entityManager->flush();
    }

    /**
     * Add the parent entity using the doctrine metadata on the target entity
     *
     * @param object $targetEntity
     * @param object $parentEntity
     * @param ClassMetadataInfo $targetMetadata
     * @param ClassMetadataInfo $parentMetadata
     *
     * @return void
     */
    private function setAssociation(
        $targetEntity,
        $parentEntity,
        ClassMetadataInfo $targetMetadata,
        ClassMetadataInfo $parentMetadata
    ) {
        $associations = $targetMetadata->getAssociationsByTargetClass($parentMetadata->name);

        foreach ($associations as $association) {

            // We can only update association where the targetEntity is the owning side, else doctrine will just
            // discard it.
            if ($association['isOwningSide']) {
                $reflection = $targetMetadata->getReflectionProperty($association['fieldName']);
                $reflection->setAccessible(true);
                $reflection->setValue($targetEntity, $parentEntity);
            }
        }

        if (count($parentMetadata->parentClasses) > 0) {
            $parentMetadata = $this->entityManager->getClassMetadata(current($parentMetadata->parentClasses));

            $this->setAssociation($targetEntity, $parentEntity, $targetMetadata, $parentMetadata);
        }
    }
}
