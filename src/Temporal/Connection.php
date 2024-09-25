<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Temporal;

final class Connection
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
     * @return Connection
     */
    public function withAddress(string $address): static
    {
        $self = clone $this;

        $self->address = $address;

        return $self;
    }

    /**
     * @param ?non-empty-string $crt
     * @return Connection
     */
    public function withCrt(?string $crt = null): static
    {
        $self = clone $this;

        $self->crt = $crt;

        return $self;
    }

     /**
     * @param ?non-empty-string $clientKey
     * @return Connection
     */
    public function withClientKey(?string $clientKey = null): static
    {
        $self = clone $this;

        $self->clientKey = $clientKey;

        return $self;
    }

     /**
     * @param ?non-empty-string $clientPem
     * @return Connection
     */
    public function withClientPem(?string $clientPem = null): static
    {
        $self = clone $this;

        $self->clientPem = $clientPem;

        return $self;
    }

     /**
     * @param ?non-empty-string $overrideServerName
     * @return Connection
     */
    public function withOverrideServerName(?string $overrideServerName = null): static
    {
        $self = clone $this;

        $self->overrideServerName = $overrideServerName;

        return $self;
    }
}
