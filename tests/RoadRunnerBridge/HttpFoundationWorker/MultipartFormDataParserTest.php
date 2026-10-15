<?php

declare(strict_types=1);

namespace Tests\Baldinof\RoadRunnerBundle\RoadRunnerBridge\HttpFoundationWorker;

use Baldinof\RoadRunnerBundle\RoadRunnerBridge\HttpFoundationWorker\MultipartFormDataParser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class MultipartFormDataParserTest extends TestCase
{
    private const CONTENT_TYPE = 'multipart/form-data; boundary=XXX';

    // Not a vfsStream directory: tempnam() doesn't support stream wrappers
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = realpath(sys_get_temp_dir()).'/'.uniqid('rr_multipart_test_');
        mkdir($this->tmpDir);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->tmpDir);
    }

    public function test_it_parses_fields()
    {
        [$fields, $uploads, $tmpFiles] = $this->parser()->parse(self::CONTENT_TYPE, self::body([
            self::field('a', '1'),
            self::field('b[c]', 'nested'),
            self::field('d[]', 'x'),
            self::field('d[]', 'y'),
            self::field('e', "multi\r\nline"),
        ]));

        $this->assertSame(['a' => '1', 'b' => ['c' => 'nested'], 'd' => ['x', 'y'], 'e' => "multi\r\nline"], $fields);
        $this->assertSame([], $uploads);
        $this->assertSame([], $tmpFiles);
    }

    public function test_it_parses_files()
    {
        [$fields, $uploads, $tmpFiles] = $this->parser()->parse(self::CONTENT_TYPE, self::body([
            self::field('a', '1'),
            self::file('doc', 'doc.txt', 'text/plain', 'Doc content'),
        ]));

        $this->assertSame(['a' => '1'], $fields);
        $this->assertCount(1, $tmpFiles);
        $this->assertStringStartsWith($this->tmpDir.'/rr_upload_', $tmpFiles[0]);
        $this->assertSame([
            'doc' => [
                'name' => 'doc.txt',
                'mime' => 'text/plain',
                'tmpName' => $tmpFiles[0],
                'error' => \UPLOAD_ERR_OK,
                'size' => 11,
            ],
        ], $uploads);
        $this->assertSame('Doc content', file_get_contents($tmpFiles[0]));
    }

    public function test_it_keeps_binary_file_content()
    {
        $content = random_bytes(1024);

        [, $uploads] = $this->parser()->parse(self::CONTENT_TYPE, self::body([
            self::file('bin', 'file.bin', 'application/octet-stream', $content),
        ]));

        $this->assertSame($content, file_get_contents($uploads['bin']['tmpName']));
    }

    public function test_it_parses_nested_files()
    {
        [, $uploads] = $this->parser()->parse(self::CONTENT_TYPE, self::body([
            self::file('docs[]', 'doc1.txt', 'text/plain', 'Doc 1'),
            self::file('docs[]', 'doc2.txt', 'text/plain', 'Doc 2'),
            self::file('x[y]', 'doc3.txt', 'text/plain', 'Doc 3'),
        ]));

        $this->assertSame('doc1.txt', $uploads['docs'][0]['name']);
        $this->assertSame('doc2.txt', $uploads['docs'][1]['name']);
        $this->assertSame('doc3.txt', $uploads['x']['y']['name']);
        $this->assertSame('Doc 2', file_get_contents($uploads['docs'][1]['tmpName']));
    }

    public function test_it_supports_quoted_boundary()
    {
        [$fields] = $this->parser()->parse('multipart/form-data; boundary="XXX"', self::body([
            self::field('a', '1'),
        ]));

        $this->assertSame(['a' => '1'], $fields);
    }

    public function test_empty_file_input_is_reported_as_no_file()
    {
        [, $uploads, $tmpFiles] = $this->parser()->parse(self::CONTENT_TYPE, self::body([
            self::file('doc', '', 'application/octet-stream', ''),
        ]));

        $this->assertSame(\UPLOAD_ERR_NO_FILE, $uploads['doc']['error']);
        $this->assertSame('', $uploads['doc']['tmpName']);
        $this->assertSame([], $tmpFiles);
    }

    public function test_too_large_file_is_reported_as_ini_size()
    {
        [, $uploads, $tmpFiles] = $this->parser(maxFileSize: 4)->parse(self::CONTENT_TYPE, self::body([
            self::file('doc', 'doc.txt', 'text/plain', 'Too large'),
        ]));

        $this->assertSame(\UPLOAD_ERR_INI_SIZE, $uploads['doc']['error']);
        $this->assertSame('', $uploads['doc']['tmpName']);
        $this->assertSame([], $tmpFiles);
    }

    public function test_files_above_max_file_uploads_are_ignored()
    {
        [, $uploads, $tmpFiles] = $this->parser(maxFileUploads: 1)->parse(self::CONTENT_TYPE, self::body([
            self::file('doc1', 'doc1.txt', 'text/plain', 'Doc 1'),
            self::file('doc2', 'doc2.txt', 'text/plain', 'Doc 2'),
        ]));

        $this->assertSame(['doc1'], array_keys($uploads));
        $this->assertCount(1, $tmpFiles);
    }

    public function test_malformed_body_is_ignored()
    {
        $result = $this->parser()->parse(self::CONTENT_TYPE, "--XXX\r\nContent-Disposition: form-data; name=\"a\"\r\n\r\n1\r\n");

        $this->assertSame([[], [], []], $result);
    }

    private function parser(?int $maxFileUploads = 20, int|float|null $maxFileSize = 1024 * 1024): MultipartFormDataParser
    {
        return new MultipartFormDataParser($maxFileUploads, $maxFileSize, $this->tmpDir);
    }

    /**
     * @param list<string> $parts
     */
    private static function body(array $parts): string
    {
        return implode('', array_map(fn (string $part) => "--XXX\r\n{$part}\r\n", $parts))."--XXX--\r\n";
    }

    private static function field(string $name, string $value): string
    {
        return "Content-Disposition: form-data; name=\"{$name}\"\r\n\r\n{$value}";
    }

    private static function file(string $name, string $fileName, string $mime, string $content): string
    {
        return "Content-Disposition: form-data; name=\"{$name}\"; filename=\"{$fileName}\"\r\nContent-Type: {$mime}\r\n\r\n{$content}";
    }
}
