<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Profiler\XHProfTideways\Storage;

use MongoDB\Client;
use Psr\Log\LoggerInterface;

class MongoStorage implements StorageInterface
{
    private $logger;
    private $connectionUri;
    private $databaseName;
    private $collectionName;
    private $uriOptions;
    private $driverOptions;

    public function __construct(
        LoggerInterface $logger,
        string $connectionUri,
        string $databaseName,
        string $collectionName = 'results',
        array $uriOptions = [],
        array $driverOptions = []
    ) {
        $this->logger = $logger;
        $this->connectionUri = $connectionUri;
        $this->databaseName = $databaseName;
        $this->collectionName = $collectionName;
        $this->uriOptions = $uriOptions;
        $this->driverOptions = $driverOptions;
    }

    public function store(array $data): void
    {
        try {
            $data = $this->prepare($data);

            $client = $this->openConnection();
            $database = $client->selectDatabase($this->databaseName);
            $collection = $database->selectCollection($this->collectionName);

            $collection->insertOne($data);
        } catch (\Exception $exception) {
            $this->logger->error($exception);
        }
    }

    protected function prepare(array $data): array
    {
        if (isset($data['meta']['request_ts'])) {
            $data['meta']['request_ts'] = new \MongoDate($data['meta']['request_ts']['sec']);
        }

        if (isset($data['meta']['request_ts_micro'])) {
            $data['meta']['request_ts_micro'] = new \MongoDate(
                $data['meta']['request_ts_micro']['sec'],
                $data['meta']['request_ts_micro']['usec']
            );
        }

        return $data;
    }

    private function openConnection(): Client
    {
        return new Client($this->connectionUri, $this->uriOptions, $this->driverOptions);
    }
}
