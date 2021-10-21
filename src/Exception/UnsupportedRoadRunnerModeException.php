<?php
declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Exception;

final class UnsupportedRoadRunnerModeException extends \RuntimeException implements ExceptionInterface
{
    public function __construct(string $mode)
    {
        $message = sprintf('Could not resolve worker for mode %s', $mode);
        parent::__construct($message);
    }
}