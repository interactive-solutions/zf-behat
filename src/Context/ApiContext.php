<?php
/**
 * @author    Erik Norgren <erik.norgren@interactivesolutions.se>
 * @copyright Interactive Solutions
 */

namespace InteractiveSolutions\ZfBehat\Context;

use Behat\Behat\Context\SnippetAcceptingContext;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;
use Closure;
use Doctrine\ORM\EntityManager;
use DomainException;
use InteractiveSolutions\ZfBehat\Assertions;
use InteractiveSolutions\ZfBehat\Context\Aware\ApiClientAwareInterface;
use InteractiveSolutions\ZfBehat\Context\Aware\ApiClientAwareTrait;
use InteractiveSolutions\ZfBehat\Util\PluralisationUtil;
use PHPUnit_Framework_ExpectationFailedException;
use RuntimeException;
use Zend\ServiceManager\ServiceManager;
use Zend\ServiceManager\ServiceManagerAwareInterface;
use function GuzzleHttp\Psr7\parse_query;

class ApiContext implements SnippetAcceptingContext, ApiClientAwareInterface, ServiceManagerAwareInterface
{
    use ApiClientAwareTrait;

    /**
     * @var UserFixtureContext
     */
    private $userFixtureContext;

    /**
     * @var EntityFixtureContext
     */
    private $entityFixtureContext;

    /**
     * @var ServiceManager
     */
    private $serviceManager;

    /**
     * @var EntityManager
     */
    private $entityManager;

    /**
     * Inject the other contexts
     *
     * @BeforeScenario
     *
     * @param BeforeScenarioScope $scope
     *
     * @return void
     */
    public function bootstrap(BeforeScenarioScope $scope)
    {
        $this->userFixtureContext   = $scope->getEnvironment()->getContext(UserFixtureContext::class);
        $this->entityFixtureContext = $scope->getEnvironment()->getContext(EntityFixtureContext::class);
        $this->entityManager        = $scope->getEnvironment()->getContext(DatabaseContext::class)->getEntityManager();
    }

    /**
     * Set service manager
     *
     * @param ServiceManager $serviceManager
     */
    public function setServiceManager(ServiceManager $serviceManager)
    {
        $this->serviceManager = $serviceManager;
    }

    /**
     * This will check if the value should be considered a alias, and if so, convert the value to the specified alias field
     *
     * @param $value
     *
     * @throws RuntimeException
     * @return string
     */
    public function convertValueToAlias($value)
    {
        if (is_array($value)) {

            array_walk_recursive($value, function (&$value) {
                $value = $this->replaceValueWithAlias($value);
            });

            return $value;
        }

        return $this->replaceValueWithAlias($value);
    }

    /**
     * @param $value
     * @return string|mixed
     */
    private function replaceValueWithAlias($value)
    {
        if (!is_string($value)) {
            return $value;
        }

        $parts = explode('%', $value);
        $result = [];

        foreach ($parts as $index => $alias) {
            // Due to the way explode works, every second (odd) item in the array should be replaced
            if ($index % 2 === 1 && $index !== count($parts) - 1) {
                $field = null;

                if (strpos($alias, ':') !== false) {
                    list($alias, $field) = explode(':', $alias);
                }

                $entity = $this->entityFixtureContext->getEntityFromAlias($alias);

                if (!$field) {
                    $field = $this->entityManager->getClassMetadata(get_class($entity))->getSingleIdentifierColumnName();
                }

                $result[] = $this->getFieldOfObject($entity, $field);
            } else {
                $result[] = $alias;
            }
        }

        return implode($result);
    }

    /**
     * @When I send a :method to :url
     *
     * @param $method
     * @param $url
     *
     * @throws RuntimeException
     */
    public function iSendATo($method, $url)
    {
        $query = $this->parseQuery($url);
        $url   = explode('?', $url)[0];

        call_user_func($this->getApiMethodToCall($method), $this->convertValueToAlias($url), $query);
    }

