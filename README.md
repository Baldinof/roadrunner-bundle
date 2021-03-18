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
- run RoadRunner with `bin/rr serve`
- visit your app at http://localhost:8080

## Configuration

If you want to override some parts of the bundle you can replace some definitions.

Example if you want to use a TCP socket as relay:

```yaml
# config/services.yaml
services:
  Spiral\Goridge\RelayInterface:
    class: 'Spiral\Goridge\SocketRelay'
    arguments:
      - localhost
      - 7000
```

```yaml
# .rr.yaml
http:
  workers:
    relay: "tcp://localhost:7000"
```

## Integrations

Depending on installed bundle & your configuration, this bundles add some integrations:

- Sentry: configure the request context (if the [`SentryBundle`](https://github.com/getsentry/sentry-symfony) is installed)
- Sessions: add the session cookie to the PSR response (if `framework.sessions.enabled` config is `true`)
- Doctrine Mongo Bundle: call `clear()` on all opened manager after each requests (not needed for regular doctrine bundle)

Default integrations can be disabled:

```yaml
baldinof_road_runner:
  default_integrations: false
```

## Middlewares

You can use middlewares to manipulate PSR request & responses. Middlewares can implements either PSR [`MiddlewareInterface`](https://www.php-fig.org/psr/psr-15/#22-psrhttpservermiddlewareinterface)
or [`Baldinof\RoadRunnerBundle\Http\IteratorMiddlewareInterface`](./src/Http/IteratorMiddlewareInterface.php).

`IteratorMiddlewareInterface` allows to do work after the response has been sent to the client, you just have to `yield` the response instead of returning it.

Example configuration:

```yaml
baldinof_road_runner:
    middlewares:
        - App\Middleware\YourMiddleware
```

Be aware that
- middlewares are run outside of Symfony `Kernel::handle()`
- the middleware stack is always resolved at worker start (can be a performance issue if your middleware initialization takes time)

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

## Kernel reboots

The Symfony kernel and the dependency injection container are **preserved between requests**. If an exception is thrown during the request handling, the kernel is rebooted and a fresh container is used.

The goal is to prevent services to be in a non recoverable state after an error.

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

If you are seeing issues and want to use a fresh container on each request you can use the `always` reboot strategy:

```yaml
# config/packages/baldinof_road_runner.yaml
baldinof_road_runner:
    kernel_reboot:
      strategy: always
```

> If some of your services are stateful, you can implement `Symfony\Contracts\Service\ResetInterface` and your service will be resetted on each request.

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

## Usage with Docker

```Dockerfile
# Dockerfile
FROM php:7.4-alpine

RUN apk add --no-cache autoconf openssl-dev g++ make pcre-dev icu-dev zlib-dev libzip-dev && \
    docker-php-ext-install bcmath intl opcache zip sockets && \
    apk del --purge autoconf g++ make

WORKDIR /usr/src/app

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./

RUN composer install --no-dev --no-scripts --no-plugins --prefer-dist --no-progress --no-interaction

RUN ./vendor/bin/rr get-binary --location /usr/local/bin

COPY . .

ENV APP_ENV=prod

RUN composer dump-autoload --optimize && \
    composer check-platform-reqs && \
    php bin/console cache:warmup

EXPOSE 8080

CMD ["rr", "serve"]
```
