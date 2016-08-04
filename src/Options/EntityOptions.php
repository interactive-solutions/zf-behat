<?php
/**
 * @author Erik Norgren <erik.norgren@interactivesolutions.se>
 * @copyright Interactive Solutions
 */

namespace InteractiveSolutions\ZfBehat\Options;

use Zend\Stdlib\AbstractOptions;

class EntityOptions extends AbstractOptions
{
    /**
     * @var array
     */
    protected $entities;

    /**
     * @return array
     */
    public function getEntities()
    {
        return $this->entities;
    }

    /**
     * @param array $entities
     */
    public function setEntities($entities)
    {
        $this->entities = $entities;
    }
}
