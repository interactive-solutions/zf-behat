<?php
/**
 * @author    Jonas Eriksson <jonas.eriksson@interactivesolutions.se>
 *
 * @copyright Interactive Solutions
 */
declare(strict_types = 1);

namespace InteractiveSolutions\ZfBehat\Context;

use Behat\Behat\Context\SnippetAcceptingContext;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Bernard\Envelope;
use Bernard\QueueFactory;
use Closure;
use InteractiveSolutions\Bernard\BernardOptions;
use InteractiveSolutions\ZfBehat\Assertions;
use Zend\ServiceManager\ServiceManager;
use Zend\ServiceManager\ServiceManagerAwareInterface;

class BernardContext implements SnippetAcceptingContext, ServiceManagerAwareInterface
{
    /**
     * @var ServiceManager
     */
    private $serviceManager;

    /**
     * @var ApiContext
     */
    private $apiContext;

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
        $this->apiContext = $scope->getEnvironment()->getContext(ApiContext::class);
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
     * @return QueueFactory
     */
    private function getQueueFactory()
    {
        /* @var $options BernardOptions */
        $options = $this->serviceManager->get(BernardOptions::class);

        /* @var QueueFactory $queueFactory */
        $queueFactory = $this->serviceManager->get($options->getQueueInstanceKey());

        return $queueFactory;
    }

    /**
     * @param $queueName
     *
     * @return \Bernard\Queue
     */
    private function createQueue($queueName)
    {
        return $this->getQueueFactory()->create($queueName);
    }

    /**
     * @param $queueName
     */
    private function clearQueue($queueName)
    {
        return $this->getQueueFactory()->remove($queueName);
    }

    /**
     * @Given all bernard queues are cleared
     */
    public function allBernardQueuesAreCleared()
    {
        foreach ($this->getQueueFactory()->all() as $queue) {
            $this->clearQueue((string) $queue);
        }
    }

    /**
     * @Then the queue :queueName should contain task :taskName
     *
     * @param $queueName
     * @param $taskName
     * @return bool
     */
    public function thenQueueShouldContainTask($queueName, $taskName)
    {
        $queue = $this->createQueue($queueName);

        /** @var Envelope $envelope */
        foreach ($queue->peek() as $envelope) {
            if ($taskName === $envelope->getName()) {
                Assertions::assertEquals($taskName, $envelope->getName());
                return true;
            }
        }

        Assertions::fail(sprintf('No task with name %s was found in the queue: %s', $taskName, $queueName));

        return false;
    }

    /**
     * @Then the task in queue :queueName with index :index should have the field :field with value :value
     *
     * @param $queueName
     * @param $index
     * @param $fields
     * @param $value
     * @return void
     * @throws \RuntimeException
     * @throws \PHPUnit_Framework_AssertionFailedError
     */
    public function theTaskInQueueWithIndexShouldHaveTheFieldWithValue($queueName, $index, $fields, $value)
    {
        $queue = $this->createQueue($queueName);

        /** @var Envelope $envelope */
        foreach ($queue->peek() as $idx => $envelope) {
            if ($idx === (int) $index) {

                $message = $envelope->getMessage();
                $fields  = explode('.', $fields);

                $actualValue   = $this->getFieldOfObject($message, reset($fields));
                $expectedValue = $this->apiContext->convertValueToAlias($value);

                // Remove the first key, because we don't need it again
                array_shift($fields);

                // Move downwards the array if multiple fields exists
                foreach ($fields as $field) {
                    Assertions::assertArrayHasKey($field, $actualValue);
                    $actualValue = $actualValue[$field];
                }

                Assertions::assertEquals($expectedValue, $actualValue);

                return;
            }
        }

        Assertions::fail(sprintf('Could not find task with index: %s in queue: %s', $index, $queueName));
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
