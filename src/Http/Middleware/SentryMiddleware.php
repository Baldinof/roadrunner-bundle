<?php

namespace Baldinof\RoadRunnerBundle\Http\Middleware;

use Baldinof\RoadRunnerBundle\Http\IteratorMiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sentry\ClientInterface;
use Sentry\Event;
use Sentry\Options;
use Sentry\State\HubInterface;
use Sentry\State\Scope;
use Sentry\UserDataBag;

/**
 * This middleware is mostly a copy of the the Sentry RequestIntegration.
 * The Sentry class does not allow to pass arbitrary request and always do
 * integrations against PHP globals.
 */
final class SentryMiddleware implements IteratorMiddlewareInterface
{
    /**
     * This constant represents the size limit in bytes beyond which the body
     * of the request is not captured when the `max_request_body_size` option
     * is set to `small`.
     */
    private const REQUEST_BODY_SMALL_MAX_CONTENT_LENGTH = 10 ** 3;

    /**
     * This constant represents the size limit in bytes beyond which the body
     * of the request is not captured when the `max_request_body_size` option
     * is set to `medium`.
     */
    private const REQUEST_BODY_MEDIUM_MAX_CONTENT_LENGTH = 10 ** 4;

    private $hub;

    public function __construct(HubInterface $hub)
    {
        $this->hub = $hub;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $next): \Iterator
    {
        $currentClient = $this->hub->getClient();

        $this->setupRequestData($currentClient, $request);

        yield $next->handle($request);

        if ($currentClient instanceof ClientInterface) {
            $currentClient->flush()->wait(false);
        }
    }

    private function setupRequestData(?ClientInterface $client, ServerRequestInterface $request): void
    {
        if (null === $client) {
            return;
        }

        $options = $client->getOptions();

        $this->hub->configureScope(function (Scope $scope) use ($request, $options) {
            $scope->clear();
            $scope->addEventProcessor(function (Event $event) use ($request, $options) {
                $this->processEvent($event, $options, $request);

                return $event;
            });
        });
    }

    private function processEvent(Event $event, Options $options, ServerRequestInterface $request): void
    {
        $requestData = [
            'url' => (string) $request->getUri(),
            'method' => $request->getMethod(),
        ];

        if ($request->getUri()->getQuery()) {
            $requestData['query_string'] = $request->getUri()->getQuery();
        }

        if ($options->shouldSendDefaultPii()) {
            $serverParams = $request->getServerParams();

            if (isset($serverParams['REMOTE_ADDR'])) {
                $requestData['env']['REMOTE_ADDR'] = $serverParams['REMOTE_ADDR'];
            }

            $requestData['cookies'] = $request->getCookieParams();
            $requestData['headers'] = $request->getHeaders();

            $userDataBag = $event->getUser();
            if (null === $userDataBag) {
                $userDataBag = new UserDataBag();
                $event->setUser($userDataBag);
            }

            if (null === $userDataBag->getIpAddress() && isset($serverParams['REMOTE_ADDR'])) {
                $userDataBag->setIpAddress($serverParams['REMOTE_ADDR']);
            }
        } else {
            $requestData['headers'] = $this->removePiiFromHeaders($request->getHeaders());
        }

        $requestBody = $this->captureRequestBody($options, $request);

        if (!empty($requestBody)) {
            $requestData['data'] = $requestBody;
        }

        $event->setRequest($requestData);
    }

    /**
     * @return mixed
     */
    private function captureRequestBody(Options $options, ServerRequestInterface $serverRequest)
    {
        $maxRequestBodySize = $options->getMaxRequestBodySize();
        $requestBody = $serverRequest->getBody();

        if (
            'none' === $maxRequestBodySize ||
            ('small' === $maxRequestBodySize && $requestBody->getSize() > self::REQUEST_BODY_SMALL_MAX_CONTENT_LENGTH) ||
            ('medium' === $maxRequestBodySize && $requestBody->getSize() > self::REQUEST_BODY_MEDIUM_MAX_CONTENT_LENGTH)
        ) {
            return null;
        }

        $requestData = $serverRequest->getParsedBody();
        $requestData = array_merge(
            $this->parseUploadedFiles($serverRequest->getUploadedFiles()),
            \is_array($requestData) ? $requestData : []
        );

        if (!empty($requestData)) {
            return $requestData;
        }

        if ('application/json' === $serverRequest->getHeaderLine('Content-Type')) {
            try {
                return json_decode($requestBody->getContents(), true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $exception) {
                // Fallback to returning the raw data from the request body
            }
        }

        return $requestBody->getContents();
    }

    private function parseUploadedFiles(array $uploadedFiles): array
    {
        $result = [];

        foreach ($uploadedFiles as $key => $item) {
            if ($item instanceof UploadedFileInterface) {
                $result[$key] = [
                    'client_filename' => $item->getClientFilename(),
                    'client_media_type' => $item->getClientMediaType(),
                    'size' => $item->getSize(),
                ];
            } elseif (\is_array($item)) {
                $result[$key] = $this->parseUploadedFiles($item);
            } else {
                throw new \UnexpectedValueException(sprintf('Expected either an object implementing the "%s" interface or an array. Got: "%s".', UploadedFileInterface::class, \is_object($item) ? \get_class($item) : \gettype($item)));
            }
        }

        return $result;
    }

    private function removePiiFromHeaders(array $headers): array
    {
        $keysToRemove = ['authorization', 'cookie', 'set-cookie', 'remote_addr'];

        return array_filter(
            $headers,
            static function (string $key) use ($keysToRemove): bool {
                return !\in_array(strtolower($key), $keysToRemove, true);
            },
            ARRAY_FILTER_USE_KEY
        );
    }
}
