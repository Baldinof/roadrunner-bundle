<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Response;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Make sure the file content can fit
 * within you memory limits,
 * because it will be loaded in memory
 * before sending back to RR.
 */
class NonStreamableBinaryFileResponse extends BinaryFileResponse
{
}
