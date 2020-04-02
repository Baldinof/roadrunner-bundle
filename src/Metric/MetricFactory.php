<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Metric;

use Baldinof\RoadRunnerBundle\Exception\UnknownRpcTransportException;
use Spiral\Goridge\RPC;
use Spiral\Goridge\SocketRelay;
use Spiral\RoadRunner\Metrics;
use Spiral\RoadRunner\MetricsInterface;

class MetricFactory
{
    /**
     * @var bool
     */
    private $metricsEnabled;

    /**
     * @var string
     */
    private $rrRpc;

    /**
     * @var string
     */
    private $kernelProjectDir;

    public function __construct(string $rrRpc, string $kernelProjectDir, bool $metricsEnabled)
    {
        $this->rrRpc = $rrRpc;
        $this->kernelProjectDir = $kernelProjectDir;
        $this->metricsEnabled = $metricsEnabled;
    }

    public function getMetricService(): MetricsInterface
    {
        $nullMetrics = new NullMetrics();

        if (!$this->metricsEnabled || !$this->rrRpc) {
            return $nullMetrics;
        }

        $rpcDsn = parse_url($this->rrRpc);
        if (!is_array($rpcDsn) || !array_key_exists('scheme', $rpcDsn)) {
            return $nullMetrics;
        }

        $rpcService = null;
        switch ($rpcDsn['scheme']) {
            case 'tcp':
                $rpcRelay = new SocketRelay($rpcDsn['host'], $rpcDsn['port'], SocketRelay::SOCK_TCP);
                break;
            case 'unix':
                $soketPath = $rpcDsn['host'].$rpcDsn['path'];
                //Is path relative? Make it absolute, from project root.
                if ('/' !== $soketPath[0]) {
                    $soketPath = $this->kernelProjectDir.'/'.$soketPath;
                }

                $rpcRelay = new SocketRelay($soketPath, null, SocketRelay::SOCK_UNIX);
                break;
            default:
                throw new UnknownRpcTransportException('Invalid RPC transport - '.$rpcDsn['scheme']);
        }

        return new Metrics(new RPC($rpcRelay));
    }
}
