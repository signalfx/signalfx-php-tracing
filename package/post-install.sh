#!/bin/bash --login

# WARNING: apk packaging via fpm (https://github.com/jordansissel/fpm) can fail because the tar file generated is not
# valid.
# This has been tested both on our custom image (fpm 1.11.0, ruby 2.5, tar 1.29), and on a ruby 3.0-bullseye
# (fpm 1.14.0, ruby 3.0, tar 1.34).
# Packing fails if this script size in bytes is between <unknown> and 7680. This value has been found empirically and
# measured via `wc -c`.
# The issue seems related to this very specific file size only, as generating a package that includes only this script
# (no binaries and sources) will result in the very same error.
# Issue reported to the fpm project (https://github.com/jordansissel/fpm/issues/1866), see there for more details and
# a reproduction case.

EXTENSION_BASE_DIR=/opt/datadog-php
EXTENSION_DIR=${EXTENSION_BASE_DIR}/extensions
EXTENSION_CFG_DIR=${EXTENSION_BASE_DIR}/etc
EXTENSION_LOGS_DIR=${EXTENSION_BASE_DIR}/log
EXTENSION_SRC_DIR=${EXTENSION_BASE_DIR}/dd-trace-sources
EXTENSION_AUTO_INSTRUMENTATION_FILE=${EXTENSION_SRC_DIR}/bridge/dd_wrap_autoloader.php
INI_FILE_NAME='ddtrace.ini'
CUSTOM_INI_FILE_NAME='ddtrace-custom.ini'

PATH="${PATH}:/usr/local/bin"

# We attempt in this order the following binary names:
#    1. php
#    2. php8 (some alpine versions install php 8.x from main repo to this binary)
#    3. php7 (some alpine versions install php 7.x from main repo to this binary)
#    4. php5 (some alpine versions install php 5.x from main repo to this binary)
if [ -z "$DD_TRACE_PHP_BIN" ]; then
    DD_TRACE_PHP_BIN=$(command -v php || true)
fi
if [ -z "$DD_TRACE_PHP_BIN" ]; then
    DD_TRACE_PHP_BIN=$(command -v php8 || true)
fi
if [ -z "$DD_TRACE_PHP_BIN" ]; then
    DD_TRACE_PHP_BIN=$(command -v php7 || true)
fi

function invoke_php() {
    # In case of .apk post-install hooks the script has no access the set of exported ENVS.
    # When 1) users import large ini files (e.g. browsercap.ini) and 2) they set memory_limit using an env
    # variable we would fail because of the memory limit reached. In order to avoid this we set a sane memory limit
    # that should cause no problem in any machine when our tracer is installed.
    # alias invoke_php="$DD_TRACE_PHP_BIN -d memory_limit=128M"
    $DD_TRACE_PHP_BIN -d memory_limit=128M "$*"
}

function println() {
    echo -e '###' "$@"
}

function append_configuration_to_file() {
    tee -a "$@" <<EOF
; Autogenerated by the Datadog post-install.sh script

${INI_FILE_CONTENTS}

; end of autogenerated part
EOF
}

function create_configuration_file() {
    tee "$@" <<EOF
; ***** DO NOT EDIT THIS FILE *****
; To overwrite the INI settings for this extension, edit
; the INI file in this directory called "${CUSTOM_INI_FILE_NAME}"

${INI_FILE_CONTENTS}
EOF
}

function generate_configuration_files() {
    INI_FILE_PATH="${EXTENSION_CFG_DIR}/$INI_FILE_NAME"
    CUSTOM_INI_FILE_PATH="${EXTENSION_CFG_DIR}/$CUSTOM_INI_FILE_NAME"

    println "Creating ${INI_FILE_NAME}"
    println "\n"

    create_configuration_file "${INI_FILE_PATH}"

    println "${INI_FILE_NAME} created"
    println

    if [[ ! -f $CUSTOM_INI_FILE_PATH ]]; then
        touch "${CUSTOM_INI_FILE_PATH}"
        println "Created empty ${CUSTOM_INI_FILE_PATH}"
        println
    fi
}

function link_ini_file() {
    test -f "${2}" && rm "${2}"
    ln -s "${1}" "${2}"
}

