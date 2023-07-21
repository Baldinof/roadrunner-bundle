<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Helpers;

use Baldinof\RoadRunnerBundle\Exception\RoadRunnerConfigYamlNotFoundException;
use Symfony\Component\Yaml\Yaml;

/**
 * Be aware that any changes during RR runtime
 * will be shown here when worker resets
 * but will never be applied
 * You need to restart RR.
 */
class RoadRunnerConfig
{
    public const HTTP_MIDDLEWARE_SENDFILE = 'sendfile';

    private string $projectDir;
    private array $config = [];

    public function __construct(string $projectDir)
    {
        $this->projectDir = $projectDir;
        $this->parseConfig();
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function isHttpMiddlewareEnabled(string $name): bool
    {
        return \in_array($name, $this->config['http']['middleware'] ?? [], true);
    }

    private function parseConfig(): void
    {
        $filename = $_ENV['RR_CONFIG_NAME'] ?? null;
        if ($filename === null && $_ENV['APP_ENV'] === 'prod') {
            $filename = '.rr.yaml';
        }

        if ($filename === null) {
            $filename = sprintf('.rr.%s.yaml', $_ENV['APP_ENV']);
        }

        $pathname = $this->projectDir.'/'.$filename;
        if (!file_exists($pathname)) {
            throw new RoadRunnerConfigYamlNotFoundException($pathname);
        }

        $config = Yaml::parseFile($pathname);
        \assert(\is_array($config));
        $this->config = $config;
    }
}
