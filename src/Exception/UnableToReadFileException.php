<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Exception;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

class UnableToReadFileException extends \RuntimeException implements ExceptionInterface
{
    public function __construct(BinaryFileResponse $symfonyResponse, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct(sprintf("Cannot read file '%s'", $symfonyResponse->getFile()->getPathname()), $code, $previous);
    }
}
