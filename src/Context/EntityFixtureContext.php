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
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\TransactionRequiredException;
use InteractiveSolutions\ZfBehat\Options\EntityOptions;
use RuntimeException;
use Zend\ServiceManager\ServiceManager;
use Zend\ServiceManager\ServiceManagerAwareInterface;

class EntityFixtureContext implements SnippetAcceptingContext, ServiceManagerAwareInterface
{
    use EntityHydratorTrait;

    /**
     * @var array
     */
    private $aliases;

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
     * Get an alias
     *
     * @param $alias
     * @return mixed
     */
    public function getEntityFromAlias(string $alias)
    {
        if (!array_key_exists($alias, $this->aliases)) {
            throw new RuntimeException('Alias not found');
        }

        return $this->aliases[$alias];
    }

    /**
     * Reset the alias list
     *
     * @BeforeScenario
     */
    public function resetAliases()
    {
        $this->aliases = [];
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
     * @throws RuntimeException
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
        if (!array_key_exists($type, $this->options->getEntities())) {
            foreach ($this->options->getEntities() as $entity) {
                if (array_key_exists('aliases', $entity) && in_array($type, $entity['aliases'], false)) {
                    return $entity;
                }
            }

            throw new RuntimeException(sprintf('No options set for "%s" entity type ', $type));
        }

        return $this->options->getEntities()[$type];
    }

    /**
     * Retrieves the primary key column of an entity
     *
     * @param $entity
     *
     * @return string
     * @throws \Doctrine\ORM\Mapping\MappingException
     */
    public function getPrimaryKeyColumnOfEntity($entity)
    {
        return $this->entityManager->getClassMetadata(get_class($entity))->getSingleIdentifierColumnName();
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
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws TransactionRequiredException
     *
     * @return mixed
     */
    public function anExisting($type)
    {
        return $this->anExistingWithValues($type, new TableNode([]));
    }


    /**
     * Add a entity of the given type with the default values created with the entities static method
     *
     * @Given an existing :type created with static method :staticMethodName
     *
     * @param string $type
     *
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws TransactionRequiredException
     *
     * @return mixed
     */
    public function anExistingTypeCreatedWithStaticMethod($type, $staticMethodName)
    {
        $entityClass = $this->getEntityClass($type);

        $values = $this->getDefaultEntityProperties($type);

        $entity = (new $entityClass())::$staticMethodName($values);

        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return $entity;
    }

    /**
     * Add a entity of the given type with the default values created with the entities static method
     *
     * @Given an existing :type created with static method :staticMethodName with alias :alias
     *
     * @param string $type
     *
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws TransactionRequiredException
     *
     * @return void
     */
    public function anExistingTypeCreatedWithStaticMethodAndAlias($type, $staticMethodName, $alias)
    {
        $entity = $this->anExistingTypeCreatedWithStaticMethod($type, $staticMethodName);

        $this->aliases[$alias] = $entity;
    }

    /**
     * Add a entity of the given type and merging the default values with the new ones
     *
     * @Given an existing :type with values:
     *
     * @param string    $type
     * @param TableNode $values
     *
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws TransactionRequiredException
     *
     * @return mixed
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

        return $entity;
    }

    /**
     * Add a entity of the given type with the default values with an alias
     *
     * @Given an existing :type as :alias
     *
     * @param string $type
     *
     * @param $alias
     * @throws \Doctrine\ORM\ORMException
     */
    public function anExistingAs($type, $alias)
    {
        $entity = $this->anExisting($type);

        $this->aliases[$alias] = $entity;
    }

    /**
     * Add a entity of the given type with values and an alias
     *
     * @Given an existing :type as :alias with values:
     *
     * @param string $type
     * @param $alias
     * @param TableNode $values
     * @throws \Doctrine\ORM\ORMException
     */
    public function anExistingAsWithValues($type, $alias, TableNode $values)
    {
        $entity = $this->anExistingWithValues($type, $values);

        $this->aliases[$alias] = $entity;
    }

    /**
     * Add an entity of a given type with default values into an aliased array
     *
     * @Given an existing :type in :alias
     *
     * @param $type
     * @param $alias
     * @throws \Doctrine\ORM\ORMException
     */
    public function anExistingIn($type, $alias)
    {
        $entity = $this->anExisting($type);

        if (!array_key_exists($alias, $this->aliases)) {
            $this->aliases[$alias] = [];
        }

        $this->aliases[] = $entity;
    }

    /**
     * Add an entity of a given type into an aliased array
     *
     * @Given an existing :type in :alias with values:
     *
     * @param $type
     * @param $alias
     * @param TableNode $values
     * @throws \Doctrine\ORM\ORMException
     */
    public function anExistingInWithValues($type, $alias, TableNode $values)
    {
        $entity = $this->anExistingWithValues($type, $values);

        if (!array_key_exists($alias, $this->aliases)) {
            $this->aliases[$alias] = [];
        }

        $this->aliases[] = $entity;
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
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws TransactionRequiredException
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
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws TransactionRequiredException
     *
     * @return void
     */
    public function anExistingWithParentAndValues($type, $parentType, $parentId, TableNode $values)
    {
        $options     = $this->getEntityOptions($type);
        $parentClass = $this->getEntityClass($parentType);
        $targetClass = $this->getEntityClass($type);

        $properties = $this->getDefaultEntityProperties($type);
        $metadata   = $this->entityManager->getClassMetadata($targetClass);

        $hydrator = isset($options['hydrator']) ? new $options['hydrator'] : null;
        $hydrator = $this->createEntityHydrator($metadata, $hydrator);

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
     * Add an entity with an association and default values to a parent entity with alias
     *
     * @Given an existing :type on alias :alias
     *
     * @param $type
     * @param $alias
     */
    public function anExistingOnAlias($type, $alias)
    {
        $this->anExistingOnAliasWithValues($type, $alias, new TableNode([]));
    }

    /**
     * Add an entity with a association to a parent entity with alias
     *
     * @Given an existing :type on alias :alias with values:
     *
     * @param string $type
     * @param $alias
     * @param TableNode $values
     */
    public function anExistingOnAliasWithValues($type, $alias, TableNode $values)
    {
        $options     = $this->getEntityOptions($type);
        $targetClass = $this->getEntityClass($type);

        $properties = $this->getDefaultEntityProperties($type);
        $metadata   = $this->entityManager->getClassMetadata($targetClass);

        $hydrator = isset($options['hydrator']) ? new $options['hydrator'] : null;
        $hydrator = $this->createEntityHydrator($metadata, $hydrator);

        foreach ($values->getRows() as list ($key, $value)) {
            $properties[$key] = $value;
        }

        $targetEntity = $hydrator->hydrate($properties, new $targetClass());
        $this->ensureEntityTimestamps($targetEntity);

        $parent = $this->getEntityFromAlias($alias);

        $parentMetadata = $this->entityManager->getClassMetadata(get_class($parent));
        $targetMetadata = $this->entityManager->getClassMetadata($targetClass);

        $this->setAssociation($targetEntity, $parent, $targetMetadata, $parentMetadata);

        $this->entityManager->persist($targetEntity);
        $this->entityManager->flush();
    }

    /**
     * Add the parent entity using the doctrine metadata on the target entity
     *
     * @param object            $targetEntity
     * @param object            $parentEntity
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
