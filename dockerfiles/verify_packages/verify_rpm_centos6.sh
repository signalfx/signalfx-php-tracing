#!/bin/sh

set -e

if [ -z "${PHP_INSTALL_DIR}" ]; then
    echo "Please set PHP_INSTALL_DIR"
    exit 1
fi

for phpVer in $(ls ${PHP_INSTALL_DIR}); do
    echo "Installing signalfx-php-tracing on PHP ${phpVer}..."
    switch-php $phpVer

    # Installing signalfx-php-tracing
    INSTALL_TYPE="${INSTALL_TYPE:-php_installer}"
    if [ "$INSTALL_TYPE" = "native_package" ]; then
        echo "Installing dd-trace-php using the OS-specific package installer"
        rpm -Uvh /build_src/build/packages/*.rpm
        php --ri=signalfx_tracing

        # Uninstall the tracer
        rpm -e signalfx_tracing
        rm -f /opt/signalfx_php_tracing/etc/signalfx-tracing.ini
    else
        echo "Installing signalfx-php-tracing using the new PHP installer"
        php /build_src/dd-library-php-setup.php --tracer-file /build_src/build/packages/*.tar.gz --php-bin all
    fi
done
