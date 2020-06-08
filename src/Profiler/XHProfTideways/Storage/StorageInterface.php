<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Profiler\XHProfTideways\Storage;

interface StorageInterface
{
    public function store(array $data): void;
}
