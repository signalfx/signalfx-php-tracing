<?php

// SIGNALFX: counterpart of datadog-setup.php

// Tests for the installer are in 'dockerfiles/verify_packages/installer'

const INI_SCANDIR = 'Scan this dir for additional .ini files';
const INI_MAIN = 'Loaded Configuration File';
const EXTENSION_DIR = 'extension_dir';
const THREAD_SAFETY = 'Thread Safety';
const PHP_API = 'PHP API';
const IS_DEBUG = 'Debug Build';

// Options
const OPT_HELP = 'help';
const OPT_INSTALL_DIR = 'install-dir';
const OPT_PHP_BIN = 'php-bin';
const OPT_FILE = 'file';
const OPT_FILE_DIR = 'file-dir';
const OPT_UNINSTALL = 'uninstall';
const OPT_LIST_CONFIG = 'list-config';
const OPT_UPDATE_CONFIG = 'update-config';

// Release version is set while generating the final release files
const RELEASE_VERSION = '@release_version@';

function main()
{
    $options = parse_validate_user_options();
    if ($options[OPT_UNINSTALL]) {
        uninstall($options);
    } elseif ($options[OPT_LIST_CONFIG]) {
        list_config($options);
    } elseif ($options[OPT_UPDATE_CONFIG]) {
        update_config($options);
    } else {
        install($options);
    }
}

function print_help()
{
    echo <<<EOD

Usage:
    Interactive
        php signalfx-setup.php ...
    Non-Interactive
        php signalfx-setup.php --php-bin php ...
        php signalfx-setup.php --php-bin php --php-bin /usr/local/sbin/php-fpm ...

Options:
    -h, --help                  Print this help text and exit
    --php-bin all|<path to php> Install the library to the specified binary or all php binaries in standard search
                                paths. The option can be provided multiple times.
    --install-dir <path>        Install to a specific directory. Default: '/opt/signalfx'
    --uninstall                 Uninstall the library from the specified binaries
    --list-config               List all available config options
    --update-config             Set the provided config options for all installed tracing libraries, example:
                                Requires additional parameters for options to set,
                                for example: --signalfx.trace.cli_enabled=true

EOD;
}

