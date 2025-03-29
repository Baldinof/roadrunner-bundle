<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Temporal;

use Temporal\Client\ClientOptions;
use Temporal\Client\GRPC\ServiceClientInterface;
use Temporal\Client\WorkflowClient;
use Temporal\Client\WorkflowClientInterface;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\Interceptor\SimplePipelineProvider;

final class WorkflowClientFactory
{
    private ServiceClientInterface $serviceClient;

    private DataConverterInterface $dataConverter;

    private ClientOptions $clientOptions;

    private SimplePipelineProvider $interceptors;

    public function __construct(
        ServiceClientInterface $serviceClient,
        DataConverter $dataConverter,
        ClientOptions $clientOptions,
        SimplePipelineProvider $interceptors,
    ) {
        $this->serviceClient = $serviceClient;
        $this->dataConverter = $dataConverter;
        $this->clientOptions = $clientOptions;
        $this->interceptors = $interceptors;
    }

    public function __invoke(): WorkflowClientInterface
    {
        return WorkflowClient::create(
            $this->serviceClient,
            $this->clientOptions,
            $this->dataConverter,
            $this->interceptors
        );
    }
}
