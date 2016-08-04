<?php
/**
 * @author    Antoine Hedgecock <antoine.hedgecock@gmail.com>
 *
 * @copyright Interactive Solutions AB
 */

namespace InteractiveSolutions\ZfBehat\Context\Aware;

use InteractiveSolutions\ZfBehat\ApiClient;

interface ApiClientAwareInterface
{
    /**
     * @param ApiClient $client
     *
     * @return void
     */
    public function setClient(ApiClient $client);
}