function install($options)
{
    $architecture = get_architecture();
    $platform = "$architecture-linux-" . (is_alpine() ? 'musl' : 'gnu');

    // Checking required libraries
    check_library_prerequisite_or_exit('libcurl');

    // Picking the right binaries to install the library
    $selectedBinaries = require_binaries_or_exit($options, false);
    $interactive = empty($options[OPT_PHP_BIN]);

    // Preparing clean tmp folder to extract files
    $tmpDir = sys_get_temp_dir() . '/signalfx-install';
    $tmpDirTarGz = $tmpDir . "/signalfx-library-php-{$platform}.tar.gz";
    $tmpArchiveRoot = $tmpDir . '/signalfx-library-php';
    $tmpArchiveTraceRoot = $tmpDir . '/signalfx-library-php/trace';
    $tmpBridgeDir = $tmpArchiveTraceRoot . '/bridge';
    execute_or_exit("Cannot create directory '$tmpDir'", "mkdir -p " . escapeshellarg($tmpDir));
    register_shutdown_function(function () use ($tmpDir) {
        execute_or_exit("Cannot remove temporary directory '$tmpDir'", "rm -rf " . escapeshellarg($tmpDir));
    });
    execute_or_exit(
        "Cannot clean '$tmpDir'",
        "rm -rf " . escapeshellarg($tmpDir) . "/* "
    );

    // Retrieve and extract the archive to a tmp location
    if (isset($options[OPT_FILE])) {
        $tmpDirTarGz = $options[OPT_FILE];
    } elseif (isset($options[OPT_FILE_DIR])) {
        $version = RELEASE_VERSION;
        $tarGzDir = $options[OPT_FILE_DIR];
        $tarGzName = "signalfx-library-php-{$version}-{$platform}.tar.gz";
        $tmpDirTarGz = "{$tarGzDir}/{$tarGzName}";

        if (!file_exists($tmpDirTarGz)) {
            print_error_and_exit("Could not find {$tarGzName} in provided directory {$tarGzDir}");
        }
        unset($version);
    } else {
        $version = RELEASE_VERSION;
        // phpcs:disable Generic.Files.LineLength.TooLong
        // For testing purposes, we need an alternate repo where we can push bundles that includes changes that we are
        // trying to test, as the previously released versions would not have those changes.
        $url = "https://github.com/signalfx/signalfx-php-tracing"
            . "/releases/download/{$version}/signalfx-library-php-{$version}-{$platform}.tar.gz";
        // phpcs:enable Generic.Files.LineLength.TooLong
        download($url, $tmpDirTarGz);
        unset($version);
    }
    execute_or_exit(
        "Cannot extract the archive",
        "tar -xf " . escapeshellarg($tmpDirTarGz) . " -C " . escapeshellarg($tmpDir)
    );

    $releaseVersion = trim(file_get_contents("$tmpArchiveRoot/VERSION"));

    $installDir = $options[OPT_INSTALL_DIR] . '/' . $releaseVersion;

    // Tracer sources
    $installDirSourcesDir = $installDir . '/dd-trace-sources';
    $installDirBridgeDir = $installDirSourcesDir . '/bridge';
    $installDirWrapperPath = $installDirBridgeDir . '/dd_wrap_autoloader.php';
    // copying sources to the final destination
    execute_or_exit(
        "Cannot create directory '$installDirSourcesDir'",
        "mkdir -p " . escapeshellarg($installDirSourcesDir)
    );
    execute_or_exit(
        "Cannot copy files from '$tmpBridgeDir' to '$installDirSourcesDir'",
        "cp -r " . escapeshellarg("$tmpBridgeDir") . ' ' . escapeshellarg($installDirSourcesDir)
    );
    echo "Installed required source files to '$installDir'\n";

    // Actual installation
    foreach ($selectedBinaries as $command => $fullPath) {
        $binaryForLog = ($command === $fullPath) ? $fullPath : "$command ($fullPath)";
        echo "Installing to binary: $binaryForLog\n";

        $phpMajorMinor = get_php_major_minor($fullPath);

        check_php_ext_prerequisite_or_exit($fullPath, 'json');

        $phpProperties = ini_values($fullPath);
        if (is_truthy($phpProperties[THREAD_SAFETY]) && is_truthy($phpProperties[IS_DEBUG])) {
            print_error_and_exit('(ZTS DEBUG) builds of PHP are currently not supported');
        }

        if (!isset($phpProperties[INI_SCANDIR])) {
            if (!isset($phpProperties[INI_MAIN])) {
                print_error_and_exit("It is not possible to perform installation on this system " .
                                    "because there is no scan directory and no configuration file loaded.");
            }

            print_warning("Performing an installation without a scan directory may result in " .
                        "fragile installations that are broken by normal system upgrades. " .
                        "It is advisable to use the configure switch " .
                        "--with-config-file-scan-dir " .
                        "when building PHP");
        }

        // Copying the extension
        $extensionVersion = $phpProperties[PHP_API];

        // Suffix (zts/debug/alpine)
        $extensionSuffix = '';
        if (is_truthy($phpProperties[IS_DEBUG])) {
            $extensionSuffix = '-debug';
        } elseif (is_truthy($phpProperties[THREAD_SAFETY])) {
            $extensionSuffix = '-zts';
        }

        // Trace
        $extensionRealPath = "$tmpArchiveTraceRoot/ext/$extensionVersion/signalfx-tracing$extensionSuffix.so";
        $extensionDestination = $phpProperties[EXTENSION_DIR] . '/signalfx-tracing.so';
        safe_copy_extension($extensionRealPath, $extensionDestination);

        // Writing the ini file
        if ($phpProperties[INI_SCANDIR]) {
            $iniFileName = '98-signalfx-tracing.ini';
            $iniFilePaths = [$phpProperties[INI_SCANDIR] . '/' . $iniFileName];

            if (\strpos($phpProperties[INI_SCANDIR], '/cli/conf.d') !== false) {
                /* debian based distros have INI folders split by SAPI, in a predefined way:
                 *   - <...>/cli/conf.d       <-- we know this from php -i
                 *   - <...>/apache2/conf.d   <-- we derive this from relative path
                 *   - <...>/fpm/conf.d       <-- we derive this from relative path
                 */
                $apacheConfd = str_replace('/cli/conf.d', '/apache2/conf.d', $phpProperties[INI_SCANDIR]);
                if (\is_dir($apacheConfd)) {
                    array_push($iniFilePaths, "$apacheConfd/$iniFileName");
                }
            }
        } else {
            $iniFileName = $phpProperties[INI_MAIN];
            $iniFilePaths = [$iniFileName];
        }

        foreach ($iniFilePaths as $iniFilePath) {
            if (!file_exists($iniFilePath)) {
                $iniDir = dirname($iniFilePath);
                execute_or_exit(
                    "Cannot create directory '$iniDir'",
                    "mkdir -p " . escapeshellarg($iniDir)
                );

                if (false === file_put_contents($iniFilePath, '')) {
                    print_error_and_exit("Cannot create INI file $iniFilePath");
                }
                echo "Created INI file '$iniFilePath'\n";
            } else {
                echo "Updating existing INI file '$iniFilePath'\n";
                // phpcs:disable Generic.Files.LineLength.TooLong
                execute_or_exit(
                    'Impossible to replace the deprecated signalfx.request_init_hook parameter with the new name.',
                    "sed -i 's|signalfx.request_init_hook|signalfx.trace.request_init_hook|g' " . escapeshellarg($iniFilePath)
                );
                execute_or_exit(
                    'Impossible to update the INI settings file.',
                    "sed -i 's@signalfx\.trace\.request_init_hook \?= \?\(.*\)@signalfx.trace.request_init_hook = '" . escapeshellarg($installDirWrapperPath) . "'@g' " . escapeshellarg($iniFilePath)
                );
                // phpcs:enable Generic.Files.LineLength.TooLong

                /* In order to support upgrading from legacy installation method to new installation method, we replace
                 * "extension = /opt/signalfx-php/xyz.so" with "extension =  signalfx-tracing.so" honoring trailing `;`,
                 * hence not automatically re-activating the extension if the user had commented it out.
                 */
                execute_or_exit(
                    'Impossible to update the INI settings file.',
                    "sed -i 's@extension \?= \?.*signalfx-tracing.*\(.*\)@extension = signalfx-tracing.so@g' "
                        . escapeshellarg($iniFilePath)
                );
            }

            add_missing_ini_settings(
                $iniFilePath,
                get_ini_settings($installDirWrapperPath)
            );

            echo "Installation to '$binaryForLog' was successful\n";
        }
    }

    echo "--------------------------------------------------\n";
    echo "SUCCESS\n\n";
    if ($interactive) {
        echo "To run this script in a non interactive mode, use the following options:\n";
        $args = array_merge(
            $_SERVER["argv"],
            array_map(
                function ($el) {
                    return '--php-bin=' . $el;
                },
                array_keys($selectedBinaries)
            )
        );
        echo "  php " . implode(" ", array_map("escapeshellarg", $args)) . "\n";
    }
}

function get_unlisted_ini_settings()
{
    return [
        'extension' => 1,
        'signalfx.trace.request_init_hook' => 1,
        'signalfx.trace.http_client_split_by_domain' => 1,
        'signalfx.trace.sample_rate' => 1,
        'signalfx.trace.sampling_rules' => 1,
        'signalfx.trace.<integration_name>_enabled' => 1,
        'signalfx.trace.<integration_name>_analytics_enabled' => 1,
        'signalfx.trace.<integration_name>_analytics_sample_rate' => 1,
        'signalfx.trace.analytics_enabled' => 1,
        'signalfx.trace.retain_thread_capabilities' => 1
    ];
}

function list_config($options)
{
    $settings = get_ini_settings('');
    $unlistedNames = get_unlisted_ini_settings();

    foreach (get_ini_settings('') as $setting) {
        if (key_exists($setting['name'], $unlistedNames)) {
            continue;
        }

        echo "  {$setting['name']}\n";
        echo "      Default: {$setting['default']}\n";

        if (is_array($setting['description'])) {
            echo "      " . implode("\n      ", $setting['description']) . "\n";
        } else {
            echo "      {$setting['description']}\n";
        }
    }
}

