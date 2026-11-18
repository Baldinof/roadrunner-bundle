# Upgrading from RoadRunner Bundle 3.5 to 3.6

The RoadRunner PHP packages were renamed from `spiral/*` to `roadrunner/*`. The renamed packages require PHP 8.2 and
RoadRunner 3, so version 3.6 of the bundle requires them too.

The PHP namespaces did not change (`Spiral\RoadRunner\…`, `Spiral\Goridge\…`), your code does not need to be updated.

## Requirements

- PHP 8.2 or higher
- RoadRunner 3. RoadRunner 2023, 2024 and 2025 are not supported anymore. See the
  [RoadRunner upgrade guide](https://docs.roadrunner.dev/docs/general/v3-migration) for the server side changes.

## Composer dependencies

If your `composer.json` requires RoadRunner packages directly, replace them with the new names:

| Before                      | After                   |
|-----------------------------|-------------------------|
| `spiral/roadrunner`         | `roadrunner/roadrunner` |
| `spiral/roadrunner-worker`  | `roadrunner/worker`     |
| `spiral/goridge`            | `roadrunner/goridge`    |
| `spiral/roadrunner-http`    | `roadrunner/http`       |
| `spiral/roadrunner-metrics` | `roadrunner/metrics`    |
| `spiral/roadrunner-grpc`    | `roadrunner/grpc`       |
| `spiral/roadrunner-kv`      | `roadrunner/kv`         |
| `spiral/roadrunner-cli`     | `roadrunner/cli`        |

The bundle conflicts with `spiral/roadrunner` releases before 3.0, Composer reports an error if one of them is still
required.

`spiral/roadrunner-cli` 2.x downloads RoadRunner 2025, use `roadrunner/cli` 3.x to download RoadRunner 3:

```bash
composer remove --dev spiral/roadrunner-cli
composer require --dev roadrunner/cli
vendor/bin/rr get --location bin/
```

## RoadRunner configuration

If you did not change the RoadRunner config files, copy the new ones:

    cp vendor/baldinof/roadrunner-bundle/.rr.* .

Otherwise, update them manually:

- RoadRunner 3 runs the HTTP middleware from left to right. Put `gzip` before `static`, otherwise static files are not
  compressed anymore:

  ```yaml
  http:
    middleware: [ "gzip", "static" ]
  ```

- Log channels do not inherit the root logger settings. In `.rr.yaml`, set `mode: production` on the `http` and
  `metrics` channels to log them as JSON like the root logger:

  ```yaml
  logs:
    mode: production
    channels:
      http:
        mode: production
        level: debug
      metrics:
        mode: production
        level: error
  ```

- The RPC address must be a full DSN, like `tcp://127.0.0.1:6001`. RoadRunner 3 does not start with `tcp:127.0.0.1:6001`.
