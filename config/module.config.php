<?php
/**
 * @author Erik Norgren <erik.norgren@interactivesolutions.se>
 * @copyright Interactive Solutions
 */

use InteractiveSolutions\ZfBehat\Factory\Options\AbstractOptionsFactory;

return [
    'service_manager' => [
        'abstract_factories' => [
            AbstractOptionsFactory::class,
        ],
    ],
];
