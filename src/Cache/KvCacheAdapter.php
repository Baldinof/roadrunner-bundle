<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Cache;

use Spiral\Goridge\RPC\RPCInterface;
use Spiral\RoadRunner\KeyValue\Factory;
use Symfony\Component\Cache\Adapter\Psr16Adapter;

/**
 * @internal
 */
final class KvCacheAdapter extends Psr16Adapter
{
    public static function createConnection(#[\SensitiveParameter] string $dsn, array $options = []): self
    {
        /** @var RPCInterface $rpc */
        $rpc = $options['rpc'];
        $factory = new Factory($rpc);
        $storage = $factory->select($options['storage']);

        return new self($storage);
    }
}