    /**
     * @When I send a :method to :url with json:
     *
     * @param $method
     * @param $url
     * @param PyStringNode $string
     *
     * @throws RuntimeException
     */
    public function iSendAToWithJson($method, $url, PyStringNode $string)
    {
        // Make sure the developer provided valid json
        Assertions::assertJson($string->getRaw());

        $body = [];
        $data = json_decode($string, true);

        foreach ($data as $key => $value) {
            $body[$key] = $this->convertValueToAlias($value);
        }

        $query = $this->parseQuery($url);
        $url   = explode('?', $url)[0];

        call_user_func($this->getApiMethodToCall($method), $this->convertValueToAlias($url), $body, $query);
    }

    /**
     * @When I send a :method to :url with json values:
     *
     * @param $method
     * @param $url
     * @param TableNode $values
     *
     * @throws RuntimeException
     */
    public function iSendAToWithJsonValues($method, $url, TableNode $values)
    {
        $body = [];

        foreach ($values->getRows() as list ($key, $value)) {
            $body[$key] = $this->convertValueToAlias($value);
        }

        $query = $this->parseQuery($url);
        $url   = explode('?', $url)[0];

        call_user_func($this->getApiMethodToCall($method), $this->convertValueToAlias($url), $body, $query);
    }

    /**
     * @When I send a POST to :url with form-data values:
     *
     * @param $url
     * @param TableNode $values
     *
     * @throws RuntimeException
     */
    public function iSendAToWithFormDataValues($url, TableNode $values)
    {
        $body = [];

        foreach ($values->getRows() as list ($key, $value)) {
            $body[$key] = $this->convertValueToAlias($value);
        }

        $this->getClient()->postWithFormData($this->convertValueToAlias($url), $body);
    }

    /**
     * @param string $method
     * @return callable
     */
    private function getApiMethodToCall(string $method): callable
    {
        switch (strtoupper($method)) {
            case 'GET':
            case 'POST':
            case 'PUT':
            case 'DELETE':
            case 'PATCH':
                return [$this->getClient(), strtolower($method)];
            default:
                throw new RuntimeException('Unsuppored http verb provided');
        }
    }

    /**
     * Parse query from provided url
     *
     * @param string $url
     * @return array
     */
    private function parseQuery(string $url)
    {
        if (strpos($url, '?') === false) {
            return [];
        }

        $query = explode('?', $url)[1];

        return parse_query($query);
    }

    // todo: Basically everything from below here should be deprecated

    /**
     * @When I retrieve all :type
     */
    public function iRetrieveAll($type)
    {
        $this->getClient()->get($this->createUri($type));
    }

    /**
     * @When /^I retrieve all "([^"]*)" with "(.*)"$/
     *
     * @param string $type
     * @param string $queryString
     *
     * @return void
     */
    public function iRetrieveAllWith($type, $queryString)
    {
        $convertedQuery = $this->convertValueToAlias(parse_query($queryString, false));

        $this->getClient()->get($this->createUri($type), $convertedQuery);
    }

    /**
     * @When I retrieve all :type from :parentType with id :parentId
     *
     * @param string $type
     * @param string $parentType
     * @param string $parentId
     *
     * @return void
     */
    public function iRetrieveAllFrom($type, $parentType, $parentId)
    {
        $this->getClient()->get($this->createUri($parentType, $parentId, $type));
    }

    /**
     * @When I retrieve all :type from :parentType with id :parentId and query string :query
     *
     * @param string $type
     * @param string $parentType
     * @param string $parentId
     * @param string $query
     *
     * @return void
     */
    public function iRetrieveAllFromWithIdAndQueryString($type, $parentType, $parentId, $query)
    {
        $convertedQuery = $this->convertValueToAlias(parse_query($query, false));

        $this->getClient()->get($this->createUri($parentType, $parentId, $type), $convertedQuery);
    }

    /**
     * @When I retrieve all :type from :parentType with alias :alias
     *
     * @param $type
     * @param $parentType
     * @param $alias
     */
    public function iRetrieveAllFromWithAlias($type, $parentType, $alias)
    {
        $parent         = $this->entityFixtureContext->getEntityFromAlias($alias);
        $parentIdColumn = $this->entityFixtureContext->getPrimaryKeyColumnOfEntity($parent);

        $this->iRetrieveAllFrom($type, $parentType, $this->getFieldOfObject($parent, $parentIdColumn));
    }

