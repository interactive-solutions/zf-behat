<?php
/**
 * @author Erik Norgren <erik.norgren@interactivesolutions.se>
 * @copyright Interactive Solutions
 */

namespace InteractiveSolutions\ZfBehat\Context;

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use DateTime;
use Doctrine\ORM\EntityManager;
use InteractiveSolutions\ZfBehat\Assertions;
use InteractiveSolutions\ZfBehat\Context\Aware\ApiClientAwareInterface;
use InteractiveSolutions\ZfBehat\Context\Aware\ApiClientAwareTrait;
use ZfrOAuth2\Server\Model\AccessToken;
use ZfrOAuth2\Server\Model\TokenOwnerInterface;

class ZfrOAuthContext implements Context, ApiClientAwareInterface
{
    use ApiClientAwareTrait;

    const TEST_ACCESS_TOKEN = 'ZfBehat-Test-Token';

    /**
     * @var EntityManager
     */
    private $entityManager;

    /**
     * @var UserFixtureContext
     */
    private $userFixtureContext;

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
        $this->entityManager      = $scope->getEnvironment()->getContext(DatabaseContext::class)->getEntityManager();
        $this->userFixtureContext = $scope->getEnvironment()->getContext(UserFixtureContext::class);
    }

    public function generateAccessToken(TokenOwnerInterface $user)
    {
        $token = AccessToken::createNewAccessToken(10000, $user);

        $this->entityManager->persist($token);
        $this->entityManager->flush();

        return $token->getToken();
    }

    /**
     * Reset Authorization header and set a token
     *
     * @param $token
     */
    protected function setAuthorizationBearerHeader($token)
    {
        $this->getClient()->removeHeader('Authorization');
        $this->getClient()->setHeader('Authorization', 'Bearer ' . $token);
    }

    /**
     * @Given I have a valid :role access token
     *
     * @param $role
     * @param null $email
     */
    public function iHaveAValidAccessToken($role, $email = null)
    {
        $user = $this->userFixtureContext->generateDefaultUser($role, $email);

        if (! $this->entityManager->contains($user)) {
            $this->entityManager->persist($user);
        }

        $this->entityManager->flush();

        $token = $this->generateAccessToken($user);

        $this->setAuthorizationBearerHeader($token);
    }

    /**
     * @Given I have a valid :role with email :email
     *
     * @param $role
     * @param $email
     */
    public function IHaveAUserWithEmailAndRole($role, $email)
    {
        $this->iHaveAValidAccessToken($role, $email);
    }

    /**
     * @Given I am unauthorized
     */
    public function IamUnauthorized()
    {
        $this->getClient()->removeHeader('Authorization');
    }

    /**
     * @When I am authorized with email :email
     *
     * @param $email
     */
    public function IAmAuthorizedWithEmail($email)
    {
        /** @var TokenOwnerInterface $user */
        $user = $this->userFixtureContext->getRepository()->findOneBy(['email' => $email]);

        if (! $user) {
            throw new \RuntimeException(sprintf('No user with email: %s was found', $email));
        }

        $token = $this->generateAccessToken($user);

        $this->setAuthorizationBearerHeader($token);
    }

    /**
     * @When I am authorized with username :email
     *
     * @param $email
     */
    public function IAmAuthorizedWithUsername(string $username)
    {
        /** @var TokenOwnerInterface $user */
        $user = $this->userFixtureContext->getRepository()->findOneBy(['username' => $username]);
        if (! $user) {
            throw new \RuntimeException(sprintf('No user with username: %s was found', $username));
        }

        $token = $this->generateAccessToken($user);

        $this->setAuthorizationBearerHeader($token);
    }

    /**
     * @When I login using password grant with :username :password
     */
    public function iLoginUsingPasswordGrantWith($username, $password)
    {
        // We need to reset the headers to be able to login correctly
        $this->IamUnauthorized();

        $this->getClient()->postWithFormData('/oauth/token', [
           'grant_type' => 'password',
           'username'   => $username,
           'password'   => $password
        ]);

        $responseBody = $this->getClient()->lastResponseBody;

        Assertions::assertJson($responseBody);
        $response = json_decode($responseBody, true);

        Assertions::assertArrayHasKey('access_token', $response);

        $this->setAuthorizationBearerHeader($response['access_token']);
    }
}
