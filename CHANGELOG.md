# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
- [RoadRunner metrics](https://roadrunner.dev/docs/beep-beep-metrics) support.  Thank you [@vsychov](https://github.com/vsychov).

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
