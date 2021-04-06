# Upgrading from RoadRunner Bundle 1.x to 2.x

Version 2.0 has a lot of breaking changes as it _totally removed the PSR layer_, and _many classes as been moved_, 
however it's mainly internal changes, if you don't use custom middleware **you should be good by just migrating to the new
configuration formats**:

```yaml
# config/packages/baldinof_road_runner.yaml
baldinof_road_runner:
    # When the kernel should be rebooted.
    # See https://github.com/baldinof/roadrunner-bundle#kernel-reboots
    kernel_reboot:
        # if you want to use a fresh container on each request, use the `always` strategy
        strategy: on_exception
        # Exceptions you KNOW that do not put your app in an errored state
        allowed_exceptions:
           - Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
           - Symfony\Component\Serializer\Exception\ExceptionInterface
           - Symfony\Contracts\HttpClient\Exception\ExceptionInterface

    # Allow to send prometheus metrics to the main RoadRunner process,
    # via a `Spiral\RoadRunner\MetricsInterface` service.
    # See https://github.com/baldinof/roadrunner-bundle#metrics
    metrics:
        enabled: false
        # collect:
        #     my_counter:
        #         type: counter
        #         help: Some help


    # You can use middlewares to manipulate Symfony requests & responses.
    # See https://github.com/baldinof/roadrunner-bundle#middlewares
    # middlewares:
    #     - App\Middleware\YourMiddleware
```

```bash
# Copy RoadRunner default configurations
cp vendor/baldinof/roadrunner-bundle/.rr.* .
```

Do not forget to update the RoadRunner binary:

```bash
vendor/bin/rr get-binary --location bin
```


> If you have custom PSR middlewares, you should migrate to the new [`MiddlewareInterface`](./src/Http/MiddlewareInterface.php) that directly use Symfony HttpFoundation instead of PSR classes.
