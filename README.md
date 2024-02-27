> [!IMPORTANT]
> The SignalFx Tracing Library for PHP is deprecated as of February 21, 2024 and will reach End of Support on February 21 2025. Until then, only critical security fixes and bug fixes will be provided. After the date, the library will be archived and no longer maintained.
>
> New customers should use the OpenTelemetry PHP instrumentation, which offers similar capabilities. Existing customers should consider migrating to OpenTelemetry PHP instrumentation which offers similar capabilities. To learn how to migrate, see the [migration guide](https://docs.splunk.com/observability/en/gdi/get-data-in/application/php/php-migration-guide.html). To learn how to use OpenTelmetry PHP instrumentation refer to the [Splunk documentation](https://docs.splunk.com/observability/en/gdi/get-data-in/application/php/get-started.html). 

# SignalFx Tracing Library for PHP

[![CircleCI](https://circleci.com/gh/signalfx/signalfx-php-tracing/tree/main.svg?style=svg)](https://circleci.com/gh/signalfx/signalfx-php-tracing/tree/main)

This library provides an OpenTracing-compatible tracer and automatically
configurable instrumentations for many popular PHP libraries and frameworks.
It is a native extension that supports PHP versions 7.0+ running on the Zend Engine.

The SignalFx Tracing Library for PHP is in beta.

# Get started

For complete instructions on how to get started with the Splunk Distribution of OpenTelemetry JS, see [Instrument PHP applications for Splunk Observability Cloud](https://quickdraw.splunk.com/redirect/?product=Observability&location=php.application&version=current) in the official documentation.

## Supported Libraries and Frameworks

These are the PHP libraries and frameworks you can instrument. All _italicized_
libraries are in beta.

If your web framework isn't supported, the instrumentation creates a span for
any fielded request using the `$_SERVER` execution environment.

| Library | Versions supported |
|---------|--------------------|
| _CakePHP_ | 2.x |
| Curl | All supported PHP versions |
| _ElasticSearch_ | 1.x |
| _Eloquent_ | All supported Laravel versions |
| _Guzzle_ | 5.0+ |
| Laravel | 4.2, 5.0+ |
| _Lumen_ | 5.2-5.8 |
| _Memcached_ | All supported PHP versions |
| _MongoDB_ | 1.4 |
| _MySQLi_ | All supported PHP versions |
| PDO | All supported PHP versions |
| _Predis_ | 1.1 |
| _Slim_ | 3.x |
| _Symfony_ | 3.3, 3.4, 4.x, 5.x |
| _Zend_ | 1.12 |

## About

This library is a fork of the [DataDog Tracing PHP Client](https://github.com/DataDog/dd-trace-php)
that has been modifed to provide Zipkin v2 JSON formatting, B3 trace propagation
functionality, and properly annotated trace data for handling by
[SignalFx Microservices APM](https://docs.signalfx.com/en/latest/apm/apm-overview/index.html).
It is released under the terms of the BSD 3-Clause license. See the
[license file](./LICENSE) for more details.