function install_conf_d_files() {
    generate_configuration_files

    println "Linking ${INI_FILE_NAME} for supported SAPI's"
    println "\n"

    # Detect installed SAPI's
    SAPI_DIR=${PHP_CFG_DIR%/*/conf.d}/
    SAPI_CONFIG_DIRS=()
    if [[ "$PHP_CFG_DIR" != "$SAPI_DIR" ]]; then
        # Detect CLI
        if [[ -d "${SAPI_DIR}cli/conf.d" ]]; then
            SAPI_CONFIG_DIRS+=("${SAPI_DIR}cli/conf.d")
        fi
        # Detect FPM
        if [[ -d "${SAPI_DIR}fpm/conf.d" ]]; then
            SAPI_CONFIG_DIRS+=("${SAPI_DIR}fpm/conf.d")
        fi
        # Detect Apache
        if [[ -d "${SAPI_DIR}apache2/conf.d" ]]; then
            SAPI_CONFIG_DIRS+=("${SAPI_DIR}apache2/conf.d")
        fi
    fi

    if [ ${#SAPI_CONFIG_DIRS[@]} -eq 0 ]; then
        SAPI_CONFIG_DIRS+=("$PHP_CFG_DIR")
    fi

    for SAPI_CFG_DIR in "${SAPI_CONFIG_DIRS[@]}"
    do
        println "Found SAPI config directory: ${SAPI_CFG_DIR}"

        PHP_DDTRACE_INI="${SAPI_CFG_DIR}/98-${INI_FILE_NAME}"
        println "Linking ${INI_FILE_NAME} to ${PHP_DDTRACE_INI}"
        link_ini_file "${INI_FILE_PATH}" "${PHP_DDTRACE_INI}"

        CUSTOM_PHP_DDTRACE_INI="${SAPI_CFG_DIR}/99-${CUSTOM_INI_FILE_NAME}"
        println "Linking ${CUSTOM_INI_FILE_NAME} to ${CUSTOM_PHP_DDTRACE_INI}"
        link_ini_file "${CUSTOM_INI_FILE_PATH}" "${CUSTOM_PHP_DDTRACE_INI}"
        println
    done
}

function fail_print_and_exit() {
    println 'Failed enabling ddtrace extension'
    println
    println "The extension has been installed but couldn't be enabled"
    println "Try adding the extension manually to your PHP - php.ini - configuration file"
    println "e.g. by adding following line: "
    println
    println "    extension=${EXTENSION_FILE_PATH}"
    println
    println "Note that your PHP API version must match the extension's API version"
    println "PHP API version can be found using following command"
    println
    println "    $DD_TRACE_PHP_BIN -i | grep 'PHP API'"
    println

    exit 0 # exit - but do not fail the installation
}

function verify_installation() {
    invoke_php -m | grep -e "^signalfx_tracing$" && \
        println "Extension enabled successfully" || \
        fail_print_and_exit
}

function verify_required_ext() {
    ext_name="$1"
    printf "Checking for extension: ${ext_name}\n"
    output=$(invoke_php -m | grep -e "^${ext_name}$" || true)

    if [ "${output}" == "${ext_name}" ]; then
        printf "Extension '${ext_name}' was found.\n"
    else
        printf "Error: PHP extension '${ext_name}' was not found.\n"
        exit 1
    fi
}

println "PHP version"
invoke_php -v

mkdir -p $EXTENSION_DIR
mkdir -p $EXTENSION_CFG_DIR
mkdir -p $EXTENSION_LOGS_DIR

println 'Installing Datadog PHP tracing extension (ddtrace)'
println
println "Logging $DD_TRACE_PHP_BIN -i to a file"
println

invoke_php -i > "$EXTENSION_LOGS_DIR/php-info.log"

PHP_VERSION=$(invoke_php -i | awk '/^PHP[ \t]+API[ \t]+=>/ { print $NF }')
PHP_CFG_DIR=$(invoke_php -i | grep 'Scan this dir for additional .ini files =>' | sed -e 's/Scan this dir for additional .ini files =>//g' | head -n 1 | awk '{print $1}')

PHP_THREAD_SAFETY=$(invoke_php -i | grep 'Thread Safety' | awk '{print $NF}' | grep -i enabled)
PHP_DEBUG_BUILD=$(invoke_php -i | grep 'Debug Build => ' | awk '{print $NF}' | grep -i yes)

VERSION_SUFFIX=""
if [[ -n $PHP_THREAD_SAFETY ]]; then
    VERSION_SUFFIX="-zts"
elif [[ -n $PHP_DEBUG_BUILD ]]; then
    VERSION_SUFFIX="-debug"
fi

OS_SPECIFIER=""
if [ -f "/etc/os-release" ] && $(grep -q 'Alpine Linux' "/etc/os-release") && [ "${VERSION_SUFFIX}" != "-zts" ]; then
    OS_SPECIFIER="-alpine"
fi

EXTENSION_NAME="ddtrace-${PHP_VERSION}${VERSION_SUFFIX}${OS_SPECIFIER}.so"
EXTENSION_FILE_PATH="${EXTENSION_DIR}/${EXTENSION_NAME}"
INI_FILE_CONTENTS=$(cat <<EOF
[datadog]
extension=${EXTENSION_FILE_PATH}
datadog.trace.request_init_hook=${EXTENSION_AUTO_INSTRUMENTATION_FILE}
EOF
)

verify_required_ext json

if [[ ! -e $PHP_CFG_DIR ]]; then
    println
    println 'conf.d folder not found falling back to appending extension config to main "php.ini"'
    PHP_CFG_FILE_PATH=$(invoke_php -i | grep 'Configuration File (php.ini) Path =>' | sed -e 's/Configuration File (php.ini) Path =>//g' | head -n 1 | awk '{print $1}')
    PHP_CFG_FILE="${PHP_CFG_FILE_PATH}/php.ini"
    if [[ ! -e $PHP_CFG_FILE_PATH ]]; then
        fail_print_and_exit
    fi

    if grep -q "${EXTENSION_FILE_PATH}" "${PHP_CFG_FILE}"; then
        println
        println '    extension configuration already exists skipping'
    else
        append_configuration_to_file "${PHP_CFG_FILE}"
    fi
else
    install_conf_d_files
fi

verify_installation
