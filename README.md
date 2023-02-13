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


## Events

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

## gRPC

gRPC support was added by the roadrunner-grpc plugin for RoadRunner 2 (https://github.com/spiral/roadrunner-grpc).

To configure Roadrunner for gRPC, refer to the configuration reference at https://roadrunner.dev/docs/beep-beep-grpc. Basic configuration example:

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

## Usage with Docker

```Dockerfile
# Dockerfile
FROM php:8.1-alpine

RUN apk add --no-cache autoconf openssl-dev g++ make pcre-dev icu-dev zlib-dev libzip-dev && \
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
