<?php
/**
 * @copyright Interactive Solutions
 */

declare(strict_types=1);

namespace InteractiveSolutions\ZfBehat;

use InteractiveSolutions\ZfBehat\Factory\Options\AbstractOptionsFactory;

final class ConfigProvider
{
    public function __invoke()
    {
        return [
            'dependencies' => [
                'abstract_factories' => [
                    AbstractOptionsFactory::class,
                ],
            ]
        ];
    }
}
