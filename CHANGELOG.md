# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [3.6.0] - Unreleased

### Changed

- The RoadRunner PHP packages were renamed from `spiral/*` to `roadrunner/*`: the bundle now requires `roadrunner/roadrunner`, `roadrunner/worker`, `roadrunner/goridge` and `roadrunner/http`, and the optional integrations use `roadrunner/metrics`, `roadrunner/grpc` and `roadrunner/kv`. PHP namespaces are unchanged. See [UPGRADE-3-6.md](./UPGRADE-3-6.md)
- PHP 8.2 and RoadRunner 3 are now required, as the renamed packages require them
- The default `.rr.yaml` and `.rr.dev.yaml` put the `gzip` middleware before `static`, because RoadRunner 3 runs middleware from left to right
- The default `.rr.yaml` logs the `http` and `metrics` channels as JSON, like the root logger
- Conflict with `spiral/roadrunner` releases before 3.0 (including RoadRunner 2023, 2024 and 2025)

### Fixed

- `TypeError` when a request has a numeric header name (e.g. `123`), which `roadrunner/http` >= 4.2 passes as an integer key

### Removed

- Support of PHP 8.1
- Support of RoadRunner 2023, 2024 and 2025

## [3.5.0] - 2026-10-26

### Added

- Support of RoadRunner 3 and PHP 8.5. Thank you [@Teko012](https://github.com/Teko012), see [#190](https://github.com/Baldinof/roadrunner-bundle/pull/190)
- Support of the RoadRunner `http.raw_body` option: `$request->getContent()` returns the original body, and `application/x-www-form-urlencoded` bodies are parsed by the bundle. Thank you [@dt-thomas-durand](https://github.com/dt-thomas-durand), see [#191](https://github.com/Baldinof/roadrunner-bundle/pull/191)

### Fixed

- Compatibility with `sentry/sentry-symfony` 5.14. Thank you [@bosvaldas](https://github.com/bosvaldas), see [#189](https://github.com/Baldinof/roadrunner-bundle/pull/189)

## [3.4.0] - 2026-05-27

### Added

- Support of streamed and file responses. Thank you [@Kenny1911](https://github.com/Kenny1911), see [#184](https://github.com/Baldinof/roadrunner-bundle/pull/184)

## [3.3.2] - 2026-01-09

### Added

- Xdebug integration, allowing Xdebug in trigger mode in RoadRunner workers. Thank you [@dt-thomas-durand](https://github.com/dt-thomas-durand), see [#183](https://github.com/Baldinof/roadrunner-bundle/pull/183)

## [3.3.1] - 2025-12-08

### Added

- Support of Symfony 8. Thank you [@dt-thomas-durand](https://github.com/dt-thomas-durand), see [#182](https://github.com/Baldinof/roadrunner-bundle/pull/182)

## [3.3.0] - 2025-10-20

The `3.2.4` tag points to the same commit as `3.3.0`.

### Added

- `summary` metric type. Thank you [@crazy-weasel](https://github.com/crazy-weasel), see [#167](https://github.com/Baldinof/roadrunner-bundle/pull/167)
- `APP_RUNTIME_MODE` is set by the runtime, so the web profiler log panel works. Thank you [@crazy-weasel](https://github.com/crazy-weasel), see [#164](https://github.com/Baldinof/roadrunner-bundle/pull/164)
- `memory` kernel reboot strategy, configured with `kernel_reboot.memory_threshold_mb`. Thank you [@AtCliffUnderline](https://github.com/AtCliffUnderline), see [#177](https://github.com/Baldinof/roadrunner-bundle/pull/177)

## [3.2.3] - 2025-05-12

### Added

- Support of RoadRunner 2025. Thank you [@okorneliuk](https://github.com/okorneliuk), see [#168](https://github.com/Baldinof/roadrunner-bundle/pull/168)

### Fixed

- Race condition when a kernel reboot is triggered. Thank you [@mikael-titinovskii](https://github.com/mikael-titinovskii), see [#169](https://github.com/Baldinof/roadrunner-bundle/pull/169)

## [3.2.2] - 2025-03-17

### Added

- Support of Symfony 5.4. Thank you [@Nyholm](https://github.com/Nyholm), see [#163](https://github.com/Baldinof/roadrunner-bundle/pull/163)

### Changed

- `tools/` and `.php-cs-fixer.dist.php` are no longer included in the package archive. Thank you [@dmitryuk](https://github.com/dmitryuk), see [#154](https://github.com/Baldinof/roadrunner-bundle/pull/154)

## [3.2.1] - 2025-03-17

### Changed

- More detailed error message when the RoadRunner request URI cannot be parsed. Thank you [@dmitryuk](https://github.com/dmitryuk), see [#155](https://github.com/Baldinof/roadrunner-bundle/pull/155)

## [3.2.0] - 2025-02-19

### Added

- gRPC interceptors, and `kernel_reboot.strategy` now applies to gRPC workers. Thank you [@dt-thomas-durand](https://github.com/dt-thomas-durand), see [#152](https://github.com/Baldinof/roadrunner-bundle/pull/152)

## [3.1.3] - 2024-12-06

### Fixed

- Follow the `trusted_headers` and `trusted_proxies` changes of Symfony 7.2. Thank you [@okorneliuk](https://github.com/okorneliuk), see [#149](https://github.com/Baldinof/roadrunner-bundle/pull/149)

## [3.1.2] - 2024-06-21

### Added

- Support of RoadRunner 2024. Thank you [@okorneliuk](https://github.com/okorneliuk), see [#142](https://github.com/Baldinof/roadrunner-bundle/pull/142)

### Changed

- `MiddlewareStack` implements `RequestHandlerInterface`, see [#145](https://github.com/Baldinof/roadrunner-bundle/pull/145)

## [3.1.1] - 2024-04-29

### Added

- Support of `sentry/sentry-symfony` 5. Thank you [@WaylandAce](https://github.com/WaylandAce), see [#141](https://github.com/Baldinof/roadrunner-bundle/pull/141)

## [3.1.0] - 2024-03-19

### Added

- KV cache adapters. Thank you [@StanJansen](https://github.com/StanJansen), see [#137](https://github.com/Baldinof/roadrunner-bundle/pull/137)

## [3.0.3] - 2023-12-05

### Fixed

- Symfony 7 compatibility: use `MAIN_REQUEST` instead of `MASTER_REQUEST`. Thank you [@FluffyDiscord](https://github.com/FluffyDiscord), see [#133](https://github.com/Baldinof/roadrunner-bundle/pull/133)

## [3.0.2] - 2023-11-16

### Fixed

- Allow installation with Symfony 6.0+ again

## [3.0.1] - 2023-11-16

### Added

- Support of Symfony 7. Thank you [@endroid](https://github.com/endroid), see [#128](https://github.com/Baldinof/roadrunner-bundle/pull/128)

## [3.0.0] - 2023-05-17
### Added
- Support of RoadRunner 2023

### Removed

- Deprecated code
- Session integration as Symfony session now natively works
- Support of Symfony 5, PHP version < 8.1
- `WorkerCommand`, usage of Symfony Runtime is now mandatory


## [2.2.3] - 2022-05-29

### Fixed

- Stops using deprecated StreamedResponseListener in Symfony >=6.1 see [#85](https://github.com/Baldinof/roadrunner-bundle/pull/85)

## [2.2.2] - 2022-05-25

### Fixed

- Fix deprecations. Thank you [@sabrimarz](https://github.com/sabrimarz), see [#83](https://github.com/Baldinof/roadrunner-bundle/pull/83)

## [2.2.1] - 2022-03-09

### Fixed

- Provided updated RoadRunner config files to new project installed via Symfony Flex

## [2.2.0] - 2022-02-28

### Changed

- Stops loading `NativeSessionMiddleware` if using Symfony >= 5.4 because it is well handled in the framework
- Override `$_SERVER` when receiving a new RR request

### Added

- Supports for gRPC. Thank you [@StephenBeirlaen](https://github.com/StephenBeirlaen), see [#62](https://github.com/Baldinof/roadrunner-bundle/pull/62)
- Supports for `REMOTE_ADDR` in `framework.trusted_proxies` configuration. See [#71](https://github.com/Baldinof/roadrunner-bundle/pull/71)


## [2.1.4] - 2022-01-16

### Fixed

- Ability to use `always` as reboot strategy. Thank you [@lnahiro](https://github.com/lnahiro), see [#68](https://github.com/Baldinof/roadrunner-bundle/pull/68)

## [2.1.2] - 2021-12-1

### Added

- Support Symfony 6.x. Thank you [@bzajacc](https://github.com/bzajacc), see [#59](https://github.com/Baldinof/roadrunner-bundle/pull/59)

## [2.1.1] - 2021-10-21

### Fixed
- Compatibility with Doctrine DBAL 3.x (stop using `Connection::ping()` method)

## [2.1.0] - 2021-06-02

### Added
- Support for Symfony Runtime component
- Forward compatibility with Symfony 6 sessions. Thank you [@Punk-UnDeaD](https://github.com/Punk-UnDeaD), see [#45](https://github.com/Baldinof/roadrunner-bundle/pull/45/files)

## [2.0.2] - 2021-05-25

### Fixed
- Compatibility with Symfony 4.4, see [#42](https://github.com/Baldinof/roadrunner-bundle/pull/42)

## [2.0.1] - 2021-04-16

### Fixed
- Uploaded files was not valid in Symfony, see [#38](https://github.com/Baldinof/roadrunner-bundle/pull/38)

## [2.0.0] - 2021-04-06

### Added
- Support for RoadRunner 2.x
- Metrics declaration via Symfony configuration
- Automatic detection of relay address (thanks to RR v2)

### Changed
- Moved all integration code to the `Integration` namespace
- No more PSR / Symfony conversion, the internal worker uses Symfony requests and responses directly

### Removed
- PSR Middleware supports
- Deprecated classes and configuration options
## [1.5.3] - 2021-03-30

### Fixed

- Bad Sentry integration DIC configuration, see [#34](https://github.com/Baldinof/roadrunner-bundle/issues/34)

## [1.5.2] - 2021-03-04

### Fixed

- Usage of deprecated autowiring alias, see [#28](https://github.com/Baldinof/roadrunner-bundle/issues/28) 

## [1.5.1] - 2021-02-11

### Fixed

- Bad dependency injection configuration. Thank you [@aldump](https://github.com/aldump) see https://github.com/Baldinof/roadrunner-bundle/pull/27

## [1.5.0] - 2021-02-10
### Added

- New event `WorkerKernelRebootedEvent`. Thank you [@fitzel](https://github.com/fitzel). See https://github.com/Baldinof/roadrunner-bundle/pull/25

## [1.4.0] - 2021-01-28
### Added
- PHP 8 support. Thank you [@hugochinchilla](https://github.com/hugochinchilla). See https://github.com/Baldinof/roadrunner-bundle/pull/23

## [1.3.3] - 2020-09-25
### Fixed
- Fix deprecation warning on "Symfony\Component\Config\Definition\Builder\NodeDefinition::setDeprecated()". Thank you [@hugochinchilla](https://github.com/hugochinchilla). See https://github.com/Baldinof/roadrunner-bundle/pull/18

## [1.3.2] - 2020-09-18
### Fixed
- Clear Sentry scope between requests. Thank you [@hugochinchilla](https://github.com/hugochinchilla). See https://github.com/Baldinof/roadrunner-bundle/pull/17

## [1.3.1] - 2020-09-05
### Fixed
- Blackfire profiling when using diactoros psr7 implementation
- Bad dependency injection configuration when installing `sensio/framework-extra-bundle` without `nyholm/psr7`.

## [1.3.0] - 2020-09-02
### Added
- Restart the kernel on exceptions
- Configuration option to not reboot the kernel on selected exceptions
- Fallback to [php-http/discovery](https://github.com/php-http/discovery) if no PSR17 factories are found in the dependency injection container
- Doctrine ORM integration (handle reconnections). Thank you [@vsychov](https://github.com/vsychov).

### Fixed
- Blackfire profiling when using diactoros psr7 implementation
- Issue when installing `sensio/framework-extra-bundle` without `nyholm/psr7`

### Changed
- Class `Baldinof\RoadRunnerBundle\Worker\Worker` is now internal

### Deprecated
- Configuration option `should_reboot_kernel` is replaced by kernel reboot strategies:
  ```yaml
    kernel_reboot:
      strategy: always # equivalent to `should_reboot_kernel: true`

    kernel_reboot:
      strategy: on_exception # equivalent to `should_reboot_kernel: false`
      allowed_exceptions:
        - Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
  ```
- Class `Baldinof\RoadRunnerBundle\Worker\Configuration` is now deprecated in favor of kernel reboot strategies

## [1.2.2] - 2020-05-14
### Fixed
- Disable default StreamedResponseListener to prevent early sending of response. See https://github.com/Baldinof/roadrunner-bundle/issues/9

## [1.2.1] - 2020-04-24
### Added
- Compatibility with `symfony/psr-http-message-bridge` 2.*

## [1.2.0] - 2020-04-24
### Added
- DEV mode config file (`.rr.dev.yaml`), with auto-reloading on php files changes.

### Fixed
- `MetricsFactory` now returns `NullMetrics` if not in RoadRunner worker process.  Thank you [@vsychov](https://github.com/vsychov).
- Kernel is now properly resetted on each request, see https://github.com/Baldinof/roadrunner-bundle/pull/5

## [1.1.0] - 2020-04-02
### Added
- [RoadRunner metrics](https://docs.roadrunner.dev/docs/logging-and-observability/metrics) support.  Thank you [@vsychov](https://github.com/vsychov).

## [1.0.2] - 2020-03-26
### Fixed
- Handle 'Authorization: Basic' header and populate PHP_AUTH_USER/PHP_AUTH_PW server variables

## [1.0.1] - 2020-02-24
### Fixed
- Close the session, even if the main handler throws

## [1.0.0] - 2020-02-12
### Added
- Middlewares support
- Sentry integration
- Doctrine MongoDB bundle integration
- Blackfire integration
- Symfony Session support
