<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Integration\Xdebug;

/**
 * @internal
 *
 * This class is a proxy to the Xdebug extension.
 * It allow to mock/stub global php functions during tests.
 * It also remove the requirement to install the Xdebug extension to run tests.
 */
class XdebugProxy
{
    public function isExtensionLoaded(): bool
    {
        return \extension_loaded('xdebug');
    }

    /**
     * @return 'yes'|'no'|'trigger'
     */
    public function startWithRequest(): string
    {
        $config = \ini_get('xdebug.start_with_request');
        if (\in_array($config, ['yes', 'no', 'trigger'], true)) {
            return $config;
        }
        if ($this->hasMode('profile')) {
            return 'yes';
        }
        if ($this->hasMode('debug') || $this->hasMode('trace')) {
            return 'trigger';
        }

        return 'no';
    }

    /**
     * @return ?non-empty-string
     */
    public function triggerValue(): ?string
    {
        return \ini_get('xdebug.trigger_value') ?: null;
    }

    public function hasMode(string $mode): bool
    {
        return \in_array($mode, xdebug_info('mode'), true);
    }

    public function notify(mixed $data): bool
    {
        return xdebug_notify($data);
    }

    public function connectToClient(): bool
    {
        return xdebug_connect_to_client();
    }
}
