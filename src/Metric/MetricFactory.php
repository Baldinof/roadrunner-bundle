<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Metric;

use Baldinof\RoadRunnerBundle\Exception\BadConfigurationException;
use Baldinof\RoadRunnerBundle\Exception\UnknownRpcTransportException;
use Spiral\Goridge\RPC;
use Spiral\Goridge\SocketRelay;
use Spiral\RoadRunner\Metrics;
use Spiral\RoadRunner\MetricsInterface;

final class MetricFactory
{
    /**
     * @var bool
     */
    private $metricsEnabled;

    /**
     * @var string|null
     */
    private $rrRpc;

    /**
     * @var string|null
     */
    private $kernelProjectDir;

    public function __construct(?string $rrRpc, string $kernelProjectDir, bool $metricsEnabled)
    {
        $this->rrRpc = $rrRpc;
        $this->kernelProjectDir = $kernelProjectDir;
        $this->metricsEnabled = $metricsEnabled;
    }

    public function getMetricService(): MetricsInterface
    {
        if (!$this->metricsEnabled) {
            return new NullMetrics();
        }

        if (empty($this->rrRpc)) {
            throw new BadConfigurationException('RPC not configured in RR config, please enable it - https://roadrunner.dev/docs/beep-beep-rpc.');
        }

        //https://github.com/spiral/roadrunner/blob/master/util/network.go
        $rpcDsn = explode('://', $this->rrRpc);

        if (!is_array($rpcDsn) || 2 !== count($rpcDsn)) {
            throw new UnknownRpcTransportException('Unable to parse RPC dsn - '.$this->rrRpc);
        }

        return new Metrics(new RPC($this->createRpcRelay($rpcDsn)));
    }

    private function createRpcRelay(array $rpcDsn): SocketRelay
    {
        switch ($rpcDsn[0]) {
            case 'tcp':
                return $this->createTcpRelay($rpcDsn);
            case 'unix':
                return $this->createUnixRelay($rpcDsn);
        }

        throw new UnknownRpcTransportException('Invalid RPC transport - '.$this->rrRpc);
    }

    private function createTcpRelay(array $rpcDsn): SocketRelay
    {
        $tcpHost = explode(':', $rpcDsn[1]);
        if (!is_array($tcpHost) || 2 !== count($tcpHost)) {
            throw new UnknownRpcTransportException('Invalid TCP RPC - '.$rpcDsn[1]);
        }

        if (empty($tcpHost[0])) {
            $tcpHost[0] = '127.0.0.1';
        }

        return new SocketRelay($tcpHost[0], (int) $tcpHost[1], SocketRelay::SOCK_TCP);
    }

    private function createUnixRelay(array $rpcDsn): SocketRelay
    {
        $socketPath = $rpcDsn[1];
        //Is path relative? Make it absolute, from project root.
        if ('/' !== $socketPath[0]) {
            $socketPath = $this->kernelProjectDir.'/'.$socketPath;
        }

        return new SocketRelay($socketPath, null, SocketRelay::SOCK_UNIX);
    }
}
