<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Temporal;

use Temporal\Client\ClientOptions;
use Temporal\Client\GRPC\ServiceClient;
use Temporal\Client\WorkflowClient;
use Temporal\Client\WorkflowClientInterface;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\Interceptor\SimplePipelineProvider;

final class ClientFactory
{
    private DataConverterInterface $dataConverter;

    private ClientOptions $clientOptions;

    private Connection $connection;

    private SimplePipelineProvider $interceptors;

    public function __construct(
        DataConverter $dataConverter,
        ClientOptions $clientOptions,
        SimplePipelineProvider $interceptors,
        Connection $connection,
    ) {
        $this->dataConverter = $dataConverter;
        $this->clientOptions = $clientOptions;
        $this->interceptors = $interceptors;
        $this->connection = $connection;
    }

    public function __invoke(): WorkflowClientInterface
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

        return WorkflowClient::create(
            $serviceClient,
            $this->clientOptions,
            $this->dataConverter,
            $this->interceptors
        );
    }
}
