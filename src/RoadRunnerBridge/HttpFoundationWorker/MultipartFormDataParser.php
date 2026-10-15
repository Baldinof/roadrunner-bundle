<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\RoadRunnerBridge\HttpFoundationWorker;

use Riverline\MultiPartParser\StreamedPart;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Parses multipart/form-data bodies forwarded untouched by RoadRunner (`http.raw_body: true`).
 *
 * Requires the optional "riverline/multipart-parser" package.
 *
 * @internal
 */
final class MultipartFormDataParser
{
    private int $maxFileUploads;
    private int|float $maxFileSize;
    private string $tmpDir;

    public function __construct(?int $maxFileUploads = null, int|float|null $maxFileSize = null, ?string $tmpDir = null)
    {
        $this->maxFileUploads = $maxFileUploads ?? (\ini_get('file_uploads') ? (int) \ini_get('max_file_uploads') : 0);
        $this->maxFileSize = $maxFileSize ?? UploadedFile::getMaxFilesize();
        $this->tmpDir = $tmpDir ?? (\ini_get('upload_tmp_dir') ?: sys_get_temp_dir());
    }

    public static function isSupported(): bool
    {
        return class_exists(StreamedPart::class);
    }

    /**
     * @return array{0: array<mixed>, 1: array<mixed>, 2: list<string>} Fields, uploads (RoadRunner format) and created temporary files
     */
    public function parse(string $contentType, string $body): array
    {
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            return [[], [], []];
        }

        fwrite($stream, "Content-Type: {$contentType}\r\n\r\n".$body);

        try {
            $parts = (new StreamedPart($stream))->getParts();
        } catch (\InvalidArgumentException|\LogicException) {
            return [[], [], []];
        } finally {
            fclose($stream);
        }

        $fields = [];
        $fileNames = [];
        $uploads = [];
        $tmpFiles = [];

        foreach ($parts as $part) {
            $name = $part->getName();

            if ($name === null || $name === '') {
                continue;
            }

            /** @var string $content */
            $content = $part->getBody();
            $fileName = $part->getFileName();

            if ($fileName === null) {
                $fields[] = rawurlencode($name).'='.rawurlencode($content);

                continue;
            }

            if (\count($uploads) >= $this->maxFileUploads) {
                continue;
            }

            $upload = [
                'name' => $fileName,
                'mime' => $part->getMimeType(),
                'tmpName' => '',
                'error' => \UPLOAD_ERR_OK,
                'size' => \strlen($content),
            ];

            if ($fileName === '' && $content === '') {
                $upload['error'] = \UPLOAD_ERR_NO_FILE;
            } elseif ($upload['size'] > $this->maxFileSize) {
                $upload['error'] = \UPLOAD_ERR_INI_SIZE;
            } else {
                $tmpName = @tempnam($this->tmpDir, 'rr_upload_');

                if ($tmpName === false) {
                    $upload['error'] = \UPLOAD_ERR_CANT_WRITE;
                } else {
                    $tmpFiles[] = $tmpName;

                    if (file_put_contents($tmpName, $content) === false) {
                        $upload['error'] = \UPLOAD_ERR_CANT_WRITE;
                    } else {
                        $upload['tmpName'] = $tmpName;
                    }
                }
            }

            $fileNames[] = rawurlencode($name).'='.\count($uploads);
            $uploads[] = $upload;
        }

        // Let parse_str() handle field names like PHP does ("a[b][]", max_input_vars, ...)
        parse_str(implode('&', $fields), $parsedFields);
        parse_str(implode('&', $fileNames), $files);

        // Replace upload indexes by their upload
        array_walk_recursive($files, function (mixed &$value) use ($uploads): void {
            if (\is_string($value)) {
                $value = $uploads[(int) $value];
            }
        });

        return [$parsedFields, $files, $tmpFiles];
    }
}
