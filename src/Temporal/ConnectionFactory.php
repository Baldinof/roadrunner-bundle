<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Temporal;

final class ConnectionFactory
{
    public static function createFromArray(array $options): Connection
    {
        $connection = new Connection();

        return $connection->withAddress($options['address'])
            ->withCrt($options['crt'])
            ->withClientKey($options['client_key'])
            ->withClientPem($options['client_pem'])
            ->withOverrideServerName($options['override_server_name']);
    }
}
