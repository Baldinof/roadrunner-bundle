<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Temporal;

final class ServiceClientConfig
{
    /**
     * @var non-empty-string
     */
    public string $address;

    /**
     * @var ?non-empty-string
     */
    public ?string $crt = null;

    /**
     * @var ?non-empty-string
     */
    public ?string $clientKey = null;

    /**
     * @var ?non-empty-string
     */
    public ?string $clientPem = null;

    /**
     * @var ?non-empty-string
     */
    public ?string $overrideServerName = null;

    /**
     * @param non-empty-string $address
     */
    public function withAddress(string $address): self
    {
        $self = clone $this;

        $self->address = $address;

        return $self;
    }

    /**
     * @param ?non-empty-string $crt
     */
    public function withCrt(?string $crt = null): self
    {
        $self = clone $this;

        $self->crt = $crt;

        return $self;
    }

    /**
     * @param ?non-empty-string $clientKey
     */
    public function withClientKey(?string $clientKey = null): self
    {
        $self = clone $this;

        $self->clientKey = $clientKey;

        return $self;
    }

    /**
     * @param ?non-empty-string $clientPem
     */
    public function withClientPem(?string $clientPem = null): self
    {
        $self = clone $this;

        $self->clientPem = $clientPem;

        return $self;
    }

    /**
     * @param ?non-empty-string $overrideServerName
     */
    public function withOverrideServerName(?string $overrideServerName = null): self
    {
        $self = clone $this;

        $self->overrideServerName = $overrideServerName;

        return $self;
    }

    public static function createFromArray(array $options): self
    {
        $config = new ServiceClientConfig();

        return $config->withAddress($options['address'])
            ->withCrt($options['crt'])
            ->withClientKey($options['client_key'])
            ->withClientPem($options['client_pem'])
            ->withOverrideServerName($options['override_server_name']);
    }
}
