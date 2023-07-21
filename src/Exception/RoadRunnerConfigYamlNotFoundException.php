<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Exception;

class RoadRunnerConfigYamlNotFoundException extends \RuntimeException implements ExceptionInterface
{
    public function __construct(string $path)
    {
        parent::__construct(sprintf(
            "Expected to find RR config at '%s', but file appears to not exist. Perhaps you want to explicitly set '%s' environment variable to match the config name you are using",
            $path,
            'RR_CONFIG_NAME'
        ));
    }
}
