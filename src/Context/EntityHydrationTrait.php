<?php
/**
 * @author Erik Norgren <erik.norgren@interactivesolutions.se>
 * @copyright Interactive Solutions
 */

namespace InteractiveSolutions\ZfBehat\Context;

use Closure;
use DateTime;
use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use Doctrine\DBAL\Types\Type;
use InvalidArgumentException;

trait EntityHydrationTrait
{
    /**
     * Hydrates an entity
     *
     * @param ClassMetadata $metadata
     * @param mixed $entity
     * @param $values
     *
     * @return mixed
     *
     * @throws InvalidArgumentException
     */
    private function hydrateEntity(ClassMetadata $metadata, $entity, $values)
    {
        foreach ($metadata->getFieldNames() as $field) {
            // Ignore whatever is not set in the properties array
            if (!array_key_exists($field, $values)) {
                continue;
            }

            switch ($metadata->getTypeOfField($field)) {
                case Type::DATETIME:
                    $this->setDatetimeFieldOfObject($entity, $field, $values[$field]);
                    break;

                case Type::SIMPLE_ARRAY:
                    $this->setSimpleArrayFieldOfObject($entity, $field, $values[$field]);
                    break;

                case Type::BOOLEAN:
                    $this->setBoolFieldOfObject($entity, $field, $values[$field]);
                    break;

                default:
                    $this->setFieldOfObject($entity, $field, $values[$field]);
            }
        }

        return $entity;
    }

    /**
     * Binds an anonymous function to an object, allowing us to access
     * instance variables directly
     *
     * @param $object
     * @param $field
     * @param $newValue
     *
     * @return void
     */
    private function setFieldOfObject($object, $field, $newValue)
    {
        $setValue = function ($object, $field, $newValue) {
            $object->{$field} = $newValue;
        };

        $setValue = Closure::bind($setValue, null, $object);

        $setValue($object, $field, $newValue);
    }

    /**
     * Sets the datetime field of an object
     *
     * @param $object
     * @param $field
     * @param $newValue
     */
    private function setDatetimeFieldOfObject($object, $field, $newValue)
    {
        $newValue = is_string($newValue) ? new DateTime($newValue) : $newValue;

        $this->setFieldOfObject($object, $field, $newValue);
    }

    /**
     * Sets the simple array field of an object
     *
     * @param $object
     * @param $field
     * @param $newValue
     */
    private function setSimpleArrayFieldOfObject($object, $field, $newValue)
    {
        $newValue = is_string($newValue) ? explode(',', $newValue) : $newValue;

        $this->setFieldOfObject($object, $field, $newValue);
    }

    /**
     * Set bool field of an object
     *
     * @param $object
     * @param $field
     * @param $newValue
     *
     * @throws InvalidArgumentException
     *
     * @return void
     */
    private function setBoolFieldOfObject($object, $field, $newValue)
    {
        if (is_bool($newValue) || is_numeric($newValue)) {
            return $this->setFieldOfObject($object, $field, (bool) $newValue);
        }

        // from now on, we assume it's a string
        switch ($newValue) {
            case '1':
            case 't':
            case 'true':
                return $this->setFieldOfObject($object, $field, true);

            case '0':
            case 'f':
            case 'false':
                return $this->setFieldOfObject($object, $field, false);

            default:
                throw new InvalidArgumentException('Failed to parse boolean value');
        }
    }

    /**
     * Ensure createdAt/updatedAt is not null after entity creation
     *
     * @param $entity
     */
    private function ensureEntityTimestamps($entity)
    {
        if (property_exists($entity, 'createdAt') && $entity->getCreatedAt() === null) {
            $this->setFieldOfObject($entity, 'createdAt', new DateTime());
        }

        if (property_exists($entity, 'updatedAt') && $entity->getCreatedAt() === null) {
            $this->setFieldOfObject($entity, 'updatedAt', new DateTime());
        }
    }
}
