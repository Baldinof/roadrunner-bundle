<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Temporal;

use Temporal\Client\GRPC\ServiceClient;
use Temporal\Client\GRPC\ServiceClientInterface;

final class ServiceClientFactory
{
    private ServiceClientConfig $connection;

    public function __construct(ServiceClientConfig $clientConfig)
    {
        $this->connection = $clientConfig;
    }

    public function __invoke(): ServiceClientInterface
    {
        $serviceClient = ServiceClient::create($this->connection->address);

        if (
            null !== $this->connection->crt
            && null !== $this->connection->clientKey
            && null !== $this->connection->clientPem
        ) {
            $serviceClient = ServiceClient::createSSL(
                $this->connection->address,
                $this->connection->crt,
                $this->connection->clientKey,
                $this->connection->clientPem,
                $this->connection->overrideServerName
            );
        }

        return $serviceClient;
    }
}
