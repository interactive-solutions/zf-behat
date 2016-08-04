<?php
/**
 * @author    Jonas Eriksson <jonas.eriksson@interactivesolutions.se>
 *
 * @copyright Interactive Solutions
 */

namespace InteractiveSolutions\ZfBehat\Context\Aware;

use GuzzleHttp\Client;

interface MailcatcherClientAwareInterface
{
    /**
     * @param Client $client
     *
     * @return void
     */
    public function setClient(Client $client);
}
