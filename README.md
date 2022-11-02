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
| _Lumen_ | 5.2-5.8 |
| _Memcached_ | All supported PHP versions |
| _MongoDB_ | 1.4 |
| _MySQLi_ | All supported PHP versions |
| PDO | All supported PHP versions |
| _Predis_ | 1.1 |
| _Slim_ | 3.x |
| _Symfony_ | 3.3, 3.4, 4.x, 5.x |
| _Zend_ | 1.12 |

## Installation

1. Download the setup script of the latest release.

```bash
curl -LO https://github.com/signalfx/signalfx-php-tracing/releases/latest/signalfx-php-tracing/signalfx-setup.php
```

Optionally, replace `latest` with the specific version you want to use.

2. Install by running the setup script.

```bash
php signalfx-setup.php --php-bin=all
```

This downloads the additional files necessary for installing the extension
and tracing library from GitHub and installs the extension.

The `--php-bin=all` option installs the extension to all PHP configurations
that can be found on the system. Alternatively, if the `--php-bin` option is
omitted, you can interactively select to which of the detected PHP
installations the extension should be installed to. You can also provide a
path to a specific binary as the value of `--php-bin` to install only for
that specific one.

It is also possible to download all the files from the GitHub release in
advance and perform an offline installation by providing the variable
`--file-dir` with the path to the directory which contains these files. For
example if all the files are in the current directory:

```bash
php signalfx-setup.php --php-bin=all --file-dir=.
```

## Configuration

Configuration can be provided either by passing environment variables to the
PHP process, or setting configuration options in the `.ini` file of the
extension.