function update_config($options)
{
    $binaries = require_binaries_or_exit($options, true);
    $iniFilePaths = [];

    foreach ($binaries as $command => $fullPath) {
        $phpProperties = ini_values($fullPath);
        if (is_truthy($phpProperties[THREAD_SAFETY]) && is_truthy($phpProperties[IS_DEBUG])) {
            continue;
        }

        if (!isset($phpProperties[INI_SCANDIR])) {
            if (!isset($phpProperties[INI_MAIN])) {
                continue;
            }
        }

        if ($phpProperties[INI_SCANDIR]) {
            $iniFileName = '98-signalfx-tracing.ini';
            // Search for pre-existing files with extension = signalfx-tracing.so to avoid conflicts
            foreach (scandir($phpProperties[INI_SCANDIR]) as $ini) {
                $path = "{$phpProperties[INI_SCANDIR]}/$ini";
                if (is_file($path)) {
                    if (preg_match('(^\s*extension\s*=.+signalfx-tracing\.so)m', file_get_contents($path))) {
                        $iniFileName = $ini;
                    }
                }
            }
            $iniFilePaths[$phpProperties[INI_SCANDIR] . '/' . $iniFileName] = 1;

            if (\strpos($phpProperties[INI_SCANDIR], '/cli/conf.d') !== false) {
                $apacheConfd = str_replace('/cli/conf.d', '/apache2/conf.d', $phpProperties[INI_SCANDIR]);
                if (\is_dir($apacheConfd)) {
                    $iniFilePaths[] = "$apacheConfd/$iniFileName";
                }
            }
        } else {
            $iniFileName = $phpProperties[INI_MAIN];
            $iniFilePaths[$iniFileName] = 1;
        }
    }

    $foundCount = 0;
    $modifiedCount = 0;

    foreach ($iniFilePaths as $iniFilePath => $discard) {
        if (file_exists($iniFilePath)) {
            $foundCount++;
            $iniFileContent = file_get_contents($iniFilePath);
            $updatedContents = update_ini_settings($iniFileContent, $options);

            if ($updatedContents !== false) {
                file_put_contents($iniFilePath, $updatedContents);
                echo "Updated $iniFilePath\n";
                $modifiedCount++;
            } else {
                echo "Nothing to update in $iniFilePath\n";
            }
        }
    }

    if ($modifiedCount > 0) {
        echo "Updating settings was successful\n";
    } elseif ($foundCount > 0) {
        print_error_and_exit("Found INI files but no settings were provided that were present in them\n");
    } else {
        print_error_and_exit("Found no INI files, and make sure tracing is installed\n");
    }
}

function update_ini_settings($iniFileContent, $options)
{
    $lines = explode("\n", $iniFileContent);
    $modified = false;

    foreach ($lines as $index => $line) {
        $settingRegex = '/;?(signalfx\.[a-zA-Z._]+)\s*=/';

        if (preg_match($settingRegex, $line, $matches)) {
            $name = $matches[1];

            if (key_exists($name, $options)) {
                $lines[$index] = $name . " = " . $options[$name];
                $modified = true;
            }
        }
    }

    if ($modified) {
        return implode("\n", $lines);
    } else {
        return false;
    }
}

/**
 * Copies an extension's file to a destination using copy+rename to avoid segfault if the file is loaded by php.
 *
 * @param string $source
 * @param string $destination
 * @return void
 */
function safe_copy_extension($source, $destination)
{
    /* Move - rename() - instead of copy() since copying does a fopen() and copies to the stream itself, causing a
    * segfault in the PHP process that is running and had loaded the old shared object file.
    */
    $tmpName = $destination . '.tmp';
    copy($source, $tmpName);
    rename($tmpName, $destination);
    echo "Copied '$source' to '$destination'\n";
}

function uninstall($options)
{
    $selectedBinaries = require_binaries_or_exit($options, false);

    foreach ($selectedBinaries as $command => $fullPath) {
        $binaryForLog = ($command === $fullPath) ? $fullPath : "$command ($fullPath)";
        echo "Uninstalling from binary: $binaryForLog\n";

        $phpProperties = ini_values($fullPath);

        $extensionDestinations = [
            $phpProperties[EXTENSION_DIR] . '/signalfx-tracing.so',
        ];

        if (isset($phpProperties[INI_SCANDIR])) {
            $iniFileName = '98-signalfx-tracing.ini';
            $iniFilePaths = [$phpProperties[INI_SCANDIR] . '/' . $iniFileName];

            if (\strpos('/cli/conf.d', $phpProperties[INI_SCANDIR]) >= 0) {
                /* debian based distros have INI folders split by SAPI, in a predefined way:
                 *   - <...>/cli/conf.d       <-- we know this from php -i
                 *   - <...>/apache2/conf.d    <-- we derive this from relative path
                 *   - <...>/fpm/conf.d       <-- we derive this from relative path
                 */
                $apacheConfd = str_replace('/cli/conf.d', '/apache2/conf.d', $phpProperties[INI_SCANDIR]);
                if (\is_dir($apacheConfd)) {
                    array_push($iniFilePaths, "$apacheConfd/$iniFileName");
                }
            }
        } else {
            if (!isset($phpProperties[INI_MAIN])) {
                print_error_and_exit("It is not possible to perform uninstallation on this system " .
                                    "because there is no scan directory and no configuration file loaded.");
            }

            $iniFilePaths = [$phpProperties[INI_MAIN]];
        }

        /* Actual uninstall
         *  1) comment out extension=signalfx-tracing.so
         *  2) remove signalfx-tracing.so
         */
        foreach ($iniFilePaths as $iniFilePath) {
            if (file_exists($iniFilePath)) {
                execute_or_exit(
                    "Impossible to disable PHP modules from '$iniFilePath'. You can disable them manually.",
                    "sed -i 's@^extension \?=@;extension =@g' " . escapeshellarg($iniFilePath)
                );
                execute_or_exit(
                    "Impossible to disable Zend modules from '$iniFilePath'. You can disable them manually.",
                    "sed -i 's@^zend_extension \?=@;zend_extension =@g' " . escapeshellarg($iniFilePath)
                );
                echo "Disabled all modules in INI file '$iniFilePath'. "
                    . "The file has not been removed to preserve custom settings.\n";
            }
        }
        $errors = false;
        foreach ($extensionDestinations as $extensionDestination) {
            if (file_exists($extensionDestination) && false === unlink($extensionDestination)) {
                print_warning("Error while removing $extensionDestination. It can be manually removed.");
                $errors = true;
            }
        }
        if ($errors) {
            echo "Uninstall from '$binaryForLog' was completed with warnings\n";
        } else {
            echo "Uninstall from '$binaryForLog' was successful\n";
        }
    }
}

