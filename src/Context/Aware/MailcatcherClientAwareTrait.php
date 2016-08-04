<?php
/**
 * @author    Jonas Eriksson <jonas.eriksson@interactivesolutions.se>
 *
 * @copyright Interactive Solutions
 */
declare(strict_types = 1);

namespace InteractiveSolutions\ZfBehat\Context\Aware;

use GuzzleHttp\Client;

trait MailcatcherClientAwareTrait
{
    /**
     * @var Client
     */
    private $client;

    /**
     * @return Client
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * @param Client $client
     */
    public function setClient(Client $client)
    {
        $this->client = $client;
    }
}