    /**
     * @When I retrieve all :type from :parentType with alias :alias and query string :query
     *
     * @param $type
     * @param $parentType
     * @param $alias
     * @param $query
     */
    public function iRetrieveAllFromWithAliasAndQueryString($type, $parentType, $alias, $query)
    {
        $parent         = $this->entityFixtureContext->getEntityFromAlias($alias);
        $parentIdColumn = $this->entityFixtureContext->getPrimaryKeyColumnOfEntity($parent);

        $this->iRetrieveAllFromWithIdAndQueryString(
            $type,
            $parentType,
            $this->getFieldOfObject($parent, $parentIdColumn),
            $query
        );
    }

    /**
     * @When I retrieve :type with id :id
     *
     * @param string $type
     * @param string $id
     *
     * @return void
     */
    public function iRetrieveWithId($type, $id)
    {
        $this->getClient()->get($this->createUri($type, $id));
    }

    /**
     * @When I retrieve :type with id :id and the query string :query
     *
     * @param string $type
     * @param string $id
     * @param string $query
     *
     * @return void
     */
    public function iRetrieveWithIdAndTheQueryString($type, $id, $query)
    {
        $convertedQuery = $this->convertValueToAlias(parse_query($query, false));

        $this->getClient()->get($this->createUri($type, $id), $convertedQuery);
    }

    /**
     * @When I retrieve :type with alias :alias
     *
     * @param $type
     * @param $alias
     */
    public function iRetrieveWithAlias($type, $alias)
    {
        $parent         = $this->entityFixtureContext->getEntityFromAlias($alias);
        $parentIdColumn = $this->entityFixtureContext->getPrimaryKeyColumnOfEntity($parent);

        $this->iRetrieveWithId($type, $this->getFieldOfObject($parent, $parentIdColumn));
    }

    /**
     * @When I retrieve :type with alias :alias and the query string :query
     *
     * @param $type
     * @param $alias
     * @param $query
     */
    public function iRetrieveWithAliasAndTheQueryString($type, $alias, $query)
    {
        $parent         = $this->entityFixtureContext->getEntityFromAlias($alias);
        $parentIdColumn = $this->entityFixtureContext->getPrimaryKeyColumnOfEntity($parent);

        $this->iRetrieveWithIdAndTheQueryString($type, $this->getFieldOfObject($parent, $parentIdColumn), $query);
    }

    /**
     * @When I add a new :type with values:
     *
     * @param string $type
     * @param TableNode $values
     *
     * @return void
     */
    public function iAddANewWithValues($type, TableNode $values)
    {
        $uri  = $this->createUri($type);
        $body = $this->entityFixtureContext->getDefaultEntityProperties($type);

        foreach ($values->getRows() as list ($key, $values)) {
            $body[$key] = $this->convertValueToAlias($values);
        }

        $this->getClient()->post($uri, $body);
    }

    /**
     * @When I add a new :type
     *
     * @param string $type
     */
    public function iAddANew($type)
    {
        $this->iAddANewWithValues($type, new TableNode([]));
    }

    /**
     * @When I add a new :type to :parentType with id :id
     *
     * @param string $type
     * @param string $parentType
     * @param string $id
     *
     * @return void
     */
    public function iAddNewTo($type, $parentType, $id)
    {
        $this->iAddANewToAndTheValues($type, $parentType, $id, new TableNode([]));
    }

    /**
     * @When I add a new :type to :parentType with id :id and the values:
     *
     * @param string $type
     * @param string $parentType
     * @param string $id
     * @param TableNode $values
     */
    public function iAddANewToAndTheValues($type, $parentType, $id, TableNode $values)
    {
        $uri  = $this->createUri($parentType, $id, $type);
        $body = $this->entityFixtureContext->getDefaultEntityProperties($type);

        foreach ($values->getRows() as list ($key, $values)) {
            $body[$key] = $this->convertValueToAlias($values);
        }

        $this->getClient()->post($uri, $body);
    }

    /**
     * @When I add a new :type to :parentType with alias :alias
     *
     * @param $type
     * @param $parentType
     * @param $alias
     */
    public function iAddANewToAlias($type, $parentType, $alias)
    {
        $this->iAddANewToAliasWithValues($type, $parentType, $alias, new TableNode([]));
    }

