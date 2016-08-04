<?php
/**
 * @author    Jonas Eriksson <jonas.eriksson@interactivesolutions.se>
 *
 * @copyright Interactive Solutions
 */
declare(strict_types = 1);

namespace InteractiveSolutions\ZfBehat\Context;

use Behat\Behat\Context\SnippetAcceptingContext;
use Bernard\Envelope;
use Bernard\QueueFactory;
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
}
