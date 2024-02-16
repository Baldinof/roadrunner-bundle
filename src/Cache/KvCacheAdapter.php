<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Cache;

use Spiral\Goridge\RPC\RPC;
use Spiral\RoadRunner\KeyValue\Factory;
use Symfony\Component\Cache\Adapter\Psr16Adapter;

class KvCacheAdapter extends Psr16Adapter
{
    public static function createConnection(#[\SensitiveParameter] string $dsn, array $options = []): mixed
    {
        $rpc = RPC::create($options['rpc_dsn']);
        $factory = new Factory($rpc);
        $storage = $factory->select($options['storage']);

        return new self($storage);
    }
}