<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Temporal;

use Temporal\Client\ClientOptions;
use Temporal\Client\GRPC\ServiceClientInterface;
use Temporal\Client\ScheduleClient;
use Temporal\Client\ScheduleClientInterface;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\DataConverterInterface;

final class ScheduleClientFactory
{
    private DataConverterInterface $dataConverter;

    private ClientOptions $clientOptions;
    private ServiceClientInterface $serviceClient;

    public function __construct(
        ServiceClientInterface $serviceClient,
        DataConverter $dataConverter,
        ClientOptions $clientOptions,
    ) {
        $this->dataConverter = $dataConverter;
        $this->clientOptions = $clientOptions;
        $this->serviceClient = $serviceClient;
    }

    public function __invoke(): ScheduleClientInterface
    {
        return ScheduleClient::create(
            $this->serviceClient,
            $this->clientOptions,
            $this->dataConverter
        );
    }
}