    /**
     * @When I add a new :type to :parentType with alias :alias and values:
     *
     * @param $type
     * @param $parentType
     * @param $alias
     * @param TableNode $values
     */
    public function iAddANewToAliasWithValues($type, $parentType, $alias, TableNode $values)
    {
        $parent         = $this->entityFixtureContext->getEntityFromAlias($alias);
        $parentIdColumn = $this->entityFixtureContext->getPrimaryKeyColumnOfEntity($parent);

        $this->iAddANewToAndTheValues($type, $parentType, $this->getFieldOfObject($parent, $parentIdColumn), $values);
    }

    /**
     * @When I update a :type with id :typeId from relation :parentType with id :parentId with values:
     *
     * @param string $type
     * @param string $typeId
     * @param string $parentType
     * @param string $parentId
     * @param TableNode $values
     */
    public function iUpdateATypeWithIdFromRelationParentTypeWithId($type, $typeId, $parentType, $parentId, TableNode $values)
    {
        $uri  = $this->createUri($parentType, $parentId, $type, $typeId);
        $body = $this->entityFixtureContext->getDefaultEntityProperties($type);

        foreach ($values->getRows() as list ($key, $values)) {
            $body[$key] = $this->convertValueToAlias($values);
        }

        $this->getClient()->put($uri, $body);
    }

    /**
     * @When I update a :type with id :id and the values:
     *
     * @param string $type
     * @param string $id
     * @param TableNode $values
     */
    public function iUpdateAWithIdAndTheValues($type, $id, TableNode $values)
    {
        $uri  = $this->createUri($type, $id);
        $body = $this->entityFixtureContext->getDefaultEntityProperties($type);

        foreach ($values->getRows() as list ($key, $values)) {
            $body[$key] = $this->convertValueToAlias($values);
        }

        $this->getClient()->put($uri, $body);
    }

    /**
     * @When I update a :type with alias :alias and the values:
     *
     * @param $type
     * @param $alias
     * @param TableNode $values
     */
    public function iUpdateAWithAliasAndTheValues($type, $alias, TableNode $values)
    {
        $parent         = $this->entityFixtureContext->getEntityFromAlias($alias);
        $parentIdColumn = $this->entityFixtureContext->getPrimaryKeyColumnOfEntity($parent);

        $this->iUpdateAWithIdAndTheValues($type, $this->getFieldOfObject($parent, $parentIdColumn), $values);
    }

    /**
     * Update a resource from a json string
     *
     * @When /^I update a "([^"]*)" with id (\d+) with:$/
     *
     * @param string $type
     * @param string $id
     * @param PyStringNode $string
     *
     * @return void
     */
    public function iUpdateAWithIdWith($type, $id, PyStringNode $string)
    {
        // Make sure the developer provided valid json
        Assertions::assertJson($string->getRaw());

        $uri  = $this->createUri($type, $id);
        $body = $this->entityFixtureContext->getDefaultEntityProperties($type);
        $data = json_decode($string, true);

        foreach ($data as $key => $value) {
            $body[$key] = $this->convertValueToAlias($value);
        }

        $this->getClient()->put($uri, $body);
    }

    /**
     * @When I update a :type with alias :alias with:
     *
     * @param $type
     * @param $alias
     * @param PyStringNode $string
     */
    public function iUpdateAWithAliasWith($type, $alias, PyStringNode $string)
    {
        $parent         = $this->entityFixtureContext->getEntityFromAlias($alias);
        $parentIdColumn = $this->entityFixtureContext->getPrimaryKeyColumnOfEntity($parent);

        $this->iUpdateAWithIdWith($type, $this->getFieldOfObject($parent, $parentIdColumn), $string);
    }

    /**
     * Add a new resource from a json string
     *
     * @When /^I add a new "([^"]*)" with:$/
     *
     * @param string $type
     * @param PyStringNode $string
     *
     * @return void
     */
    public function iAddANewWith($type, PyStringNode $string)
    {
        // Make sure the developer provided valid json
        Assertions::assertJson($string->getRaw());

        $uri  = $this->createUri($type);
        $body = $this->entityFixtureContext->getDefaultEntityProperties($type);
        $data = json_decode($string, true);

        foreach ($data as $key => $value) {
            $body[$key] = $this->convertValueToAlias($value);
        }

        $this->getClient()->post($uri, $body);
    }

