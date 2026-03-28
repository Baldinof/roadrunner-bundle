<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Temporal;

use Temporal\Client\ClientOptions;

final class ClientOptionsFactory
{
    public static function createFromArray(array $options): ClientOptions
    {
        $clientOptions = new ClientOptions();

        if (isset($options['namespace'])) {
            $clientOptions = $clientOptions->withNamespace($options['namespace']);
        }

        if (isset($options['identity'])) {
            $clientOptions = $clientOptions->withIdentity($options['identity']);
        }

        if (isset($options['query_rejection_condition'])) {
            $clientOptions = $clientOptions->withQueryRejectionCondition($options['query_rejection_condition']);
        }

        return $clientOptions;
    }
}
