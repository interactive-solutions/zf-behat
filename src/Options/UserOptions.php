<?php
/**
 * @author Erik Norgren <erik.norgren@interactivesolutions.se>
 * @copyright Interactive Solutions
 */

namespace InteractiveSolutions\ZfBehat\Options;

use Zend\Stdlib\AbstractOptions;

class UserOptions extends AbstractOptions
{
    /**
     * @var string
     */
    protected $userEntityClass;

    /**
     * @var string
     */
    protected $userStepIdentifier = 'email';

    /**
     * @var array
     */
    protected $defaultUserProperties;

    /**
     * @return string
     */
    public function getUserEntityClass()
    {
        return $this->userEntityClass;
    }

    /**
     * @param string $userEntityClass
     */
    public function setUserEntityClass($userEntityClass)
    {
        $this->userEntityClass = $userEntityClass;
    }

    /**
     * @return array
     */
    public function getDefaultUserProperties()
    {
        return $this->defaultUserProperties;
    }

    /**
     * @param array $defaultUserProperties
     */
    public function setDefaultUserProperties(array $defaultUserProperties)
    {
        $this->defaultUserProperties = $defaultUserProperties;
    }

    /**
     * @return string
     */
    public function getUserStepIdentifier()
    {
        return $this->userStepIdentifier;
    }

    /**
     * @param string $userStepIdentifier
     */
    public function setUserStepIdentifier($userStepIdentifier)
    {
        $this->userStepIdentifier = $userStepIdentifier;
    }
}