/**
 * Returns a list of php binaries where the library will be installed. If not explicitly provided by the CLI options,
 * then the list is retrieved using an interactive session.
 *
 * @param array $options
 * @return array
 */
function require_binaries_or_exit($options, $defaultAll)
{
    $selectedBinaries = [];
    if (empty($options[OPT_PHP_BIN])) {
        if ($defaultAll) {
            foreach (search_php_binaries() as $command => $binaryinfo) {
                if (!$binaryinfo["shebang"]) {
                    $selectedBinaries[$command] = $binaryinfo["path"];
                }
            }
        } else {
            $selectedBinaries = pick_binaries_interactive($options, search_php_binaries());
        }
    } else {
        foreach ($options[OPT_PHP_BIN] as $command) {
            if ($command == "all") {
                foreach (search_php_binaries() as $command => $binaryinfo) {
                    if (!$binaryinfo["shebang"]) {
                        $selectedBinaries[$command] = $binaryinfo["path"];
                    }
                }
            } elseif ($resolvedPath = resolve_command_full_path($command)) {
                $selectedBinaries[$command] = $resolvedPath;
            } else {
                print_error_and_exit("Provided PHP binary '$command' was not found.\n");
            }
        }
    }

    if (empty($selectedBinaries)) {
        print_error_and_exit("At least one binary must be specified\n");
    }

    return $selectedBinaries;
}

function search_for_working_ldconfig()
{
    static $path;

    if ($path) {
        return $path;
    }

    $paths = [
        "/sbin", /* this is most likely path */
        "/usr/sbin",
        "/usr/local/sbin",
        "/bin",
        "/usr/bin",
        "/usr/local/bin",
    ];

    $search = function (&$path) {
        exec("find $path -name ldconfig", $found, $result);

        if ($result == 0) {
            return $path = \end($found);
        }
    };

    /* searching individual paths is much faster than searching
        them all */
    foreach ($paths as $path) {
        if ($search($path)) {
            return $path;
        }
    }

    /* probably won't get this far, but just in case */
    foreach (\explode(":", \getenv("PATH")) as $path) {
        if (\array_search($path, $paths) === false) {
            if ($search($path)) {
                return $path;
            }
        }
    }

    /*
        we cannot find a working ldconfig binary on this system,
        fall back on previous behaviour:
        there is a slim outside chance that exec() expands ldconfig
    */
    return $path = "ldconfig";
}

/**
 * Checks if a library is available or not in an OS-independent way.
 *
 * @param string $requiredLibrary E.g. libcurl
 * @return void
 */
function check_library_prerequisite_or_exit($requiredLibrary)
{
    if (is_alpine()) {
        $lastLine = execute_or_exit(
            "Error while searching for library '$requiredLibrary'.",
            "find /usr/local/lib /usr/lib -type f -name '*{$requiredLibrary}*.so*'"
        );
    } else {
        $ldconfig = search_for_working_ldconfig();
        $lastLine = execute_or_exit(
            "Cannot find library '$requiredLibrary'",
            "$ldconfig -p | grep $requiredLibrary"
        );
    }

    if (empty($lastLine)) {
        print_error_and_exit("Required library '$requiredLibrary' not found.\n");
    }
}

/**
 * Checks if an extension is enabled or not.
 *
 * @param string $binary
 * @param string $requiredLibrary E.g. json
 * @return void
 */
function check_php_ext_prerequisite_or_exit($binary, $extName)
{
    $lastLine = execute_or_exit(
        "Cannot retrieve extensions list",
        // '|| true' is necessary because grep exits with 1 if the pattern was not found.
        "$binary -m | grep '$extName' || true"
    );


    if (empty($lastLine)) {
        print_error_and_exit("Required PHP extension '$extName' not found.\n");
    }
}

/**
 * @return bool
 */
function is_alpine()
{
    $osInfoFile = '/etc/os-release';
    // if /etc/os-release is not readable, we cannot tell and we assume NO
    if (!is_readable($osInfoFile)) {
        return false;
    }
    return false !== stripos(file_get_contents($osInfoFile), 'alpine');
}

/**
 * Returns the host architecture, e.g. x86_64, aarch64
 *
 * @return string
 */
function get_architecture()
{
    return execute_or_exit(
        "Cannot detect host architecture (uname -m)",
        "uname -m"
    );
}

/**
 * Parses command line options provided by the user and generate a normalized $options array.

 * @return array
 */
