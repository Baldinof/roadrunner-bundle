<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Response;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Mime\MimeTypes;

use function Symfony\Component\String\u;

/**
 * Sends file path to RR
 * which then handles file streaming
 * all by itself, the http middleware 'sendfile'
 * needs to be enabled.
 */
class StreamableFileResponse extends Response
{
    public function __construct(
        string $pathname,
        string $disposition = ResponseHeaderBag::DISPOSITION_ATTACHMENT,
        ?string $contentType = null,
        ?string $filename = null,
        ?string $filenameFallback = null,
    ) {
        $realpath = realpath($pathname);
        parent::__construct(null, 201, $this->getHeaders(
            $realpath !== false ? $realpath : $pathname,
            $disposition,
            $contentType,
            $filename,
            $filenameFallback,
        ));
    }

    public static function fromBinaryFileResponse(BinaryFileResponse $binaryFileResponse): self
    {
        return new self(
            $binaryFileResponse->getFile()->getPathname(),
            $binaryFileResponse->headers->get('Content-Disposition', ''),
            $binaryFileResponse->headers->get('Content-Type'),
            $binaryFileResponse->getFile()->getFilename(),
        );
    }

    private function getHeaders(
        string $pathname,
        string $disposition,
        ?string $contentType,
        ?string $filename,
        ?string $filenameFallback = null,
    ): array {
        return [
            'Content-Length' => (int) @filesize($pathname),
            'Content-Type' => u($contentType ?? MimeTypes::getDefault()->guessMimeType($pathname))->ensureEnd('; charset=UTF-8')->toString(),
            'x-sendfile' => $pathname,
            'Content-Disposition' => \in_array($disposition, [HeaderUtils::DISPOSITION_ATTACHMENT, HeaderUtils::DISPOSITION_INLINE]) ? HeaderUtils::makeDisposition(
                $disposition,
                $filename ?? pathinfo($pathname, PATHINFO_BASENAME),
                $filenameFallback ?? u(pathinfo($filename ?? $pathname, PATHINFO_FILENAME))
                ->ascii()
                ->replace('/', '-')
                ->append('.')
                ->append(pathinfo($filename ?? $pathname, PATHINFO_EXTENSION))
                ->toString()
            ) : $disposition,
        ];
    }
}
