<?php
/**
 * @author    Antoine Hedgecock <antoine.hedgecock@gmail.com>
 *
 * @copyright Interactive Solutions AB
 */

namespace InteractiveSolutions\ZfBehat\Context\Aware;

use GuzzleHttp\Client;
use InteractiveSolutions\ZfBehat\ApiClient;

trait ApiClientAwareTrait
{
    /**
     * @var ApiClient
     */
    private $client;

    /**
     * @return ApiClient
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * @param ApiClient $client
     */
    public function setClient(ApiClient $client)
    {
        $this->client = $client;
    }
}