function parse_validate_user_options()
{
    $shortOptions = "h";
    $longOptions = [
        OPT_HELP,
        OPT_PHP_BIN . ':',
        OPT_FILE . ':',
        OPT_FILE_DIR . ':',
        OPT_INSTALL_DIR . ':',
        OPT_UNINSTALL,
        OPT_LIST_CONFIG,
        OPT_UPDATE_CONFIG,
    ];

    $unlistedNames = get_unlisted_ini_settings();
    $listedNames = [];

    foreach (get_ini_settings('') as $setting) {
        if (!key_exists($setting['name'], $unlistedNames)) {
            $longOptions[] = $setting['name'] . ":";
            $listedNames[] = $setting['name'];
        }
    }

    $options = getopt($shortOptions, $longOptions);

    global $argc;
    if ($options === false || (empty($options) && $argc > 1)) {
        print_error_and_exit("Failed to parse options", true);
    }

    // Help and exit
    if (key_exists('h', $options) || key_exists(OPT_HELP, $options)) {
        print_help();
        exit(0);
    }

    $normalizedOptions = [];

    $normalizedOptions[OPT_UNINSTALL] = isset($options[OPT_UNINSTALL]) ? true : false;
    $normalizedOptions[OPT_LIST_CONFIG] = isset($options[OPT_LIST_CONFIG]) ? true : false;
    $normalizedOptions[OPT_UPDATE_CONFIG] = isset($options[OPT_UPDATE_CONFIG]) ? true : false;

    if (!$normalizedOptions[OPT_UNINSTALL]) {
        if (isset($options[OPT_FILE])) {
            if (is_array($options[OPT_FILE])) {
                print_error_and_exit('Only one --file can be provided', true);
            }
            $normalizedOptions[OPT_FILE] = $options[OPT_FILE];
        }

        if (isset($options[OPT_FILE_DIR])) {
            if (is_array($options[OPT_FILE_DIR])) {
                print_error_and_exit('Only one --file can be provided', true);
            }
            $normalizedOptions[OPT_FILE_DIR] = $options[OPT_FILE_DIR];
        }
    }

    foreach ($listedNames as $listedName) {
        if (isset($options[$listedName])) {
            $value = $options[$listedName];

            if (is_array($value)) {
                $normalizedOptions[$listedName] = implode(',', $value);
            } else {
                $normalizedOptions[$listedName] = $value;
            }
        }
    }

    if (isset($options[OPT_PHP_BIN])) {
        $normalizedOptions[OPT_PHP_BIN] =
            is_array($options[OPT_PHP_BIN])
            ? $options[OPT_PHP_BIN]
            : [$options[OPT_PHP_BIN]];
    }

    $normalizedOptions[OPT_INSTALL_DIR] =
        isset($options[OPT_INSTALL_DIR])
        ? rtrim($options[OPT_INSTALL_DIR], '/')
        : '/opt/signalfx';
    $normalizedOptions[OPT_INSTALL_DIR] =  $normalizedOptions[OPT_INSTALL_DIR] . '/signalfx-library';

    return $normalizedOptions;
}

function print_error_and_exit($message, $printHelp = false)
{
    echo "ERROR: $message\n";
    if ($printHelp) {
        print_help();
    }
    exit(1);
}

function print_warning($message)
{
    echo "WARNING: $message\n";
}

/**
 * Given a certain set of available PHP binaries, let users pick in an interactive way the ones where the library
 * should be installed to.
 *
 * @param array $php_binaries
 * @return array
 */
function pick_binaries_interactive($options, array $php_binaries)
{
    echo sprintf(
        "Multiple PHP binaries detected. Please select the binaries the signalfx library will be %s:\n\n",
        $options[OPT_UNINSTALL] ? "uninstalled from" : "installed to"
    );
    $commands = array_keys($php_binaries);
    for ($index = 0; $index < count($commands); $index++) {
        $command = $commands[$index];
        $fullPath = $php_binaries[$command]["path"];
        echo "  "
            . str_pad($index + 1, 2, ' ', STR_PAD_LEFT)
            . ". "
            . ($command !== $fullPath ? "$command --> " : "")
            . $fullPath
            . ($php_binaries[$command]["shebang"] ? " (not a binary)" : "")
            . "\n";
    }
    echo "\n";
    flush();

    echo "Select binaries using their number. Multiple binaries separated by space (example: 1 3): ";
    $userInput = fgets(STDIN);
    $choices = array_map('intval', array_filter(explode(' ', $userInput)));

    $pickedBinaries = [];
    foreach ($choices as $choice) {
        $index = $choice - 1; // we render to the user as 1-indexed
        if (!isset($commands[$index])) {
            echo "\nERROR: Wrong choice: $choice\n\n";
            return pick_binaries_interactive($options, $php_binaries);
        }
        $command = $commands[$index];
        $pickedBinaries[$command] = $php_binaries[$command]["path"];
    }

    return $pickedBinaries;
}

function execute_or_exit($exitMessage, $command)
{
    $output = [];
    $returnCode = 0;
    $lastLine = exec($command, $output, $returnCode);
    if (false === $lastLine || $returnCode > 0) {
        print_error_and_exit(
            $exitMessage .
                "\nFailed command (return code $returnCode): $command\n---- Output ----\n" .
                implode("\n", $output) .
                "\n---- End of output ----\n"
        );
    }

    return $lastLine;
}

/**
 * Downloads the library applying a number of fallback mechanisms if specific libraries/binaries are not available.
 *
 * @param string $url
 * @param string $destination
 */
function download($url, $destination)
{
    echo "Downloading installable archive from $url.\n";
    echo "This operation might take a while.\n";

    $okMessage = "\nDownload completed\n\n";

    /* We try the following options, mostly to provide progress report, if possible:
     *   1) `ext-curl` (with progress report); if 'ext-curl' is not installed...
     *   2) `curl` from CLI (it shows progress); if `curl` is not installed...
     *   3) `file_get_contents()` (no progress report); if `allow_url_fopen=0`...
     *   4) exit with errror
     */

    // ext-curl
    if (extension_loaded('curl')) {
        if (false === $fp = fopen($destination, 'w+')) {
            print_error_and_exit("Error while opening target file '$destination' for writing\n");
        }
        global $progress_counter;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_TIMEOUT, 50);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, 'on_download_progress');
        curl_setopt($ch, CURLOPT_NOPROGRESS, false);
        $progress_counter = 0;
        $return = curl_exec($ch);
        curl_close($ch);
        fclose($fp);

        if (false !== $return) {
            echo $okMessage;
            return;
        }
        // Otherwise we attempt other methods
    }

    // curl
    $statusCode = 0;
    $output = [];
    if (false !== exec('curl --version', $output, $statusCode) && $statusCode === 0) {
        $curlInvocationStatusCode = 0;
        system(
            'curl -L --output ' . escapeshellarg($destination) . ' ' . escapeshellarg($url),
            $curlInvocationStatusCode
        );

        if ($curlInvocationStatusCode === 0) {
            echo $okMessage;
            return;
        }
        // Otherwise we attempt other methods
    }

    // file_get_contents
    if (is_truthy(ini_get('allow_url_fopen'))) {
        if (false === file_put_contents($destination, file_get_contents($url))) {
            print_error_and_exit("Error while downloading the installable archive from $url\n");
        }

        echo $okMessage;
        return;
    }

    echo "Error: Cannot download the installable archive.\n";
    echo "  One of the following prerequisites must be satisfied:\n";
    echo "    - PHP ext-curl extension is installed\n";
    echo "    - curl CLI command is available\n";
    echo "    - the INI setting 'allow_url_fopen=1'\n";

    exit(1);
}

