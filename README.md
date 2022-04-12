# SignalFx Tracing Library for PHP

[![CircleCI](https://circleci.com/gh/signalfx/signalfx-php-tracing/tree/master.svg?style=svg)](https://circleci.com/gh/signalfx/signalfx-php-tracing/tree/master)
[![Packagist Version](https://img.shields.io/packagist/v/signalfx/signalfx-tracing.svg)](https://packagist.org/packages/signalfx/signalfx-tracing)

This library provides an OpenTracing-compatible tracer and automatically
configurable instrumentations for many popular PHP libraries and frameworks.
It is a native extension that supports PHP versions 5.4+ running on the Zend Engine.

The SignalFx Tracing Library for PHP is in beta.

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
| _Lumen_ | 5.2+ |
| _Memcached_ | All supported PHP versions |
| _MongoDB_ | 1.4 |
| _MySQLi_ | All supported PHP versions |
| PDO | All supported PHP versions |
| _Predis_ | 1.1 |
| _Slim_ | 3.x |
| _Symfony_ | 3.3, 3.4, 4.x |
| _Zend_ | 1.12 |

## Configuration values

Configure the tracer and instrumentation with these environment variables:

| Environment variable | Description | Default value |
|----------------------|-------------|---------------|
| `SIGNALFX_SERVICE_NAME` | The name to identify the service in SignalFx. | `'unnamed-php-service'` |
| `SIGNALFX_ENDPOINT_URL` | The endpoint the tracer sends spans to. Send spans to a Smart Agent. | `'http://localhost:9080/v1/trace'` |
| `SIGNALFX_TRACING_ENABLED` | Whether to enable automatic tracer creation and instrumentation. | `true` |
| `SIGNALFX_TRACE_CLI_ENABLED` | Whether to enable automatic tracer creation and instrumentation for `cli` SAPI. | `false` |
| `SIGNALFX_TRACE_DEBUG` | Whether to enable debug-level logging. | `false` |
| `SIGNALFX_DISTRIBUTED_TRACING` | Whether to enable B3 context propagation for applicable client and server libraries. | `true` |
| `SIGNALFX_RECORDED_VALUE_MAX_LENGTH` | Maximum length an attribute value can have. Values longer than this are truncated. | `1200` |
| `SIGNALFX_CAPTURE_ENV_VARS` | Comma separated list of environment variables to attach to the root span. | ` ` |
| `SIGNALFX_CAPTURE_REQUEST_HEADERS` | Comma separated list of incoming request headers to turn into spans. For example `User-Agent` will be captured as `http.request.headers.user_agent`. | ` ` |

Because auto-instrumentation is applied during initialization, all configuration
environment variables MUST be set by launch time. Anything set via `putenv()`
may not be considered in configuration loading.

## Configure the SignalFx Tracing Library for PHP

Download the tracing library and install the PHP extension with your system's
package manager. After you install the PHP extension, your application sends
trace data to the endpoint URL you specify.

1. Download the [latest release](https://github.com/signalfx/signalfx-php-tracing/releases/latest)
    of the SignalFx Tracing Library for PHP.
2. Install with PHP extension with your system's package manager:
    ```bash
    # Using dpkg:
    $ dpkg -i signalfx-tracing.deb

    # Using rpm:
    $ rpm -ivh signalfx-tracing.rpm

    # Using apk:
    $ apk add signalfx-tracing.apk --allow-untrusted

    # Directly from the release bundle:
    $ tar -xf signalfx-tracing.tar.gz -C / && /opt/signalfx-php-tracing/bin/post-install.sh
    ```

### Use OpenTracing for custom instrumentation

The `signalfx_tracing` extension provides and configures an
[OpenTracing-compatible tracer](https://github.com/opentracing/opentracing-php)
you can use for custom instrumentation:

```php
use SignalFx\GlobalTracer; // Suggested namespace over OpenTracing for GlobalTracer
use OpenTracing\Tags;

function myApplicationLogic($indicator) {
  $tracer = GlobalTracer::get(); //  Will provide the tracer instance used by provided instrumentations
  $span = $tracer->startActiveSpan('myApplicationLogic')->getSpan();
  $span->setTag('indicator', $indicator);

  try {
    $widget = myAdditionalApplicationLogic($indicator);
    $span->setTag('widget', $widget);
    return $widget;
  } catch (Exception $e) {
    $span->setTag(Tags\ERROR, true);
    throw $e;
  } finally {
    $span->finish();
  }
}
```

The OpenTracing-compatible tracer provides a
[ beta implementation](https://github.com/opentracing/opentracing-php/blob/1.0.0-beta6/src/OpenTracing/ScopeManager.php)
of the [Scope Manager](https://github.com/opentracing/specification/blob/master/rfc/scope_manager.md).
If you aren't using the beta `1.0.x` `opentracing/opentracing` release, the
registered tracer provided by `SignalFx\GlobalTracer::get()` supports the `0.3.x`
version API as well. As there have been breaking changes to the
`OpenTracing\GlobalTracer`, use the `SignalFx\GlobalTracer` proxy's `get()`
method for accessing the tracer instance.

### Tracing CLI sessions

If you want to trace `cli` SAPI functionality, you have to manually enable
it. When you enable `cli` tracing, the instrumentation automatically creates a
root span to denote the lifetime of your `cli` session. This SAPI is disabled
by default to avoid undesired traced system activity.

```bash
$ export SIGNALFX_TRACE_CLI_ENABLED=true
$ php artisan migrate:fresh
$ php myTracedCliScript.php
```

## Advanced Usage  

The Signalfx-Tracing library for PHP wraps the
[spl_autoload_register](https://www.php.net/manual/en/function.spl-autoload-register.php)
function to allow the automatic tracing of supported functions without user
action. In cases where autoloader classes aren't used, a manual invocation
to create a tracer and invoke auto-instrumentation is required as the first
action for your application:

```php
// Note: this must occur before any other library is imported and used!
use SignalFx\Tracing;

$tracer = Tracing::autoInstrument();
// or if you only prefer a tracer instance without enabling auto-instrumentation:
$tracer = Tracing::createTracer();
```

## About

This library is a fork of the [DataDog Tracing PHP Client](https://github.com/DataDog/dd-trace-php)
that has been modifed to provide Zipkin v2 JSON formatting, B3 trace propagation
functionality, and properly annotated trace data for handling by
[SignalFx Microservices APM](https://docs.signalfx.com/en/latest/apm/apm-overview/index.html).
It is released under the terms of the BSD 3-Clause license. See the
[license file](./LICENSE) for more details.
