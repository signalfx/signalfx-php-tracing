# SignalFx PHP Tracing

[![CircleCI](https://circleci.com/gh/signalfx/signalfx-php-tracing/tree/master.svg?style=svg)](https://circleci.com/gh/signalfx/signalfx-php-tracing/tree/master)
[![OpenTracing Badge](https://img.shields.io/badge/OpenTracing-enabled-blue.svg)](http://opentracing.io)
[![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%205.6-8892BF.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-BSD%203--Clause-blue.svg)](LICENSE)
[![Packagist Version](https://img.shields.io/packagist/v/signalfx/signalfx-tracing.svg)](https://packagist.org/packages/signalfx/signalfx-tracing)

PHP Tracer

This is forked from the [DataDog PHP Tracer](https://github.com/DataDog/dd-trace-php).

> **This is Beta software.** We do not recommend using it in production yet.

### Installing the extension

You can install the extension from a package download. First [download the appropriate package](https://github.com/signalfx/signalfx-php-tracing/releases) from the releases page. Then install the package with one of the commands below.

```bash
# using RPM package (RHEL/Centos 6+, Fedora 20+)
$ rpm -ivh signalfx-php-tracer.rpm

# using DEB package (Debian Jessie+ , Ubuntu 14.04+)
$ dpkg -i signalfx-php-tracer.deb

# using APK package (Alpine)
$ apk add signalfx-php-tracer.apk --allow-untrusted

# using tar.gz archive (Other distributions using libc6)
$ tar -xf signalfx-php-tracer.tar.gz -C /
  /opt/signalfx-php/bin/post-install.sh
```

## Beta support  for PECL

Preliminary beta support for PECL installation is required [PECL](https://pecl.php.net/package/signalfx_tracing).

```bash
$ sudo pecl install signalfx_tracing-beta
```

### Instrumentation

Once the `ddtrace` extension is installed, you should be already good to go. There are a few framework instrumentations available out of the box.

* [Laravel 4 & 5 instrumentation](docs/getting_started.md#laravel-integration)
* [Lumen 5 instrumentation](docs/getting_started.md#lumen-integration)
* [Symfony 3 & 4 instrumentation](docs/getting_started.md#symfony-integration)
* [Zend Framework 1 instrumentation](docs/getting_started.md#zend-framework-1-integration)

### Manual instrumentation

If you are using another framework or CMS that is not listed above, you can manually instrument the tracer by wrapping your application code with a root span from the tracer.

```php
use DDTrace\Tracer;
use DDTrace\GlobalTracer;
use DDTrace\Integrations\IntegrationsLoader;

// Enable the built-in integrations
IntegrationsLoader::load();

// Start a root span
$span = $tracer->startSpan('my_base_trace');

// Run your application here
// $myApplication->run();

// Close the root span after the application code has finished
$span->finish();
```

### Advanced configuration

> **Note:** As this tracer is modeled off of [OpenTracing](https://opentracing.io/), it is recommended to read the [OpenTracing specification](https://github.com/opentracing/specification/blob/master/specification.md) to familiarize yourself with distributed tracing concepts. The ddtrace package also provides an [OpenTracing-compatible tracer](docs/open_tracing.md).

The transport can be customized by the config parameters:

```php
use DDTrace\Encoders\Json;
use DDTrace\Transport\Http;

$transport = new Http(
    new Json(),
    $logger,
    [
        'endpoint' => 'http://localhost:8126/v0.3/traces', // Agent endpoint
    ]
);
```

The tracer can be customized by the config settings:

```php
use DDTrace\Tracer;
use DDTrace\Format;

// Config for tracer
$config = [
    'service_name' => 'my_service', // The name of the service.
    'enabled' => true, // If tracer is not enabled, all spans will be created as noop.
    'global_tags' => ['host' => 'hostname'], // Set of tags being added to every span.
];

$tracer = new Tracer(
    $transport,
    [ Format::TEXT_MAP => $textMap ],
    $config
);
```

### OpenTracing

The `DDTrace` package provides an [OpenTracing-compatible tracer](docs/open_tracing.md).

## Contributing

Before contributing to this open source project, read our [CONTRIBUTING.md](CONTRIBUTING.md).

## Releasing

See [RELEASING](RELEASING.md) for more information on releasing new versions.