/**
 * Progress callback as specified by the ext-curl documentation.
 *   see: https://www.php.net/manual/en/function.curl-setopt.php#:~:text=CURLOPT_PROGRESSFUNCTION
 *
 * @return int
 */
function on_download_progress($curlHandle, $download_size, $downloaded)
{
    global $progress_counter;

    if ($download_size === 0) {
        return 0;
    }
    $ratio = $downloaded / $download_size;
    if ($ratio == 1) {
        return 0;
    }

    // Max 20 dots to show progress
    if ($ratio >= ($progress_counter + (1 / 20))) {
        $progress_counter = $ratio;
        echo ".";
    }

    flush();
    return 0;
}

/**
 * Extracts and normalizes a set of properties from PHP's ini values.
 *
 * @param string $binary
 * @return array
 */
function ini_values($binary)
{
    $properties = [INI_MAIN, INI_SCANDIR, EXTENSION_DIR, THREAD_SAFETY, PHP_API, IS_DEBUG];
    $lines = [];
    // Timezone is irrelevant to this script. Quick-and-dirty workaround to the PHP 5 warning with missing timezone
    exec(escapeshellarg($binary) . " -d date.timezone=UTC -i", $lines);
    $found = [];
    foreach ($lines as $line) {
        $parts = explode('=>', $line);
        if (count($parts) === 2 || count($parts) === 3) {
            $key = trim($parts[0]);
            if (in_array($key, $properties)) {
                $value = trim(count($parts) === 2 ? $parts[1] : $parts[2]);

                if ($value === "(none)") {
                    continue;
                }

                $found[$key] = $value;
            }
        }
    }
    return $found;
}

function is_truthy($value)
{
    if ($value === null) {
        return false;
    }

    $normalized = trim(strtolower($value));
    return in_array($normalized, ['1', 'true', 'yes', 'enabled']);
}

/**
 * @param array $phpVersions
 * @param string $prefix Default ''. Used for testing purposes only.
 * @return array
 */
function search_php_binaries($prefix = '')
{
    echo "Searching for available php binaries, this operation might take a while.\n";

    $resolvedPaths = [];

    $allPossibleCommands = build_known_command_names_matrix();

    // First, we search in $PATH, for php, php7, php74, php7.4, php7.4-fpm, etc....
    foreach ($allPossibleCommands as $command) {
        if ($resolvedPath = resolve_command_full_path($command)) {
            $resolvedPaths[$command] = $resolvedPath;
        }
    }

    // Then we search in known possible locations for popular installable paths on different systems.
    $standardPaths = [
        $prefix . '/usr/bin',
        $prefix . '/usr/sbin',
        $prefix . '/usr/local/bin',
        $prefix . '/usr/local/sbin',
    ];

    $remiSafePaths = array_map(function ($phpVersion) use ($prefix) {
        list($major, $minor) = explode('.', $phpVersion);
        /* php is installed to /usr/bin/php{$major}{$minor} so we do not need to do anything special, while php-fpm
         * is installed to /opt/remi/php{$major}{$minor}/root/usr/sbin and it needs to be added to the searched
         * locations.
         */
        return "{$prefix}/opt/remi/php{$major}{$minor}/root/usr/sbin";
    }, get_supported_php_versions());

    $pleskPaths = array_map(function ($phpVersion) use ($prefix) {
        return "/opt/plesk/php/$phpVersion/bin";
    }, get_supported_php_versions());

    $escapedSearchLocations = implode(
        ' ',
        array_map('escapeshellarg', array_merge($standardPaths, $remiSafePaths, $pleskPaths))
    );
    $escapedCommandNamesForFind = implode(
        ' -o ',
        array_map(
            function ($cmd) {
                return '-name ' . escapeshellarg($cmd);
            },
            $allPossibleCommands
        )
    );

    $pathsFound = [];
    exec(
        "find -L $escapedSearchLocations -type f \( $escapedCommandNamesForFind \) 2>/dev/null",
        $pathsFound
    );

    foreach ($pathsFound as $path) {
        $resolved = realpath($path);
        if (in_array($resolved, array_values($resolvedPaths))) {
            continue;
        }
        $resolvedPaths[$path] = $resolved;
    }

    $results = [];

    foreach ($resolvedPaths as $command => $realpath) {
        $hasShebang = file_get_contents($realpath, false, null, 0, 2) === "#!";
        $results[$command] = [
            "shebang" => $hasShebang,
            "path" => $realpath,
        ];
    }

    return $results;
}

/**
 * @param mixed $command
 * @return string|false
 */
function resolve_command_full_path($command)
{
    $path = exec("command -v " . escapeshellarg($command));
    if (false === $path || empty($path)) {
        // command is not defined
        return false;
    }

    // Resolving symlinks
    return realpath($path);
}

function build_known_command_names_matrix()
{
    $results = ['php', 'php-fpm'];

    foreach (get_supported_php_versions() as $phpVersion) {
        list($major, $minor) = explode('.', $phpVersion);
        array_push(
            $results,
            "php{$major}",
            "php{$major}{$minor}",
            "php{$major}.{$minor}",
            "php{$major}-fpm",
            "php{$major}{$minor}-fpm",
            "php{$major}.{$minor}-fpm",
            "php-fpm{$major}",
            "php-fpm{$major}{$minor}",
            "php-fpm{$major}.{$minor}"
        );
    }

    return array_unique($results);
}

/**
 * Adds ini entries that are not present in the provided ini file.
 *
 * @param string $iniFilePath
 */
