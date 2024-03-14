# Roadrunner Bundle

[RoadRunner](https://roadrunner.dev/) is a high-performance PHP application server, load-balancer, and process manager written in Golang.

This bundle provides a RoadRunner Worker integrated in Symfony, it's easily configurable and extendable.

## Installation

Run the following command:

```
composer require baldinof/roadrunner-bundle
```

If you don't use Symfony Flex:
- register `Baldinof\RoadRunnerBundle\BaldinofRoadRunnerBundle` in your kernel
- copy default RoadRunner configuration files: `cp vendor/baldinof/roadrunner-bundle/.rr.* .`

## Usage

- require the RoadRunner download utility: `composer require --dev spiral/roadrunner-cli`
- get the RoadRunner binary: `vendor/bin/rr get --location bin/`
- run RoadRunner with `bin/rr serve` or `bin/rr serve -c .rr.dev.yaml` (watch mode)
- visit your app at http://localhost:8080

## Integrations

Depending on installed bundle & your configuration, this bundles add some integrations:

- **Sentry**: configure the request context (if the [`SentryBundle`](https://github.com/getsentry/sentry-symfony) is installed)
- **Sessions**: add the session cookie to the Symfony response (if `framework.sessions.enabled` config is `true`)
- **Doctrine Mongo Bundle**: clear opened managers after each requests (if [`DoctrineMongoDBBundle`](https://github.com/doctrine/DoctrineMongoDBBundle) is installed)
- **Doctrine ORM Bundle**: clear opened managers and check connection is still usable after each requests (if [`DoctrineBundle`](https://github.com/doctrine/DoctrineBundle) is installed)
- **Blackfire**: enable the probe when a profile is requested (if the [`blackfire`](https://blackfire.io/) extension is installed)

Even if it is not recommended, you can disable default integrations:

```yaml
baldinof_road_runner:
  default_integrations: false
```

## Middlewares

You can use middlewares to manipulate request & responses. Middlewares must implements [`Baldinof\RoadRunnerBundle\Http\MiddlewareInterface`](./src/Http/MiddlewareInterface.php).

Example configuration:

```yaml
baldinof_road_runner:
    middlewares:
        - App\Middleware\YourMiddleware
```

Be aware that
- middlewares are run outside of Symfony `Kernel::handle()`
- the middleware stack is always resolved at worker start (can be a performance issue if your middleware initialization takes time)

## Kernel reboots

The Symfony kernel and the dependency injection container are **preserved between requests**. If an exception is thrown during the request handling, the kernel is rebooted and a fresh container is used.

The goal is to prevent services to be in a non-recoverable state after an error.

To optimize your worker you can allow exceptions that does not put your app in an errored state:

```yaml
# config/packages/baldinof_road_runner.yaml
baldinof_road_runner:
    kernel_reboot:
      strategy: on_exception
      allowed_exceptions:
        - Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
        - Symfony\Component\Serializer\Exception\ExceptionInterface
        - App\Exception\YourDomainException
```
> If some of your services are stateful, you can implement `Symfony\Contracts\Service\ResetInterface` and your service will be resetted on each request.

If you are seeing issues and want to use a fresh container on each request you can use the `always` reboot strategy:

```yaml
# config/packages/baldinof_road_runner.yaml
baldinof_road_runner:
    kernel_reboot:
      strategy: always
```

If you are building long-running application and need to reboot it every XXX request to prevent memory leaks you can use `max_jobs` reboot strategy:

```yaml
# config/packages/baldinof_road_runner.yaml
baldinof_road_runner:
    kernel_reboot:
      strategy: max_jobs
      max_jobs: 1000 # maximum number of request
      max_jobs_dispersion: 0.2 # dispersion 20% used to prevent simultaneous reboot of all active workers (kernel will rebooted between 800 and 1000 requests) 
```

You can combine reboot strategies:


```yaml
# config/packages/baldinof_road_runner.yaml
baldinof_road_runner:
    kernel_reboot:
      strategy: [on_exception, max_jobs]
      allowed_exceptions:
        - Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
        - Symfony\Component\Serializer\Exception\ExceptionInterface
        - App\Exception\YourDomainException
      max_jobs: 1000
      max_jobs_dispersion: 0.2
```


## HTTP worker Events

The following events are dispatched throughout the worker lifecycle:

- `Baldinof\RoadRunnerBundle\Event\WorkerStartEvent`: Dispatched right before the worker starts listening to requests.
- `Baldinof\RoadRunnerBundle\Event\WorkerStopEvent`: Dispatched right before the worker closes.
- `Baldinof\RoadRunnerBundle\Event\WorkerExceptionEvent`: Dispatched after encountering an uncaught exception during request handling.
- `Baldinof\RoadRunnerBundle\Event\WorkerKernelRebootedEvent`: Dispatched after the symfony kernel was rebooted (see Kernel reboots).

## Development mode

Copy the dev config file if it's not present: `cp vendor/baldinof/roadrunner-bundle/.rr.dev.yaml .`

Start RoadRunner with the dev config file:

```
bin/rr serve -c .rr.dev.yaml
```

Reference: https://roadrunner.dev/docs/beep-beep-reload

If you use the Symfony VarDumper, dumps will not be shown in the HTTP Response body. You can view dumps with `bin/console server:dump` or in the profiler.

## Metrics
Roadrunner can [collect application metrics](https://roadrunner.dev/docs/beep-beep-metrics), and expose a prometheus endpoint.

Example configuration:

```yaml
# config/packages/baldinof_road_runner.yaml
baldinof_road_runner:
  metrics:
    enabled: true
    collect:
      user_login:
        type: counter
        help: "Number of logged in user"
```

And configure RoadRunner:

```yaml
# .rr.yaml
rpc:
  listen: "tcp:127.0.0.1:6001"

metrics:
  address: "0.0.0.0:9180" # prometheus endpoint
```

Then simply inject `Spiral\RoadRunner\MetricsInterface` to record metrics:

```php
class YouController
{
    public function index(MetricsInterface $metrics): Response
    {
        $metrics->add('user_login', 1);

        return new Response("...");
    }
}
```

## Centrifuge.js (websocket)
Bundle supports Centrifuge websocket plugin for RoadRunner 2 using Symfony's
event system. Configuration reference can be found at https://roadrunner.dev/docs/plugins-centrifuge.
By default, worker will answer with empty/default corresponding responses for each Centrifuge websocket request.
Every request type can be found at `Baldinof\RoadRunnerBundle\Event\Centrifuge` namespace. Be aware that you are bypassing
a lot of Symfony's security checks, remember, this is not a HttpWorker

Example usage

```yaml
service:
  centrifuge:
    command: "./bin/centrifuge --config=centrifuge.json" # you need to download the bin/centrifuge, just follow reference configuration link from RoadRunner docs
    process_num: 1
    remain_after_exit: true
    service_name_in_log: true
    restart_sec: 1

centrifuge:
  proxy_address: "tcp://127.0.0.1:10001"
  grpc_api_address: "tcp://127.0.0.1:10000"
  pool:
    reset_timeout: 10
    num_workers: 1
    max_jobs: 1

# centrifuge requires rpc plugin
rpc:
  listen: tcp://127.0.0.1:6001
```

centrifuge.json (in project root)
```json5
{
  "allowed_origins": [
    "*"
  ],
  "publish": true,
  "proxy_publish": true,
  "proxy_subscribe": true,
  "proxy_connect": true,
  "allow_subscribe_for_client": true,
  "address": "127.0.0.1",  // use  0.0.0.0 if you are running RR in docker
  "grpc_api": true,
  "grpc_api_address": "127.0.0.1",  // use  0.0.0.0 if you are running RR in docker
  "grpc_api_port": 10000,
  "port": 8081, // exposed centrifuge port
  "proxy_connect_endpoint": "grpc://127.0.0.1:10001",
  "proxy_connect_timeout": "10s",
  "proxy_publish_endpoint": "grpc://127.0.0.1:10001",
  "proxy_publish_timeout": "10s",
  "proxy_subscribe_endpoint": "grpc://127.0.0.1:10001",
  "proxy_subscribe_timeout": "10s",
  "proxy_refresh_endpoint": "grpc://127.0.0.1:10001",
  "proxy_refresh_timeout": "10s",
  "proxy_rpc_endpoint": "grpc://127.0.0.1:10001",
  "proxy_rpc_timeout": "10s"
}
```

docker-compose.yaml
```yaml
services:
  app:
    ports:
      - "8080:8080" # your RoadRunner port
      - "8081:8081" # centrifuge port
```

ConnectListener.php
```php
<?php

namespace App\EventListener\Centrifuge;

use App\Entity\User;
use App\Repository\UserRepository;
use Baldinof\RoadRunnerBundle\Event\Centrifuge\ConnectEvent;
use RoadRunner\Centrifugo\Payload\ConnectResponse;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;

#[AsEventListener]
readonly class ConnectListener
{
    public function __construct(private UserRepository $userRepository)
    {
    }

    public function __invoke(ConnectEvent $event): void
    {
        $token = $event->getRequest()->getData()["token"] ?? null;
        if ($token === null) {
            $event->getRequest()->disconnect(Response::HTTP_FORBIDDEN, 'Missing user "token"');
            $event->stopPropagation();
            return;
        }

        $user = $this->userRepository->findOneBy(["token" => $token]);
        if ($user === null) {
            $event->getRequest()->disconnect(Response::HTTP_UNAUTHORIZED, 'Invalid user "token"');
            $event->stopPropagation();
            return;
        }

        $event->setResponse(new ConnectResponse(
            user: $user->getId(),
            channels: ["my_global_channel"],
        ));
    }
}
```

publishing messages programmatically can be done by injecting `RoadRunner\Centrifugo\RPCCentrifugoApi`
```php

namespace App\Services;

use RoadRunner\Centrifugo\RPCCentrifugoApi;

readonly class MyService
{
    public function __construct(private RPCCentrifugoApi $centrifugoApi)
    {
    }

    public function doSomething(): void
    {
        $this->centrifugoApi->publish("my_global_channel", json_encode([
            "data" => "to_send",
        ]));
    }
}
```


## gRPC

gRPC support was added by the roadrunner-grpc plugin for RoadRunner 2 (https://github.com/spiral/roadrunner-grpc).

To configure Roadrunner for gRPC, refer to the configuration reference at https://roadrunner.dev/docs/plugins-grpc/current. Basic configuration example:

```yaml
server:
  command: "php public/index.php"
  env:
    APP_RUNTIME: Baldinof\RoadRunnerBundle\Runtime\Runtime

grpc:
  listen: "tcp://:9001"

  proto:
    - "calculator.proto"
```

Once you have generated your PHP files from proto files, you just have to implement the service interfaces. GRPC services are registered automatically. Example service:

```php
<?php

namespace App\Grpc;

use Spiral\RoadRunner\GRPC;
use App\Grpc\Generated\Calculator\Sum;
use App\Grpc\Generated\Calculator\Result;
use App\Grpc\Generated\Calculator\CalculatorInterface;

class Calculator implements CalculatorInterface
{
    public function Sum(GRPC\ContextInterface $ctx, Sum $in): Result
    {
        return (new Result())->setResult($in->getA() + $in->getB());
    }
}
```

## KV caching

Roadrunner has a KV (Key-Value) plugin that can be used to cache data between requests. 

To use it, refer to the configuration reference at https://roadrunner.dev/docs/kv-overview. 
This requires the `spiral/roadrunner-kv`, `spiral/goridge` and `symfony/cache` composer dependencies. Basic configuration example:

Example configuration:

```yaml
# config/packages/baldinof_road_runner.yaml
baldinof_road_runner:
  kv:
    storages:
      - example
```

And configure RoadRunner:

```yaml
# .rr.yaml
rpc:
  listen: tcp://127.0.0.1:6001

kv:
  example:
    driver: memory
    config: { }
```

An adapter service will now be created automatically for your storage with the name `cache.adapter.roadrunner.kv_<YOUR_STORAGE_NAME>`.

Basic usage example:

```yaml
# config/packages/cache.yaml
framework:
  cache:
    pools:
      cache.example:
        adapter: cache.adapter.roadrunner.kv_example
```

## Usage with Docker

```Dockerfile
# Dockerfile
FROM php:8.1-alpine

RUN apk add --no-cache linux-headers autoconf openssl-dev g++ make pcre-dev icu-dev zlib-dev libzip-dev && \
    docker-php-ext-install bcmath intl opcache zip sockets && \
    apk del --purge autoconf g++ make

WORKDIR /usr/src/app

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./

RUN composer install --no-dev --no-scripts --prefer-dist --no-progress --no-interaction

RUN ./vendor/bin/rr get-binary --location /usr/local/bin

COPY . .

ENV APP_ENV=prod

RUN composer dump-autoload --optimize && \
    composer check-platform-reqs && \
    php bin/console cache:warmup

EXPOSE 8080

CMD ["rr", "serve"]
```
