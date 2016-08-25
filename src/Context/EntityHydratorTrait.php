<?php
/**
 * @author Erik Norgren <erik.norgren@interactivesolutions.se>
 * @copyright Interactive Solutions
 */

namespace InteractiveSolutions\ZfBehat\Context;

use DateTime;
use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use Doctrine\DBAL\Types\Type;
use InteractiveSolutions\Stdlib\Hydrator\Strategy\DateTimeStrategy;
use InteractiveSolutions\ZfBehat\Stdlib\HydratorStrategy\BooleanStrategy;
use InteractiveSolutions\ZfBehat\Stdlib\HydratorStrategy\SimpleArrayStrategy;
use Zend\Hydrator\ClassMethods;
use Zend\Hydrator\HydratorInterface;

trait EntityHydratorTrait
{
    /**
     * Get the default hydrator
     *
     * @param ClassMetadata     $metadata
     * @param HydratorInterface $hydrator
     *
     * @return HydratorInterface
     */
    private function createEntityHydrator(ClassMetadata $metadata, HydratorInterface $hydrator = null)
    {
        $hydrator = $hydrator ?: new ClassMethods();

        foreach ($metadata->getFieldNames() as $field) {

            switch ($metadata->getTypeOfField($field)) {
                case Type::DATETIME:
                    $hydrator->addStrategy($field, new DateTimeStrategy());
                    break;

                case Type::SIMPLE_ARRAY:
                    $hydrator->addStrategy($field, new SimpleArrayStrategy());
                    break;

                case Type::BOOLEAN:
                    $hydrator->addStrategy($field, new BooleanStrategy());
                    break;
            }
        }

        return $hydrator;
    }

    private function ensureEntityTimestamps($entity)
    {
        if (method_exists($entity, 'setCreatedAt') && $entity->getCreatedAt() === null) {
            $entity->setCreatedAt(new DateTime());
        }

        if (method_exists($entity, 'setUpdatedAt') && $entity->getUpdatedAt() === null) {
            $entity->setUpdatedAt(new DateTime());
        }
    }
}
