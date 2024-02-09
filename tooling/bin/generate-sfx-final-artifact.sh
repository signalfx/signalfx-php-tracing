#!/usr/bin/env bash
# SIGNALFX: generate SFX tar.gz bundles by repackaging DD bundles

set -xeuo pipefail
IFS=$'\n\t'

release_version=$1
dd_packages_build_dir=$2
sfx_packages_build_dir=$3

tmp_folder=/tmp/bundle
tmp_folder_final=$tmp_folder/final

architectures=(x86_64 aarch64)

for architecture in "${architectures[@]}"; do
    tmp_folder_final_gnu=$tmp_folder_final/$architecture-linux-gnu
    tmp_folder_final_musl=$tmp_folder_final/$architecture-linux-musl

    # Starting from a clean folder
    rm -rf $tmp_folder
    mkdir -p $tmp_folder_final_gnu
    mkdir -p $tmp_folder_final_musl

    tmp_folder_dd_gnu=$tmp_folder_final/dd-gnu
    tmp_folder_dd_musl=$tmp_folder_final/dd-musl
    mkdir -p $tmp_folder_dd_gnu
    mkdir -p $tmp_folder_dd_musl

    tar -xzf $dd_packages_build_dir/dd-library-php-${release_version}-$architecture-linux-gnu.tar.gz -C $tmp_folder_dd_gnu
    tar -xzf $dd_packages_build_dir/dd-library-php-${release_version}-$architecture-linux-musl.tar.gz -C $tmp_folder_dd_musl

    ########################
    # Trace
    ########################
    tmp_folder_trace=$tmp_folder/trace
    mkdir -p $tmp_folder_trace
    tmp_folder_final_gnu_trace=$tmp_folder_final_gnu/signalfx-library-php/trace
    tmp_folder_final_musl_trace=$tmp_folder_final_musl/signalfx-library-php/trace
    dd_folder_trace_gnu=$tmp_folder_dd_gnu/dd-library-php/trace
    dd_folder_trace_musl=$tmp_folder_dd_musl/dd-library-php/trace

    php_apis=(20151012 20160303 20170718 20180731 20190902 20200930 20210902 20220829 20230831)
    # SIGNALFX: API versions 20100412 20121113 20131106 are PHP5 which is built from a separate branch in upstream, which is not present here
    for php_api in "${php_apis[@]}"; do
        mkdir -p ${tmp_folder_final_gnu_trace}/ext/$php_api ${tmp_folder_final_musl_trace}/ext/$php_api;
        cp $dd_folder_trace_gnu/ext/$php_api/ddtrace.so ${tmp_folder_final_gnu_trace}/ext/$php_api/signalfx-tracing.so;
        cp $dd_folder_trace_gnu/ext/$php_api/ddtrace-zts.so ${tmp_folder_final_gnu_trace}/ext/$php_api/signalfx-tracing-zts.so;
        cp $dd_folder_trace_gnu/ext/$php_api/ddtrace-debug.so ${tmp_folder_final_gnu_trace}/ext/$php_api/signalfx-tracing-debug.so;
        cp $dd_folder_trace_musl/ext/$php_api/ddtrace.so ${tmp_folder_final_musl_trace}/ext/$php_api/signalfx-tracing.so;
    done;
    cp -r ${dd_folder_trace_gnu}/bridge ${tmp_folder_final_gnu_trace};
    cp -r ${dd_folder_trace_musl}/bridge ${tmp_folder_final_musl_trace};

    ########################
    # Final archives
    ########################
    echo "$release_version" > ${tmp_folder_final_gnu}/signalfx-library-php/VERSION
    tar -czv \
        -f ${sfx_packages_build_dir}/signalfx-library-php-${release_version}-$architecture-linux-gnu.tar.gz \
        -C ${tmp_folder_final_gnu} . --owner=0 --group=0

    echo "$release_version" > ${tmp_folder_final_musl}/signalfx-library-php/VERSION
    tar -czv \
        -f ${sfx_packages_build_dir}/signalfx-library-php-${release_version}-$architecture-linux-musl.tar.gz \
        -C ${tmp_folder_final_musl} . --owner=0 --group=0
done
