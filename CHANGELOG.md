# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
