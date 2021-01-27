<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Helpers;

use Jean85\PrettyVersions;
use Sentry\ClientInterface;
use Sentry\Event;
use Sentry\FlushableClientInterface;
use Sentry\UserDataBag;

class SentryHelper
{
    /**
     * @var bool
     */
    private static $isSentrySdkVersion3OrHigher = null;

    public static function isVersion3(): bool
    {
        if (self::$isSentrySdkVersion3OrHigher === null) {
            $version = PrettyVersions::getVersion('sentry/sentry')->getPrettyVersion();

            self::$isSentrySdkVersion3OrHigher = version_compare($version, '3.0.0', '>=');
        }

        return self::$isSentrySdkVersion3OrHigher;
    }

    public static function flushClient(ClientInterface $client = null): void
    {
        if ($client instanceof FlushableClientInterface) {
            $client->flush();
        } elseif ($client instanceof ClientInterface) {
            $client->flush()->wait(false);
        }
    }

    public static function configureUserData(Event $event, array $serverParams): void
    {
        if (self::isVersion3()) {
            self::configureWithUserDataBag($event, $serverParams);
        } else {
            self::configureWithUserContext($event, $serverParams);
        }
    }

    private static function configureWithUserContext(Event $event, array $serverParams): void
    {
        $userContext = $event->getUserContext();

        if (null === $userContext->getIpAddress() && isset($serverParams['REMOTE_ADDR'])) {
            $userContext->setIpAddress($serverParams['REMOTE_ADDR']);
        }
    }

    private static function configureWithUserDataBag(Event $event, array $serverParams): void
    {
        $userDataBag = $event->getUser();
        if (null === $userDataBag) {
            $userDataBag = new UserDataBag();
            $event->setUser($userDataBag);
        }

        if (null === $userDataBag->getIpAddress() && isset($serverParams['REMOTE_ADDR'])) {
            $userDataBag->setIpAddress($serverParams['REMOTE_ADDR']);
        }
    }
}
