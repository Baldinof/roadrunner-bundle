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

## Limitations

### Long running kernel

The kernel is preserved between requests. If you want to reboot it, and use a fresh container on each request you can configure the worker:

```yaml
# config/packages/baldinof_road_runner.yaml
baldinof_road_runner:
    should_reboot_kernel: true
```

If you want to reset just some services between requests (database connections), you can create service resetters by implementing `Symfony\Contracts\Service\ResetInterface`.

### Sessions

Currently RoadRunner is not compatible with Symfony Sessions, symfony use the native php session mecanism and does not set cookies on symfony responses.

See: https://github.com/spiral/roadrunner/issues/18

### Development mode

As everything is loaded in memory at startup, you should restart roadrunner after code changes.

You can use a configuration that reload the worker after each request:

```
bin/rr serve -o 'http.workers.pool.numWorkers=1' -o 'http.workers.pool.maxJobs=1'
```

Reference: https://roadrunner.dev/docs/php-developer

If you use the Symfony VarDumper, dumps will not be shown in the HTTP Response body. You can view dumps with `bin/console server:dump` or in the profiler.