    /**
     * @When I partially update a :type with id :id and the values:
     */
    public function iPartiallyUpdateAWithIdAndTheValues($type, $id, TableNode $values)
    {
        $uri  = $this->createUri($type, $id);
        $body = [];

        foreach ($values->getRows() as list ($key, $values)) {
            $body[$key] = $this->convertValueToAlias($values);
        }

        $this->getClient()->patch($uri, $body);
    }

    /**
     * @When I partially update a :type with alias :alias and the values:
     *
     * @param $type
     * @param $alias
     * @param TableNode $values
     */
    public function iPartiallyUpdateAWithAliasAndTheValues($type, $alias, TableNode $values)
    {
        $parent         = $this->entityFixtureContext->getEntityFromAlias($alias);
        $parentIdColumn = $this->entityFixtureContext->getPrimaryKeyColumnOfEntity($parent);

        $this->iPartiallyUpdateAWithIdAndTheValues($type, $this->getFieldOfObject($parent, $parentIdColumn), $values);
    }

    /**
     * @When I delete a :type with id :id
     */
    public function iDeleteAWithId($type, $id)
    {
        $uri = $this->createUri($type, $id);

        $this->getClient()->delete($uri);
    }

    /**
     * @When I delete a :type with alias :alias
     *
     * @param $type
     * @param $alias
     */
    public function iDeleteAWithAlias($type, $alias)
    {
        $parent         = $this->entityFixtureContext->getEntityFromAlias($alias);
        $parentIdColumn = $this->entityFixtureContext->getPrimaryKeyColumnOfEntity($parent);

        $this->iDeleteAWithId($type, $this->getFieldOfObject($parent, $parentIdColumn));
    }

    /**
     * @When I remove a :type with id :typeId from relation :parentType with id :id
     *
     * @param string $type
     * @param $typeId
     * @param string $parentType
     * @param $parentId
     */
    public function iRemoveATypeWithIdFromRelationParentTypeWithId($type, $typeId, $parentType, $parentId)
    {
        $uri = $this->createUri($parentType, $parentId, $type, $typeId);

        $this->getClient()->delete($uri);
    }

    /**
     * @When I send a :action action with method :method
     *
     * @param string $action
     * @param string $method
     */
    public function iSendAActionWithMethod($action, $method)
    {
        $this->iSendAActionWithMethodAndValues($action, $method, new TableNode([]));
    }

    /**
     * @When I send a :action action with method :method and values:
     *
     * @param $action
     * @param $method
     * @param TableNode $values
     */
    public function iSendAActionWithMethodAndValues($action, $method, TableNode $values)
    {
        $body = [];

        foreach ($values->getRows() as list ($key, $values)) {
            $body[$key] = $this->convertValueToAlias($values);
        }

        $this->sendRpcAction(sprintf('/%s', $action), $method, $body);
    }

    /**
     * @When I send a :action action with method :method and body:
     *
     * @param $action
     * @param $method
     * @param PyStringNode $string
     */
    public function iSendAActionWithMethodAndBody($action, $method, PyStringNode $string)
    {
        // Make sure the developer provided valid json
        Assertions::assertJson($string->getRaw());

        $data = json_decode($string, true);

        foreach ($data as $key => $value) {
            $body[$key] = $this->convertValueToAlias($value);
        }

        $this->sendRpcAction(sprintf('/%s', $action), $method, $body);
    }

    /**
     * @When I send a :action action to resource :type with id :id and method :method
     *
     * @param string $action
     * @param string $type
     * @param int $id
     * @param string $method
     */
    public function iSendAActionToResourceWithIdAndMethod($action, $type, $id, $method)
    {
        $this->iSendAActionToResourceWithIdWithMethodAndValues($action, $type, $id, $method, new TableNode([]));
    }

    /**
     * @When I send a :action action to resource :type with id :id and method :method with values:
     *
     * @param string $action
     * @param string $type
     * @param int $id
     * @param string $method
     * @param TableNode $values
     */
    public function iSendAActionToResourceWithIdWithMethodAndValues($action, $type, $id, $method, TableNode $values)
    {
        $body = [];

        foreach ($values->getRows() as list ($key, $values)) {
            $body[$key] = $this->convertValueToAlias($values);
        }

        $uri = $this->createUri($type, $id);
        $uri .= '/' . $action;

        $this->sendRpcAction($uri, $method, $body);
    }