function add_missing_ini_settings($iniFilePath, $settings)
{
    $iniFileContent = file_get_contents($iniFilePath);
    $formattedMissingProperties = '';

    foreach ($settings as $setting) {
        // The extension setting is not unique, so make sure we check that the
        // right extension setting is available.
        $settingRegex = '/' . str_replace('.', '\.', $setting['name']) . '\s?=\s?';
        if ($setting['name'] === 'extension' || $setting['name'] == 'zend_extension') {
            $settingRegex .= str_replace('.', '\.', $setting['default']);
        }
        $settingRegex .= '/';

        $settingMightExist = 1 === preg_match($settingRegex, $iniFileContent);

        if ($settingMightExist) {
            continue;
        }

        // Formatting the setting to be added.
        $description =
            is_string($setting['description'])
            ? '; ' . $setting['description']
            : implode(
                "\n",
                array_map(
                    function ($line) {
                        return '; ' . $line;
                    },
                    $setting['description']
                )
            );
        $setting = ($setting['commented'] ? ';' : '') . $setting['name'] . ' = ' . $setting['default'];
        $formattedMissingProperties .= "\n$description\n$setting\n";
    }

    if ($formattedMissingProperties !== '') {
        if (false === file_put_contents($iniFilePath, $iniFileContent . $formattedMissingProperties)) {
            print_error_and_exit("Cannot add additional settings to the INI file $iniFilePath");
        }
    }
}

function get_php_major_minor($binary)
{
    return execute_or_exit(
        "Cannot read PHP version",
        "$binary -v | grep -oE 'PHP [[:digit:]]+.[[:digit:]]+' | awk '{print \$NF}'"
    );
}

/**
 * Returns array of associative arrays with the following keys:
 *   - name (string): the setting name;
 *   - default (string): the default value;
 *   - commented (bool): whether this setting should be commented or not when added;
 *   - description (string|string[]): A string (or an array of strings, each representing a line) that describes
 *                                    the setting.
 *
 * @param string $requestInitHookPath
 * @return array
 */
