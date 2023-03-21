<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Reboot;

class MaxJobsRebootStrategy implements KernelRebootStrategyInterface
{
    private int $jobsCount = 0;
    private int $maxJobs;

    public function __construct(int $maxJobs, float $dispersion)
    {
        $minJobs = $maxJobs - (int) round($maxJobs * $dispersion);
        $this->maxJobs = random_int($minJobs, $maxJobs);
    }

    public function shouldReboot(): bool
    {
        if ($this->jobsCount < $this->maxJobs) {
            ++$this->jobsCount;

            return false;
        }

        return true;
    }

    public function clear(): void
    {
    }
}
