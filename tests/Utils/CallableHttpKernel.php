<?php

namespace Tests\Baldinof\RoadRunnerBundle\Utils;

use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CallableHttpKernel implements HttpKernelInterface
{
    private \Closure $callable;

    public function __construct(\Closure $callable)
    {
        $this->callable = $callable;
    }

    public function handle(Request $request, int $type = self::MASTER_REQUEST, bool $catch = true): Response
    {
        return ($this->callable)($request);
    }
}