### Configuration options

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
| `SIGNALFX_ACCESS_TOKEN` | Access token - only needed when [sending data directly](https://docs.splunk.com/Observability/apm/apm-spans-traces/span-formats.html#span-formats-compatible-with-the-ingest-endpoint). Not necessary when using OpenTelemetry Collector or SignalFx Smart Agent. | ` ` |

Because auto-instrumentation is applied during initialization, all configuration
environment variables MUST be set by launch time. Anything set via `putenv()`
may not be considered in configuration loading.

### Setting environment variables for Apache / httpd

Apache usually runs under a different user, environment variables for Apache need to be set in the configuration file (e.g. `/etc/apache2/apache2.conf`) via [`SetEnv`](https://httpd.apache.org/docs/2.4/mod/mod_env.html):

```
SetEnv SIGNALFX_SERVICE_NAME "my-service"
SetEnv SIGNALFX_ENDPOINT_URL "http://collector:9411/api/v2/traces"
```

### Setting configuration via INI file

If no environment variable is provided for a specific configuration option,
configuration is taken from the INI file of the extension. Configuration
option names there are derived from the environment variable name, with
INI name prefix `signalfx.trace.` for variables starting with
`SIGNALFX_TRACE_` and prefix `signalfx.` for other veriables starting with
`SIGNALFX_`. For example `signalfx.service_name` and
`signalfx.trace.cli_enabled`.

The `signalfx-setup.php` script downloaded in the installation step can be used
to set INI file options without having to manually locate the files. For
example:

```bash
php signalfx-setup.php --update-config --signalfx.endpoint_url=http://172.17.0.1:9080/v1/trace
```

This is useful for options which can be the same for all PHP services running
in the system. A common case where this might not be suitable is for providing
`SIGNALFX_SERVICE_NAME` when there are multiple Apache VirtualHost
configurations where the service name should be different.

## Sending traces to Splunk

There are three different methods available to get the traces from this tracing
library to Splunk APM. Before you configure the endpoint, make sure that the
service name is configured either by:

```bash
SIGNALFX_SERVICE_NAME=my-php-service
# or
php signalfx-setup.php --update-config --signalfx.service_name=my-php-service
```

For more information about what types of endpoints Splunk APM has and which
types of data they accept, see [Compatible span formats for Splunk APM](https://docs.splunk.com/Observability/apm/apm-spans-traces/span-formats.html#span-formats-compatible-with-the-ingest-endpoint).

### Via Splunk OpenTelemetry Collector

This is the recommended way. With this option, the tracing library is configured
to send the traces to the [Splunk OpenTelemetry Collector](https://github.com/signalfx/splunk-otel-collector),
which will then send the data to Splunk ingest endpoints. In this case, the
access token only has to be configured in the collector, and the tracing library
sends the data to the collector without any authentication.

To configure the tracing library for this option, only the endpoint URL has to
be changed. In case the collector is configured as shown here, the value would
be `http://<collector_host>:9080/v1/trace`, where `<collector_host>` is the
hostname or IP address of the collector from the perspective of the instance
running PHP.

```bash
SIGNALFX_ENDPOINT_URL=http://<collector_host>:9080/v1/trace
# or
php signalfx-setup.php --update-config --signalfx.endpoint_url=http://<collector_host>:9080/v1/trace
```

The following is an example configuration for Splunk OpenTelemetry Collector
for forwarding the traces from PHP tracing library agent to Splunk APM:

```
receivers:
  smartagent/signalfx-forwarder:
    type: signalfx-forwarder
    listenAddress: 0.0.0.0:9080
  
  exporters:
    sapm:
      access_token: <token>
      endpoint: https://ingest.<realm>.signalfx.com/v2/trace
  
  service:
    extensions: []
    pipelines:
      traces:
        receivers: [smartagent/signalfx-forwarder]
        exporters: [sapm]
```

The `<token>` is a Splunk SignalFX access token with `INGEST` authorization
scope. Tokens can be viewed/edited/added under "Settings -> Access Tokens"
on Splunk APM website. If unsure about what the value of `<realm>` should
be, this can be checked under "Settings -> Profile -> Organizations".

If traces do not appear in Splunk APM, you can add debug logging to make
sure they reach the collector. To do this, change `exporters: [sapm]` to
`exporters: [logging, sapm]` and add the following to `exporters:` block:

```
    logging:
      loglevel: debug
      sampling_initial: 0
      sampling_thereafter: 1
```

If the spans are logged, but not displayed by Splunk APM, the collector
configuration should be double-checked. If they are not logged, the tracing
library configuration should be double checked, especially to make sure the
endpoint that has been specified is reachable from that instance (can check
with cURL for example).

### Sent directly to Splunk ingest endpoint

The tracing library can also send traces directly to Splunk APM without the
use of either Splunk OpenTelemetry Collector nor SignalFx Smart Agent. In this
case, both the endpoint URL and access token has to be configured.

```bash
SIGNALFX_ENDPOINT_URL=https://ingest.<realm>.signalfx.com/v2/trace/signalfxv1
SIGNALFX_ACCESS_TOKEN=<token>
# or
php signalfx-setup.php --update-config --signalfx.endpoint_url=https://ingest.<realm>.signalfx.com/v2/trace/signalfxv1
php signalfx-setup.php --update-config --signalfx.access_token=<token>
```

See previous section about Splunk OpenTelemetry Collector for more information
about `<realm>` and `<token>`.

### Via SignalFx Smart Agent

This option is not recommended. While Splunk APM still supports this, it is
scheduled to be deprecated. New deployments should not use this option.

Configuration of the tracing library for Smart Agent is basically the same as
for Splunk OpenTelemetry Collector, as just the endpoint URL has to be set. For
example:

```bash
SIGNALFX_ENDPOINT_URL=http://<smartagent_host>:9080/v1/trace
# or
php signalfx-setup.php --update-config --signalfx.endpoint_url=http://<smartagent_host>:9080/v1/trace
```

## Use OpenTracing for custom instrumentation

The `signalfx_tracing` extension provides and configures an
[OpenTracing-compatible tracer](https://github.com/opentracing/opentracing-php)
you can use for custom instrumentation:

```php
use SignalFx\GlobalTracer; // Suggested namespace over OpenTracing for GlobalTracer

function myApplicationLogic($indicator) {
  $tracer = GlobalTracer::get(); //  Will provide the tracer instance used by provided instrumentations
  $span = $tracer->startActiveSpan('myApplicationLogic')->getSpan();
  $span->setTag('indicator', $indicator);

  try {
    $widget = myAdditionalApplicationLogic($indicator);
    $span->setTag('widget', $widget);
    return $widget;
  } catch (Exception $e) {
    $span->setTag('error', true);
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