    /**
     * @When I send a :action action to resource :type with id :id and method :method with:
     *
     * @param string $action
     * @param string $type
     * @param int $id
     * @param string $method
     * @param PyStringNode $string
     */
    public function iSendAActionToResourceWithIdWithMethodWith($action, $type, $id, $method, PyStringNode $string)
    {
        // Make sure the developer provided valid json
        Assertions::assertJson($string->getRaw());

        $body = $this->entityFixtureContext->getDefaultEntityProperties($type);
        $data = json_decode($string, true);

        foreach ($data as $key => $value) {
            $body[$key] = $this->convertValueToAlias($value);
        }

        $uri = $this->createUri($type, $id);
        $uri .= '/' . $action;

        $this->sendRpcAction($uri, $method, $body);
    }

    /**
     * @When I send a :action action to resource :type with alias :alias and method :method
     *
     * @param $action
     * @param $type
     * @param $alias
     * @param $method
     */
    public function iSendAActionToResourceWithAliasAndMethod($action, $type, $alias, $method)
    {
        $parent         = $this->entityFixtureContext->getEntityFromAlias($alias);
        $parentIdColumn = $this->entityFixtureContext->getPrimaryKeyColumnOfEntity($parent);

        $this->iSendAActionToResourceWithIdAndMethod(
            $action,
            $type,
            $this->getFieldOfObject($parent, $parentIdColumn),
            $method
        );
    }

    /**
     * @When I send a :action action to resource :type with alias :alias and method :method with values:
     *
     * @param $action
     * @param $type
     * @param $alias
     * @param $method
     * @param TableNode $values
     */
    public function iSendAActionToResourceWithAliasWithMethodAndValues(
        $action,
        $type,
        $alias,
        $method,
        TableNode $values
    ) {
        $parent         = $this->entityFixtureContext->getEntityFromAlias($alias);
        $parentIdColumn = $this->entityFixtureContext->getPrimaryKeyColumnOfEntity($parent);

        $this->iSendAActionToResourceWithIdWithMethodAndValues(
            $action,
            $type,
            $this->getFieldOfObject($parent, $parentIdColumn),
            $method,
            $values
        );
    }

    /**
     * @When I send a :action action to resource :type with alias :alias and method :method with:
     *
     * @param $action
     * @param $type
     * @param $alias
     * @param $method
     * @param PyStringNode $string
     */
    public function iSendAActionToResourceWithAliasAndMethodWith($action, $type, $alias, $method, PyStringNode $string)
    {
        $parent         = $this->entityFixtureContext->getEntityFromAlias($alias);
        $parentIdColumn = $this->entityFixtureContext->getPrimaryKeyColumnOfEntity($parent);

        $this->iSendAActionToResourceWithIdWithMethodWith(
            $action,
            $type,
            $this->getFieldOfObject($parent, $parentIdColumn),
            $method,
            $string
        );
    }

    /**
     * @param string $uri
     * @param string $method
     * @param array $body
     */
    private function sendRpcAction($uri, $method, array $body = [])
    {
        // The method name is lower case letters
        $method = strtolower($method);

        $httpVerbs = [
            'get',
            'post',
            'put',
            'patch',
            'delete',
        ];

        // Check if the provided method actually is an HTTP verb
        if (!in_array($method, $httpVerbs)) {
            throw new \InvalidArgumentException('The provided method is not a valid HTTP verb');
        }

        empty($body) ? $this->getClient()->{$method}($uri) : $this->getClient()->{$method}($uri, $body);
    }

    /**
     * Generate the uri to a api resource or collection
     *
     * @param string $type
     * @param string|null $typeId
     * @param string|null $subType
     * @param string|null $subTypeId
     *
     * @return string
     */
    private function createUri($type, $typeId = null, $subType = null, $subTypeId = null)
    {
        $uri       = '/';
        $route     = $this->entityFixtureContext->getEntityRoute($type);
        $typeId    = $this->replaceValueWithAlias($typeId);
        $subTypeId = $this->replaceValueWithAlias($subTypeId);

        if (!$route) {
            $route = PluralisationUtil::pluralize($type);
        }

        $uri = $uri . $route;

        if ($typeId) {
            $uri = $uri . '/' . $typeId;
        }

        if ($typeId && $subType) {
            $route = $this->entityFixtureContext->getEntityRoute($subType);

            if (!$route) {
                $route = PluralisationUtil::pluralize($subType);
            }

            $uri = $uri . '/' . $route;
        }

        if ($subTypeId) {
            $uri = $uri . '/' . $subTypeId;
        }

        return $uri;
    }

