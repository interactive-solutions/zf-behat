<?php
/**
 * @author    Antoine Hedgecock <antoine.hedgecock@gmail.com>
 *
 * @copyright Interactive Solutions AB
 */

namespace InteractiveSolutions\ZfBehat\Stdlib\HydratorStrategy;

use InvalidArgumentException;
use Zend\Hydrator\Strategy\DefaultStrategy;

/**
 * Class BooleanStrategy
 *
 * Converts boolean values in the behat features to PHP boolean
 *
 * @internal
 */
class BooleanStrategy extends DefaultStrategy
{
    /**
     * Converts the given value so that it can be extracted by the hydrator.
     *
     * @param mixed  $value  The original value.
     * @param object $object (optional) The original object for context.
     *
     * @return mixed Returns the value that should be extracted.
     */
    public function extract($value)
    {
        return $value;
    }

    /**
     * Converts the given value so that it can be hydrated by the hydrator.
     *
     * @param mixed $value The original value.
     * @param array $data  (optional) The original data for context.
     *
     * @return mixed Returns the value that should be hydrated.
     */
    public function hydrate($value)
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (bool) $value;
        }

        // from now on, we assume it's a string
        switch ($value)
        {
            case 'true':
                return true;

            case 'false':
                return false;

            default:
                throw new InvalidArgumentException('Failed to parse boolean value');
        }
    }
}
