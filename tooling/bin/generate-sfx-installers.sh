#!/usr/bin/env sh
# SIGNALFX: generate signalfx-setup.php with version number set

set -e

release_version=$1
dd_packages_build_dir=$2

########################
# Installers
########################
sed "s|@release_version@|${release_version}|g" ./signalfx-setup.php > "${packages_build_dir}/signalfx-setup.php"
