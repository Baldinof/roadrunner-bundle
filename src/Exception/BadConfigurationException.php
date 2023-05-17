<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Exception;

final class BadConfigurationException extends \RuntimeException implements ExceptionInterface
{
    public static function rpcNotEnabled(): self
    {
        return new self("The RoadRunner plugin 'rpc' is disabled, try to add a `rpc` section to the configuration file.");
    }

    public static function missingRpcAddr(): self
    {
        return new self("The RoadRunner plugin 'rpc' is enabled but no address has been found.");
    }
}