    /**
     * @Then I should receive an unauthorized error
     */
    public function iShouldReceiveAnUnauthorizedError()
    {
        try {
            Assertions::assertEquals(401, $this->getClient()->lastResponse->getStatusCode());

        } catch (PHPUnit_Framework_ExpectationFailedException $e) {

            // Allows for easier debugging
            echo $this->getClient()->lastResponse->getStatusCode() . PHP_EOL;
            echo $this->getClient()->lastResponseBody;

            throw $e;
        }
    }

    /**
     * @Then I should receive an error with statusCode :statusCode
     */
    public function iShouldReceiveAnErrorMessage($statusCode, $message = null)
    {
        try {
            Assertions::assertEquals($statusCode, $this->getClient()->lastResponse->getStatusCode());

            if ($message) {
                Assertions::assertEquals($message, json_decode($this->getClient()->lastResponseBody, true)['message']);
            }

        } catch (PHPUnit_Framework_ExpectationFailedException $e) {

            // Allows for easier debugging
            echo $this->getClient()->lastResponse->getStatusCode() . PHP_EOL;
            echo $this->getClient()->lastResponseBody;

            throw $e;
        }
    }

    /**
     * @Then I should receive an error with statusCode :statusCode and message :message
     */
    public function iShouldReceiveAnErrorWithStatusCodeAndMessage($statusCode, $message)
    {
        return $this->iShouldReceiveAnErrorMessage($statusCode, $message);
    }

    /**
     * @Then I should receive a validation error
     */
    public function iShouldReceiveAValidationError()
    {
        try {
            Assertions::assertEquals(422, $this->getClient()->lastResponse->getStatusCode());

        } catch (PHPUnit_Framework_ExpectationFailedException $e) {

            // Allows for easier debugging
            echo $this->getClient()->lastResponse->getStatusCode() . PHP_EOL;
            echo $this->getClient()->lastResponseBody;

            throw $e;
        }
    }

    /**
     * @Then /^I should receive a validation error with count (\d+)$/
     */
    public function iShouldReceiveAValidationErrorWithCount($count)
    {
        $responseBody = $this->getClient()->lastResponseBody;

        try {
            Assertions::assertJson($responseBody);
            Assertions::assertEquals(422, $this->getClient()->lastResponse->getStatusCode());

            Assertions::assertCount((int) $count, json_decode($responseBody, true)['errors']);

        } catch (PHPUnit_Framework_ExpectationFailedException $e) {

            // Allows for easier debugging
            echo $this->getClient()->lastResponse->getStatusCode() . PHP_EOL;
            echo $responseBody;

            throw $e;
        }
    }

    /**
     * @Then /^I should receive a (\d+)$/
     *
     * @param int $statusCode
     *
     * @return void
     */
    public function iShouldReceiveA($statusCode)
    {
        try {
            Assertions::assertEquals($statusCode, $this->getClient()->lastResponse->getStatusCode());
        } catch (PHPUnit_Framework_ExpectationFailedException $e) {

            // Allows for easier debugging
            echo $this->getClient()->lastResponse->getStatusCode() . PHP_EOL;
            echo $this->getClient()->lastResponseBody;

            throw $e;
        }
    }

    /**
     * @Then /^I should receive a (\d+) with valid json$/
     *
     * @param int $statusCode
     *
     * @return void
     */
    public function iShouldReceiveAWithValidJson($statusCode)
    {
        $responseBody = $this->getClient()->lastResponseBody;

        try {

            Assertions::assertEquals($statusCode, $this->getClient()->lastResponse->getStatusCode());
            Assertions::assertJson($responseBody);

        } catch (PHPUnit_Framework_ExpectationFailedException $e) {

            // Allows for easier debugging
            echo $this->getClient()->lastResponse->getStatusCode() . PHP_EOL;
            echo $responseBody;

            throw $e;
        }

    }