function get_ini_settings($requestInitHookPath)
{
    // phpcs:disable Generic.Files.LineLength.TooLong
    return [
        [
            'name' => 'extension',
            'default' => 'signalfx-tracing.so',
            'commented' => false,
            'description' => 'Enables or disables tracing (set by the installer, do not change it)',
        ],
        [
            'name' => 'signalfx.trace.request_init_hook',
            'default' => $requestInitHookPath,
            'commented' => false,
            'description' => 'Path to the request init hook (set by the installer, do not change it)',
        ],
        [
            'name' => 'signalfx.trace.enabled',
            'default' => 'On',
            'commented' => true,
            'description' => 'Enables or disables tracing. On by default',
        ],
        [
            'name' => 'signalfx.trace.cli_enabled',
            'default' => 'Off',
            'commented' => true,
            'description' => 'Enable or disable tracing of CLI scripts. Off by default',
        ],
        [
            'name' => 'signalfx.trace.auto_flush_enabled',
            'default' => 'Off',
            'commented' => true,
            'description' => 'For long running processes, this setting has to be set to On',
        ],
        [
            'name' => 'signalfx.trace.generate_root_span',
            'default' => 'On',
            'commented' => true,
            'description' => 'For long running processes, this setting has to be set to Off',
        ],
        [
            'name' => 'signalfx.trace.debug',
            'default' => 'Off',
            'commented' => true,
            'description' => 'Enables or disables debug mode.  When On logs are printed to the error_log',
        ],
        [
            'name' => 'signalfx.trace.startup_logs',
            'default' => 'On',
            'commented' => true,
            'description' => 'Enables startup logs, including diagnostic checks',
        ],
        [
            'name' => 'signalfx.service_name',
            'default' => 'unnamed-php-service',
            'commented' => true,
            'description' => 'Sets a custom service name for the application',
        ],
        [
            'name' => 'signalfx.env',
            'default' => 'my_env',
            'commented' => true,
            'description' => 'Sets a custom environment name for the application',
        ],
        [
            'name' => 'signalfx.version',
            'default' => '1.0.0',
            'commented' => true,
            'description' => 'Sets a version for the user application, not the signalfx php library',
        ],
        [
            'name' => 'signalfx.endpoint_url',
            'default' => 'http://localhost:9080/v1/trace',
            'commented' => true,
            'description' => [
                'Sets the full SignalFX endpoint URL. If empty, specific port/hostname/path/https options are used instead.',
                'The default values of the specific options are equivalent to http://localhost:9080/v1/trace full URL',
            ],
        ],
        [
            'name' => 'signalfx.endpoint_host',
            'default' => 'localhost',
            'commented' => true,
            'description' => 'Sets the SignalFX endpoint hostname. Ignored if signalfx.endpoint_url is set',
        ],
        [
            'name' => 'signalfx.endpoint_port',
            'default' => '9080',
            'commented' => true,
            'description' => 'Sets the SignalFX endpoint port. Ignored if signalfx.endpoint_url is set',
        ],
        [
            'name' => 'signalfx.endpoint_https',
            'default' => 'Off',
            'commented' => true,
            'description' => 'Sets whether HTTPS is eanbled for the SignalFX endpoint. Ignored if signalfx.endpoint_url is set',
        ],
        [
            'name' => 'signalfx.endpoint_path',
            'default' => '/v1/trace',
            'commented' => true,
            'description' => 'Sets the SignalFX endpoint path. Ignored if signalfx.endpoint_url is set',
        ],
        [
            'name' => 'signalfx.access_token',
            'default' => '',
            'commented' => true,
            'description' => 'Ingest access token, which is required if sending directly to the ingest endpoint',
        ],
        [
            'name' => 'signalfx.trace.response_header_enabled',
            'default' => 'On',
            'commented' => true,
            'description' => 'Sets whether setting the response header that links with RUM traces is enabled',
        ],
        [
            'name' => 'signalfx.capture_env_vars',
            'default' => '',
            'commented' => true,
            'description' => [
                'Comma-separated case-sensitive list of environment variables to capture as span tags.',
                'The names of the tags will start with php.env. prefix, followed by lowercase variable name',
            ],
        ],
        [
            'name' => 'signalfx.capture_request_headers',
            'default' => '',
            'commented' => true,
            'description'  => [
                'Comma-separated case-insensitive list of request headers to capture as span tags.',
                'The names of the tags will start with http.request.header. prefix,',
                'followed by lowercase request header name',
            ],
        ],
        [
            'name' => 'signalfx.recorded_value_max_length',
            'default' => '1200',
            'commented' => true,
            'description' => 'Sets the maximum length of tag values in serialized format',
        ],
        [
            'name' => 'signalfx.error_stack_max_length',
            'default' => '8192',
            'commented' => true,
            'description' => 'Sets the maximum length for the error.stack tag value in serialized format',
        ],
        [
            'name' => 'signalfx.trace.json',
            'default' => 'false',
            'commented' => true,
            'description' => 'Sets whether automatic tracing of json_encode and json_decode functions is enabled',
        ],
        [
            'name' => 'signalfx.trace.file_get_contents',
            'default' => 'false',
            'commented' => true,
            'description' => 'Sets whether automatic tracing of file_get_contents function is enabled',
        ],
        [
            'name' => 'signalfx.drupal_rename_root_span',
            'default' => 'true',
            'commented' => true,
            'description' => 'Sets whether the root span of the request is set to Drupal route path',
        ],
        [
            'name' => 'signalfx.trace.http_client_split_by_domain',
            'default' => 'Off',
            'commented' => true,
            'description' => 'Sets the service name of spans generated for HTTP clients\' requests to host-<hostname>',
        ],
        [
            'name' => 'signalfx.trace.url_as_resource_names_enabled',
            'default' => 'On',
            'commented' => true,
            'description' => [
                'Enables URL to resource name normalization',
            ],
        ],
        [
            'name' => 'signalfx.trace.resource_uri_fragment_regex',
            'default' => '',
            'commented' => true,
            'description' => [
                'Configures obfuscation patterns based on regex',
            ],
        ],
        [
            'name' => 'signalfx.trace.resource_uri_mapping_incoming',
            'default' => '',
            'commented' => true,
            'description' => [
                'Configures obfuscation path fragments for incoming requests',
            ],
        ],
        [
            'name' => 'signalfx.trace.resource_uri_mapping_outgoing',
            'default' => '',
            'commented' => true,
            'description' => [
                'Configures obfuscation path fragments for outgoing requests',
            ],
        ],
        [
            'name' => 'signalfx.service_mapping',
            'default' => '',
            'commented' => true,
            'description' => [
                'Changes the default name of an APM integration. Rename one or more integrations at a time, for example:',
                '"pdo:payments-db,mysqli:orders-db"',
            ],
        ],
        [
            'name' => 'signalfx.tags',
            'default' => '',
            'commented' => true,
            'description' => 'Tags to be set on all spans, for example: "key1:value1,key2:value2"',
        ],
        [
            'name' => 'signalfx.trace.sample_rate',
            'default' => '1.0',
            'commented' => true,
            'description' => 'The sampling rate for the trace. Valid values are between 0.0 and 1.0',
        ],
        [
            'name' => 'signalfx.trace.sampling_rules',
            'default' => '',
            'commented' => true,
            'description' => [
                'A JSON encoded string to configure the sampling rate.',
                'Examples:',
                '  - Set the sample rate to 20%: \'[{"sample_rate": 0.2}]\'.',
                '  - Set the sample rate to 10% for services starting with `a` and span name `b` and set the sample rate to 20%',
                '    for all other services: \'[{"service": "a.*", "name": "b", "sample_rate": 0.1}, {"sample_rate": 0.2}]\'',
                '**Note** that the JSON object must be included in single quotes (\') to avoid problems with escaping of the',
                'double quote (") character.',
            ],
        ],
        [
            'name' => 'signalfx.trace.<integration_name>_enabled',
            'default' => 'On',
            'commented' => true,
            'description' => [
                'Whether a specific integration is enabled',
            ],
        ],
        [
            'name' => 'signalfx.trace.<integration_name>_analytics_enabled',
            'default' => 'Off',
            'commented' => true,
            'description' => [
                'Whether analytics for the integration is enabled',
            ],
        ],
        [
            'name' => 'signalfx.trace.<integration_name>_analytics_sample_rate',
            'default' => '1.0',
            'commented' => true,
            'description' => [
                'Sampling rate for analyzed spans. Valid values are between 0.0 and 1.0',
            ],
        ],
        [
            'name' => 'signalfx.distributed_tracing',
            'default' => 'On',
            'commented' => true,
            'description' => 'Enables distributed tracing',
        ],
        [
            'name' => 'signalfx.trace.analytics_enabled',
            'default' => 'Off',
            'commented' => true,
            'description' => 'Global switch for trace analytics',
        ],
        [
            'name' => 'signalfx.trace.bgs_connect_timeout',
            'default' => '2000',
            'commented' => true,
            'description' => 'Set connection timeout in milliseconds while connecting to the endpoint',
        ],
        [
            'name' => 'signalfx.trace.bgs_timeout',
            'default' => '5000',
            'commented' => true,
            'description' => 'Set request timeout in milliseconds while sending payloads to the endpoint',
        ],
        [
            'name' => 'signalfx.trace.spans_limit',
            'default' => '1000',
            'commented' => true,
            'description' => 'signalfx.trace.spans_limit = 1000',
        ],
        [
            'name' => 'signalfx.trace.retain_thread_capabilities',
            'default' => 'Off',
            'commented' => true,
            'description' => [
                'Only for Linux. Set to `true` to retain capabilities on SignalFX background threads when you change the effective',
                'user ID. This option does not affect most setups, but some modules - to date SignalFX is only aware of Apache`s',
                'mod-ruid2 - may invoke `setuid()` or similar syscalls, leading to crashes or loss of functionality as it loses',
                'capabilities.',
                '**Note** Enabling this option may compromise security. This option, standalone, does not pose a security risk.',
                'However, an attacker being able to exploit a vulnerability in PHP or web server may be able to escalate privileges',
                'with relative ease, if the web server or PHP were started with full capabilities, as the background threads will',
                'retain their original capabilities. SignalFX recommends restricting the capabilities of the web server with the',
                'setcap utility.',
            ],
        ],
    ];
    // phpcs:enable Generic.Files.LineLength.TooLong
}

/**
 * @return string[]
 */
function get_supported_php_versions()
{
    return ['7.0', '7.1', '7.2', '7.3', '7.4', '8.0', '8.1', '8.2', '8.3'];
}

main();
