<?php
/**
 * @author    Jonas Eriksson <jonas.eriksson@interactivesolutions.se>
 *
 * @copyright Interactive Solutions
 */
declare(strict_types = 1);

namespace InteractiveSolutions\ZfBehat\Context\Initializer;

use Behat\Behat\Context\Context;
use Behat\Behat\Context\Initializer\ContextInitializer;
use GuzzleHttp\Client;
use InteractiveSolutions\ZfBehat\Context\Aware\MailcatcherClientAwareInterface;

class MailcatcherClientInitializer implements ContextInitializer
{
    /**
     * @var Client
     */
    private $client;

    /**
     * @param string $url
     */
    public function __construct($url)
    {
        $this->client = new Client(['base_uri' => $url]);
    }

    /**
     * Initializes provided context.
     *
     * @param Context $context
     */
    public function initializeContext(Context $context)
    {
        if ($context instanceof MailcatcherClientAwareInterface) {
            $context->setClient($this->client);
        }
    }
}
