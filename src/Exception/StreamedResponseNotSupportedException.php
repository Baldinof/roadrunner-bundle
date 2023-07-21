<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Exception;

use Baldinof\RoadRunnerBundle\Response\NonStreamableBinaryFileResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StreamedResponseNotSupportedException extends \RuntimeException implements ExceptionInterface
{
    public function __construct(StreamedResponse $streamedResponse, bool $sendFileMiddlewareEnabled)
    {
        parent::__construct(sprintf(
            "'%s' is pointless in context of RoadRunner as the content needs to be fully generated before passing it to RoadRunner, thus losing the streaming part. Use '%s' or '%s'. If you need to send big file, send '%s' only with a file path - it will be streamed using RoadRunner middleware '%s'%s",
            $streamedResponse::class,
            NonStreamableBinaryFileResponse::class,
            Response::class,
            BinaryFileResponse::class,
            'sendfile',
            !$sendFileMiddlewareEnabled ? ', which appears to be disabled, make sure it\'s enabled and the RR has restarted' : ''
        ));
    }
}
