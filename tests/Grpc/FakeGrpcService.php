<?php

declare(strict_types=1);

namespace Tests\Baldinof\RoadRunnerBundle\Grpc;

use Google\Protobuf\Internal\Message as ProtoMessage;
use Spiral\RoadRunner\GRPC\ContextInterface;
use Spiral\RoadRunner\GRPC\ServiceInterface;

class FakeGrpcService implements ServiceInterface
{
    // GRPC specific service name.
    public const NAME = 'fake.Fake';

    public function fake(ContextInterface $ctx, FakeGrpcRequest $in): FakeGrpcResponse
    {
        return new FakeGrpcResponse();
    }
}

class FakeGrpcRequest extends ProtoMessage
{
}

class FakeGrpcResponse extends ProtoMessage
{
}
