<?php
/**
 * @author    Antoine Hedgecock <antoine.hedgecock@gmail.com>
 *
 * @copyright Interactive Solutions AB
 */

namespace InteractiveSolutions\ZfBehat\Stdlib\HydratorStrategy;

use Zend\Hydrator\Strategy\DefaultStrategy;

/**
 * Class SimpleArrayStrategy
 *
 * This should not be used by application code, it's used internally by the entity fixture context to hydrate values
 * from the behat feature files.
 *
 * todo: in php7 this could be replaced by an anonymous class
 *
 * @internal
 */
class SimpleArrayStrategy extends DefaultStrategy
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
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        return $value;
    }
}