    /**
     * @Given /^the response should match the request properties$/
     */
    public function theResponseShouldMatchTheRequestProperties()
    {
        if ($this->getClient()->lastRequestBody === null) {
            throw new DomainException('No request body is available');
        }

        $responseBody = $this->getClient()->lastResponseBody;

        try {
            Assertions::assertJson($responseBody);
        } catch (PHPUnit_Framework_ExpectationFailedException $e) {
            echo $responseBody;
        }

        $json = json_decode($responseBody, true);

        try {

            foreach ($this->getClient()->lastRequestBody as $key => $value) {

                // Updating the entity should ALWAYS change the updatedAt timestamp
                if (in_array($key, ['updatedAt', 'password', 'repeatPassword'])) continue;

                Assertions::assertArrayHasKey($key, $json);

                if (is_bool($json[$key])) {
                    Assertions::assertEquals((bool) $value, $json[$key]);
                } else {
                    Assertions::assertEquals($value, $json[$key]);
                }
            }

        } catch (PHPUnit_Framework_ExpectationFailedException $e) {

            // Easier debugging
            print_r($json);

            throw $e;
        }
    }

    /**
     * @Given /^it should contain (\d+) records$/
     *
     * @param int $records
     *
     * @return void
     */
    public function itShouldContainRecords($records)
    {
        $responseBody = $this->getClient()->lastResponseBody;

        try {

            Assertions::assertJson($responseBody);

            $json = json_decode($responseBody, true);

            Assertions::assertArrayHasKey('data', $json);
            Assertions::assertCount((int) $records, $json['data']);

        } catch (PHPUnit_Framework_ExpectationFailedException $e) {

            // Allows for easier debugging
            echo $this->getClient()->lastResponse->getStatusCode() . PHP_EOL;
            echo $responseBody;

            throw $e;
        }
    }

    /**
     * @Then /^it should match the following properties:$/
     */
    public function itShouldMatchTheFollowingProperties(TableNode $table)
    {
        $jsonString = $this->getClient()->lastResponseBody;

        Assertions::assertJson($jsonString);

        $json = json_decode($jsonString, true);

        foreach ($table->getRows() as list ($key, $value)) {
            // So we can detect checks for null values
            $value = $value === 'null' ? null : $value;

            Assertions::assertArrayHasKey($key, $json);

            if (is_bool($json[$key])) {
                Assertions::assertEquals((bool) $value, $json[$key]);
            } else {
                Assertions::assertEquals($value, $json[$key]);
            }
        }
    }

    /**
     * @Then It should dump the last response
     */
    public function itShouldDumpTheLastResponse()
    {
        $responseBody = $this->getClient()->lastResponseBody;

        var_dump($responseBody);
    }

    /**
     * :direction can be "not be" or "be"
     *
     * @Then /^the "([^"]*)" field "([^"]*)" should ([^"]*) "([^"]*)"$/
     *
     * @param $typeOfValue
     * @param $field
     * @param $direction
     * @param $shouldBe
     */
    public function theBooleanFieldFieldShouldBeValue($typeOfValue, $field, $direction, $shouldBe)
    {
        $direction = ($direction === 'be') ? 'assertEquals' : 'assertNotEquals';

        $body = json_decode($this->getClient()->lastResponseBody, true);

        $fields = explode('.', $field);

        foreach ($fields as $field) {
            $body = $body[$field];
        }

        $shouldBe = $this->convertValueToAlias($shouldBe);

        switch ($typeOfValue) {
            case 'boolean':
            case 'bool':
                $shouldBe = (strtolower($shouldBe) === 'false') ? false : $shouldBe;
                $shouldBe = (boolean) $shouldBe;
                break;
            case 'string':
                $shouldBe = (string) $shouldBe;
                break;
            case 'int':
            case 'integer':
                $shouldBe = (int) $shouldBe;
                break;
            case 'nullable':
                $shouldBe = null;
                break;
            default:
                $shouldBe = (string) $shouldBe;
        }

        Assertions::$direction($shouldBe, $body);
    }

    /**
     * Binds an anonymous function to an object, allowing us to access
     * instance variables directly
     *
     * @param $object
     * @param $field
     * @return mixed
     */
    private function getFieldOfObject($object, $field)
    {
        $getValue = function ($object, $field) {
            return $object->{$field};
        };

        $getValue = Closure::bind($getValue, null, $object);

        return $getValue($object, $field);
    }
}
