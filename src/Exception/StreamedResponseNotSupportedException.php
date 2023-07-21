<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Exception;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StreamedResponseNotSupportedException extends \RuntimeException implements ExceptionInterface
{
    public function __construct(StreamedResponse $streamedResponse, bool $sendFileMiddlewareEnabled)
    {
        parent::__construct(sprintf(
            "'%s' is pointless in context of RoadRunner as the content needs to be fully generated before passing it to RoadRunner, thus losing the streaming part. Use normal '%s' or if you need to send big file, simply send '%s' only with a file path - it will be streamed using RoadRunner middleware '%s'%s",
            $streamedResponse::class,
            Response::class,
            BinaryFileResponse::class,
            'sendfile',
            !$sendFileMiddlewareEnabled ? ', which appears to be disabled, have you restarted RR?' : ''
        ));
    }
}
