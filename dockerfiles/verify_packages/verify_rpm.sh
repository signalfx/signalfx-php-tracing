#!/bin/sh

rpm -Uvh /build_src/build/packages/*.rpm

php -m | grep signalfx_tracing
