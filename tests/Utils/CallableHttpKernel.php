<?php

declare(strict_types=1);

namespace Tests\Baldinof\RoadRunnerBundle\Utils;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class CallableHttpKernel implements HttpKernelInterface
{
    private \Closure $callable;

    public function __construct(\Closure $callable)
    {
        $this->callable = $callable;
    }

    public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): Response
    {
        return ($this->callable)($request);
    }
}
