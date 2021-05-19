#!/bin/sh

set -e

if [ -z "${PHP_INSTALL_DIR}" ]; then
    echo "Please set PHP_INSTALL_DIR"
    exit 1
fi

for phpVer in $(ls ${PHP_INSTALL_DIR}); do
    echo "Installing signalfx-php-tracing on PHP ${phpVer}..."
    switch-php $phpVer
    rpm -Uvh /build_src/build/packages/*.rpm
    php --ri=signalfx_tracing

    # Uninstall the tracer
    rpm -e signalfx-tracing
    rm -f /opt/signalfx-php-tracing/etc/ddtrace.ini
done
