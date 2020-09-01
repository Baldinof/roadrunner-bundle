<?php

namespace Baldinof\RoadRunnerBundle;

if (!\function_exists('Baldinof\RoadRunnerBundle\consumes')) {
    /**
     * @internal
     */
    function consumes(\Iterator $gen): void
    {
        foreach ($gen as $_);
    }
}
