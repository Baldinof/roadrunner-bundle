# Roadrunner Bundle

**Roadrunner Bundle** provides a [RoadRunner](https://roadrunner.dev/) worker integrated in symfony.

## Installation

Run the following command:

```
composer require baldinof/roadrunner-bundle
```

If you don't use Symfony Flex:
- register `Baldinof\RoadRunnerBundle\BaldinofRoadRunnerBundle` in your kernel
- copy the default RoadRunner configuration: `cp vendor/baldinof/roadrunner-bundle/.rr.yaml .`

## Usage

- get the RoadRunner binary: `vendor/bin/rr get --location bin/`
- run RoadRunner with `bin/rr`
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

## Middlewares

You can use middlewares to manipulate PSR request & responses. Middlewares can implements either PSR [`MiddlewareInterface`](https://www.php-fig.org/psr/psr-15/#22-psrhttpservermiddlewareinterface)
or [`Baldinof\RoadRunnerBundle\Http\IteratorMiddlewareInterface`](./src/Http/IteratorMiddlewareInterface.php).

`IteratorMiddlewareInterface` allows to do work after the response has been sent to the client, you just have to `yield` the response instead of returning it.

Example configuration:

```yaml
baldinof_road_runner:
    default_middlewares: true
    middlewares:
        - App\Middleware\YourMiddleware
```

Some default middlewares are enabled by default, depending on your configuration:
- Sentry: configure the request context (if the [`SentryBundle`](https://github.com/getsentry/sentry-symfony) is installed)
- Sessions: add the session cookie to the PSR response (if `framework.sessions.enabled` config is `true`)

Beware that
- middlewares are run outside of Symfony `Kernel::handle()`
- the middleware stack is always resolved at worker start (can be a performance issue if your middleware initialization takes time)

## Limitations

### Long running kernel

The kernel is preserved between requests. If you want to reboot it, and use a fresh container on each request you can configure the worker:

```yaml
# config/packages/baldinof_road_runner.yaml
baldinof_road_runner:
    should_reboot_kernel: true
```

If you want to reset just some services between requests (database connections), you can create service resetters by implementing `Symfony\Contracts\Service\ResetInterface`.

### Development mode

As everything is loaded in memory at startup, you should restart roadrunner after code changes.

You can use a configuration that reload the worker after each request:

```
bin/rr serve -o 'http.workers.pool.numWorkers=1' -o 'http.workers.pool.maxJobs=1'
```

Reference: https://roadrunner.dev/docs/php-developer

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

RUN composer install --no-dev --optimize-autoloader --no-scripts --no-plugins --prefer-dist --no-progress --no-interaction

RUN ./vendor/bin/rr get-binary --location /usr/local/bin

COPY . .

ENV APP_ENV=prod

RUN php bin/console cache:warmup

EXPOSE 8080

CMD ["rr", "serve"]
```
