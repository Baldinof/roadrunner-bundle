<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Helpers;

use Baldinof\RoadRunnerBundle\Exception\BadConfigurationException;
use Spiral\Goridge\RPC\RPC;
use Spiral\Goridge\RPC\RPCInterface;
use Spiral\RoadRunner\EnvironmentInterface;

/**
 * @internal
 */
final class RPCFactory
{
    public static function fromEnvironment(EnvironmentInterface $environment): RPCInterface
    {
        $rpcAddr = $_ENV['RR_RPC'] ?? $_SERVER['RR_RPC'] ?? null;

        if ($rpcAddr === null) {
            throw BadConfigurationException::rpcNotEnabled();
        }

        return RPC::create($environment->getRPCAddress() ?: throw BadConfigurationException::missingRpcAddr());
    }
}
