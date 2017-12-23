<?php
/**
 * @author Erik Norgren <erik.norgren@interactivesolutions.se>
 * @copyright Interactive Solutions
 */

namespace InteractiveSolutions\ZfBehat\Context;

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Common\Persistence\ObjectRepository;
use InteractiveSolutions\ZfBehat\Options\UserOptions;
use Zend\Crypt\Password\Bcrypt;
use Zend\ServiceManager\ServiceManager;
use ZfrOAuth2\Server\Entity\TokenOwnerInterface;

class UserFixtureContext implements Context
{
    use EntityHydrationTrait;

    /**
     * @var ObjectManager
     */
    protected $objectManager;

    /**
     * @var ServiceManager
     */
    private $serviceManager;

    /**
     * @var UserOptions
     */
    private $options;

    /**
     * Get the database context
     *
     * @BeforeScenario
     *
     * @param BeforeScenarioScope $scope
     *
     * @return void
     */
    public function bootstrap(BeforeScenarioScope $scope)
    {
        $this->objectManager = $scope->getEnvironment()->getContext(DatabaseContext::class)->getEntityManager();
    }

    public function setContainer(ServiceManager $serviceManager)
    {
        $this->serviceManager = $serviceManager;
        $this->options        = $serviceManager->get(UserOptions::class);
    }

    /**
     * @return ObjectRepository
     */
    public function getRepository()
    {
        // get from config the repository
        return $this->objectManager->getRepository($this->options->getUserEntityClass());
    }

    /**
     * @param string $role
     * @param null $stepIdentifierValue
     * @return TokenOwnerInterface
     */
    public function generateDefaultUser($role = 'user', $stepIdentifierValue = null)
    {
        $userEntityClass = $this->options->getUserEntityClass();
        $userProperties  = $this->options->getDefaultUserProperties();
        $stepIdentifier  = $this->options->getUserStepIdentifier();

        // If we have a password, bcrypt it
        if (isset($userProperties['password'])) {
            $userProperties['password'] = (new Bcrypt(['cost' => 4]))->create($userProperties['password']);
        }

        $userProperties['roles'] = [$role];

        if ($stepIdentifierValue) {
            $userProperties[$stepIdentifier] = $stepIdentifierValue;
        }

        /* @var TokenOwnerInterface $user */
        $user = $this->getRepository()->findOneBy([$stepIdentifier => $userProperties[$stepIdentifier]]);

        if (!$user) {
            $user = new $userEntityClass();
        }

        $metadata = $this->objectManager->getClassMetadata($userEntityClass);
        $user     = $this->hydrateEntity($metadata, $user, $userProperties);

        return $user;
    }
}
