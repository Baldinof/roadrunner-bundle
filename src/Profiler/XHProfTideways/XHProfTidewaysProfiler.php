<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Profiler\XHProfTideways;

use Baldinof\RoadRunnerBundle\Profiler\ExtensionNotLoadedException;
use Baldinof\RoadRunnerBundle\Profiler\ProfilerInterface;
use Baldinof\RoadRunnerBundle\Profiler\XHProfTideways\Storage\StorageInterface;
use Psr\Http\Message\ServerRequestInterface;

class XHProfTidewaysProfiler implements ProfilerInterface
{
    private $dataStorage;
    private $ratio;
    private $skipBuiltIn;

    private $requestTimeFloat = '';
    private $requestUri = '';
    private $isStarted = false;

    public function __construct(StorageInterface $dataStorage, int $ratio = 0, bool $skipBuiltIn = false)
    {
        $this->dataStorage = $dataStorage;
        $this->ratio = $ratio;
        $this->skipBuiltIn = $skipBuiltIn;

        if (false === \extension_loaded('tideways_xhprof')) {
            throw new ExtensionNotLoadedException('PHP extension "tideways_xhprof" is not loaded.');
        }
    }

    public function start(ServerRequestInterface $request): void
    {
        $this->isStarted = $this->isStarted();

        if ($this->isStarted) {
            \tideways_xhprof_enable($this->extensionFlags());

            $this->requestTimeFloat = (string) \microtime(true);
            $this->requestUri = (string) $request->getUri();
        }
    }

    public function finish(): void
    {
        if ($this->isStarted) {
            $data = $this->makeData();
            $this->dataStorage->store($data);
        }
    }

    protected function makeData(): array
    {
        $time = \time();
        $requestTimeFloat = \explode('.', $this->requestTimeFloat);

        return [
            'profile' => \tideways_xhprof_disable(),
            'meta' => [
                'url' => $this->requestUri,
                'simple_url' => \preg_replace('/\/?\?(.+)/', '', $this->requestUri),
                'SERVER' => $_SERVER,
                'get' => $_GET,
                'env' => $_ENV,
                'request_ts' => [
                    'sec' => $time,
                    'usec' => 0,
                ],
                'request_ts_micro' => [
                    'sec' => $requestTimeFloat[0],
                    'usec' => $requestTimeFloat[1] ?? 0,
                ],
                'request_date' => \date('Y-m-d', $time),
            ],
        ];
    }

    protected function isStarted(): bool
    {
        try {
            return \random_int(1, 100) <= $this->ratio;
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function extensionFlags(): int
    {
        $flags = TIDEWAYS_XHPROF_FLAGS_CPU | TIDEWAYS_XHPROF_FLAGS_MEMORY;

        if ($this->skipBuiltIn) {
            $flags |= TIDEWAYS_XHPROF_FLAGS_NO_BUILTINS;
        }

        return $flags;
    }
}
