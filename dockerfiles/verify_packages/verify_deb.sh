#!/bin/sh
set -xe
dpkg -i build/packages/*.deb
php -m | grep signalfx_tracing
php -r 'echo phpversion("signalfx_tracing") . PHP_EOL;'
