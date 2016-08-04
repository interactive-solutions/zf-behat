<?php
/**
 * @author    Antoine Hedgecock <antoine.hedgecock@gmail.com>
 *
 * @copyright Interactive Solutions AB
 */

namespace InteractiveSolutions\ZfBehat\Context\Initializer;

use Behat\Behat\Context\Context;
use Behat\Behat\Context\Initializer\ContextInitializer;
use GuzzleHttp\Client;
use InteractiveSolutions\ZfBehat\ApiClient;
use InteractiveSolutions\ZfBehat\Context\Aware\ApiClientAwareInterface;

class ApiClientInitializer implements ContextInitializer
{
    /**
     * @var ApiClient
     */
    private $client;

    /**
     * @param string $apiUri
     */
    public function __construct($apiUri)
    {
        $this->client = new ApiClient(new Client(['base_uri' => $apiUri]));
    }

    /**
     * Initializes provided context.
     *
     * @param Context $context
     */
    public function initializeContext(Context $context)
    {
        if ($context instanceof ApiClientAwareInterface) {
            $context->setClient($this->client);
        }
    }
}
