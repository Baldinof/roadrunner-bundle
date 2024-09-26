<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Temporal;

use Temporal\Client\ClientOptions;

final class ClientOptionsFactory
{
    public static function createFromArray(array $options): ClientOptions
    {
        $clientOptions = new ClientOptions();

        if (\array_key_exists('namespace', $options)) {
            $clientOptions = $clientOptions->withNamespace($options['namespace']);
        }

        if (\array_key_exists('identity', $options) && null !== $options['identity']) {
            $clientOptions = $clientOptions->withIdentity($options['identity']);
        }

        if (\array_key_exists('query_rejection_condition', $options) && null !== $options['query_rejection_condition']) {
            $clientOptions = $clientOptions->withQueryRejectionCondition($options['query_rejection_condition']);
        }

        return $clientOptions;
    }
}
