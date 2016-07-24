<?php

/*
 * This file is part of Composer.
 *
 * (c) Nils Adermann <naderman@naderman.de>
 *     Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

process(is_array($argv) ? $argv : array());

/**
 * processes the installer
 */
function process($argv)
{
    // Determine ANSI output from --ansi and --no-ansi flags
    setUseAnsi($argv);

    if (in_array('--help', $argv)) {
        displayHelp();
        exit(0);
    }

    $check      = in_array('--check', $argv);
    $help       = in_array('--help', $argv);
    $force      = in_array('--force', $argv);
    $quiet      = in_array('--quiet', $argv);
    $channel    = in_array('--snapshot', $argv) ? 'snapshot' : (in_array('--preview', $argv) ? 'preview' : 'stable');
    $disableTls = in_array('--disable-tls', $argv);
    $installDir = getOptValue('--install-dir', $argv, false);
    $version    = getOptValue('--version', $argv, false);
    $filename   = getOptValue('--filename', $argv, 'composer.phar');
    $cafile     = getOptValue('--cafile', $argv, false);

    if (!checkParams($installDir, $version, $cafile)) {
        exit(1);
    }

    $ok = checkPlatform($warnings, $quiet, $disableTls);

    if ($check) {
        // Only show warnings if we haven't output any errors
        if ($ok) {
            showWarnings($warnings);
            showSecurityWarning($disableTls);
        }
        exit($ok ? 0 : 1);
    }

    if ($ok || $force) {
        installComposer($version, $installDir, $filename, $quiet, $disableTls, $cafile, $channel);
        showWarnings($warnings);
        showSecurityWarning($disableTls);
        exit(0);
    }

    exit(1);
}

/**
 * displays the help
 */
function displayHelp()
{
    echo <<<EOF
Composer Installer
------------------
Options
--help               this help
--check              for checking environment only
--force              forces the installation
--ansi               force ANSI color output
--no-ansi            disable ANSI color output
--quiet              do not output unimportant messages
--install-dir="..."  accepts a target installation directory
--preview            install the latest version from the preview (alpha/beta/rc) channel instead of stable
--snapshot           install the latest version from the snapshot (dev builds) channel instead of stable
--version="..."      accepts a specific version to install instead of the latest
--filename="..."     accepts a target filename (default: composer.phar)
--disable-tls        disable SSL/TLS security for file downloads
--cafile="..."       accepts a path to a Certificate Authority (CA) certificate file for SSL/TLS verification

EOF;
}

/**
 * Sets the USE_ANSI define for colorizing output
 *
 * @param array $argv Command-line arguments
 */
function setUseAnsi($argv)
{
    // --no-ansi wins over --ansi
    if (in_array('--no-ansi', $argv)) {
        define('USE_ANSI', false);
    } elseif (in_array('--ansi', $argv)) {
        define('USE_ANSI', true);
    } else {
        // On Windows, default to no ANSI, except in ANSICON and ConEmu.
        // Everywhere else, default to ANSI if stdout is a terminal.
        define(
            'USE_ANSI',
            (DIRECTORY_SEPARATOR == '\\')
                ? (false !== getenv('ANSICON') || 'ON' === getenv('ConEmuANSI'))
                : (function_exists('posix_isatty') && posix_isatty(1))
        );
    }
}

/**
 * Returns the value of a command-line option
 *
 * @param string $opt The command-line option to check
 * @param array $argv Command-line arguments
 * @param mixed $default Default value to be returned
 *
 * @return mixed The command-line value or the default
 */
function getOptValue($opt, $argv, $default)
{
    $optLength = strlen($opt);

    foreach ($argv as $key => $value) {
        $next = $key + 1;
        if (0 === strpos($value, $opt)) {
            if ($optLength === strlen($value) && isset($argv[$next])) {
                return trim($argv[$next]);
            } else {
                return trim(substr($value, $optLength + 1));
            }
        }
    }

    return $default;
}

/**
 * Checks that user-supplied params are valid
 *
 * @param mixed $installDir The required istallation directory
 * @param mixed $version The required composer version to install
 * @param mixed $cafile Certificate Authority file
 *
 * @return bool True if the supplied params are okay
 */
function checkParams($installDir, $version, $cafile)
{
    $result = true;

    if (false !== $installDir && !is_dir($installDir)) {
        out("The defined install dir ({$installDir}) does not exist.", 'info');
        $result = false;
    }

    if (false !== $version && 1 !== preg_match('/^\d+\.\d+\.\d+(\-(alpha|beta)\d+)*$/', $version)) {
        out("The defined install version ({$version}) does not match release pattern.", 'info');
        $result = false;
    }

    if (false !== $cafile && (!file_exists($cafile) || !is_readable($cafile))) {
        out("The defined Certificate Authority (CA) cert file ({$cafile}) does not exist or is not readable.", 'info');
        $result = false;
    }
    return $result;
}

/**
 * Checks the platform for possible issues running Composer
 *
 * Errors are written to the output, warnings are saved for later display.
 *
 * @param array $warnings Populated by method, to be shown later
 * @param bool $quiet Quiet mode
 * @param bool $disableTls Bypass tls
 *
 * @return bool True if there are no errors
 */
function checkPlatform(&$warnings, $quiet, $disableTls)
{
    getPlatformIssues($errors, $warnings);

    // Make openssl warning an error if tls has not been specifically disabled
    if (isset($warnings['openssl']) && !$disableTls) {
        $errors['openssl'] = $warnings['openssl'];
        unset($warnings['openssl']);
    }

    if (!empty($errors)) {
        out('Some settings on your machine make Composer unable to work properly.', 'error');
        out('Make sure that you fix the issues listed below and run this script again:', 'error');
        outputIssues($errors);
        return false;
    }

    if (empty($warnings) && !$quiet) {
        out('All settings correct for using Composer', 'success');
    }
    return true;
}

/**
 * Checks platform configuration for common incompatibility issues
 *
 * @param array $errors Populated by method
 * @param array $warnings Populated by method
 *
 * @return bool If any errors or warnings have been found
 */
function getPlatformIssues(&$errors, &$warnings)
{
    $errors = array();
    $warnings = array();

    if ($iniPath = php_ini_loaded_file()) {
        $iniMessage = PHP_EOL.'The php.ini used by your command-line PHP is: ' . $iniPath;
    } else {
        $iniMessage = PHP_EOL.'A php.ini file does not exist. You will have to create one.';
    }
    $iniMessage .= PHP_EOL.'If you can not modify the ini file, you can also run `php -d option=value` to modify ini values on the fly. You can use -d multiple times.';

    if (ini_get('detect_unicode')) {
        $errors['unicode'] = array(
            'The detect_unicode setting must be disabled.',
            'Add the following to the end of your `php.ini`:',
            '    detect_unicode = Off',
            $iniMessage
        );
    }

    if (extension_loaded('suhosin')) {
        $suhosin = ini_get('suhosin.executor.include.whitelist');
        $suhosinBlacklist = ini_get('suhosin.executor.include.blacklist');
        if (false === stripos($suhosin, 'phar') && (!$suhosinBlacklist || false !== stripos($suhosinBlacklist, 'phar'))) {
            $errors['suhosin'] = array(
                'The suhosin.executor.include.whitelist setting is incorrect.',
                'Add the following to the end of your `php.ini` or suhosin.ini (Example path [for Debian]: /etc/php5/cli/conf.d/suhosin.ini):',
                '    suhosin.executor.include.whitelist = phar '.$suhosin,
                $iniMessage
            );
        }
    }

    if (!function_exists('json_decode')) {
        $errors['json'] = array(
            'The json extension is missing.',
            'Install it or recompile php without --disable-json'
        );
    }

    if (!extension_loaded('Phar')) {
        $errors['phar'] = array(
            'The phar extension is missing.',
            'Install it or recompile php without --disable-phar'
        );
    }

    if (!extension_loaded('filter')) {
        $errors['filter'] = array(
            'The filter extension is missing.',
            'Install it or recompile php without --disable-filter'
        );
    }

    if (!extension_loaded('hash')) {
        $errors['hash'] = array(
            'The hash extension is missing.',
            'Install it or recompile php without --disable-hash'
        );
    }

    if (!extension_loaded('iconv') && !extension_loaded('mbstring')) {
        $errors['iconv_mbstring'] = array(
            'The iconv OR mbstring extension is required and both are missing.',
            'Install either of them or recompile php without --disable-iconv'
        );
    }

    if (!ini_get('allow_url_fopen')) {
        $errors['allow_url_fopen'] = array(
            'The allow_url_fopen setting is incorrect.',
            'Add the following to the end of your `php.ini`:',
            '    allow_url_fopen = On',
            $iniMessage
        );
    }

    if (extension_loaded('ionCube Loader') && ioncube_loader_iversion() < 40009) {
        $ioncube = ioncube_loader_version();
        $errors['ioncube'] = array(
            'Your ionCube Loader extension ('.$ioncube.') is incompatible with Phar files.',
            'Upgrade to ionCube 4.0.9 or higher or remove this line (path may be different) from your `php.ini` to disable it:',
            '    zend_extension = /usr/lib/php5/20090626+lfs/ioncube_loader_lin_5.3.so',
            $iniMessage
        );
    }

    if (version_compare(PHP_VERSION, '5.3.2', '<')) {
        $errors['php'] = array(
            'Your PHP ('.PHP_VERSION.') is too old, you must upgrade to PHP 5.3.2 or higher.'
        );
    }

    if (version_compare(PHP_VERSION, '5.3.4', '<')) {
        $warnings['php'] = array(
            'Your PHP ('.PHP_VERSION.') is quite old, upgrading to PHP 5.3.4 or higher is recommended.',
            'Composer works with 5.3.2+ for most people, but there might be edge case issues.'
        );
    }

    if (!extension_loaded('openssl')) {
        $warnings['openssl'] = array(
            'The openssl extension is missing, which means that secure HTTPS transfers are impossible.',
            'If possible you should enable it or recompile php with --with-openssl'
        );
    }

    if (extension_loaded('openssl') && OPENSSL_VERSION_NUMBER < 0x1000100f) {
        // Attempt to parse version number out, fallback to whole string value.
        $opensslVersion = trim(strstr(OPENSSL_VERSION_TEXT, ' '));
        $opensslVersion = substr($opensslVersion, 0, strpos($opensslVersion, ' '));
        $opensslVersion = $opensslVersion ? $opensslVersion : OPENSSL_VERSION_TEXT;

        $warnings['openssl_version'] = array(
            'The OpenSSL library ('.$opensslVersion.') used by PHP does not support TLSv1.2 or TLSv1.1.',
            'If possible you should upgrade OpenSSL to version 1.0.1 or above.'
        );
    }

    if (!defined('HHVM_VERSION') && !extension_loaded('apcu') && ini_get('apc.enable_cli')) {
        $warnings['apc_cli'] = array(
            'The apc.enable_cli setting is incorrect.',
            'Add the following to the end of your `php.ini`:',
            '    apc.enable_cli = Off',
            $iniMessage
        );
    }

    if (extension_loaded('xdebug')) {
        $warnings['xdebug_loaded'] = array(
            'The xdebug extension is loaded, this can slow down Composer a little.',
            'Disabling it when using Composer is recommended.'
        );

        if (ini_get('xdebug.profiler_enabled')) {
            $warnings['xdebug_profile'] = array(
                'The xdebug.profiler_enabled setting is enabled, this can slow down Composer a lot.',
                'Add the following to the end of your `php.ini` to disable it:',
                '    xdebug.profiler_enabled = 0',
                $iniMessage
            );
        }
    }

    ob_start();
    phpinfo(INFO_GENERAL);
    $phpinfo = ob_get_clean();
    if (preg_match('{Configure Command(?: *</td><td class="v">| *=> *)(.*?)(?:</td>|$)}m', $phpinfo, $match)) {
        $configure = $match[1];

        if (false !== strpos($configure, '--enable-sigchild')) {
            $warnings['sigchild'] = array(
                'PHP was compiled with --enable-sigchild which can cause issues on some platforms.',
                'Recompile it without this flag if possible, see also:',
                '    https://bugs.php.net/bug.php?id=22999'
            );
        }

        if (false !== strpos($configure, '--with-curlwrappers')) {
            $warnings['curlwrappers'] = array(
                'PHP was compiled with --with-curlwrappers which will cause issues with HTTP authentication and GitHub.',
                'Recompile it without this flag if possible'
            );
        }
    }

    // Stringify the message arrays
    foreach ($errors as $key => $value) {
        $errors[$key] = PHP_EOL.implode(PHP_EOL, $value);
    }

    foreach ($warnings as $key => $value) {
        $warnings[$key] = PHP_EOL.implode(PHP_EOL, $value);
    }

    return !empty($errors) || !empty($warnings);
}


/**
 * Outputs an array of issues
 *
 * @param array $issues
 */
function outputIssues($issues)
{
    foreach ($issues as $issue) {
        out($issue, 'info');
    }
    out('');
}

/**
 * Outputs any warnings found
 *
 * @param array $warnings
 */
function showWarnings($warnings)
{
    if (!empty($warnings)) {
        out('Some settings on your machine may cause stability issues with Composer.', 'error');
        out('If you encounter issues, try to change the following:', 'error');
        outputIssues($warnings);
    }
}

/**
 * Outputs an end of process warning if tls has been bypassed
 *
 * @param bool $disableTls Bypass tls
 */
function showSecurityWarning($disableTls)
{
    if ($disableTls) {
        out('You have instructed the Installer not to enforce SSL/TLS security on remote HTTPS requests.', 'info');
        out('This will leave all downloads during installation vulnerable to Man-In-The-Middle (MITM) attacks', 'info');
    }
}

/**
 * installs composer to the current working directory
 */
function installComposer($version, $installDir, $filename, $quiet, $disableTls, $cafile, $channel)
{
    $installPath = (is_dir($installDir) ? rtrim($installDir, '/').'/' : '') . $filename;
    $installDir  = realpath($installDir) ? realpath($installDir) : getcwd();
    $file        = $installDir.DIRECTORY_SEPARATOR.$filename;

    if (is_readable($file)) {
        @unlink($file);
    }

    $home = getHomeDir();

    if (!is_dir($home)) {
        @mkdir($home, 0777, true);
    }

    file_put_contents($home.'/keys.dev.pub', <<<DEVPUBKEY
-----BEGIN PUBLIC KEY-----
MIICIjANBgkqhkiG9w0BAQEFAAOCAg8AMIICCgKCAgEAnBDHjZS6e0ZMoK3xTD7f
FNCzlXjX/Aie2dit8QXA03pSrOTbaMnxON3hUL47Lz3g1SC6YJEMVHr0zYq4elWi
i3ecFEgzLcj+pZM5X6qWu2Ozz4vWx3JYo1/a/HYdOuW9e3lwS8VtS0AVJA+U8X0A
hZnBmGpltHhO8hPKHgkJtkTUxCheTcbqn4wGHl8Z2SediDcPTLwqezWKUfrYzu1f
o/j3WFwFs6GtK4wdYtiXr+yspBZHO3y1udf8eFFGcb2V3EaLOrtfur6XQVizjOuk
8lw5zzse1Qp/klHqbDRsjSzJ6iL6F4aynBc6Euqt/8ccNAIz0rLjLhOraeyj4eNn
8iokwMKiXpcrQLTKH+RH1JCuOVxQ436bJwbSsp1VwiqftPQieN+tzqy+EiHJJmGf
TBAbWcncicCk9q2md+AmhNbvHO4PWbbz9TzC7HJb460jyWeuMEvw3gNIpEo2jYa9
pMV6cVqnSa+wOc0D7pC9a6bne0bvLcm3S+w6I5iDB3lZsb3A9UtRiSP7aGSo7D72
8tC8+cIgZcI7k9vjvOqH+d7sdOU2yPCnRY6wFh62/g8bDnUpr56nZN1G89GwM4d4
r/TU7BQQIzsZgAiqOGXvVklIgAMiV0iucgf3rNBLjjeNEwNSTTG9F0CtQ+7JLwaE
wSEuAuRm+pRqi8BRnQ/GKUcCAwEAAQ==
-----END PUBLIC KEY-----
DEVPUBKEY
);
    file_put_contents($home.'/keys.tags.pub', <<<TAGSPUBKEY
-----BEGIN PUBLIC KEY-----
MIICIjANBgkqhkiG9w0BAQEFAAOCAg8AMIICCgKCAgEA0Vi/2K6apCVj76nCnCl2
MQUPdK+A9eqkYBacXo2wQBYmyVlXm2/n/ZsX6pCLYPQTHyr5jXbkQzBw8SKqPdlh
vA7NpbMeNCz7wP/AobvUXM8xQuXKbMDTY2uZ4O7sM+PfGbptKPBGLe8Z8d2sUnTO
bXtX6Lrj13wkRto7st/w/Yp33RHe9SlqkiiS4MsH1jBkcIkEHsRaveZzedUaxY0M
mba0uPhGUInpPzEHwrYqBBEtWvP97t2vtfx8I5qv28kh0Y6t+jnjL1Urid2iuQZf
noCMFIOu4vksK5HxJxxrN0GOmGmwVQjOOtxkwikNiotZGPR4KsVj8NnBrLX7oGuM
nQvGciiu+KoC2r3HDBrpDeBVdOWxDzT5R4iI0KoLzFh2pKqwbY+obNPS2bj+2dgJ
rV3V5Jjry42QOCBN3c88wU1PKftOLj2ECpewY6vnE478IipiEu7EAdK8Zwj2LmTr
RKQUSa9k7ggBkYZWAeO/2Ag0ey3g2bg7eqk+sHEq5ynIXd5lhv6tC5PBdHlWipDK
tl2IxiEnejnOmAzGVivE1YGduYBjN+mjxDVy8KGBrjnz1JPgAvgdwJ2dYw4Rsc/e
TzCFWGk/HM6a4f0IzBWbJ5ot0PIi4amk07IotBXDWwqDiQTwyuGCym5EqWQ2BD95
RGv89BPD+2DLnJysngsvVaUCAwEAAQ==
-----END PUBLIC KEY-----
TAGSPUBKEY
);

    if (false === $disableTls && empty($cafile) && !HttpClient::getSystemCaRootBundlePath()) {
        $errorHandler = new ErrorHandler();
        set_error_handler(array($errorHandler, 'handleError'));

        $target = $home . '/cacert.pem';
        $write = file_put_contents($target, HttpClient::getPackagedCaFile(), LOCK_EX);
        @chmod($target, 0644);

        restore_error_handler();

        if (!$write) {
            throw new RuntimeException('Unable to write bundled cacert.pem to: '.$target);
        }
        $cafile = $target;
    }

    $httpClient = new HttpClient($disableTls, $cafile);
    $uriScheme = false === $disableTls ? 'https' : 'http';

    if (!$version) {
        $versions = json_decode($httpClient->get($uriScheme . '://getcomposer.org/versions'), true);
        foreach ($versions[$channel] as $candidate) {
            if ($candidate['min-php'] <= PHP_VERSION_ID) {
                $version = $candidate['version'];
                $downloadUrl = $candidate['path'];
                break;
            }
        }

        if (!$version) {
            out('None of the '.count($versions[$channel]).' '.$channel.' version(s) of Composer matches your PHP version ('.PHP_VERSION.' / ID: '.PHP_VERSION_ID.')', 'error');
            exit(1);
        }
    } else {
        $downloadUrl = "/download/{$version}/composer.phar";
    }

    $retries = 3;
    while ($retries--) {
        if (!$quiet) {
            out("Downloading $version...", 'info');
        }

        $url       = "{$uriScheme}://getcomposer.org{$downloadUrl}";
        $errorHandler = new ErrorHandler();
        set_error_handler(array($errorHandler, 'handleError'));

        // download signature file
        if (false === $disableTls) {
            $signature = $httpClient->get($url.'.sig');
            if (!$signature) {
                out('Download failed: '.$errorHandler->message, 'error');
            } else {
                $signature = json_decode($signature, true);
                $signature = base64_decode($signature['sha384']);
            }
        }

        $fh = fopen($file, 'w');
        if (!$fh) {
            out('Could not create file '.$file.': '.$errorHandler->message, 'error');
        }
        if (!fwrite($fh, $httpClient->get($url))) {
            out('Download failed: '.$errorHandler->message, 'error');
        }
        fclose($fh);

        restore_error_handler();
        if ($errorHandler->message) {
            continue;
        }

        try {
            // create a temp file ending in .phar since the Phar class only accepts that
            if ('.phar' !== substr($file, -5)) {
                copy($file, $file.'.tmp.phar');
                $pharFile = $file.'.tmp.phar';
            } else {
                $pharFile = $file;
            }

            // verify signature
            if (false === $disableTls) {
                $pubkeyid = openssl_pkey_get_public('file://'.$home.'/' . (preg_match('{^[0-9a-f]{40}$}', $version) ? 'keys.dev.pub' : 'keys.tags.pub'));
                $algo = defined('OPENSSL_ALGO_SHA384') ? OPENSSL_ALGO_SHA384 : 'SHA384';
                if (!in_array('SHA384', openssl_get_md_methods())) {
                    out('SHA384 is not supported by your openssl extension, could not verify the phar file integrity', 'error');
                    exit(1);
                }
                $verified = 1 === openssl_verify(file_get_contents($file), $signature, $pubkeyid, $algo);
                openssl_free_key($pubkeyid);
                if (!$verified) {
                    out('Signature mismatch, could not verify the phar file integrity', 'error');
                    exit(1);
                }
            }

            // test the phar validity
            if (!ini_get('phar.readonly')) {
                $phar = new Phar($pharFile);
                // free the variable to unlock the file
                unset($phar);
            }
            // clean up temp file if needed
            if ($file !== $pharFile) {
                unlink($pharFile);
            }
            break;
        } catch (Exception $e) {
            if (!$e instanceof UnexpectedValueException && !$e instanceof PharException) {
                throw $e;
            }
            // clean up temp file if needed
            if ($file !== $pharFile) {
                unlink($pharFile);
            }
            unlink($file);
            if ($retries) {
                if (!$quiet) {
                   out('The download is corrupt, retrying...', 'error');
                }
            } else {
                out('The download is corrupt ('.$e->getMessage().'), aborting.', 'error');
                exit(1);
            }
        }
    }

    if ($errorHandler->message) {
        out('The download failed repeatedly, aborting.', 'error');
        exit(1);
    }

    chmod($file, 0755);

    if (!$quiet) {
        out(PHP_EOL."Composer successfully installed to: " . $file, 'success', false);
        out(PHP_EOL."Use it: php $installPath", 'info');
    }
}

/**
 * colorize output
 */
function out($text, $color = null, $newLine = true)
{
    $styles = array(
        'success' => "\033[0;32m%s\033[0m",
        'error' => "\033[31;31m%s\033[0m",
        'info' => "\033[33;33m%s\033[0m"
    );

    $format = '%s';

    if (isset($styles[$color]) && USE_ANSI) {
        $format = $styles[$color];
    }

    if ($newLine) {
        $format .= PHP_EOL;
    }

    printf($format, $text);
}

/**
 * Returns the system-dependent Composer home location, which may not exist
 *
 * @return string
 */
function getHomeDir()
{
    $home = getenv('COMPOSER_HOME');

    if (!$home) {
        $userDir = getUserDir();

        if (defined('PHP_WINDOWS_VERSION_MAJOR')) {
            $home = $userDir.'/Composer';
        } else {
            $home = $userDir.'/.composer';

            if (!is_dir($home) && useXdg()) {
                // XDG Base Directory Specifications
                if (!($xdgConfig = getenv('XDG_CONFIG_HOME'))) {
                    $xdgConfig = $userDir.'/.config';
                }
                $home = $xdgConfig.'/composer';
            }
        }
    }
    return $home;
}

/**
 * Returns the location of the user directory from the environment
 * @throws Runtime Exception If the environment value does not exists
 *
 * @return string
 */
function getUserDir()
{
    $userEnv = defined('PHP_WINDOWS_VERSION_MAJOR') ? 'APPDATA' : 'HOME';
    $userDir = getenv($userEnv);

    if (!$userDir) {
        throw new RuntimeException('The '.$userEnv.' or COMPOSER_HOME environment variable must be set for composer to run correctly');
    }

    return rtrim(strtr($userDir, '\\', '/'), '/');
}

/**
 * @return bool
 */
function useXdg()
{
    foreach (array_keys($_SERVER) as $key) {
        if (substr($key, 0, 4) === 'XDG_') {
            return true;
        }
    }
    return false;
}

function validateCaFile($contents)
{
    // assume the CA is valid if php is vulnerable to
    // https://www.sektioneins.de/advisories/advisory-012013-php-openssl_x509_parse-memory-corruption-vulnerability.html
    if (
        PHP_VERSION_ID <= 50327
        || (PHP_VERSION_ID >= 50400 && PHP_VERSION_ID < 50422)
        || (PHP_VERSION_ID >= 50500 && PHP_VERSION_ID < 50506)
    ) {
        return !empty($contents);
    }

    return (bool) openssl_x509_parse($contents);
}

class ErrorHandler
{
    public $message = '';

    public function handleError($code, $msg)
    {
        if ($this->message) {
            $this->message .= "\n";
        }
        $this->message .= preg_replace('{^copy\(.*?\): }', '', $msg);
    }
}

class HttpClient {

    private $options = array('http' => array());
    private $disableTls = false;

    public function __construct($disableTls = false, $cafile = false)
    {
        $this->disableTls = $disableTls;
        if ($this->disableTls === false) {
            if (!empty($cafile) && !is_dir($cafile)) {
                if (!is_readable($cafile) || !validateCaFile(file_get_contents($cafile))) {
                    throw new RuntimeException('The configured cafile (' .$cafile. ') was not valid or could not be read.');
                }
            }
            $options = $this->getTlsStreamContextDefaults($cafile);
            $this->options = array_replace_recursive($this->options, $options);
        }
    }

    public function get($url)
    {
        $context = $this->getStreamContext($url);
        $result = file_get_contents($url, false, $context);

        if ($result && extension_loaded('zlib')) {
            $decode = false;
            foreach ($http_response_header as $header) {
                if (preg_match('{^content-encoding: *gzip *$}i', $header)) {
                    $decode = true;
                    continue;
                } elseif (preg_match('{^HTTP/}i', $header)) {
                    $decode = false;
                }
            }

            if ($decode) {
                if (version_compare(PHP_VERSION, '5.4.0', '>=')) {
                    $result = zlib_decode($result);
                } else {
                    // work around issue with gzuncompress & co that do not work with all gzip checksums
                    $result = file_get_contents('compress.zlib://data:application/octet-stream;base64,'.base64_encode($result));
                }

                if (!$result) {
                    throw new RuntimeException('Failed to decode zlib stream');
                }
            }
        }

        return $result;
    }

    protected function getStreamContext($url)
    {
        if ($this->disableTls === false) {
            $host = parse_url($url, PHP_URL_HOST);
            if (PHP_VERSION_ID < 50600) {
                $this->options['ssl']['CN_match'] = $host;
                $this->options['ssl']['SNI_server_name'] = $host;
            }
        }
        // Keeping the above mostly isolated from the code copied from Composer.
        return $this->getMergedStreamContext($url);
    }

    protected function getTlsStreamContextDefaults($cafile)
    {
        $ciphers = implode(':', array(
            'ECDHE-RSA-AES128-GCM-SHA256',
            'ECDHE-ECDSA-AES128-GCM-SHA256',
            'ECDHE-RSA-AES256-GCM-SHA384',
            'ECDHE-ECDSA-AES256-GCM-SHA384',
            'DHE-RSA-AES128-GCM-SHA256',
            'DHE-DSS-AES128-GCM-SHA256',
            'kEDH+AESGCM',
            'ECDHE-RSA-AES128-SHA256',
            'ECDHE-ECDSA-AES128-SHA256',
            'ECDHE-RSA-AES128-SHA',
            'ECDHE-ECDSA-AES128-SHA',
            'ECDHE-RSA-AES256-SHA384',
            'ECDHE-ECDSA-AES256-SHA384',
            'ECDHE-RSA-AES256-SHA',
            'ECDHE-ECDSA-AES256-SHA',
            'DHE-RSA-AES128-SHA256',
            'DHE-RSA-AES128-SHA',
            'DHE-DSS-AES128-SHA256',
            'DHE-RSA-AES256-SHA256',
            'DHE-DSS-AES256-SHA',
            'DHE-RSA-AES256-SHA',
            'AES128-GCM-SHA256',
             'AES256-GCM-SHA384',
            'ECDHE-RSA-RC4-SHA',
            'ECDHE-ECDSA-RC4-SHA',
            'AES128',
            'AES256',
            'RC4-SHA',
            'HIGH',
            '!aNULL',
            '!eNULL',
            '!EXPORT',
            '!DES',
            '!3DES',
            '!MD5',
            '!PSK'
        ));

        /**
         * CN_match and SNI_server_name are only known once a URL is passed.
         * They will be set in the getOptionsForUrl() method which receives a URL.
         *
         * cafile or capath can be overridden by passing in those options to constructor.
         */
        $options = array(
            'ssl' => array(
                'ciphers' => $ciphers,
                'verify_peer' => true,
                'verify_depth' => 7,
                'SNI_enabled' => true,
            )
        );

        /**
         * Attempt to find a local cafile or throw an exception.
         * The user may go download one if this occurs.
         */
        if (!$cafile) {
            $cafile = self::getSystemCaRootBundlePath();
        }
        if (is_dir($cafile)) {
            $options['ssl']['capath'] = $cafile;
        } elseif ($cafile) {
            $options['ssl']['cafile'] = $cafile;
        } else {
            throw new RuntimeException('A valid cafile could not be located automatically.');
        }

        /**
         * Disable TLS compression to prevent CRIME attacks where supported.
         */
        if (version_compare(PHP_VERSION, '5.4.13') >= 0) {
            $options['ssl']['disable_compression'] = true;
        }

        return $options;
    }

    /**
     * function copied from Composer\Util\StreamContextFactory::getContext
     *
     * Any changes should be applied there as well, or backported here.
     *
     * @param string $url URL the context is to be used for
     * @return resource Default context
     * @throws \RuntimeException if https proxy required and OpenSSL uninstalled
     */
    protected function getMergedStreamContext($url)
    {
        $options = $this->options;

        // Handle system proxy
        if (!empty($_SERVER['HTTP_PROXY']) || !empty($_SERVER['http_proxy'])) {
            // Some systems seem to rely on a lowercased version instead...
            $proxy = parse_url(!empty($_SERVER['http_proxy']) ? $_SERVER['http_proxy'] : $_SERVER['HTTP_PROXY']);
        }

        if (!empty($proxy)) {
            $proxyURL = isset($proxy['scheme']) ? $proxy['scheme'] . '://' : '';
            $proxyURL .= isset($proxy['host']) ? $proxy['host'] : '';

            if (isset($proxy['port'])) {
                $proxyURL .= ":" . $proxy['port'];
            } elseif ('http://' == substr($proxyURL, 0, 7)) {
                $proxyURL .= ":80";
            } elseif ('https://' == substr($proxyURL, 0, 8)) {
                $proxyURL .= ":443";
            }

            // http(s):// is not supported in proxy
            $proxyURL = str_replace(array('http://', 'https://'), array('tcp://', 'ssl://'), $proxyURL);

            if (0 === strpos($proxyURL, 'ssl:') && !extension_loaded('openssl')) {
                throw new RuntimeException('You must enable the openssl extension to use a proxy over https');
            }

            $options['http'] = array(
                'proxy'           => $proxyURL,
            );

            // enabled request_fulluri unless it is explicitly disabled
            switch (parse_url($url, PHP_URL_SCHEME)) {
                case 'http': // default request_fulluri to true
                    $reqFullUriEnv = getenv('HTTP_PROXY_REQUEST_FULLURI');
                    if ($reqFullUriEnv === false || $reqFullUriEnv === '' || (strtolower($reqFullUriEnv) !== 'false' && (bool) $reqFullUriEnv)) {
                        $options['http']['request_fulluri'] = true;
                    }
                    break;
                case 'https': // default request_fulluri to true
                    $reqFullUriEnv = getenv('HTTPS_PROXY_REQUEST_FULLURI');
                    if ($reqFullUriEnv === false || $reqFullUriEnv === '' || (strtolower($reqFullUriEnv) !== 'false' && (bool) $reqFullUriEnv)) {
                        $options['http']['request_fulluri'] = true;
                    }
                    break;
            }


            if (isset($proxy['user'])) {
                $auth = urldecode($proxy['user']);
                if (isset($proxy['pass'])) {
                    $auth .= ':' . urldecode($proxy['pass']);
                }
                $auth = base64_encode($auth);

                $options['http']['header'] = "Proxy-Authorization: Basic {$auth}\r\n";
            }
        }

        if (isset($options['http']['header'])) {
            $options['http']['header'] .= "Connection: close\r\n";
        } else {
            $options['http']['header'] = "Connection: close\r\n";
        }
        if (extension_loaded('zlib')) {
            $options['http']['header'] .= "Accept-Encoding: gzip\r\n";
        }
        $options['http']['header'] .= "User-Agent: Composer Installer\r\n";
        $options['http']['protocol_version'] = 1.1;

        return stream_context_create($options);
    }

    /**
    * This method was adapted from Sslurp.
    * https://github.com/EvanDotPro/Sslurp
    *
    * (c) Evan Coury <me@evancoury.com>
    *
    * For the full copyright and license information, please see below:
    *
    * Copyright (c) 2013, Evan Coury
    * All rights reserved.
    *
    * Redistribution and use in source and binary forms, with or without modification,
    * are permitted provided that the following conditions are met:
    *
    *     * Redistributions of source code must retain the above copyright notice,
    *       this list of conditions and the following disclaimer.
    *
    *     * Redistributions in binary form must reproduce the above copyright notice,
    *       this list of conditions and the following disclaimer in the documentation
    *       and/or other materials provided with the distribution.
    *
    * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
    * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
    * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
    * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
    * ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
    * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
    * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
    * ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
    * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
    * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
    */
    public static function getSystemCaRootBundlePath()
    {
        static $caPath = null;

        if ($caPath !== null) {
            return $caPath;
        }

        // If SSL_CERT_FILE env variable points to a valid certificate/bundle, use that.
        // This mimics how OpenSSL uses the SSL_CERT_FILE env variable.
        $envCertFile = getenv('SSL_CERT_FILE');
        if ($envCertFile && is_readable($envCertFile) && validateCaFile(file_get_contents($envCertFile))) {
            return $caPath = $envCertFile;
        }

        // If SSL_CERT_DIR env variable points to a valid certificate/bundle, use that.
        // This mimics how OpenSSL uses the SSL_CERT_FILE env variable.
        $envCertDir = getenv('SSL_CERT_DIR');
        if ($envCertDir && is_dir($envCertDir) && is_readable($envCertDir)) {
            return $caPath = $envCertDir;
        }

        $configured = ini_get('openssl.cafile');
        if ($configured && strlen($configured) > 0 && is_readable($configured) && validateCaFile(file_get_contents($configured))) {
            return $caPath = $configured;
        }

        $configured = ini_get('openssl.capath');
        if ($configured && is_dir($configured) && is_readable($configured)) {
            return $caPath = $configured;
        }

        $caBundlePaths = array(
            '/etc/pki/tls/certs/ca-bundle.crt', // Fedora, RHEL, CentOS (ca-certificates package)
            '/etc/ssl/certs/ca-certificates.crt', // Debian, Ubuntu, Gentoo, Arch Linux (ca-certificates package)
            '/etc/ssl/ca-bundle.pem', // SUSE, openSUSE (ca-certificates package)
            '/usr/local/share/certs/ca-root-nss.crt', // FreeBSD (ca_root_nss_package)
            '/usr/ssl/certs/ca-bundle.crt', // Cygwin
            '/opt/local/share/curl/curl-ca-bundle.crt', // OS X macports, curl-ca-bundle package
            '/usr/local/share/curl/curl-ca-bundle.crt', // Default cURL CA bunde path (without --with-ca-bundle option)
            '/usr/share/ssl/certs/ca-bundle.crt', // Really old RedHat?
            '/etc/ssl/cert.pem', // OpenBSD
            '/usr/local/etc/ssl/cert.pem', // FreeBSD 10.x
        );

        foreach ($caBundlePaths as $caBundle) {
            if (@is_readable($caBundle) && validateCaFile(file_get_contents($caBundle))) {
                return $caPath = $caBundle;
            }
        }

        foreach ($caBundlePaths as $caBundle) {
            $caBundle = dirname($caBundle);
            if (is_dir($caBundle) && glob($caBundle.'/*')) {
                return $caPath = $caBundle;
            }
        }

        return $caPath = false;
    }

    public static function getPackagedCaFile()
    {
        return <<<CACERT
##
## Bundle of CA Root Certificates
##
## Certificate data from Mozilla as of: Wed Oct 28 22:42:42 2015
##
## This is a bundle of X.509 certificates of public Certificate Authorities
## (CA). These were automatically extracted from Mozilla's root certificates
## file (certdata.txt).  This file can be found at
## https://raw.githubusercontent.com/bagder/ca-bundle/master/ca-bundle.crt
##
## It contains the certificates in PEM format and therefore
## can be directly used with curl / libcurl / php_curl, or with
## an Apache+mod_ssl webserver for SSL client authentication.
## Just configure this file as the SSLCACertificateFile.
##
## Conversion done with mk-ca-bundle.pl version 1.25.
## SHA1: 6d7d2f0a4fae587e7431be191a081ac1257d300a
##


Equifax Secure CA
=================
-----BEGIN CERTIFICATE-----
MIIDIDCCAomgAwIBAgIENd70zzANBgkqhkiG9w0BAQUFADBOMQswCQYDVQQGEwJVUzEQMA4GA1UE
ChMHRXF1aWZheDEtMCsGA1UECxMkRXF1aWZheCBTZWN1cmUgQ2VydGlmaWNhdGUgQXV0aG9yaXR5
MB4XDTk4MDgyMjE2NDE1MVoXDTE4MDgyMjE2NDE1MVowTjELMAkGA1UEBhMCVVMxEDAOBgNVBAoT
B0VxdWlmYXgxLTArBgNVBAsTJEVxdWlmYXggU2VjdXJlIENlcnRpZmljYXRlIEF1dGhvcml0eTCB
nzANBgkqhkiG9w0BAQEFAAOBjQAwgYkCgYEAwV2xWGcIYu6gmi0fCG2RFGiYCh7+2gRvE4RiIcPR
fM6fBeC4AfBONOziipUEZKzxa1NfBbPLZ4C/QgKO/t0BCezhABRP/PvwDN1Dulsr4R+AcJkVV5MW
8Q+XarfCaCMczE1ZMKxRHjuvK9buY0V7xdlfUNLjUA86iOe/FP3gx7kCAwEAAaOCAQkwggEFMHAG
A1UdHwRpMGcwZaBjoGGkXzBdMQswCQYDVQQGEwJVUzEQMA4GA1UEChMHRXF1aWZheDEtMCsGA1UE
CxMkRXF1aWZheCBTZWN1cmUgQ2VydGlmaWNhdGUgQXV0aG9yaXR5MQ0wCwYDVQQDEwRDUkwxMBoG
A1UdEAQTMBGBDzIwMTgwODIyMTY0MTUxWjALBgNVHQ8EBAMCAQYwHwYDVR0jBBgwFoAUSOZo+SvS
spXXR9gjIBBPM5iQn9QwHQYDVR0OBBYEFEjmaPkr0rKV10fYIyAQTzOYkJ/UMAwGA1UdEwQFMAMB
Af8wGgYJKoZIhvZ9B0EABA0wCxsFVjMuMGMDAgbAMA0GCSqGSIb3DQEBBQUAA4GBAFjOKer89961
zgK5F7WF0bnj4JXMJTENAKaSbn+2kmOeUJXRmm/kEd5jhW6Y7qj/WsjTVbJmcVfewCHrPSqnI0kB
BIZCe/zuf6IWUrVnZ9NA2zsmWLIodz2uFHdh1voqZiegDfqnc1zqcPGUIWVEX/r87yloqaKHee95
70+sB3c4
-----END CERTIFICATE-----

GlobalSign Root CA
==================
-----BEGIN CERTIFICATE-----
MIIDdTCCAl2gAwIBAgILBAAAAAABFUtaw5QwDQYJKoZIhvcNAQEFBQAwVzELMAkGA1UEBhMCQkUx
GTAXBgNVBAoTEEdsb2JhbFNpZ24gbnYtc2ExEDAOBgNVBAsTB1Jvb3QgQ0ExGzAZBgNVBAMTEkds
b2JhbFNpZ24gUm9vdCBDQTAeFw05ODA5MDExMjAwMDBaFw0yODAxMjgxMjAwMDBaMFcxCzAJBgNV
BAYTAkJFMRkwFwYDVQQKExBHbG9iYWxTaWduIG52LXNhMRAwDgYDVQQLEwdSb290IENBMRswGQYD
VQQDExJHbG9iYWxTaWduIFJvb3QgQ0EwggEiMA0GCSqGSIb3DQEBAQUAA4IBDwAwggEKAoIBAQDa
DuaZjc6j40+Kfvvxi4Mla+pIH/EqsLmVEQS98GPR4mdmzxzdzxtIK+6NiY6arymAZavpxy0Sy6sc
THAHoT0KMM0VjU/43dSMUBUc71DuxC73/OlS8pF94G3VNTCOXkNz8kHp1Wrjsok6Vjk4bwY8iGlb
Kk3Fp1S4bInMm/k8yuX9ifUSPJJ4ltbcdG6TRGHRjcdGsnUOhugZitVtbNV4FpWi6cgKOOvyJBNP
c1STE4U6G7weNLWLBYy5d4ux2x8gkasJU26Qzns3dLlwR5EiUWMWea6xrkEmCMgZK9FGqkjWZCrX
gzT/LCrBbBlDSgeF59N89iFo7+ryUp9/k5DPAgMBAAGjQjBAMA4GA1UdDwEB/wQEAwIBBjAPBgNV
HRMBAf8EBTADAQH/MB0GA1UdDgQWBBRge2YaRQ2XyolQL30EzTSo//z9SzANBgkqhkiG9w0BAQUF
AAOCAQEA1nPnfE920I2/7LqivjTFKDK1fPxsnCwrvQmeU79rXqoRSLblCKOzyj1hTdNGCbM+w6Dj
Y1Ub8rrvrTnhQ7k4o+YviiY776BQVvnGCv04zcQLcFGUl5gE38NflNUVyRRBnMRddWQVDf9VMOyG
j/8N7yy5Y0b2qvzfvGn9LhJIZJrglfCm7ymPAbEVtQwdpf5pLGkkeB6zpxxxYu7KyJesF12KwvhH
hm4qxFYxldBniYUr+WymXUadDKqC5JlR3XC321Y9YeRq4VzW9v493kHMB65jUr9TU/Qr6cf9tveC
X4XSQRjbgbMEHMUfpIBvFSDJ3gyICh3WZlXi/EjJKSZp4A==
-----END CERTIFICATE-----

GlobalSign Root CA - R2
=======================
-----BEGIN CERTIFICATE-----
MIIDujCCAqKgAwIBAgILBAAAAAABD4Ym5g0wDQYJKoZIhvcNAQEFBQAwTDEgMB4GA1UECxMXR2xv
YmFsU2lnbiBSb290IENBIC0gUjIxEzARBgNVBAoTCkdsb2JhbFNpZ24xEzARBgNVBAMTCkdsb2Jh
bFNpZ24wHhcNMDYxMjE1MDgwMDAwWhcNMjExMjE1MDgwMDAwWjBMMSAwHgYDVQQLExdHbG9iYWxT
aWduIFJvb3QgQ0EgLSBSMjETMBEGA1UEChMKR2xvYmFsU2lnbjETMBEGA1UEAxMKR2xvYmFsU2ln
bjCCASIwDQYJKoZIhvcNAQEBBQADggEPADCCAQoCggEBAKbPJA6+Lm8omUVCxKs+IVSbC9N/hHD6
ErPLv4dfxn+G07IwXNb9rfF73OX4YJYJkhD10FPe+3t+c4isUoh7SqbKSaZeqKeMWhG8eoLrvozp
s6yWJQeXSpkqBy+0Hne/ig+1AnwblrjFuTosvNYSuetZfeLQBoZfXklqtTleiDTsvHgMCJiEbKjN
S7SgfQx5TfC4LcshytVsW33hoCmEofnTlEnLJGKRILzdC9XZzPnqJworc5HGnRusyMvo4KD0L5CL
TfuwNhv2GXqF4G3yYROIXJ/gkwpRl4pazq+r1feqCapgvdzZX99yqWATXgAByUr6P6TqBwMhAo6C
ygPCm48CAwEAAaOBnDCBmTAOBgNVHQ8BAf8EBAMCAQYwDwYDVR0TAQH/BAUwAwEB/zAdBgNVHQ4E
FgQUm+IHV2ccHsBqBt5ZtJot39wZhi4wNgYDVR0fBC8wLTAroCmgJ4YlaHR0cDovL2NybC5nbG9i
YWxzaWduLm5ldC9yb290LXIyLmNybDAfBgNVHSMEGDAWgBSb4gdXZxwewGoG3lm0mi3f3BmGLjAN
BgkqhkiG9w0BAQUFAAOCAQEAmYFThxxol4aR7OBKuEQLq4GsJ0/WwbgcQ3izDJr86iw8bmEbTUsp
9Z8FHSbBuOmDAGJFtqkIk7mpM0sYmsL4h4hO291xNBrBVNpGP+DTKqttVCL1OmLNIG+6KYnX3ZHu
01yiPqFbQfXf5WRDLenVOavSot+3i9DAgBkcRcAtjOj4LaR0VknFBbVPFd5uRHg5h6h+u/N5GJG7
9G+dwfCMNYxdAfvDbbnvRG15RjF+Cv6pgsH/76tuIMRQyV+dTZsXjAzlAcmgQWpzU/qlULRuJQ/7
TBj0/VLZjmmx6BEP3ojY+x1J96relc8geMJgEtslQIxq/H5COEBkEveegeGTLg==
-----END CERTIFICATE-----

Verisign Class 3 Public Primary Certification Authority - G3
============================================================
-----BEGIN CERTIFICATE-----
MIIEGjCCAwICEQCbfgZJoz5iudXukEhxKe9XMA0GCSqGSIb3DQEBBQUAMIHKMQswCQYDVQQGEwJV
UzEXMBUGA1UEChMOVmVyaVNpZ24sIEluYy4xHzAdBgNVBAsTFlZlcmlTaWduIFRydXN0IE5ldHdv
cmsxOjA4BgNVBAsTMShjKSAxOTk5IFZlcmlTaWduLCBJbmMuIC0gRm9yIGF1dGhvcml6ZWQgdXNl
IG9ubHkxRTBDBgNVBAMTPFZlcmlTaWduIENsYXNzIDMgUHVibGljIFByaW1hcnkgQ2VydGlmaWNh
dGlvbiBBdXRob3JpdHkgLSBHMzAeFw05OTEwMDEwMDAwMDBaFw0zNjA3MTYyMzU5NTlaMIHKMQsw
CQYDVQQGEwJVUzEXMBUGA1UEChMOVmVyaVNpZ24sIEluYy4xHzAdBgNVBAsTFlZlcmlTaWduIFRy
dXN0IE5ldHdvcmsxOjA4BgNVBAsTMShjKSAxOTk5IFZlcmlTaWduLCBJbmMuIC0gRm9yIGF1dGhv
cml6ZWQgdXNlIG9ubHkxRTBDBgNVBAMTPFZlcmlTaWduIENsYXNzIDMgUHVibGljIFByaW1hcnkg
Q2VydGlmaWNhdGlvbiBBdXRob3JpdHkgLSBHMzCCASIwDQYJKoZIhvcNAQEBBQADggEPADCCAQoC
ggEBAMu6nFL8eB8aHm8bN3O9+MlrlBIwT/A2R/XQkQr1F8ilYcEWQE37imGQ5XYgwREGfassbqb1
EUGO+i2tKmFZpGcmTNDovFJbcCAEWNF6yaRpvIMXZK0Fi7zQWM6NjPXr8EJJC52XJ2cybuGukxUc
cLwgTS8Y3pKI6GyFVxEa6X7jJhFUokWWVYPKMIno3Nij7SqAP395ZVc+FSBmCC+Vk7+qRy+oRpfw
EuL+wgorUeZ25rdGt+INpsyow0xZVYnm6FNcHOqd8GIWC6fJXwzw3sJ2zq/3avL6QaaiMxTJ5Xpj
055iN9WFZZ4O5lMkdBteHRJTW8cs54NJOxWuimi5V5cCAwEAATANBgkqhkiG9w0BAQUFAAOCAQEA
ERSWwauSCPc/L8my/uRan2Te2yFPhpk0djZX3dAVL8WtfxUfN2JzPtTnX84XA9s1+ivbrmAJXx5f
j267Cz3qWhMeDGBvtcC1IyIuBwvLqXTLR7sdwdela8wv0kL9Sd2nic9TutoAWii/gt/4uhMdUIaC
/Y4wjylGsB49Ndo4YhYYSq3mtlFs3q9i6wHQHiT+eo8SGhJouPtmmRQURVyu565pF4ErWjfJXir0
xuKhXFSbplQAz/DxwceYMBo7Nhbbo27q/a2ywtrvAkcTisDxszGtTxzhT5yvDwyd93gN2PQ1VoDa
t20Xj50egWTh/sVFuq1ruQp6Tk9LhO5L8X3dEQ==
-----END CERTIFICATE-----

Verisign Class 4 Public Primary Certification Authority - G3
============================================================
-----BEGIN CERTIFICATE-----
MIIEGjCCAwICEQDsoKeLbnVqAc/EfMwvlF7XMA0GCSqGSIb3DQEBBQUAMIHKMQswCQYDVQQGEwJV
UzEXMBUGA1UEChMOVmVyaVNpZ24sIEluYy4xHzAdBgNVBAsTFlZlcmlTaWduIFRydXN0IE5ldHdv
cmsxOjA4BgNVBAsTMShjKSAxOTk5IFZlcmlTaWduLCBJbmMuIC0gRm9yIGF1dGhvcml6ZWQgdXNl
IG9ubHkxRTBDBgNVBAMTPFZlcmlTaWduIENsYXNzIDQgUHVibGljIFByaW1hcnkgQ2VydGlmaWNh
dGlvbiBBdXRob3JpdHkgLSBHMzAeFw05OTEwMDEwMDAwMDBaFw0zNjA3MTYyMzU5NTlaMIHKMQsw
CQYDVQQGEwJVUzEXMBUGA1UEChMOVmVyaVNpZ24sIEluYy4xHzAdBgNVBAsTFlZlcmlTaWduIFRy
dXN0IE5ldHdvcmsxOjA4BgNVBAsTMShjKSAxOTk5IFZlcmlTaWduLCBJbmMuIC0gRm9yIGF1dGhv
cml6ZWQgdXNlIG9ubHkxRTBDBgNVBAMTPFZlcmlTaWduIENsYXNzIDQgUHVibGljIFByaW1hcnkg
Q2VydGlmaWNhdGlvbiBBdXRob3JpdHkgLSBHMzCCASIwDQYJKoZIhvcNAQEBBQADggEPADCCAQoC
ggEBAK3LpRFpxlmr8Y+1GQ9Wzsy1HyDkniYlS+BzZYlZ3tCD5PUPtbut8XzoIfzk6AzufEUiGXaS
tBO3IFsJ+mGuqPKljYXCKtbeZjbSmwL0qJJgfJxptI8kHtCGUvYynEFYHiK9zUVilQhu0GbdU6LM
8BDcVHOLBKFGMzNcF0C5nk3T875Vg+ixiY5afJqWIpA7iCXy0lOIAgwLePLmNxdLMEYH5IBtptiW
Lugs+BGzOA1mppvqySNb247i8xOOGlktqgLw7KSHZtzBP/XYufTsgsbSPZUd5cBPhMnZo0QoBmrX
Razwa2rvTl/4EYIeOGM0ZlDUPpNz+jDDZq3/ky2X7wMCAwEAATANBgkqhkiG9w0BAQUFAAOCAQEA
j/ola09b5KROJ1WrIhVZPMq1CtRK26vdoV9TxaBXOcLORyu+OshWv8LZJxA6sQU8wHcxuzrTBXtt
mhwwjIDLk5Mqg6sFUYICABFna/OIYUdfA5PVWw3g8dShMjWFsjrbsIKr0csKvE+MW8VLADsfKoKm
fjaF3H48ZwC15DtS4KjrXRX5xm3wrR0OhbepmnMUWluPQSjA1egtTaRezarZ7c7c2NU8Qh0XwRJd
RTjDOPP8hS6DRkiy1yBfkjaP53kPmF6Z6PDQpLv1U70qzlmwr25/bLvSHgCwIe34QWKCudiyxLtG
UPMxxY8BqHTr9Xgn2uf3ZkPznoM+IKrDNWCRzg==
-----END CERTIFICATE-----

Entrust.net Premium 2048 Secure Server CA
=========================================
-----BEGIN CERTIFICATE-----
MIIEKjCCAxKgAwIBAgIEOGPe+DANBgkqhkiG9w0BAQUFADCBtDEUMBIGA1UEChMLRW50cnVzdC5u
ZXQxQDA+BgNVBAsUN3d3dy5lbnRydXN0Lm5ldC9DUFNfMjA0OCBpbmNvcnAuIGJ5IHJlZi4gKGxp
bWl0cyBsaWFiLikxJTAjBgNVBAsTHChjKSAxOTk5IEVudHJ1c3QubmV0IExpbWl0ZWQxMzAxBgNV
BAMTKkVudHJ1c3QubmV0IENlcnRpZmljYXRpb24gQXV0aG9yaXR5ICgyMDQ4KTAeFw05OTEyMjQx
NzUwNTFaFw0yOTA3MjQxNDE1MTJaMIG0MRQwEgYDVQQKEwtFbnRydXN0Lm5ldDFAMD4GA1UECxQ3
d3d3LmVudHJ1c3QubmV0L0NQU18yMDQ4IGluY29ycC4gYnkgcmVmLiAobGltaXRzIGxpYWIuKTEl
MCMGA1UECxMcKGMpIDE5OTkgRW50cnVzdC5uZXQgTGltaXRlZDEzMDEGA1UEAxMqRW50cnVzdC5u
ZXQgQ2VydGlmaWNhdGlvbiBBdXRob3JpdHkgKDIwNDgpMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8A
MIIBCgKCAQEArU1LqRKGsuqjIAcVFmQqK0vRvwtKTY7tgHalZ7d4QMBzQshowNtTK91euHaYNZOL
Gp18EzoOH1u3Hs/lJBQesYGpjX24zGtLA/ECDNyrpUAkAH90lKGdCCmziAv1h3edVc3kw37XamSr
hRSGlVuXMlBvPci6Zgzj/L24ScF2iUkZ/cCovYmjZy/Gn7xxGWC4LeksyZB2ZnuU4q941mVTXTzW
nLLPKQP5L6RQstRIzgUyVYr9smRMDuSYB3Xbf9+5CFVghTAp+XtIpGmG4zU/HoZdenoVve8AjhUi
VBcAkCaTvA5JaJG/+EfTnZVCwQ5N328mz8MYIWJmQ3DW1cAH4QIDAQABo0IwQDAOBgNVHQ8BAf8E
BAMCAQYwDwYDVR0TAQH/BAUwAwEB/zAdBgNVHQ4EFgQUVeSB0RGAvtiJuQijMfmhJAkWuXAwDQYJ
KoZIhvcNAQEFBQADggEBADubj1abMOdTmXx6eadNl9cZlZD7Bh/KM3xGY4+WZiT6QBshJ8rmcnPy
T/4xmf3IDExoU8aAghOY+rat2l098c5u9hURlIIM7j+VrxGrD9cv3h8Dj1csHsm7mhpElesYT6Yf
zX1XEC+bBAlahLVu2B064dae0Wx5XnkcFMXj0EyTO2U87d89vqbllRrDtRnDvV5bu/8j72gZyxKT
J1wDLW8w0B62GqzeWvfRqqgnpv55gcR5mTNXuhKwqeBCbJPKVt7+bYQLCIt+jerXmCHG8+c8eS9e
nNFMFY3h7CI3zJpDC5fcgJCNs2ebb0gIFVbPv/ErfF6adulZkMV8gzURZVE=
-----END CERTIFICATE-----

Baltimore CyberTrust Root
=========================
-----BEGIN CERTIFICATE-----
MIIDdzCCAl+gAwIBAgIEAgAAuTANBgkqhkiG9w0BAQUFADBaMQswCQYDVQQGEwJJRTESMBAGA1UE
ChMJQmFsdGltb3JlMRMwEQYDVQQLEwpDeWJlclRydXN0MSIwIAYDVQQDExlCYWx0aW1vcmUgQ3li
ZXJUcnVzdCBSb290MB4XDTAwMDUxMjE4NDYwMFoXDTI1MDUxMjIzNTkwMFowWjELMAkGA1UEBhMC
SUUxEjAQBgNVBAoTCUJhbHRpbW9yZTETMBEGA1UECxMKQ3liZXJUcnVzdDEiMCAGA1UEAxMZQmFs
dGltb3JlIEN5YmVyVHJ1c3QgUm9vdDCCASIwDQYJKoZIhvcNAQEBBQADggEPADCCAQoCggEBAKME
uyKrmD1X6CZymrV51Cni4eiVgLGw41uOKymaZN+hXe2wCQVt2yguzmKiYv60iNoS6zjrIZ3AQSsB
UnuId9Mcj8e6uYi1agnnc+gRQKfRzMpijS3ljwumUNKoUMMo6vWrJYeKmpYcqWe4PwzV9/lSEy/C
G9VwcPCPwBLKBsua4dnKM3p31vjsufFoREJIE9LAwqSuXmD+tqYF/LTdB1kC1FkYmGP1pWPgkAx9
XbIGevOF6uvUA65ehD5f/xXtabz5OTZydc93Uk3zyZAsuT3lySNTPx8kmCFcB5kpvcY67Oduhjpr
l3RjM71oGDHweI12v/yejl0qhqdNkNwnGjkCAwEAAaNFMEMwHQYDVR0OBBYEFOWdWTCCR1jMrPoI
VDaGezq1BE3wMBIGA1UdEwEB/wQIMAYBAf8CAQMwDgYDVR0PAQH/BAQDAgEGMA0GCSqGSIb3DQEB
BQUAA4IBAQCFDF2O5G9RaEIFoN27TyclhAO992T9Ldcw46QQF+vaKSm2eT929hkTI7gQCvlYpNRh
cL0EYWoSihfVCr3FvDB81ukMJY2GQE/szKN+OMY3EU/t3WgxjkzSswF07r51XgdIGn9w/xZchMB5
hbgF/X++ZRGjD8ACtPhSNzkE1akxehi/oCr0Epn3o0WC4zxe9Z2etciefC7IpJ5OCBRLbf1wbWsa
Y71k5h+3zvDyny67G7fyUIhzksLi4xaNmjICq44Y3ekQEe5+NauQrz4wlHrQMz2nZQ/1/I6eYs9H
RCwBXbsdtTLSR9I4LtD+gdwyah617jzV/OeBHRnDJELqYzmp
-----END CERTIFICATE-----

AddTrust Low-Value Services Root
================================
-----BEGIN CERTIFICATE-----
MIIEGDCCAwCgAwIBAgIBATANBgkqhkiG9w0BAQUFADBlMQswCQYDVQQGEwJTRTEUMBIGA1UEChML
QWRkVHJ1c3QgQUIxHTAbBgNVBAsTFEFkZFRydXN0IFRUUCBOZXR3b3JrMSEwHwYDVQQDExhBZGRU
cnVzdCBDbGFzcyAxIENBIFJvb3QwHhcNMDAwNTMwMTAzODMxWhcNMjAwNTMwMTAzODMxWjBlMQsw
CQYDVQQGEwJTRTEUMBIGA1UEChMLQWRkVHJ1c3QgQUIxHTAbBgNVBAsTFEFkZFRydXN0IFRUUCBO
ZXR3b3JrMSEwHwYDVQQDExhBZGRUcnVzdCBDbGFzcyAxIENBIFJvb3QwggEiMA0GCSqGSIb3DQEB
AQUAA4IBDwAwggEKAoIBAQCWltQhSWDia+hBBwzexODcEyPNwTXH+9ZOEQpnXvUGW2ulCDtbKRY6
54eyNAbFvAWlA3yCyykQruGIgb3WntP+LVbBFc7jJp0VLhD7Bo8wBN6ntGO0/7Gcrjyvd7ZWxbWr
oulpOj0OM3kyP3CCkplhbY0wCI9xP6ZIVxn4JdxLZlyldI+Yrsj5wAYi56xz36Uu+1LcsRVlIPo1
Zmne3yzxbrww2ywkEtvrNTVokMsAsJchPXQhI2U0K7t4WaPW4XY5mqRJjox0r26kmqPZm9I4XJui
GMx1I4S+6+JNM3GOGvDC+Mcdoq0Dlyz4zyXG9rgkMbFjXZJ/Y/AlyVMuH79NAgMBAAGjgdIwgc8w
HQYDVR0OBBYEFJWxtPCUtr3H2tERCSG+wa9J/RB7MAsGA1UdDwQEAwIBBjAPBgNVHRMBAf8EBTAD
AQH/MIGPBgNVHSMEgYcwgYSAFJWxtPCUtr3H2tERCSG+wa9J/RB7oWmkZzBlMQswCQYDVQQGEwJT
RTEUMBIGA1UEChMLQWRkVHJ1c3QgQUIxHTAbBgNVBAsTFEFkZFRydXN0IFRUUCBOZXR3b3JrMSEw
HwYDVQQDExhBZGRUcnVzdCBDbGFzcyAxIENBIFJvb3SCAQEwDQYJKoZIhvcNAQEFBQADggEBACxt
ZBsfzQ3duQH6lmM0MkhHma6X7f1yFqZzR1r0693p9db7RcwpiURdv0Y5PejuvE1Uhh4dbOMXJ0Ph
iVYrqW9yTkkz43J8KiOavD7/KCrto/8cI7pDVwlnTUtiBi34/2ydYB7YHEt9tTEv2dB8Xfjea4MY
eDdXL+gzB2ffHsdrKpV2ro9Xo/D0UrSpUwjP4E/TelOL/bscVjby/rK25Xa71SJlpz/+0WatC7xr
mYbvP33zGDLKe8bjq2RGlfgmadlVg3sslgf/WSxEo8bl6ancoWOAWiFeIc9TVPC6b4nbqKqVz4vj
ccweGyBECMB6tkD9xOQ14R0WHNC8K47Wcdk=
-----END CERTIFICATE-----

AddTrust External Root
======================
-----BEGIN CERTIFICATE-----
MIIENjCCAx6gAwIBAgIBATANBgkqhkiG9w0BAQUFADBvMQswCQYDVQQGEwJTRTEUMBIGA1UEChML
QWRkVHJ1c3QgQUIxJjAkBgNVBAsTHUFkZFRydXN0IEV4dGVybmFsIFRUUCBOZXR3b3JrMSIwIAYD
VQQDExlBZGRUcnVzdCBFeHRlcm5hbCBDQSBSb290MB4XDTAwMDUzMDEwNDgzOFoXDTIwMDUzMDEw
NDgzOFowbzELMAkGA1UEBhMCU0UxFDASBgNVBAoTC0FkZFRydXN0IEFCMSYwJAYDVQQLEx1BZGRU
cnVzdCBFeHRlcm5hbCBUVFAgTmV0d29yazEiMCAGA1UEAxMZQWRkVHJ1c3QgRXh0ZXJuYWwgQ0Eg
Um9vdDCCASIwDQYJKoZIhvcNAQEBBQADggEPADCCAQoCggEBALf3GjPm8gAELTngTlvtH7xsD821
+iO2zt6bETOXpClMfZOfvUq8k+0DGuOPz+VtUFrWlymUWoCwSXrbLpX9uMq/NzgtHj6RQa1wVsfw
Tz/oMp50ysiQVOnGXw94nZpAPA6sYapeFI+eh6FqUNzXmk6vBbOmcZSccbNQYArHE504B4YCqOmo
aSYYkKtMsE8jqzpPhNjfzp/haW+710LXa0Tkx63ubUFfclpxCDezeWWkWaCUN/cALw3CknLa0Dhy
2xSoRcRdKn23tNbE7qzNE0S3ySvdQwAl+mG5aWpYIxG3pzOPVnVZ9c0p10a3CitlttNCbxWyuHv7
7+ldU9U0WicCAwEAAaOB3DCB2TAdBgNVHQ4EFgQUrb2YejS0Jvf6xCZU7wO94CTLVBowCwYDVR0P
BAQDAgEGMA8GA1UdEwEB/wQFMAMBAf8wgZkGA1UdIwSBkTCBjoAUrb2YejS0Jvf6xCZU7wO94CTL
VBqhc6RxMG8xCzAJBgNVBAYTAlNFMRQwEgYDVQQKEwtBZGRUcnVzdCBBQjEmMCQGA1UECxMdQWRk
VHJ1c3QgRXh0ZXJuYWwgVFRQIE5ldHdvcmsxIjAgBgNVBAMTGUFkZFRydXN0IEV4dGVybmFsIENB
IFJvb3SCAQEwDQYJKoZIhvcNAQEFBQADggEBALCb4IUlwtYj4g+WBpKdQZic2YR5gdkeWxQHIzZl
j7DYd7usQWxHYINRsPkyPef89iYTx4AWpb9a/IfPeHmJIZriTAcKhjW88t5RxNKWt9x+Tu5w/Rw5
6wwCURQtjr0W4MHfRnXnJK3s9EK0hZNwEGe6nQY1ShjTK3rMUUKhemPR5ruhxSvCNr4TDea9Y355
e6cJDUCrat2PisP29owaQgVR1EX1n6diIWgVIEM8med8vSTYqZEXc4g/VhsxOBi0cQ+azcgOno4u
G+GMmIPLHzHxREzGBHNJdmAPx/i9F4BrLunMTA5amnkPIAou1Z5jJh5VkpTYghdae9C8x49OhgQ=
-----END CERTIFICATE-----

AddTrust Public Services Root
=============================
-----BEGIN CERTIFICATE-----
MIIEFTCCAv2gAwIBAgIBATANBgkqhkiG9w0BAQUFADBkMQswCQYDVQQGEwJTRTEUMBIGA1UEChML
QWRkVHJ1c3QgQUIxHTAbBgNVBAsTFEFkZFRydXN0IFRUUCBOZXR3b3JrMSAwHgYDVQQDExdBZGRU
cnVzdCBQdWJsaWMgQ0EgUm9vdDAeFw0wMDA1MzAxMDQxNTBaFw0yMDA1MzAxMDQxNTBaMGQxCzAJ
BgNVBAYTAlNFMRQwEgYDVQQKEwtBZGRUcnVzdCBBQjEdMBsGA1UECxMUQWRkVHJ1c3QgVFRQIE5l
dHdvcmsxIDAeBgNVBAMTF0FkZFRydXN0IFB1YmxpYyBDQSBSb290MIIBIjANBgkqhkiG9w0BAQEF
AAOCAQ8AMIIBCgKCAQEA6Rowj4OIFMEg2Dybjxt+A3S72mnTRqX4jsIMEZBRpS9mVEBV6tsfSlbu
nyNu9DnLoblv8n75XYcmYZ4c+OLspoH4IcUkzBEMP9smcnrHAZcHF/nXGCwwfQ56HmIexkvA/X1i
d9NEHif2P0tEs7c42TkfYNVRknMDtABp4/MUTu7R3AnPdzRGULD4EfL+OHn3Bzn+UZKXC1sIXzSG
Aa2Il+tmzV7R/9x98oTaunet3IAIx6eH1lWfl2royBFkuucZKT8Rs3iQhCBSWxHveNCD9tVIkNAw
HM+A+WD+eeSI8t0A65RF62WUaUC6wNW0uLp9BBGo6zEFlpROWCGOn9Bg/QIDAQABo4HRMIHOMB0G
A1UdDgQWBBSBPjfYkrAfd59ctKtzquf2NGAv+jALBgNVHQ8EBAMCAQYwDwYDVR0TAQH/BAUwAwEB
/zCBjgYDVR0jBIGGMIGDgBSBPjfYkrAfd59ctKtzquf2NGAv+qFopGYwZDELMAkGA1UEBhMCU0Ux
FDASBgNVBAoTC0FkZFRydXN0IEFCMR0wGwYDVQQLExRBZGRUcnVzdCBUVFAgTmV0d29yazEgMB4G
A1UEAxMXQWRkVHJ1c3QgUHVibGljIENBIFJvb3SCAQEwDQYJKoZIhvcNAQEFBQADggEBAAP3FUr4
JNojVhaTdt02KLmuG7jD8WS6IBh4lSknVwW8fCr0uVFV2ocC3g8WFzH4qnkuCRO7r7IgGRLlk/lL
+YPoRNWyQSW/iHVv/xD8SlTQX/D67zZzfRs2RcYhbbQVuE7PnFylPVoAjgbjPGsye/Kf8Lb93/Ao
GEjwxrzQvzSAlsJKsW2Ox5BF3i9nrEUEo3rcVZLJR2bYGozH7ZxOmuASu7VqTITh4SINhwBk/ox9
Yjllpu9CtoAlEmEBqCQTcAARJl/6NVDFSMwGR+gn2HCNX2TmoUQmXiLsks3/QppEIW1cxeMiHV9H
EufOX1362KqxMy3ZdvJOOjMMK7MtkAY=
-----END CERTIFICATE-----

AddTrust Qualified Certificates Root
====================================
-----BEGIN CERTIFICATE-----
MIIEHjCCAwagAwIBAgIBATANBgkqhkiG9w0BAQUFADBnMQswCQYDVQQGEwJTRTEUMBIGA1UEChML
QWRkVHJ1c3QgQUIxHTAbBgNVBAsTFEFkZFRydXN0IFRUUCBOZXR3b3JrMSMwIQYDVQQDExpBZGRU
cnVzdCBRdWFsaWZpZWQgQ0EgUm9vdDAeFw0wMDA1MzAxMDQ0NTBaFw0yMDA1MzAxMDQ0NTBaMGcx
CzAJBgNVBAYTAlNFMRQwEgYDVQQKEwtBZGRUcnVzdCBBQjEdMBsGA1UECxMUQWRkVHJ1c3QgVFRQ
IE5ldHdvcmsxIzAhBgNVBAMTGkFkZFRydXN0IFF1YWxpZmllZCBDQSBSb290MIIBIjANBgkqhkiG
9w0BAQEFAAOCAQ8AMIIBCgKCAQEA5B6a/twJWoekn0e+EV+vhDTbYjx5eLfpMLXsDBwqxBb/4Oxx
64r1EW7tTw2R0hIYLUkVAcKkIhPHEWT/IhKauY5cLwjPcWqzZwFZ8V1G87B4pfYOQnrjfxvM0PC3
KP0q6p6zsLkEqv32x7SxuCqg+1jxGaBvcCV+PmlKfw8i2O+tCBGaKZnhqkRFmhJePp1tUvznoD1o
L/BLcHwTOK28FSXx1s6rosAx1i+f4P8UWfyEk9mHfExUE+uf0S0R+Bg6Ot4l2ffTQO2kBhLEO+GR
wVY18BTcZTYJbqukB8c10cIDMzZbdSZtQvESa0NvS3GU+jQd7RNuyoB/mC9suWXY6QIDAQABo4HU
MIHRMB0GA1UdDgQWBBQ5lYtii1zJ1IC6WA+XPxUIQ8yYpzALBgNVHQ8EBAMCAQYwDwYDVR0TAQH/
BAUwAwEB/zCBkQYDVR0jBIGJMIGGgBQ5lYtii1zJ1IC6WA+XPxUIQ8yYp6FrpGkwZzELMAkGA1UE
BhMCU0UxFDASBgNVBAoTC0FkZFRydXN0IEFCMR0wGwYDVQQLExRBZGRUcnVzdCBUVFAgTmV0d29y
azEjMCEGA1UEAxMaQWRkVHJ1c3QgUXVhbGlmaWVkIENBIFJvb3SCAQEwDQYJKoZIhvcNAQEFBQAD
ggEBABmrder4i2VhlRO6aQTvhsoToMeqT2QbPxj2qC0sVY8FtzDqQmodwCVRLae/DLPt7wh/bDxG
GuoYQ992zPlmhpwsaPXpF/gxsxjE1kh9I0xowX67ARRvxdlu3rsEQmr49lx95dr6h+sNNVJn0J6X
dgWTP5XHAeZpVTh/EGGZyeNfpso+gmNIquIISD6q8rKFYqa0p9m9N5xotS1WfbC3P6CxB9bpT9ze
RXEwMn8bLgn5v1Kh7sKAPgZcLlVAwRv1cEWw3F369nJad9Jjzc9YiQBCYz95OdBEsIJuQRno3eDB
iFrRHnGTHyQwdOUeqN48Jzd/g66ed8/wMLH/S5noxqE=
-----END CERTIFICATE-----

Entrust Root Certification Authority
====================================
-----BEGIN CERTIFICATE-----
MIIEkTCCA3mgAwIBAgIERWtQVDANBgkqhkiG9w0BAQUFADCBsDELMAkGA1UEBhMCVVMxFjAUBgNV
BAoTDUVudHJ1c3QsIEluYy4xOTA3BgNVBAsTMHd3dy5lbnRydXN0Lm5ldC9DUFMgaXMgaW5jb3Jw
b3JhdGVkIGJ5IHJlZmVyZW5jZTEfMB0GA1UECxMWKGMpIDIwMDYgRW50cnVzdCwgSW5jLjEtMCsG
A1UEAxMkRW50cnVzdCBSb290IENlcnRpZmljYXRpb24gQXV0aG9yaXR5MB4XDTA2MTEyNzIwMjM0
MloXDTI2MTEyNzIwNTM0MlowgbAxCzAJBgNVBAYTAlVTMRYwFAYDVQQKEw1FbnRydXN0LCBJbmMu
MTkwNwYDVQQLEzB3d3cuZW50cnVzdC5uZXQvQ1BTIGlzIGluY29ycG9yYXRlZCBieSByZWZlcmVu
Y2UxHzAdBgNVBAsTFihjKSAyMDA2IEVudHJ1c3QsIEluYy4xLTArBgNVBAMTJEVudHJ1c3QgUm9v
dCBDZXJ0aWZpY2F0aW9uIEF1dGhvcml0eTCCASIwDQYJKoZIhvcNAQEBBQADggEPADCCAQoCggEB
ALaVtkNC+sZtKm9I35RMOVcF7sN5EUFoNu3s/poBj6E4KPz3EEZmLk0eGrEaTsbRwJWIsMn/MYsz
A9u3g3s+IIRe7bJWKKf44LlAcTfFy0cOlypowCKVYhXbR9n10Cv/gkvJrT7eTNuQgFA/CYqEAOww
Cj0Yzfv9KlmaI5UXLEWeH25DeW0MXJj+SKfFI0dcXv1u5x609mhF0YaDW6KKjbHjKYD+JXGIrb68
j6xSlkuqUY3kEzEZ6E5Nn9uss2rVvDlUccp6en+Q3X0dgNmBu1kmwhH+5pPi94DkZfs0Nw4pgHBN
rziGLp5/V6+eF67rHMsoIV+2HNjnogQi+dPa2MsCAwEAAaOBsDCBrTAOBgNVHQ8BAf8EBAMCAQYw
DwYDVR0TAQH/BAUwAwEB/zArBgNVHRAEJDAigA8yMDA2MTEyNzIwMjM0MlqBDzIwMjYxMTI3MjA1
MzQyWjAfBgNVHSMEGDAWgBRokORnpKZTgMeGZqTx90tD+4S9bTAdBgNVHQ4EFgQUaJDkZ6SmU4DH
hmak8fdLQ/uEvW0wHQYJKoZIhvZ9B0EABBAwDhsIVjcuMTo0LjADAgSQMA0GCSqGSIb3DQEBBQUA
A4IBAQCT1DCw1wMgKtD5Y+iRDAUgqV8ZyntyTtSx29CW+1RaGSwMCPeyvIWonX9tO1KzKtvn1ISM
Y/YPyyYBkVBs9F8U4pN0wBOeMDpQ47RgxRzwIkSNcUesyBrJ6ZuaAGAT/3B+XxFNSRuzFVJ7yVTa
v52Vr2ua2J7p8eRDjeIRRDq/r72DQnNSi6q7pynP9WQcCk3RvKqsnyrQ/39/2n3qse0wJcGE2jTS
W3iDVuycNsMm4hH2Z0kdkquM++v/eu6FSqdQgPCnXEqULl8FmTxSQeDNtGPPAUO6nIPcj2A781q0
tHuu2guQOHXvgR1m0vdXcDazv/wor3ElhVsT/h5/WrQ8
-----END CERTIFICATE-----

RSA Security 2048 v3
====================
-----BEGIN CERTIFICATE-----
MIIDYTCCAkmgAwIBAgIQCgEBAQAAAnwAAAAKAAAAAjANBgkqhkiG9w0BAQUFADA6MRkwFwYDVQQK
ExBSU0EgU2VjdXJpdHkgSW5jMR0wGwYDVQQLExRSU0EgU2VjdXJpdHkgMjA0OCBWMzAeFw0wMTAy
MjIyMDM5MjNaFw0yNjAyMjIyMDM5MjNaMDoxGTAXBgNVBAoTEFJTQSBTZWN1cml0eSBJbmMxHTAb
BgNVBAsTFFJTQSBTZWN1cml0eSAyMDQ4IFYzMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKC
AQEAt49VcdKA3XtpeafwGFAyPGJn9gqVB93mG/Oe2dJBVGutn3y+Gc37RqtBaB4Y6lXIL5F4iSj7
Jylg/9+PjDvJSZu1pJTOAeo+tWN7fyb9Gd3AIb2E0S1PRsNO3Ng3OTsor8udGuorryGlwSMiuLgb
WhOHV4PR8CDn6E8jQrAApX2J6elhc5SYcSa8LWrg903w8bYqODGBDSnhAMFRD0xS+ARaqn1y07iH
KrtjEAMqs6FPDVpeRrc9DvV07Jmf+T0kgYim3WBU6JU2PcYJk5qjEoAAVZkZR73QpXzDuvsf9/UP
+Ky5tfQ3mBMY3oVbtwyCO4dvlTlYMNpuAWgXIszACwIDAQABo2MwYTAPBgNVHRMBAf8EBTADAQH/
MA4GA1UdDwEB/wQEAwIBBjAfBgNVHSMEGDAWgBQHw1EwpKrpRa41JPr/JCwz0LGdjDAdBgNVHQ4E
FgQUB8NRMKSq6UWuNST6/yQsM9CxnYwwDQYJKoZIhvcNAQEFBQADggEBAF8+hnZuuDU8TjYcHnmY
v/3VEhF5Ug7uMYm83X/50cYVIeiKAVQNOvtUudZj1LGqlk2iQk3UUx+LEN5/Zb5gEydxiKRz44Rj
0aRV4VCT5hsOedBnvEbIvz8XDZXmxpBp3ue0L96VfdASPz0+f00/FGj1EVDVwfSQpQgdMWD/YIwj
VAqv/qFuxdF6Kmh4zx6CCiC0H63lhbJqaHVOrSU3lIW+vaHU6rcMSzyd6BIA8F+sDeGscGNz9395
nzIlQnQFgCi/vcEkllgVsRch6YlL2weIZ/QVrXA+L02FO8K32/6YaCOJ4XQP3vTFhGMpG8zLB8kA
pKnXwiJPZ9d37CAFYd4=
-----END CERTIFICATE-----

GeoTrust Global CA
==================
-----BEGIN CERTIFICATE-----
MIIDVDCCAjygAwIBAgIDAjRWMA0GCSqGSIb3DQEBBQUAMEIxCzAJBgNVBAYTAlVTMRYwFAYDVQQK
Ew1HZW9UcnVzdCBJbmMuMRswGQYDVQQDExJHZW9UcnVzdCBHbG9iYWwgQ0EwHhcNMDIwNTIxMDQw
MDAwWhcNMjIwNTIxMDQwMDAwWjBCMQswCQYDVQQGEwJVUzEWMBQGA1UEChMNR2VvVHJ1c3QgSW5j
LjEbMBkGA1UEAxMSR2VvVHJ1c3QgR2xvYmFsIENBMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIB
CgKCAQEA2swYYzD99BcjGlZ+W988bDjkcbd4kdS8odhM+KhDtgPpTSEHCIjaWC9mOSm9BXiLnTjo
BbdqfnGk5sRgprDvgOSJKA+eJdbtg/OtppHHmMlCGDUUna2YRpIuT8rxh0PBFpVXLVDviS2Aelet
8u5fa9IAjbkU+BQVNdnARqN7csiRv8lVK83Qlz6cJmTM386DGXHKTubU1XupGc1V3sjs0l44U+Vc
T4wt/lAjNvxm5suOpDkZALeVAjmRCw7+OC7RHQWa9k0+bw8HHa8sHo9gOeL6NlMTOdReJivbPagU
vTLrGAMoUgRx5aszPeE4uwc2hGKceeoWMPRfwCvocWvk+QIDAQABo1MwUTAPBgNVHRMBAf8EBTAD
AQH/MB0GA1UdDgQWBBTAephojYn7qwVkDBF9qn1luMrMTjAfBgNVHSMEGDAWgBTAephojYn7qwVk
DBF9qn1luMrMTjANBgkqhkiG9w0BAQUFAAOCAQEANeMpauUvXVSOKVCUn5kaFOSPeCpilKInZ57Q
zxpeR+nBsqTP3UEaBU6bS+5Kb1VSsyShNwrrZHYqLizz/Tt1kL/6cdjHPTfStQWVYrmm3ok9Nns4
d0iXrKYgjy6myQzCsplFAMfOEVEiIuCl6rYVSAlk6l5PdPcFPseKUgzbFbS9bZvlxrFUaKnjaZC2
mqUPuLk/IH2uSrW4nOQdtqvmlKXBx4Ot2/Unhw4EbNX/3aBd7YdStysVAq45pmp06drE57xNNB6p
XE0zX5IJL4hmXXeXxx12E6nV5fEWCRE11azbJHFwLJhWC9kXtNHjUStedejV0NxPNO3CBWaAocvm
Mw==
-----END CERTIFICATE-----

GeoTrust Global CA 2
====================
-----BEGIN CERTIFICATE-----
MIIDZjCCAk6gAwIBAgIBATANBgkqhkiG9w0BAQUFADBEMQswCQYDVQQGEwJVUzEWMBQGA1UEChMN
R2VvVHJ1c3QgSW5jLjEdMBsGA1UEAxMUR2VvVHJ1c3QgR2xvYmFsIENBIDIwHhcNMDQwMzA0MDUw
MDAwWhcNMTkwMzA0MDUwMDAwWjBEMQswCQYDVQQGEwJVUzEWMBQGA1UEChMNR2VvVHJ1c3QgSW5j
LjEdMBsGA1UEAxMUR2VvVHJ1c3QgR2xvYmFsIENBIDIwggEiMA0GCSqGSIb3DQEBAQUAA4IBDwAw
ggEKAoIBAQDvPE1APRDfO1MA4Wf+lGAVPoWI8YkNkMgoI5kF6CsgncbzYEbYwbLVjDHZ3CB5JIG/
NTL8Y2nbsSpr7iFY8gjpeMtvy/wWUsiRxP89c96xPqfCfWbB9X5SJBri1WeR0IIQ13hLTytCOb1k
LUCgsBDTOEhGiKEMuzozKmKY+wCdE1l/bztyqu6mD4b5BWHqZ38MN5aL5mkWRxHCJ1kDs6ZgwiFA
Vvqgx306E+PsV8ez1q6diYD3Aecs9pYrEw15LNnA5IZ7S4wMcoKK+xfNAGw6EzywhIdLFnopsk/b
HdQL82Y3vdj2V7teJHq4PIu5+pIaGoSe2HSPqht/XvT+RSIhAgMBAAGjYzBhMA8GA1UdEwEB/wQF
MAMBAf8wHQYDVR0OBBYEFHE4NvICMVNHK266ZUapEBVYIAUJMB8GA1UdIwQYMBaAFHE4NvICMVNH
K266ZUapEBVYIAUJMA4GA1UdDwEB/wQEAwIBhjANBgkqhkiG9w0BAQUFAAOCAQEAA/e1K6tdEPx7
srJerJsOflN4WT5CBP51o62sgU7XAotexC3IUnbHLB/8gTKY0UvGkpMzNTEv/NgdRN3ggX+d6Yvh
ZJFiCzkIjKx0nVnZellSlxG5FntvRdOW2TF9AjYPnDtuzywNA0ZF66D0f0hExghAzN4bcLUprbqL
OzRldRtxIR0sFAqwlpW41uryZfspuk/qkZN0abby/+Ea0AzRdoXLiiW9l14sbxWZJue2Kf8i7MkC
x1YAzUm5s2x7UwQa4qjJqhIFI8LO57sEAszAR6LkxCkvW0VXiVHuPOtSCP8HNR6fNWpHSlaY0VqF
H4z1Ir+rzoPz4iIprn2DQKi6bA==
-----END CERTIFICATE-----

GeoTrust Universal CA
=====================
-----BEGIN CERTIFICATE-----
MIIFaDCCA1CgAwIBAgIBATANBgkqhkiG9w0BAQUFADBFMQswCQYDVQQGEwJVUzEWMBQGA1UEChMN
R2VvVHJ1c3QgSW5jLjEeMBwGA1UEAxMVR2VvVHJ1c3QgVW5pdmVyc2FsIENBMB4XDTA0MDMwNDA1
MDAwMFoXDTI5MDMwNDA1MDAwMFowRTELMAkGA1UEBhMCVVMxFjAUBgNVBAoTDUdlb1RydXN0IElu
Yy4xHjAcBgNVBAMTFUdlb1RydXN0IFVuaXZlcnNhbCBDQTCCAiIwDQYJKoZIhvcNAQEBBQADggIP
ADCCAgoCggIBAKYVVaCjxuAfjJ0hUNfBvitbtaSeodlyWL0AG0y/YckUHUWCq8YdgNY96xCcOq9t
JPi8cQGeBvV8Xx7BDlXKg5pZMK4ZyzBIle0iN430SppyZj6tlcDgFgDgEB8rMQ7XlFTTQjOgNB0e
RXbdT8oYN+yFFXoZCPzVx5zw8qkuEKmS5j1YPakWaDwvdSEYfyh3peFhF7em6fgemdtzbvQKoiFs
7tqqhZJmr/Z6a4LauiIINQ/PQvE1+mrufislzDoR5G2vc7J2Ha3QsnhnGqQ5HFELZ1aD/ThdDc7d
8Lsrlh/eezJS/R27tQahsiFepdaVaH/wmZ7cRQg+59IJDTWU3YBOU5fXtQlEIGQWFwMCTFMNaN7V
qnJNk22CDtucvc+081xdVHppCZbW2xHBjXWotM85yM48vCR85mLK4b19p71XZQvk/iXttmkQ3Cga
Rr0BHdCXteGYO8A3ZNY9lO4L4fUorgtWv3GLIylBjobFS1J72HGrH4oVpjuDWtdYAVHGTEHZf9hB
Z3KiKN9gg6meyHv8U3NyWfWTehd2Ds735VzZC1U0oqpbtWpU5xPKV+yXbfReBi9Fi1jUIxaS5BZu
KGNZMN9QAZxjiRqf2xeUgnA3wySemkfWWspOqGmJch+RbNt+nhutxx9z3SxPGWX9f5NAEC7S8O08
ni4oPmkmM8V7AgMBAAGjYzBhMA8GA1UdEwEB/wQFMAMBAf8wHQYDVR0OBBYEFNq7LqqwDLiIJlF0
XG0D08DYj3rWMB8GA1UdIwQYMBaAFNq7LqqwDLiIJlF0XG0D08DYj3rWMA4GA1UdDwEB/wQEAwIB
hjANBgkqhkiG9w0BAQUFAAOCAgEAMXjmx7XfuJRAyXHEqDXsRh3ChfMoWIawC/yOsjmPRFWrZIRc
aanQmjg8+uUfNeVE44B5lGiku8SfPeE0zTBGi1QrlaXv9z+ZhP015s8xxtxqv6fXIwjhmF7DWgh2
qaavdy+3YL1ERmrvl/9zlcGO6JP7/TG37FcREUWbMPEaiDnBTzynANXH/KttgCJwpQzgXQQpAvvL
oJHRfNbDflDVnVi+QTjruXU8FdmbyUqDWcDaU/0zuzYYm4UPFd3uLax2k7nZAY1IEKj79TiG8dsK
xr2EoyNB3tZ3b4XUhRxQ4K5RirqNPnbiucon8l+f725ZDQbYKxek0nxru18UGkiPGkzns0ccjkxF
KyDuSN/n3QmOGKjaQI2SJhFTYXNd673nxE0pN2HrrDktZy4W1vUAg4WhzH92xH3kt0tm7wNFYGm2
DFKWkoRepqO1pD4r2czYG0eq8kTaT/kD6PAUyz/zg97QwVTjt+gKN02LIFkDMBmhLMi9ER/frslK
xfMnZmaGrGiR/9nmUxwPi1xpZQomyB40w11Re9epnAahNt3ViZS82eQtDF4JbAiXfKM9fJP/P6EU
p8+1Xevb2xzEdt+Iub1FBZUbrvxGakyvSOPOrg/SfuvmbJxPgWp6ZKy7PtXny3YuxadIwVyQD8vI
P/rmMuGNG2+k5o7Y+SlIis5z/iw=
-----END CERTIFICATE-----

GeoTrust Universal CA 2
=======================
-----BEGIN CERTIFICATE-----
MIIFbDCCA1SgAwIBAgIBATANBgkqhkiG9w0BAQUFADBHMQswCQYDVQQGEwJVUzEWMBQGA1UEChMN
R2VvVHJ1c3QgSW5jLjEgMB4GA1UEAxMXR2VvVHJ1c3QgVW5pdmVyc2FsIENBIDIwHhcNMDQwMzA0
MDUwMDAwWhcNMjkwMzA0MDUwMDAwWjBHMQswCQYDVQQGEwJVUzEWMBQGA1UEChMNR2VvVHJ1c3Qg
SW5jLjEgMB4GA1UEAxMXR2VvVHJ1c3QgVW5pdmVyc2FsIENBIDIwggIiMA0GCSqGSIb3DQEBAQUA
A4ICDwAwggIKAoICAQCzVFLByT7y2dyxUxpZKeexw0Uo5dfR7cXFS6GqdHtXr0om/Nj1XqduGdt0
DE81WzILAePb63p3NeqqWuDW6KFXlPCQo3RWlEQwAx5cTiuFJnSCegx2oG9NzkEtoBUGFF+3Qs17
j1hhNNwqCPkuwwGmIkQcTAeC5lvO0Ep8BNMZcyfwqph/Lq9O64ceJHdqXbboW0W63MOhBW9Wjo8Q
JqVJwy7XQYci4E+GymC16qFjwAGXEHm9ADwSbSsVsaxLse4YuU6W3Nx2/zu+z18DwPw76L5GG//a
QMJS9/7jOvdqdzXQ2o3rXhhqMcceujwbKNZrVMaqW9eiLBsZzKIC9ptZvTdrhrVtgrrY6slWvKk2
WP0+GfPtDCapkzj4T8FdIgbQl+rhrcZV4IErKIM6+vR7IVEAvlI4zs1meaj0gVbi0IMJR1FbUGrP
20gaXT73y/Zl92zxlfgCOzJWgjl6W70viRu/obTo/3+NjN8D8WBOWBFM66M/ECuDmgFz2ZRthAAn
ZqzwcEAJQpKtT5MNYQlRJNiS1QuUYbKHsu3/mjX/hVTK7URDrBs8FmtISgocQIgfksILAAX/8sgC
SqSqqcyZlpwvWOB94b67B9xfBHJcMTTD7F8t4D1kkCLm0ey4Lt1ZrtmhN79UNdxzMk+MBB4zsslG
8dhcyFVQyWi9qLo2CQIDAQABo2MwYTAPBgNVHRMBAf8EBTADAQH/MB0GA1UdDgQWBBR281Xh+qQ2
+/CfXGJx7Tz0RzgQKzAfBgNVHSMEGDAWgBR281Xh+qQ2+/CfXGJx7Tz0RzgQKzAOBgNVHQ8BAf8E
BAMCAYYwDQYJKoZIhvcNAQEFBQADggIBAGbBxiPz2eAubl/oz66wsCVNK/g7WJtAJDday6sWSf+z
dXkzoS9tcBc0kf5nfo/sm+VegqlVHy/c1FEHEv6sFj4sNcZj/NwQ6w2jqtB8zNHQL1EuxBRa3ugZ
4T7GzKQp5y6EqgYweHZUcyiYWTjgAA1i00J9IZ+uPTqM1fp3DRgrFg5fNuH8KrUwJM/gYwx7WBr+
mbpCErGR9Hxo4sjoryzqyX6uuyo9DRXcNJW2GHSoag/HtPQTxORb7QrSpJdMKu0vbBKJPfEncKpq
A1Ihn0CoZ1Dy81of398j9tx4TuaYT1U6U+Pv8vSfx3zYWK8pIpe44L2RLrB27FcRz+8pRPPphXpg
Y+RdM4kX2TGq2tbzGDVyz4crL2MjhF2EjD9XoIj8mZEoJmmZ1I+XRL6O1UixpCgp8RW04eWe3fiP
pm8m1wk8OhwRDqZsN/etRIcsKMfYdIKz0G9KV7s1KSegi+ghp4dkNl3M2Basx7InQJJVOCiNUW7d
FGdTbHFcJoRNdVq2fmBWqU2t+5sel/MN2dKXVHfaPRK34B7vCAas+YWH6aLcr34YEoP9VhdBLtUp
gn2Z9DH2canPLAEnpQW5qrJITirvn5NSUZU8UnOOVkwXQMAJKOSLakhT2+zNVVXxxvjpoixMptEm
X36vWkzaH6byHCx+rgIW0lbQL1dTR+iS
-----END CERTIFICATE-----

Visa eCommerce Root
===================
-----BEGIN CERTIFICATE-----
MIIDojCCAoqgAwIBAgIQE4Y1TR0/BvLB+WUF1ZAcYjANBgkqhkiG9w0BAQUFADBrMQswCQYDVQQG
EwJVUzENMAsGA1UEChMEVklTQTEvMC0GA1UECxMmVmlzYSBJbnRlcm5hdGlvbmFsIFNlcnZpY2Ug
QXNzb2NpYXRpb24xHDAaBgNVBAMTE1Zpc2EgZUNvbW1lcmNlIFJvb3QwHhcNMDIwNjI2MDIxODM2
WhcNMjIwNjI0MDAxNjEyWjBrMQswCQYDVQQGEwJVUzENMAsGA1UEChMEVklTQTEvMC0GA1UECxMm
VmlzYSBJbnRlcm5hdGlvbmFsIFNlcnZpY2UgQXNzb2NpYXRpb24xHDAaBgNVBAMTE1Zpc2EgZUNv
bW1lcmNlIFJvb3QwggEiMA0GCSqGSIb3DQEBAQUAA4IBDwAwggEKAoIBAQCvV95WHm6h2mCxlCfL
F9sHP4CFT8icttD0b0/Pmdjh28JIXDqsOTPHH2qLJj0rNfVIsZHBAk4ElpF7sDPwsRROEW+1QK8b
RaVK7362rPKgH1g/EkZgPI2h4H3PVz4zHvtH8aoVlwdVZqW1LS7YgFmypw23RuwhY/81q6UCzyr0
TP579ZRdhE2o8mCP2w4lPJ9zcc+U30rq299yOIzzlr3xF7zSujtFWsan9sYXiwGd/BmoKoMWuDpI
/k4+oKsGGelT84ATB+0tvz8KPFUgOSwsAGl0lUq8ILKpeeUYiZGo3BxN77t+Nwtd/jmliFKMAGzs
GHxBvfaLdXe6YJ2E5/4tAgMBAAGjQjBAMA8GA1UdEwEB/wQFMAMBAf8wDgYDVR0PAQH/BAQDAgEG
MB0GA1UdDgQWBBQVOIMPPyw/cDMezUb+B4wg4NfDtzANBgkqhkiG9w0BAQUFAAOCAQEAX/FBfXxc
CLkr4NWSR/pnXKUTwwMhmytMiUbPWU3J/qVAtmPN3XEolWcRzCSs00Rsca4BIGsDoo8Ytyk6feUW
YFN4PMCvFYP3j1IzJL1kk5fui/fbGKhtcbP3LBfQdCVp9/5rPJS+TUtBjE7ic9DjkCJzQ83z7+pz
zkWKsKZJ/0x9nXGIxHYdkFsd7v3M9+79YKWxehZx0RbQfBI8bGmX265fOZpwLwU8GUYEmSA20GBu
YQa7FkKMcPcw++DbZqMAAb3mLNqRX6BGi01qnD093QVG/na/oAo85ADmJ7f/hC3euiInlhBx6yLt
398znM/jra6O1I7mT1GvFpLgXPYHDw==
-----END CERTIFICATE-----

Certum Root CA
==============
-----BEGIN CERTIFICATE-----
MIIDDDCCAfSgAwIBAgIDAQAgMA0GCSqGSIb3DQEBBQUAMD4xCzAJBgNVBAYTAlBMMRswGQYDVQQK
ExJVbml6ZXRvIFNwLiB6IG8uby4xEjAQBgNVBAMTCUNlcnR1bSBDQTAeFw0wMjA2MTExMDQ2Mzla
Fw0yNzA2MTExMDQ2MzlaMD4xCzAJBgNVBAYTAlBMMRswGQYDVQQKExJVbml6ZXRvIFNwLiB6IG8u
by4xEjAQBgNVBAMTCUNlcnR1bSBDQTCCASIwDQYJKoZIhvcNAQEBBQADggEPADCCAQoCggEBAM6x
wS7TT3zNJc4YPk/EjG+AanPIW1H4m9LcuwBcsaD8dQPugfCI7iNS6eYVM42sLQnFdvkrOYCJ5JdL
kKWoePhzQ3ukYbDYWMzhbGZ+nPMJXlVjhNWo7/OxLjBos8Q82KxujZlakE403Daaj4GIULdtlkIJ
89eVgw1BS7Bqa/j8D35in2fE7SZfECYPCE/wpFcozo+47UX2bu4lXapuOb7kky/ZR6By6/qmW6/K
Uz/iDsaWVhFu9+lmqSbYf5VT7QqFiLpPKaVCjF62/IUgAKpoC6EahQGcxEZjgoi2IrHu/qpGWX7P
NSzVttpd90gzFFS269lvzs2I1qsb2pY7HVkCAwEAAaMTMBEwDwYDVR0TAQH/BAUwAwEB/zANBgkq
hkiG9w0BAQUFAAOCAQEAuI3O7+cUus/usESSbLQ5PqKEbq24IXfS1HeCh+YgQYHu4vgRt2PRFze+
GXYkHAQaTOs9qmdvLdTN/mUxcMUbpgIKumB7bVjCmkn+YzILa+M6wKyrO7Do0wlRjBCDxjTgxSvg
GrZgFCdsMneMvLJymM/NzD+5yCRCFNZX/OYmQ6kd5YCQzgNUKD73P9P4Te1qCjqTE5s7FCMTY5w/
0YcneeVMUeMBrYVdGjux1XMQpNPyvG5k9VpWkKjHDkx0Dy5xO/fIR/RpbxXyEV6DHpx8Uq79AtoS
qFlnGNu8cN2bsWntgM6JQEhqDjXKKWYVIZQs6GAqm4VKQPNriiTsBhYscw==
-----END CERTIFICATE-----

Comodo AAA Services root
========================
-----BEGIN CERTIFICATE-----
MIIEMjCCAxqgAwIBAgIBATANBgkqhkiG9w0BAQUFADB7MQswCQYDVQQGEwJHQjEbMBkGA1UECAwS
R3JlYXRlciBNYW5jaGVzdGVyMRAwDgYDVQQHDAdTYWxmb3JkMRowGAYDVQQKDBFDb21vZG8gQ0Eg
TGltaXRlZDEhMB8GA1UEAwwYQUFBIENlcnRpZmljYXRlIFNlcnZpY2VzMB4XDTA0MDEwMTAwMDAw
MFoXDTI4MTIzMTIzNTk1OVowezELMAkGA1UEBhMCR0IxGzAZBgNVBAgMEkdyZWF0ZXIgTWFuY2hl
c3RlcjEQMA4GA1UEBwwHU2FsZm9yZDEaMBgGA1UECgwRQ29tb2RvIENBIExpbWl0ZWQxITAfBgNV
BAMMGEFBQSBDZXJ0aWZpY2F0ZSBTZXJ2aWNlczCCASIwDQYJKoZIhvcNAQEBBQADggEPADCCAQoC
ggEBAL5AnfRu4ep2hxxNRUSOvkbIgwadwSr+GB+O5AL686tdUIoWMQuaBtDFcCLNSS1UY8y2bmhG
C1Pqy0wkwLxyTurxFa70VJoSCsN6sjNg4tqJVfMiWPPe3M/vg4aijJRPn2jymJBGhCfHdr/jzDUs
i14HZGWCwEiwqJH5YZ92IFCokcdmtet4YgNW8IoaE+oxox6gmf049vYnMlhvB/VruPsUK6+3qszW
Y19zjNoFmag4qMsXeDZRrOme9Hg6jc8P2ULimAyrL58OAd7vn5lJ8S3frHRNG5i1R8XlKdH5kBjH
Ypy+g8cmez6KJcfA3Z3mNWgQIJ2P2N7Sw4ScDV7oL8kCAwEAAaOBwDCBvTAdBgNVHQ4EFgQUoBEK
Iz6W8Qfs4q8p74Klf9AwpLQwDgYDVR0PAQH/BAQDAgEGMA8GA1UdEwEB/wQFMAMBAf8wewYDVR0f
BHQwcjA4oDagNIYyaHR0cDovL2NybC5jb21vZG9jYS5jb20vQUFBQ2VydGlmaWNhdGVTZXJ2aWNl
cy5jcmwwNqA0oDKGMGh0dHA6Ly9jcmwuY29tb2RvLm5ldC9BQUFDZXJ0aWZpY2F0ZVNlcnZpY2Vz
LmNybDANBgkqhkiG9w0BAQUFAAOCAQEACFb8AvCb6P+k+tZ7xkSAzk/ExfYAWMymtrwUSWgEdujm
7l3sAg9g1o1QGE8mTgHj5rCl7r+8dFRBv/38ErjHT1r0iWAFf2C3BUrz9vHCv8S5dIa2LX1rzNLz
Rt0vxuBqw8M0Ayx9lt1awg6nCpnBBYurDC/zXDrPbDdVCYfeU0BsWO/8tqtlbgT2G9w84FoVxp7Z
8VlIMCFlA2zs6SFz7JsDoeA3raAVGI/6ugLOpyypEBMs1OUIJqsil2D4kF501KKaU73yqWjgom7C
12yxow+ev+to51byrvLjKzg6CYG1a4XXvi3tPxq3smPi9WIsgtRqAEFQ8TmDn5XpNpaYbg==
-----END CERTIFICATE-----

Comodo Secure Services root
===========================
-----BEGIN CERTIFICATE-----
MIIEPzCCAyegAwIBAgIBATANBgkqhkiG9w0BAQUFADB+MQswCQYDVQQGEwJHQjEbMBkGA1UECAwS
R3JlYXRlciBNYW5jaGVzdGVyMRAwDgYDVQQHDAdTYWxmb3JkMRowGAYDVQQKDBFDb21vZG8gQ0Eg
TGltaXRlZDEkMCIGA1UEAwwbU2VjdXJlIENlcnRpZmljYXRlIFNlcnZpY2VzMB4XDTA0MDEwMTAw
MDAwMFoXDTI4MTIzMTIzNTk1OVowfjELMAkGA1UEBhMCR0IxGzAZBgNVBAgMEkdyZWF0ZXIgTWFu
Y2hlc3RlcjEQMA4GA1UEBwwHU2FsZm9yZDEaMBgGA1UECgwRQ29tb2RvIENBIExpbWl0ZWQxJDAi
BgNVBAMMG1NlY3VyZSBDZXJ0aWZpY2F0ZSBTZXJ2aWNlczCCASIwDQYJKoZIhvcNAQEBBQADggEP
ADCCAQoCggEBAMBxM4KK0HDrc4eCQNUd5MvJDkKQ+d40uaG6EfQlhfPMcm3ye5drswfxdySRXyWP
9nQ95IDC+DwN879A6vfIUtFyb+/Iq0G4bi4XKpVpDM3SHpR7LZQdqnXXs5jLrLxkU0C8j6ysNstc
rbvd4JQX7NFc0L/vpZXJkMWwrPsbQ996CF23uPJAGysnnlDOXmWCiIxe004MeuoIkbY2qitC++rC
oznl2yY4rYsK7hljxxwk3wN42ubqwUcaCwtGCd0C/N7Lh1/XMGNooa7cMqG6vv5Eq2i2pRcV/b3V
p6ea5EQz6YiO/O1R65NxTq0B50SOqy3LqP4BSUjwwN3HaNiS/j0CAwEAAaOBxzCBxDAdBgNVHQ4E
FgQUPNiTiMLAggnMAZkGkyDpnnAJY08wDgYDVR0PAQH/BAQDAgEGMA8GA1UdEwEB/wQFMAMBAf8w
gYEGA1UdHwR6MHgwO6A5oDeGNWh0dHA6Ly9jcmwuY29tb2RvY2EuY29tL1NlY3VyZUNlcnRpZmlj
YXRlU2VydmljZXMuY3JsMDmgN6A1hjNodHRwOi8vY3JsLmNvbW9kby5uZXQvU2VjdXJlQ2VydGlm
aWNhdGVTZXJ2aWNlcy5jcmwwDQYJKoZIhvcNAQEFBQADggEBAIcBbSMdflsXfcFhMs+P5/OKlFlm
4J4oqF7Tt/Q05qo5spcWxYJvMqTpjOev/e/C6LlLqqP05tqNZSH7uoDrJiiFGv45jN5bBAS0VPmj
Z55B+glSzAVIqMk/IQQezkhr/IXownuvf7fM+F86/TXGDe+X3EyrEeFryzHRbPtIgKvcnDe4IRRL
DXE97IMzbtFuMhbsmMcWi1mmNKsFVy2T96oTy9IT4rcuO81rUBcJaD61JlfutuC23bkpgHl9j6Pw
pCikFcSF9CfUa7/lXORlAnZUtOM3ZiTTGWHIUhDlizeauan5Hb/qmZJhlv8BzaFfDbxxvA6sCx1H
RR3B7Hzs/Sk=
-----END CERTIFICATE-----

Comodo Trusted Services root
============================
-----BEGIN CERTIFICATE-----
MIIEQzCCAyugAwIBAgIBATANBgkqhkiG9w0BAQUFADB/MQswCQYDVQQGEwJHQjEbMBkGA1UECAwS
R3JlYXRlciBNYW5jaGVzdGVyMRAwDgYDVQQHDAdTYWxmb3JkMRowGAYDVQQKDBFDb21vZG8gQ0Eg
TGltaXRlZDElMCMGA1UEAwwcVHJ1c3RlZCBDZXJ0aWZpY2F0ZSBTZXJ2aWNlczAeFw0wNDAxMDEw
MDAwMDBaFw0yODEyMzEyMzU5NTlaMH8xCzAJBgNVBAYTAkdCMRswGQYDVQQIDBJHcmVhdGVyIE1h
bmNoZXN0ZXIxEDAOBgNVBAcMB1NhbGZvcmQxGjAYBgNVBAoMEUNvbW9kbyBDQSBMaW1pdGVkMSUw
IwYDVQQDDBxUcnVzdGVkIENlcnRpZmljYXRlIFNlcnZpY2VzMIIBIjANBgkqhkiG9w0BAQEFAAOC
AQ8AMIIBCgKCAQEA33FvNlhTWvI2VFeAxHQIIO0Yfyod5jWaHiWsnOWWfnJSoBVC21ndZHoa0Lh7
3TkVvFVIxO06AOoxEbrycXQaZ7jPM8yoMa+j49d/vzMtTGo87IvDktJTdyR0nAducPy9C1t2ul/y
/9c3S0pgePfw+spwtOpZqqPOSC+pw7ILfhdyFgymBwwbOM/JYrc/oJOlh0Hyt3BAd9i+FHzjqMB6
juljatEPmsbS9Is6FARW1O24zG71++IsWL1/T2sr92AkWCTOJu80kTrV44HQsvAEAtdbtz6SrGsS
ivnkBbA7kUlcsutT6vifR4buv5XAwAaf0lteERv0xwQ1KdJVXOTt6wIDAQABo4HJMIHGMB0GA1Ud
DgQWBBTFe1i97doladL3WRaoszLAeydb9DAOBgNVHQ8BAf8EBAMCAQYwDwYDVR0TAQH/BAUwAwEB
/zCBgwYDVR0fBHwwejA8oDqgOIY2aHR0cDovL2NybC5jb21vZG9jYS5jb20vVHJ1c3RlZENlcnRp
ZmljYXRlU2VydmljZXMuY3JsMDqgOKA2hjRodHRwOi8vY3JsLmNvbW9kby5uZXQvVHJ1c3RlZENl
cnRpZmljYXRlU2VydmljZXMuY3JsMA0GCSqGSIb3DQEBBQUAA4IBAQDIk4E7ibSvuIQSTI3S8Ntw
uleGFTQQuS9/HrCoiWChisJ3DFBKmwCL2Iv0QeLQg4pKHBQGsKNoBXAxMKdTmw7pSqBYaWcOrp32
pSxBvzwGa+RZzG0Q8ZZvH9/0BAKkn0U+yNj6NkZEUD+Cl5EfKNsYEYwq5GWDVxISjBc/lDb+XbDA
BHcTuPQV1T84zJQ6VdCsmPW6AF/ghhmBeC8owH7TzEIK9a5QoNE+xqFx7D+gIIxmOom0jtTYsU0l
R+4viMi14QVFwL4Ucd56/Y57fU0IlqUSc/AtyjcndBInTMu2l+nZrghtWjlA3QVHdWpaIbOjGM9O
9y5Xt5hwXsjEeLBi
-----END CERTIFICATE-----

QuoVadis Root CA
================
-----BEGIN CERTIFICATE-----
MIIF0DCCBLigAwIBAgIEOrZQizANBgkqhkiG9w0BAQUFADB/MQswCQYDVQQGEwJCTTEZMBcGA1UE
ChMQUXVvVmFkaXMgTGltaXRlZDElMCMGA1UECxMcUm9vdCBDZXJ0aWZpY2F0aW9uIEF1dGhvcml0
eTEuMCwGA1UEAxMlUXVvVmFkaXMgUm9vdCBDZXJ0aWZpY2F0aW9uIEF1dGhvcml0eTAeFw0wMTAz
MTkxODMzMzNaFw0yMTAzMTcxODMzMzNaMH8xCzAJBgNVBAYTAkJNMRkwFwYDVQQKExBRdW9WYWRp
cyBMaW1pdGVkMSUwIwYDVQQLExxSb290IENlcnRpZmljYXRpb24gQXV0aG9yaXR5MS4wLAYDVQQD
EyVRdW9WYWRpcyBSb290IENlcnRpZmljYXRpb24gQXV0aG9yaXR5MIIBIjANBgkqhkiG9w0BAQEF
AAOCAQ8AMIIBCgKCAQEAv2G1lVO6V/z68mcLOhrfEYBklbTRvM16z/Ypli4kVEAkOPcahdxYTMuk
J0KX0J+DisPkBgNbAKVRHnAEdOLB1Dqr1607BxgFjv2DrOpm2RgbaIr1VxqYuvXtdj182d6UajtL
F8HVj71lODqV0D1VNk7feVcxKh7YWWVJWCCYfqtffp/p1k3sg3Spx2zY7ilKhSoGFPlU5tPaZQeL
YzcS19Dsw3sgQUSj7cugF+FxZc4dZjH3dgEZyH0DWLaVSR2mEiboxgx24ONmy+pdpibu5cxfvWen
AScOospUxbF6lR1xHkopigPcakXBpBlebzbNw6Kwt/5cOOJSvPhEQ+aQuwIDAQABo4ICUjCCAk4w
PQYIKwYBBQUHAQEEMTAvMC0GCCsGAQUFBzABhiFodHRwczovL29jc3AucXVvdmFkaXNvZmZzaG9y
ZS5jb20wDwYDVR0TAQH/BAUwAwEB/zCCARoGA1UdIASCAREwggENMIIBCQYJKwYBBAG+WAABMIH7
MIHUBggrBgEFBQcCAjCBxxqBxFJlbGlhbmNlIG9uIHRoZSBRdW9WYWRpcyBSb290IENlcnRpZmlj
YXRlIGJ5IGFueSBwYXJ0eSBhc3N1bWVzIGFjY2VwdGFuY2Ugb2YgdGhlIHRoZW4gYXBwbGljYWJs
ZSBzdGFuZGFyZCB0ZXJtcyBhbmQgY29uZGl0aW9ucyBvZiB1c2UsIGNlcnRpZmljYXRpb24gcHJh
Y3RpY2VzLCBhbmQgdGhlIFF1b1ZhZGlzIENlcnRpZmljYXRlIFBvbGljeS4wIgYIKwYBBQUHAgEW
Fmh0dHA6Ly93d3cucXVvdmFkaXMuYm0wHQYDVR0OBBYEFItLbe3TKbkGGew5Oanwl4Rqy+/fMIGu
BgNVHSMEgaYwgaOAFItLbe3TKbkGGew5Oanwl4Rqy+/foYGEpIGBMH8xCzAJBgNVBAYTAkJNMRkw
FwYDVQQKExBRdW9WYWRpcyBMaW1pdGVkMSUwIwYDVQQLExxSb290IENlcnRpZmljYXRpb24gQXV0
aG9yaXR5MS4wLAYDVQQDEyVRdW9WYWRpcyBSb290IENlcnRpZmljYXRpb24gQXV0aG9yaXR5ggQ6
tlCLMA4GA1UdDwEB/wQEAwIBBjANBgkqhkiG9w0BAQUFAAOCAQEAitQUtf70mpKnGdSkfnIYj9lo
fFIk3WdvOXrEql494liwTXCYhGHoG+NpGA7O+0dQoE7/8CQfvbLO9Sf87C9TqnN7Az10buYWnuul
LsS/VidQK2K6vkscPFVcQR0kvoIgR13VRH56FmjffU1RcHhXHTMe/QKZnAzNCgVPx7uOpHX6Sm2x
gI4JVrmcGmD+XcHXetwReNDWXcG31a0ymQM6isxUJTkxgXsTIlG6Rmyhu576BGxJJnSP0nPrzDCi
5upZIof4l/UO/erMkqQWxFIY6iHOsfHmhIHluqmGKPJDWl0Snawe2ajlCmqnf6CHKc/yiU3U7MXi
5nrQNiOKSnQ2+Q==
-----END CERTIFICATE-----

QuoVadis Root CA 2
==================
-----BEGIN CERTIFICATE-----
MIIFtzCCA5+gAwIBAgICBQkwDQYJKoZIhvcNAQEFBQAwRTELMAkGA1UEBhMCQk0xGTAXBgNVBAoT
EFF1b1ZhZGlzIExpbWl0ZWQxGzAZBgNVBAMTElF1b1ZhZGlzIFJvb3QgQ0EgMjAeFw0wNjExMjQx
ODI3MDBaFw0zMTExMjQxODIzMzNaMEUxCzAJBgNVBAYTAkJNMRkwFwYDVQQKExBRdW9WYWRpcyBM
aW1pdGVkMRswGQYDVQQDExJRdW9WYWRpcyBSb290IENBIDIwggIiMA0GCSqGSIb3DQEBAQUAA4IC
DwAwggIKAoICAQCaGMpLlA0ALa8DKYrwD4HIrkwZhR0In6spRIXzL4GtMh6QRr+jhiYaHv5+HBg6
XJxgFyo6dIMzMH1hVBHL7avg5tKifvVrbxi3Cgst/ek+7wrGsxDp3MJGF/hd/aTa/55JWpzmM+Yk
lvc/ulsrHHo1wtZn/qtmUIttKGAr79dgw8eTvI02kfN/+NsRE8Scd3bBrrcCaoF6qUWD4gXmuVbB
lDePSHFjIuwXZQeVikvfj8ZaCuWw419eaxGrDPmF60Tp+ARz8un+XJiM9XOva7R+zdRcAitMOeGy
lZUtQofX1bOQQ7dsE/He3fbE+Ik/0XX1ksOR1YqI0JDs3G3eicJlcZaLDQP9nL9bFqyS2+r+eXyt
66/3FsvbzSUr5R/7mp/iUcw6UwxI5g69ybR2BlLmEROFcmMDBOAENisgGQLodKcftslWZvB1Jdxn
wQ5hYIizPtGo/KPaHbDRsSNU30R2be1B2MGyIrZTHN81Hdyhdyox5C315eXbyOD/5YDXC2Og/zOh
D7osFRXql7PSorW+8oyWHhqPHWykYTe5hnMz15eWniN9gqRMgeKh0bpnX5UHoycR7hYQe7xFSkyy
BNKr79X9DFHOUGoIMfmR2gyPZFwDwzqLID9ujWc9Otb+fVuIyV77zGHcizN300QyNQliBJIWENie
J0f7OyHj+OsdWwIDAQABo4GwMIGtMA8GA1UdEwEB/wQFMAMBAf8wCwYDVR0PBAQDAgEGMB0GA1Ud
DgQWBBQahGK8SEwzJQTU7tD2A8QZRtGUazBuBgNVHSMEZzBlgBQahGK8SEwzJQTU7tD2A8QZRtGU
a6FJpEcwRTELMAkGA1UEBhMCQk0xGTAXBgNVBAoTEFF1b1ZhZGlzIExpbWl0ZWQxGzAZBgNVBAMT
ElF1b1ZhZGlzIFJvb3QgQ0EgMoICBQkwDQYJKoZIhvcNAQEFBQADggIBAD4KFk2fBluornFdLwUv
Z+YTRYPENvbzwCYMDbVHZF34tHLJRqUDGCdViXh9duqWNIAXINzng/iN/Ae42l9NLmeyhP3ZRPx3
UIHmfLTJDQtyU/h2BwdBR5YM++CCJpNVjP4iH2BlfF/nJrP3MpCYUNQ3cVX2kiF495V5+vgtJodm
VjB3pjd4M1IQWK4/YY7yarHvGH5KWWPKjaJW1acvvFYfzznB4vsKqBUsfU16Y8Zsl0Q80m/DShcK
+JDSV6IZUaUtl0HaB0+pUNqQjZRG4T7wlP0QADj1O+hA4bRuVhogzG9Yje0uRY/W6ZM/57Es3zrW
IozchLsib9D45MY56QSIPMO661V6bYCZJPVsAfv4l7CUW+v90m/xd2gNNWQjrLhVoQPRTUIZ3Ph1
WVaj+ahJefivDrkRoHy3au000LYmYjgahwz46P0u05B/B5EqHdZ+XIWDmbA4CD/pXvk1B+TJYm5X
f6dQlfe6yJvmjqIBxdZmv3lh8zwc4bmCXF2gw+nYSL0ZohEUGW6yhhtoPkg3Goi3XZZenMfvJ2II
4pEZXNLxId26F0KCl3GBUzGpn/Z9Yr9y4aOTHcyKJloJONDO1w2AFrR4pTqHTI2KpdVGl/IsELm8
VCLAAVBpQ570su9t+Oza8eOx79+Rj1QqCyXBJhnEUhAFZdWCEOrCMc0u
-----END CERTIFICATE-----

QuoVadis Root CA 3
==================
-----BEGIN CERTIFICATE-----
MIIGnTCCBIWgAwIBAgICBcYwDQYJKoZIhvcNAQEFBQAwRTELMAkGA1UEBhMCQk0xGTAXBgNVBAoT
EFF1b1ZhZGlzIExpbWl0ZWQxGzAZBgNVBAMTElF1b1ZhZGlzIFJvb3QgQ0EgMzAeFw0wNjExMjQx
OTExMjNaFw0zMTExMjQxOTA2NDRaMEUxCzAJBgNVBAYTAkJNMRkwFwYDVQQKExBRdW9WYWRpcyBM
aW1pdGVkMRswGQYDVQQDExJRdW9WYWRpcyBSb290IENBIDMwggIiMA0GCSqGSIb3DQEBAQUAA4IC
DwAwggIKAoICAQDMV0IWVJzmmNPTTe7+7cefQzlKZbPoFog02w1ZkXTPkrgEQK0CSzGrvI2RaNgg
DhoB4hp7Thdd4oq3P5kazethq8Jlph+3t723j/z9cI8LoGe+AaJZz3HmDyl2/7FWeUUrH556VOij
KTVopAFPD6QuN+8bv+OPEKhyq1hX51SGyMnzW9os2l2ObjyjPtr7guXd8lyyBTNvijbO0BNO/79K
DDRMpsMhvVAEVeuxu537RR5kFd5VAYwCdrXLoT9CabwvvWhDFlaJKjdhkf2mrk7AyxRllDdLkgbv
BNDInIjbC3uBr7E9KsRlOni27tyAsdLTmZw67mtaa7ONt9XOnMK+pUsvFrGeaDsGb659n/je7Mwp
p5ijJUMv7/FfJuGITfhebtfZFG4ZM2mnO4SJk8RTVROhUXhA+LjJou57ulJCg54U7QVSWllWp5f8
nT8KKdjcT5EOE7zelaTfi5m+rJsziO+1ga8bxiJTyPbH7pcUsMV8eFLI8M5ud2CEpukqdiDtWAEX
MJPpGovgc2PZapKUSU60rUqFxKMiMPwJ7Wgic6aIDFUhWMXhOp8q3crhkODZc6tsgLjoC2SToJyM
Gf+z0gzskSaHirOi4XCPLArlzW1oUevaPwV/izLmE1xr/l9A4iLItLRkT9a6fUg+qGkM17uGcclz
uD87nSVL2v9A6wIDAQABo4IBlTCCAZEwDwYDVR0TAQH/BAUwAwEB/zCB4QYDVR0gBIHZMIHWMIHT
BgkrBgEEAb5YAAMwgcUwgZMGCCsGAQUFBwICMIGGGoGDQW55IHVzZSBvZiB0aGlzIENlcnRpZmlj
YXRlIGNvbnN0aXR1dGVzIGFjY2VwdGFuY2Ugb2YgdGhlIFF1b1ZhZGlzIFJvb3QgQ0EgMyBDZXJ0
aWZpY2F0ZSBQb2xpY3kgLyBDZXJ0aWZpY2F0aW9uIFByYWN0aWNlIFN0YXRlbWVudC4wLQYIKwYB
BQUHAgEWIWh0dHA6Ly93d3cucXVvdmFkaXNnbG9iYWwuY29tL2NwczALBgNVHQ8EBAMCAQYwHQYD
VR0OBBYEFPLAE+CCQz777i9nMpY1XNu4ywLQMG4GA1UdIwRnMGWAFPLAE+CCQz777i9nMpY1XNu4
ywLQoUmkRzBFMQswCQYDVQQGEwJCTTEZMBcGA1UEChMQUXVvVmFkaXMgTGltaXRlZDEbMBkGA1UE
AxMSUXVvVmFkaXMgUm9vdCBDQSAzggIFxjANBgkqhkiG9w0BAQUFAAOCAgEAT62gLEz6wPJv92ZV
qyM07ucp2sNbtrCD2dDQ4iH782CnO11gUyeim/YIIirnv6By5ZwkajGxkHon24QRiSemd1o417+s
hvzuXYO8BsbRd2sPbSQvS3pspweWyuOEn62Iix2rFo1bZhfZFvSLgNLd+LJ2w/w4E6oM3kJpK27z
POuAJ9v1pkQNn1pVWQvVDVJIxa6f8i+AxeoyUDUSly7B4f/xI4hROJ/yZlZ25w9Rl6VSDE1JUZU2
Pb+iSwwQHYaZTKrzchGT5Or2m9qoXadNt54CrnMAyNojA+j56hl0YgCUyyIgvpSnWbWCar6ZeXqp
8kokUvd0/bpO5qgdAm6xDYBEwa7TIzdfu4V8K5Iu6H6li92Z4b8nby1dqnuH/grdS/yO9SbkbnBC
bjPsMZ57k8HkyWkaPcBrTiJt7qtYTcbQQcEr6k8Sh17rRdhs9ZgC06DYVYoGmRmioHfRMJ6szHXu
g/WwYjnPbFfiTNKRCw51KBuav/0aQ/HKd/s7j2G4aSgWQgRecCocIdiP4b0jWy10QJLZYxkNc91p
vGJHvOB0K7Lrfb5BG7XARsWhIstfTsEokt4YutUqKLsRixeTmJlglFwjz1onl14LBQaTNx47aTbr
qZ5hHY8y2o4M1nQ+ewkk2gF3R8Q7zTSMmfXK4SVhM7JZG+Ju1zdXtg2pEto=
-----END CERTIFICATE-----

Security Communication Root CA
==============================
-----BEGIN CERTIFICATE-----
MIIDWjCCAkKgAwIBAgIBADANBgkqhkiG9w0BAQUFADBQMQswCQYDVQQGEwJKUDEYMBYGA1UEChMP
U0VDT00gVHJ1c3QubmV0MScwJQYDVQQLEx5TZWN1cml0eSBDb21tdW5pY2F0aW9uIFJvb3RDQTEw
HhcNMDMwOTMwMDQyMDQ5WhcNMjMwOTMwMDQyMDQ5WjBQMQswCQYDVQQGEwJKUDEYMBYGA1UEChMP
U0VDT00gVHJ1c3QubmV0MScwJQYDVQQLEx5TZWN1cml0eSBDb21tdW5pY2F0aW9uIFJvb3RDQTEw
ggEiMA0GCSqGSIb3DQEBAQUAA4IBDwAwggEKAoIBAQCzs/5/022x7xZ8V6UMbXaKL0u/ZPtM7orw
8yl89f/uKuDp6bpbZCKamm8sOiZpUQWZJtzVHGpxxpp9Hp3dfGzGjGdnSj74cbAZJ6kJDKaVv0uM
DPpVmDvY6CKhS3E4eayXkmmziX7qIWgGmBSWh9JhNrxtJ1aeV+7AwFb9Ms+k2Y7CI9eNqPPYJayX
5HA49LY6tJ07lyZDo6G8SVlyTCMwhwFY9k6+HGhWZq/NQV3Is00qVUarH9oe4kA92819uZKAnDfd
DJZkndwi92SL32HeFZRSFaB9UslLqCHJxrHty8OVYNEP8Ktw+N/LTX7s1vqr2b1/VPKl6Xn62dZ2
JChzAgMBAAGjPzA9MB0GA1UdDgQWBBSgc0mZaNyFW2XjmygvV5+9M7wHSDALBgNVHQ8EBAMCAQYw
DwYDVR0TAQH/BAUwAwEB/zANBgkqhkiG9w0BAQUFAAOCAQEAaECpqLvkT115swW1F7NgE+vGkl3g
0dNq/vu+m22/xwVtWSDEHPC32oRYAmP6SBbvT6UL90qY8j+eG61Ha2POCEfrUj94nK9NrvjVT8+a
mCoQQTlSxN3Zmw7vkwGusi7KaEIkQmywszo+zenaSMQVy+n5Bw+SUEmK3TGXX8npN6o7WWWXlDLJ
s58+OmJYxUmtYg5xpTKqL8aJdkNAExNnPaJUJRDL8Try2frbSVa7pv6nQTXD4IhhyYjH3zYQIphZ
6rBK+1YWc26sTfcioU+tHXotRSflMMFe8toTyyVCUZVHA4xsIcx0Qu1T/zOLjw9XARYvz6buyXAi
FL39vmwLAw==
-----END CERTIFICATE-----

Sonera Class 2 Root CA
======================
-----BEGIN CERTIFICATE-----
MIIDIDCCAgigAwIBAgIBHTANBgkqhkiG9w0BAQUFADA5MQswCQYDVQQGEwJGSTEPMA0GA1UEChMG
U29uZXJhMRkwFwYDVQQDExBTb25lcmEgQ2xhc3MyIENBMB4XDTAxMDQwNjA3Mjk0MFoXDTIxMDQw
NjA3Mjk0MFowOTELMAkGA1UEBhMCRkkxDzANBgNVBAoTBlNvbmVyYTEZMBcGA1UEAxMQU29uZXJh
IENsYXNzMiBDQTCCASIwDQYJKoZIhvcNAQEBBQADggEPADCCAQoCggEBAJAXSjWdyvANlsdE+hY3
/Ei9vX+ALTU74W+oZ6m/AxxNjG8yR9VBaKQTBME1DJqEQ/xcHf+Js+gXGM2RX/uJ4+q/Tl18GybT
dXnt5oTjV+WtKcT0OijnpXuENmmz/V52vaMtmdOQTiMofRhj8VQ7Jp12W5dCsv+u8E7s3TmVToMG
f+dJQMjFAbJUWmYdPfz56TwKnoG4cPABi+QjVHzIrviQHgCWctRUz2EjvOr7nQKV0ba5cTppCD8P
tOFCx4j1P5iop7oc4HFx71hXgVB6XGt0Rg6DA5jDjqhu8nYybieDwnPz3BjotJPqdURrBGAgcVeH
nfO+oJAjPYok4doh28MCAwEAAaMzMDEwDwYDVR0TAQH/BAUwAwEB/zARBgNVHQ4ECgQISqCqWITT
XjwwCwYDVR0PBAQDAgEGMA0GCSqGSIb3DQEBBQUAA4IBAQBazof5FnIVV0sd2ZvnoiYw7JNn39Yt
0jSv9zilzqsWuasvfDXLrNAPtEwr/IDva4yRXzZ299uzGxnq9LIR/WFxRL8oszodv7ND6J+/3DEI
cbCdjdY0RzKQxmUk96BKfARzjzlvF4xytb1LyHr4e4PDKE6cCepnP7JnBBvDFNr450kkkdAdavph
Oe9r5yF1BgfYErQhIHBCcYHaPJo2vqZbDWpsmh+Re/n570K6Tk6ezAyNlNzZRZxe7EJQY670XcSx
EtzKO6gunRRaBXW37Ndj4ro1tgQIkejanZz2ZrUYrAqmVCY0M9IbwdR/GjqOC6oybtv8TyWf2TLH
llpwrN9M
-----END CERTIFICATE-----

Staat der Nederlanden Root CA
=============================
-----BEGIN CERTIFICATE-----
MIIDujCCAqKgAwIBAgIEAJiWijANBgkqhkiG9w0BAQUFADBVMQswCQYDVQQGEwJOTDEeMBwGA1UE
ChMVU3RhYXQgZGVyIE5lZGVybGFuZGVuMSYwJAYDVQQDEx1TdGFhdCBkZXIgTmVkZXJsYW5kZW4g
Um9vdCBDQTAeFw0wMjEyMTcwOTIzNDlaFw0xNTEyMTYwOTE1MzhaMFUxCzAJBgNVBAYTAk5MMR4w
HAYDVQQKExVTdGFhdCBkZXIgTmVkZXJsYW5kZW4xJjAkBgNVBAMTHVN0YWF0IGRlciBOZWRlcmxh
bmRlbiBSb290IENBMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAmNK1URF6gaYUmHFt
vsznExvWJw56s2oYHLZhWtVhCb/ekBPHZ+7d89rFDBKeNVU+LCeIQGv33N0iYfXCxw719tV2U02P
jLwYdjeFnejKScfST5gTCaI+Ioicf9byEGW07l8Y1Rfj+MX94p2i71MOhXeiD+EwR+4A5zN9RGca
C1Hoi6CeUJhoNFIfLm0B8mBF8jHrqTFoKbt6QZ7GGX+UtFE5A3+y3qcym7RHjm+0Sq7lr7HcsBth
vJly3uSJt3omXdozSVtSnA71iq3DuD3oBmrC1SoLbHuEvVYFy4ZlkuxEK7COudxwC0barbxjiDn6
22r+I/q85Ej0ZytqERAhSQIDAQABo4GRMIGOMAwGA1UdEwQFMAMBAf8wTwYDVR0gBEgwRjBEBgRV
HSAAMDwwOgYIKwYBBQUHAgEWLmh0dHA6Ly93d3cucGtpb3ZlcmhlaWQubmwvcG9saWNpZXMvcm9v
dC1wb2xpY3kwDgYDVR0PAQH/BAQDAgEGMB0GA1UdDgQWBBSofeu8Y6R0E3QA7Jbg0zTBLL9s+DAN
BgkqhkiG9w0BAQUFAAOCAQEABYSHVXQ2YcG70dTGFagTtJ+k/rvuFbQvBgwp8qiSpGEN/KtcCFtR
EytNwiphyPgJWPwtArI5fZlmgb9uXJVFIGzmeafR2Bwp/MIgJ1HI8XxdNGdphREwxgDS1/PTfLbw
MVcoEoJz6TMvplW0C5GUR5z6u3pCMuiufi3IvKwUv9kP2Vv8wfl6leF9fpb8cbDCTMjfRTTJzg3y
nGQI0DvDKcWy7ZAEwbEpkcUwb8GpcjPM/l0WFywRaed+/sWDCN+83CI6LiBpIzlWYGeQiy52OfsR
iJf2fL1LuCAWZwWN4jvBcj+UlTfHXbme2JOhF4//DGYVwSR8MnwDHTuhWEUykw==
-----END CERTIFICATE-----

UTN DATACorp SGC Root CA
========================
-----BEGIN CERTIFICATE-----
MIIEXjCCA0agAwIBAgIQRL4Mi1AAIbQR0ypoBqmtaTANBgkqhkiG9w0BAQUFADCBkzELMAkGA1UE
BhMCVVMxCzAJBgNVBAgTAlVUMRcwFQYDVQQHEw5TYWx0IExha2UgQ2l0eTEeMBwGA1UEChMVVGhl
IFVTRVJUUlVTVCBOZXR3b3JrMSEwHwYDVQQLExhodHRwOi8vd3d3LnVzZXJ0cnVzdC5jb20xGzAZ
BgNVBAMTElVUTiAtIERBVEFDb3JwIFNHQzAeFw05OTA2MjQxODU3MjFaFw0xOTA2MjQxOTA2MzBa
MIGTMQswCQYDVQQGEwJVUzELMAkGA1UECBMCVVQxFzAVBgNVBAcTDlNhbHQgTGFrZSBDaXR5MR4w
HAYDVQQKExVUaGUgVVNFUlRSVVNUIE5ldHdvcmsxITAfBgNVBAsTGGh0dHA6Ly93d3cudXNlcnRy
dXN0LmNvbTEbMBkGA1UEAxMSVVROIC0gREFUQUNvcnAgU0dDMIIBIjANBgkqhkiG9w0BAQEFAAOC
AQ8AMIIBCgKCAQEA3+5YEKIrblXEjr8uRgnn4AgPLit6E5Qbvfa2gI5lBZMAHryv4g+OGQ0SR+ys
raP6LnD43m77VkIVni5c7yPeIbkFdicZD0/Ww5y0vpQZY/KmEQrrU0icvvIpOxboGqBMpsn0GFlo
wHDyUwDAXlCCpVZvNvlK4ESGoE1O1kduSUrLZ9emxAW5jh70/P/N5zbgnAVssjMiFdC04MwXwLLA
9P4yPykqlXvY8qdOD1R8oQ2AswkDwf9c3V6aPryuvEeKaq5xyh+xKrhfQgUL7EYw0XILyulWbfXv
33i+Ybqypa4ETLyorGkVl73v67SMvzX41MPRKA5cOp9wGDMgd8SirwIDAQABo4GrMIGoMAsGA1Ud
DwQEAwIBxjAPBgNVHRMBAf8EBTADAQH/MB0GA1UdDgQWBBRTMtGzz3/64PGgXYVOktKeRR20TzA9
BgNVHR8ENjA0MDKgMKAuhixodHRwOi8vY3JsLnVzZXJ0cnVzdC5jb20vVVROLURBVEFDb3JwU0dD
LmNybDAqBgNVHSUEIzAhBggrBgEFBQcDAQYKKwYBBAGCNwoDAwYJYIZIAYb4QgQBMA0GCSqGSIb3
DQEBBQUAA4IBAQAnNZcAiosovcYzMB4p/OL31ZjUQLtgyr+rFywJNn9Q+kHcrpY6CiM+iVnJowft
Gzet/Hy+UUla3joKVAgWRcKZsYfNjGjgaQPpxE6YsjuMFrMOoAyYUJuTqXAJyCyjj98C5OBxOvG0
I3KgqgHf35g+FFCgMSa9KOlaMCZ1+XtgHI3zzVAmbQQnmt/VDUVHKWss5nbZqSl9Mt3JNjy9rjXx
EZ4du5A/EkdOjtd+D2JzHVImOBwYSf0wdJrE5SIv2MCN7ZF6TACPcn9d2t0bi0Vr591pl6jFVkwP
DPafepE39peC4N1xaf92P2BNPM/3mfnGV/TJVTl4uix5yaaIK/QI
-----END CERTIFICATE-----

UTN USERFirst Hardware Root CA
==============================
-----BEGIN CERTIFICATE-----
MIIEdDCCA1ygAwIBAgIQRL4Mi1AAJLQR0zYq/mUK/TANBgkqhkiG9w0BAQUFADCBlzELMAkGA1UE
BhMCVVMxCzAJBgNVBAgTAlVUMRcwFQYDVQQHEw5TYWx0IExha2UgQ2l0eTEeMBwGA1UEChMVVGhl
IFVTRVJUUlVTVCBOZXR3b3JrMSEwHwYDVQQLExhodHRwOi8vd3d3LnVzZXJ0cnVzdC5jb20xHzAd
BgNVBAMTFlVUTi1VU0VSRmlyc3QtSGFyZHdhcmUwHhcNOTkwNzA5MTgxMDQyWhcNMTkwNzA5MTgx
OTIyWjCBlzELMAkGA1UEBhMCVVMxCzAJBgNVBAgTAlVUMRcwFQYDVQQHEw5TYWx0IExha2UgQ2l0
eTEeMBwGA1UEChMVVGhlIFVTRVJUUlVTVCBOZXR3b3JrMSEwHwYDVQQLExhodHRwOi8vd3d3LnVz
ZXJ0cnVzdC5jb20xHzAdBgNVBAMTFlVUTi1VU0VSRmlyc3QtSGFyZHdhcmUwggEiMA0GCSqGSIb3
DQEBAQUAA4IBDwAwggEKAoIBAQCx98M4P7Sof885glFn0G2f0v9Y8+efK+wNiVSZuTiZFvfgIXlI
wrthdBKWHTxqctU8EGc6Oe0rE81m65UJM6Rsl7HoxuzBdXmcRl6Nq9Bq/bkqVRcQVLMZ8Jr28bFd
tqdt++BxF2uiiPsA3/4aMXcMmgF6sTLjKwEHOG7DpV4jvEWbe1DByTCP2+UretNb+zNAHqDVmBe8
i4fDidNdoI6yqqr2jmmIBsX6iSHzCJ1pLgkzmykNRg+MzEk0sGlRvfkGzWitZky8PqxhvQqIDsjf
Pe58BEydCl5rkdbux+0ojatNh4lz0G6k0B4WixThdkQDf2Os5M1JnMWS9KsyoUhbAgMBAAGjgbkw
gbYwCwYDVR0PBAQDAgHGMA8GA1UdEwEB/wQFMAMBAf8wHQYDVR0OBBYEFKFyXyYbKJhDlV0HN9WF
lp1L0sNFMEQGA1UdHwQ9MDswOaA3oDWGM2h0dHA6Ly9jcmwudXNlcnRydXN0LmNvbS9VVE4tVVNF
UkZpcnN0LUhhcmR3YXJlLmNybDAxBgNVHSUEKjAoBggrBgEFBQcDAQYIKwYBBQUHAwUGCCsGAQUF
BwMGBggrBgEFBQcDBzANBgkqhkiG9w0BAQUFAAOCAQEARxkP3nTGmZev/K0oXnWO6y1n7k57K9cM
//bey1WiCuFMVGWTYGufEpytXoMs61quwOQt9ABjHbjAbPLPSbtNk28GpgoiskliCE7/yMgUsogW
XecB5BKV5UU0s4tpvc+0hY91UZ59Ojg6FEgSxvunOxqNDYJAB+gECJChicsZUN/KHAG8HQQZexB2
lzvukJDKxA4fFm517zP4029bHpbj4HR3dHuKom4t3XbWOTCC8KucUvIqx69JXn7HaOWCgchqJ/kn
iCrVWFCVH/A7HFe7fRQ5YiuayZSSKqMiDP+JJn1fIytH1xUdqWqeUQ0qUZ6B+dQ7XnASfxAynB67
nfhmqA==
-----END CERTIFICATE-----

Camerfirma Chambers of Commerce Root
====================================
-----BEGIN CERTIFICATE-----
MIIEvTCCA6WgAwIBAgIBADANBgkqhkiG9w0BAQUFADB/MQswCQYDVQQGEwJFVTEnMCUGA1UEChMe
QUMgQ2FtZXJmaXJtYSBTQSBDSUYgQTgyNzQzMjg3MSMwIQYDVQQLExpodHRwOi8vd3d3LmNoYW1i
ZXJzaWduLm9yZzEiMCAGA1UEAxMZQ2hhbWJlcnMgb2YgQ29tbWVyY2UgUm9vdDAeFw0wMzA5MzAx
NjEzNDNaFw0zNzA5MzAxNjEzNDRaMH8xCzAJBgNVBAYTAkVVMScwJQYDVQQKEx5BQyBDYW1lcmZp
cm1hIFNBIENJRiBBODI3NDMyODcxIzAhBgNVBAsTGmh0dHA6Ly93d3cuY2hhbWJlcnNpZ24ub3Jn
MSIwIAYDVQQDExlDaGFtYmVycyBvZiBDb21tZXJjZSBSb290MIIBIDANBgkqhkiG9w0BAQEFAAOC
AQ0AMIIBCAKCAQEAtzZV5aVdGDDg2olUkfzIx1L4L1DZ77F1c2VHfRtbunXF/KGIJPov7coISjlU
xFF6tdpg6jg8gbLL8bvZkSM/SAFwdakFKq0fcfPJVD0dBmpAPrMMhe5cG3nCYsS4No41XQEMIwRH
NaqbYE6gZj3LJgqcQKH0XZi/caulAGgq7YN6D6IUtdQis4CwPAxaUWktWBiP7Zme8a7ileb2R6jW
DA+wWFjbw2Y3npuRVDM30pQcakjJyfKl2qUMI/cjDpwyVV5xnIQFUZot/eZOKjRa3spAN2cMVCFV
d9oKDMyXroDclDZK9D7ONhMeU+SsTjoF7Nuucpw4i9A5O4kKPnf+dQIBA6OCAUQwggFAMBIGA1Ud
EwEB/wQIMAYBAf8CAQwwPAYDVR0fBDUwMzAxoC+gLYYraHR0cDovL2NybC5jaGFtYmVyc2lnbi5v
cmcvY2hhbWJlcnNyb290LmNybDAdBgNVHQ4EFgQU45T1sU3p26EpW1eLTXYGduHRooowDgYDVR0P
AQH/BAQDAgEGMBEGCWCGSAGG+EIBAQQEAwIABzAnBgNVHREEIDAegRxjaGFtYmVyc3Jvb3RAY2hh
bWJlcnNpZ24ub3JnMCcGA1UdEgQgMB6BHGNoYW1iZXJzcm9vdEBjaGFtYmVyc2lnbi5vcmcwWAYD
VR0gBFEwTzBNBgsrBgEEAYGHLgoDATA+MDwGCCsGAQUFBwIBFjBodHRwOi8vY3BzLmNoYW1iZXJz
aWduLm9yZy9jcHMvY2hhbWJlcnNyb290Lmh0bWwwDQYJKoZIhvcNAQEFBQADggEBAAxBl8IahsAi
fJ/7kPMa0QOx7xP5IV8EnNrJpY0nbJaHkb5BkAFyk+cefV/2icZdp0AJPaxJRUXcLo0waLIJuvvD
L8y6C98/d3tGfToSJI6WjzwFCm/SlCgdbQzALogi1djPHRPH8EjX1wWnz8dHnjs8NMiAT9QUu/wN
UPf6s+xCX6ndbcj0dc97wXImsQEcXCz9ek60AcUFV7nnPKoF2YjpB0ZBzu9Bga5Y34OirsrXdx/n
ADydb47kMgkdTXg0eDQ8lJsm7U9xxhl6vSAiSFr+S30Dt+dYvsYyTnQeaN2oaFuzPu5ifdmA6Ap1
erfutGWaIZDgqtCYvDi1czyL+Nw=
-----END CERTIFICATE-----

Camerfirma Global Chambersign Root
==================================
-----BEGIN CERTIFICATE-----
MIIExTCCA62gAwIBAgIBADANBgkqhkiG9w0BAQUFADB9MQswCQYDVQQGEwJFVTEnMCUGA1UEChMe
QUMgQ2FtZXJmaXJtYSBTQSBDSUYgQTgyNzQzMjg3MSMwIQYDVQQLExpodHRwOi8vd3d3LmNoYW1i
ZXJzaWduLm9yZzEgMB4GA1UEAxMXR2xvYmFsIENoYW1iZXJzaWduIFJvb3QwHhcNMDMwOTMwMTYx
NDE4WhcNMzcwOTMwMTYxNDE4WjB9MQswCQYDVQQGEwJFVTEnMCUGA1UEChMeQUMgQ2FtZXJmaXJt
YSBTQSBDSUYgQTgyNzQzMjg3MSMwIQYDVQQLExpodHRwOi8vd3d3LmNoYW1iZXJzaWduLm9yZzEg
MB4GA1UEAxMXR2xvYmFsIENoYW1iZXJzaWduIFJvb3QwggEgMA0GCSqGSIb3DQEBAQUAA4IBDQAw
ggEIAoIBAQCicKLQn0KuWxfH2H3PFIP8T8mhtxOviteePgQKkotgVvq0Mi+ITaFgCPS3CU6gSS9J
1tPfnZdan5QEcOw/Wdm3zGaLmFIoCQLfxS+EjXqXd7/sQJ0lcqu1PzKY+7e3/HKE5TWH+VX6ox8O
by4o3Wmg2UIQxvi1RMLQQ3/bvOSiPGpVeAp3qdjqGTK3L/5cPxvusZjsyq16aUXjlg9V9ubtdepl
6DJWk0aJqCWKZQbua795B9Dxt6/tLE2Su8CoX6dnfQTyFQhwrJLWfQTSM/tMtgsL+xrJxI0DqX5c
8lCrEqWhz0hQpe/SyBoT+rB/sYIcd2oPX9wLlY/vQ37mRQklAgEDo4IBUDCCAUwwEgYDVR0TAQH/
BAgwBgEB/wIBDDA/BgNVHR8EODA2MDSgMqAwhi5odHRwOi8vY3JsLmNoYW1iZXJzaWduLm9yZy9j
aGFtYmVyc2lnbnJvb3QuY3JsMB0GA1UdDgQWBBRDnDafsJ4wTcbOX60Qq+UDpfqpFDAOBgNVHQ8B
Af8EBAMCAQYwEQYJYIZIAYb4QgEBBAQDAgAHMCoGA1UdEQQjMCGBH2NoYW1iZXJzaWducm9vdEBj
aGFtYmVyc2lnbi5vcmcwKgYDVR0SBCMwIYEfY2hhbWJlcnNpZ25yb290QGNoYW1iZXJzaWduLm9y
ZzBbBgNVHSAEVDBSMFAGCysGAQQBgYcuCgEBMEEwPwYIKwYBBQUHAgEWM2h0dHA6Ly9jcHMuY2hh
bWJlcnNpZ24ub3JnL2Nwcy9jaGFtYmVyc2lnbnJvb3QuaHRtbDANBgkqhkiG9w0BAQUFAAOCAQEA
PDtwkfkEVCeR4e3t/mh/YV3lQWVPMvEYBZRqHN4fcNs+ezICNLUMbKGKfKX0j//U2K0X1S0E0T9Y
gOKBWYi+wONGkyT+kL0mojAt6JcmVzWJdJYY9hXiryQZVgICsroPFOrGimbBhkVVi76SvpykBMdJ
PJ7oKXqJ1/6v/2j1pReQvayZzKWGVwlnRtvWFsJG8eSpUPWP0ZIV018+xgBJOm5YstHRJw0lyDL4
IBHNfTIzSJRUTN3cecQwn+uOuFW114hcxWokPbLTBQNRxgfvzBRydD1ucs4YKIxKoHflCStFREes
t2d/AYoFWpO+ocH/+OcOZ6RHSXZddZAa9SaP8A==
-----END CERTIFICATE-----

NetLock Notary (Class A) Root
=============================
-----BEGIN CERTIFICATE-----
MIIGfTCCBWWgAwIBAgICAQMwDQYJKoZIhvcNAQEEBQAwga8xCzAJBgNVBAYTAkhVMRAwDgYDVQQI
EwdIdW5nYXJ5MREwDwYDVQQHEwhCdWRhcGVzdDEnMCUGA1UEChMeTmV0TG9jayBIYWxvemF0Yml6
dG9uc2FnaSBLZnQuMRowGAYDVQQLExFUYW51c2l0dmFueWtpYWRvazE2MDQGA1UEAxMtTmV0TG9j
ayBLb3pqZWd5em9pIChDbGFzcyBBKSBUYW51c2l0dmFueWtpYWRvMB4XDTk5MDIyNDIzMTQ0N1oX
DTE5MDIxOTIzMTQ0N1owga8xCzAJBgNVBAYTAkhVMRAwDgYDVQQIEwdIdW5nYXJ5MREwDwYDVQQH
EwhCdWRhcGVzdDEnMCUGA1UEChMeTmV0TG9jayBIYWxvemF0Yml6dG9uc2FnaSBLZnQuMRowGAYD
VQQLExFUYW51c2l0dmFueWtpYWRvazE2MDQGA1UEAxMtTmV0TG9jayBLb3pqZWd5em9pIChDbGFz
cyBBKSBUYW51c2l0dmFueWtpYWRvMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAvHSM
D7tM9DceqQWC2ObhbHDqeLVu0ThEDaiDzl3S1tWBxdRL51uUcCbbO51qTGL3cfNk1mE7PetzozfZ
z+qMkjvN9wfcZnSX9EUi3fRc4L9t875lM+QVOr/bmJBVOMTtplVjC7B4BPTjbsE/jvxReB+SnoPC
/tmwqcm8WgD/qaiYdPv2LD4VOQ22BFWoDpggQrOxJa1+mm9dU7GrDPzr4PN6s6iz/0b2Y6LYOph7
tqyF/7AlT3Rj5xMHpQqPBffAZG9+pyeAlt7ULoZgx2srXnN7F+eRP2QM2EsiNCubMvJIH5+hCoR6
4sKtlz2O1cH5VqNQ6ca0+pii7pXmKgOM3wIDAQABo4ICnzCCApswDgYDVR0PAQH/BAQDAgAGMBIG
A1UdEwEB/wQIMAYBAf8CAQQwEQYJYIZIAYb4QgEBBAQDAgAHMIICYAYJYIZIAYb4QgENBIICURaC
Ak1GSUdZRUxFTSEgRXplbiB0YW51c2l0dmFueSBhIE5ldExvY2sgS2Z0LiBBbHRhbGFub3MgU3pv
bGdhbHRhdGFzaSBGZWx0ZXRlbGVpYmVuIGxlaXJ0IGVsamFyYXNvayBhbGFwamFuIGtlc3p1bHQu
IEEgaGl0ZWxlc2l0ZXMgZm9seWFtYXRhdCBhIE5ldExvY2sgS2Z0LiB0ZXJtZWtmZWxlbG9zc2Vn
LWJpenRvc2l0YXNhIHZlZGkuIEEgZGlnaXRhbGlzIGFsYWlyYXMgZWxmb2dhZGFzYW5hayBmZWx0
ZXRlbGUgYXogZWxvaXJ0IGVsbGVub3J6ZXNpIGVsamFyYXMgbWVndGV0ZWxlLiBBeiBlbGphcmFz
IGxlaXJhc2EgbWVndGFsYWxoYXRvIGEgTmV0TG9jayBLZnQuIEludGVybmV0IGhvbmxhcGphbiBh
IGh0dHBzOi8vd3d3Lm5ldGxvY2submV0L2RvY3MgY2ltZW4gdmFneSBrZXJoZXRvIGF6IGVsbGVu
b3J6ZXNAbmV0bG9jay5uZXQgZS1tYWlsIGNpbWVuLiBJTVBPUlRBTlQhIFRoZSBpc3N1YW5jZSBh
bmQgdGhlIHVzZSBvZiB0aGlzIGNlcnRpZmljYXRlIGlzIHN1YmplY3QgdG8gdGhlIE5ldExvY2sg
Q1BTIGF2YWlsYWJsZSBhdCBodHRwczovL3d3dy5uZXRsb2NrLm5ldC9kb2NzIG9yIGJ5IGUtbWFp
bCBhdCBjcHNAbmV0bG9jay5uZXQuMA0GCSqGSIb3DQEBBAUAA4IBAQBIJEb3ulZv+sgoA0BO5TE5
ayZrU3/b39/zcT0mwBQOxmd7I6gMc90Bu8bKbjc5VdXHjFYgDigKDtIqpLBJUsY4B/6+CgmM0ZjP
ytoUMaFP0jn8DxEsQ8Pdq5PHVT5HfBgaANzze9jyf1JsIPQLX2lS9O74silg6+NJMSEN1rUQQeJB
CWziGppWS3cC9qCbmieH6FUpccKQn0V4GuEVZD3QDtigdp+uxdAu6tYPVuxkf1qbFFgBJ34TUMdr
KuZoPL9coAob4Q566eKAw+np9v1sEZ7Q5SgnK1QyQhSCdeZK8CtmdWOMovsEPoMOmzbwGOQmIMOM
8CgHrTwXZoi1/baI
-----END CERTIFICATE-----

XRamp Global CA Root
====================
-----BEGIN CERTIFICATE-----
MIIEMDCCAxigAwIBAgIQUJRs7Bjq1ZxN1ZfvdY+grTANBgkqhkiG9w0BAQUFADCBgjELMAkGA1UE
BhMCVVMxHjAcBgNVBAsTFXd3dy54cmFtcHNlY3VyaXR5LmNvbTEkMCIGA1UEChMbWFJhbXAgU2Vj
dXJpdHkgU2VydmljZXMgSW5jMS0wKwYDVQQDEyRYUmFtcCBHbG9iYWwgQ2VydGlmaWNhdGlvbiBB
dXRob3JpdHkwHhcNMDQxMTAxMTcxNDA0WhcNMzUwMTAxMDUzNzE5WjCBgjELMAkGA1UEBhMCVVMx
HjAcBgNVBAsTFXd3dy54cmFtcHNlY3VyaXR5LmNvbTEkMCIGA1UEChMbWFJhbXAgU2VjdXJpdHkg
U2VydmljZXMgSW5jMS0wKwYDVQQDEyRYUmFtcCBHbG9iYWwgQ2VydGlmaWNhdGlvbiBBdXRob3Jp
dHkwggEiMA0GCSqGSIb3DQEBAQUAA4IBDwAwggEKAoIBAQCYJB69FbS638eMpSe2OAtp87ZOqCwu
IR1cRN8hXX4jdP5efrRKt6atH67gBhbim1vZZ3RrXYCPKZ2GG9mcDZhtdhAoWORlsH9KmHmf4MMx
foArtYzAQDsRhtDLooY2YKTVMIJt2W7QDxIEM5dfT2Fa8OT5kavnHTu86M/0ay00fOJIYRyO82FE
zG+gSqmUsE3a56k0enI4qEHMPJQRfevIpoy3hsvKMzvZPTeL+3o+hiznc9cKV6xkmxnr9A8ECIqs
AxcZZPRaJSKNNCyy9mgdEm3Tih4U2sSPpuIjhdV6Db1q4Ons7Be7QhtnqiXtRYMh/MHJfNViPvry
xS3T/dRlAgMBAAGjgZ8wgZwwEwYJKwYBBAGCNxQCBAYeBABDAEEwCwYDVR0PBAQDAgGGMA8GA1Ud
EwEB/wQFMAMBAf8wHQYDVR0OBBYEFMZPoj0GY4QJnM5i5ASsjVy16bYbMDYGA1UdHwQvMC0wK6Ap
oCeGJWh0dHA6Ly9jcmwueHJhbXBzZWN1cml0eS5jb20vWEdDQS5jcmwwEAYJKwYBBAGCNxUBBAMC
AQEwDQYJKoZIhvcNAQEFBQADggEBAJEVOQMBG2f7Shz5CmBbodpNl2L5JFMn14JkTpAuw0kbK5rc
/Kh4ZzXxHfARvbdI4xD2Dd8/0sm2qlWkSLoC295ZLhVbO50WfUfXN+pfTXYSNrsf16GBBEYgoyxt
qZ4Bfj8pzgCT3/3JknOJiWSe5yvkHJEs0rnOfc5vMZnT5r7SHpDwCRR5XCOrTdLaIR9NmXmd4c8n
nxCbHIgNsIpkQTG4DmyQJKSbXHGPurt+HBvbaoAPIbzp26a3QPSyi6mx5O+aGtA9aZnuqCij4Tyz
8LIRnM98QObd50N9otg6tamN8jSZxNQQ4Qb9CYQQO+7ETPTsJ3xCwnR8gooJybQDJbw=
-----END CERTIFICATE-----

Go Daddy Class 2 CA
===================
-----BEGIN CERTIFICATE-----
MIIEADCCAuigAwIBAgIBADANBgkqhkiG9w0BAQUFADBjMQswCQYDVQQGEwJVUzEhMB8GA1UEChMY
VGhlIEdvIERhZGR5IEdyb3VwLCBJbmMuMTEwLwYDVQQLEyhHbyBEYWRkeSBDbGFzcyAyIENlcnRp
ZmljYXRpb24gQXV0aG9yaXR5MB4XDTA0MDYyOTE3MDYyMFoXDTM0MDYyOTE3MDYyMFowYzELMAkG
A1UEBhMCVVMxITAfBgNVBAoTGFRoZSBHbyBEYWRkeSBHcm91cCwgSW5jLjExMC8GA1UECxMoR28g
RGFkZHkgQ2xhc3MgMiBDZXJ0aWZpY2F0aW9uIEF1dGhvcml0eTCCASAwDQYJKoZIhvcNAQEBBQAD
ggENADCCAQgCggEBAN6d1+pXGEmhW+vXX0iG6r7d/+TvZxz0ZWizV3GgXne77ZtJ6XCAPVYYYwhv
2vLM0D9/AlQiVBDYsoHUwHU9S3/Hd8M+eKsaA7Ugay9qK7HFiH7Eux6wwdhFJ2+qN1j3hybX2C32
qRe3H3I2TqYXP2WYktsqbl2i/ojgC95/5Y0V4evLOtXiEqITLdiOr18SPaAIBQi2XKVlOARFmR6j
YGB0xUGlcmIbYsUfb18aQr4CUWWoriMYavx4A6lNf4DD+qta/KFApMoZFv6yyO9ecw3ud72a9nmY
vLEHZ6IVDd2gWMZEewo+YihfukEHU1jPEX44dMX4/7VpkI+EdOqXG68CAQOjgcAwgb0wHQYDVR0O
BBYEFNLEsNKR1EwRcbNhyz2h/t2oatTjMIGNBgNVHSMEgYUwgYKAFNLEsNKR1EwRcbNhyz2h/t2o
atTjoWekZTBjMQswCQYDVQQGEwJVUzEhMB8GA1UEChMYVGhlIEdvIERhZGR5IEdyb3VwLCBJbmMu
MTEwLwYDVQQLEyhHbyBEYWRkeSBDbGFzcyAyIENlcnRpZmljYXRpb24gQXV0aG9yaXR5ggEAMAwG
A1UdEwQFMAMBAf8wDQYJKoZIhvcNAQEFBQADggEBADJL87LKPpH8EsahB4yOd6AzBhRckB4Y9wim
PQoZ+YeAEW5p5JYXMP80kWNyOO7MHAGjHZQopDH2esRU1/blMVgDoszOYtuURXO1v0XJJLXVggKt
I3lpjbi2Tc7PTMozI+gciKqdi0FuFskg5YmezTvacPd+mSYgFFQlq25zheabIZ0KbIIOqPjCDPoQ
HmyW74cNxA9hi63ugyuV+I6ShHI56yDqg+2DzZduCLzrTia2cyvk0/ZM/iZx4mERdEr/VxqHD3VI
Ls9RaRegAhJhldXRQLIQTO7ErBBDpqWeCtWVYpoNz4iCxTIM5CufReYNnyicsbkqWletNw+vHX/b
vZ8=
-----END CERTIFICATE-----

Starfield Class 2 CA
====================
-----BEGIN CERTIFICATE-----
MIIEDzCCAvegAwIBAgIBADANBgkqhkiG9w0BAQUFADBoMQswCQYDVQQGEwJVUzElMCMGA1UEChMc
U3RhcmZpZWxkIFRlY2hub2xvZ2llcywgSW5jLjEyMDAGA1UECxMpU3RhcmZpZWxkIENsYXNzIDIg
Q2VydGlmaWNhdGlvbiBBdXRob3JpdHkwHhcNMDQwNjI5MTczOTE2WhcNMzQwNjI5MTczOTE2WjBo
MQswCQYDVQQGEwJVUzElMCMGA1UEChMcU3RhcmZpZWxkIFRlY2hub2xvZ2llcywgSW5jLjEyMDAG
A1UECxMpU3RhcmZpZWxkIENsYXNzIDIgQ2VydGlmaWNhdGlvbiBBdXRob3JpdHkwggEgMA0GCSqG
SIb3DQEBAQUAA4IBDQAwggEIAoIBAQC3Msj+6XGmBIWtDBFk385N78gDGIc/oav7PKaf8MOh2tTY
bitTkPskpD6E8J7oX+zlJ0T1KKY/e97gKvDIr1MvnsoFAZMej2YcOadN+lq2cwQlZut3f+dZxkqZ
JRRU6ybH838Z1TBwj6+wRir/resp7defqgSHo9T5iaU0X9tDkYI22WY8sbi5gv2cOj4QyDvvBmVm
epsZGD3/cVE8MC5fvj13c7JdBmzDI1aaK4UmkhynArPkPw2vCHmCuDY96pzTNbO8acr1zJ3o/WSN
F4Azbl5KXZnJHoe0nRrA1W4TNSNe35tfPe/W93bC6j67eA0cQmdrBNj41tpvi/JEoAGrAgEDo4HF
MIHCMB0GA1UdDgQWBBS/X7fRzt0fhvRbVazc1xDCDqmI5zCBkgYDVR0jBIGKMIGHgBS/X7fRzt0f
hvRbVazc1xDCDqmI56FspGowaDELMAkGA1UEBhMCVVMxJTAjBgNVBAoTHFN0YXJmaWVsZCBUZWNo
bm9sb2dpZXMsIEluYy4xMjAwBgNVBAsTKVN0YXJmaWVsZCBDbGFzcyAyIENlcnRpZmljYXRpb24g
QXV0aG9yaXR5ggEAMAwGA1UdEwQFMAMBAf8wDQYJKoZIhvcNAQEFBQADggEBAAWdP4id0ckaVaGs
afPzWdqbAYcaT1epoXkJKtv3L7IezMdeatiDh6GX70k1PncGQVhiv45YuApnP+yz3SFmH8lU+nLM
PUxA2IGvd56Deruix/U0F47ZEUD0/CwqTRV/p2JdLiXTAAsgGh1o+Re49L2L7ShZ3U0WixeDyLJl
xy16paq8U4Zt3VekyvggQQto8PT7dL5WXXp59fkdheMtlb71cZBDzI0fmgAKhynpVSJYACPq4xJD
KVtHCN2MQWplBqjlIapBtJUhlbl90TSrE9atvNziPTnNvT51cKEYWQPJIrSPnNVeKtelttQKbfi3
QBFGmh95DmK/D5fs4C8fF5Q=
-----END CERTIFICATE-----

StartCom Certification Authority
================================
-----BEGIN CERTIFICATE-----
MIIHyTCCBbGgAwIBAgIBATANBgkqhkiG9w0BAQUFADB9MQswCQYDVQQGEwJJTDEWMBQGA1UEChMN
U3RhcnRDb20gTHRkLjErMCkGA1UECxMiU2VjdXJlIERpZ2l0YWwgQ2VydGlmaWNhdGUgU2lnbmlu
ZzEpMCcGA1UEAxMgU3RhcnRDb20gQ2VydGlmaWNhdGlvbiBBdXRob3JpdHkwHhcNMDYwOTE3MTk0
NjM2WhcNMzYwOTE3MTk0NjM2WjB9MQswCQYDVQQGEwJJTDEWMBQGA1UEChMNU3RhcnRDb20gTHRk
LjErMCkGA1UECxMiU2VjdXJlIERpZ2l0YWwgQ2VydGlmaWNhdGUgU2lnbmluZzEpMCcGA1UEAxMg
U3RhcnRDb20gQ2VydGlmaWNhdGlvbiBBdXRob3JpdHkwggIiMA0GCSqGSIb3DQEBAQUAA4ICDwAw
ggIKAoICAQDBiNsJvGxGfHiflXu1M5DycmLWwTYgIiRezul38kMKogZkpMyONvg45iPwbm2xPN1y
o4UcodM9tDMr0y+v/uqwQVlntsQGfQqedIXWeUyAN3rfOQVSWff0G0ZDpNKFhdLDcfN1YjS6LIp/
Ho/u7TTQEceWzVI9ujPW3U3eCztKS5/CJi/6tRYccjV3yjxd5srhJosaNnZcAdt0FCX+7bWgiA/d
eMotHweXMAEtcnn6RtYTKqi5pquDSR3l8u/d5AGOGAqPY1MWhWKpDhk6zLVmpsJrdAfkK+F2PrRt
2PZE4XNiHzvEvqBTViVsUQn3qqvKv3b9bZvzndu/PWa8DFaqr5hIlTpL36dYUNk4dalb6kMMAv+Z
6+hsTXBbKWWc3apdzK8BMewM69KN6Oqce+Zu9ydmDBpI125C4z/eIT574Q1w+2OqqGwaVLRcJXrJ
osmLFqa7LH4XXgVNWG4SHQHuEhANxjJ/GP/89PrNbpHoNkm+Gkhpi8KWTRoSsmkXwQqQ1vp5Iki/
untp+HDH+no32NgN0nZPV/+Qt+OR0t3vwmC3Zzrd/qqc8NSLf3Iizsafl7b4r4qgEKjZ+xjGtrVc
UjyJthkqcwEKDwOzEmDyei+B26Nu/yYwl/WL3YlXtq09s68rxbd2AvCl1iuahhQqcvbjM4xdCUsT
37uMdBNSSwIDAQABo4ICUjCCAk4wDAYDVR0TBAUwAwEB/zALBgNVHQ8EBAMCAa4wHQYDVR0OBBYE
FE4L7xqkQFulF2mHMMo0aEPQQa7yMGQGA1UdHwRdMFswLKAqoCiGJmh0dHA6Ly9jZXJ0LnN0YXJ0
Y29tLm9yZy9zZnNjYS1jcmwuY3JsMCugKaAnhiVodHRwOi8vY3JsLnN0YXJ0Y29tLm9yZy9zZnNj
YS1jcmwuY3JsMIIBXQYDVR0gBIIBVDCCAVAwggFMBgsrBgEEAYG1NwEBATCCATswLwYIKwYBBQUH
AgEWI2h0dHA6Ly9jZXJ0LnN0YXJ0Y29tLm9yZy9wb2xpY3kucGRmMDUGCCsGAQUFBwIBFilodHRw
Oi8vY2VydC5zdGFydGNvbS5vcmcvaW50ZXJtZWRpYXRlLnBkZjCB0AYIKwYBBQUHAgIwgcMwJxYg
U3RhcnQgQ29tbWVyY2lhbCAoU3RhcnRDb20pIEx0ZC4wAwIBARqBl0xpbWl0ZWQgTGlhYmlsaXR5
LCByZWFkIHRoZSBzZWN0aW9uICpMZWdhbCBMaW1pdGF0aW9ucyogb2YgdGhlIFN0YXJ0Q29tIENl
cnRpZmljYXRpb24gQXV0aG9yaXR5IFBvbGljeSBhdmFpbGFibGUgYXQgaHR0cDovL2NlcnQuc3Rh
cnRjb20ub3JnL3BvbGljeS5wZGYwEQYJYIZIAYb4QgEBBAQDAgAHMDgGCWCGSAGG+EIBDQQrFilT
dGFydENvbSBGcmVlIFNTTCBDZXJ0aWZpY2F0aW9uIEF1dGhvcml0eTANBgkqhkiG9w0BAQUFAAOC
AgEAFmyZ9GYMNPXQhV59CuzaEE44HF7fpiUFS5Eyweg78T3dRAlbB0mKKctmArexmvclmAk8jhvh
3TaHK0u7aNM5Zj2gJsfyOZEdUauCe37Vzlrk4gNXcGmXCPleWKYK34wGmkUWFjgKXlf2Ysd6AgXm
vB618p70qSmD+LIU424oh0TDkBreOKk8rENNZEXO3SipXPJzewT4F+irsfMuXGRuczE6Eri8sxHk
fY+BUZo7jYn0TZNmezwD7dOaHZrzZVD1oNB1ny+v8OqCQ5j4aZyJecRDjkZy42Q2Eq/3JR44iZB3
fsNrarnDy0RLrHiQi+fHLB5LEUTINFInzQpdn4XBidUaePKVEFMy3YCEZnXZtWgo+2EuvoSoOMCZ
EoalHmdkrQYuL6lwhceWD3yJZfWOQ1QOq92lgDmUYMA0yZZwLKMS9R9Ie70cfmu3nZD0Ijuu+Pwq
yvqCUqDvr0tVk+vBtfAii6w0TiYiBKGHLHVKt+V9E9e4DGTANtLJL4YSjCMJwRuCO3NJo2pXh5Tl
1njFmUNj403gdy3hZZlyaQQaRwnmDwFWJPsfvw55qVguucQJAX6Vum0ABj6y6koQOdjQK/W/7HW/
lwLFCRsI3FU34oH7N4RDYiDK51ZLZer+bMEkkyShNOsF/5oirpt9P/FlUQqmMGqz9IgcgA38coro
g14=
-----END CERTIFICATE-----

Taiwan GRCA
===========
-----BEGIN CERTIFICATE-----
MIIFcjCCA1qgAwIBAgIQH51ZWtcvwgZEpYAIaeNe9jANBgkqhkiG9w0BAQUFADA/MQswCQYDVQQG
EwJUVzEwMC4GA1UECgwnR292ZXJubWVudCBSb290IENlcnRpZmljYXRpb24gQXV0aG9yaXR5MB4X
DTAyMTIwNTEzMjMzM1oXDTMyMTIwNTEzMjMzM1owPzELMAkGA1UEBhMCVFcxMDAuBgNVBAoMJ0dv
dmVybm1lbnQgUm9vdCBDZXJ0aWZpY2F0aW9uIEF1dGhvcml0eTCCAiIwDQYJKoZIhvcNAQEBBQAD
ggIPADCCAgoCggIBAJoluOzMonWoe/fOW1mKydGGEghU7Jzy50b2iPN86aXfTEc2pBsBHH8eV4qN
w8XRIePaJD9IK/ufLqGU5ywck9G/GwGHU5nOp/UKIXZ3/6m3xnOUT0b3EEk3+qhZSV1qgQdW8or5
BtD3cCJNtLdBuTK4sfCxw5w/cP1T3YGq2GN49thTbqGsaoQkclSGxtKyyhwOeYHWtXBiCAEuTk8O
1RGvqa/lmr/czIdtJuTJV6L7lvnM4T9TjGxMfptTCAtsF/tnyMKtsc2AtJfcdgEWFelq16TheEfO
htX7MfP6Mb40qij7cEwdScevLJ1tZqa2jWR+tSBqnTuBto9AAGdLiYa4zGX+FVPpBMHWXx1E1wov
J5pGfaENda1UhhXcSTvxls4Pm6Dso3pdvtUqdULle96ltqqvKKyskKw4t9VoNSZ63Pc78/1Fm9G7
Q3hub/FCVGqY8A2tl+lSXunVanLeavcbYBT0peS2cWeqH+riTcFCQP5nRhc4L0c/cZyu5SHKYS1t
B6iEfC3uUSXxY5Ce/eFXiGvviiNtsea9P63RPZYLhY3Naye7twWb7LuRqQoHEgKXTiCQ8P8NHuJB
O9NAOueNXdpm5AKwB1KYXA6OM5zCppX7VRluTI6uSw+9wThNXo+EHWbNxWCWtFJaBYmOlXqYwZE8
lSOyDvR5tMl8wUohAgMBAAGjajBoMB0GA1UdDgQWBBTMzO/MKWCkO7GStjz6MmKPrCUVOzAMBgNV
HRMEBTADAQH/MDkGBGcqBwAEMTAvMC0CAQAwCQYFKw4DAhoFADAHBgVnKgMAAAQUA5vwIhP/lSg2
09yewDL7MTqKUWUwDQYJKoZIhvcNAQEFBQADggIBAECASvomyc5eMN1PhnR2WPWus4MzeKR6dBcZ
TulStbngCnRiqmjKeKBMmo4sIy7VahIkv9Ro04rQ2JyftB8M3jh+Vzj8jeJPXgyfqzvS/3WXy6Tj
Zwj/5cAWtUgBfen5Cv8b5Wppv3ghqMKnI6mGq3ZW6A4M9hPdKmaKZEk9GhiHkASfQlK3T8v+R0F2
Ne//AHY2RTKbxkaFXeIksB7jSJaYV0eUVXoPQbFEJPPB/hprv4j9wabak2BegUqZIJxIZhm1AHlU
D7gsL0u8qV1bYH+Mh6XgUmMqvtg7hUAV/h62ZT/FS9p+tXo1KaMuephgIqP0fSdOLeq0dDzpD6Qz
DxARvBMB1uUO07+1EqLhRSPAzAhuYbeJq4PjJB7mXQfnHyA+z2fI56wwbSdLaG5LKlwCCDTb+Hbk
Z6MmnD+iMsJKxYEYMRBWqoTvLQr/uB930r+lWKBi5NdLkXWNiYCYfm3LU05er/ayl4WXudpVBrkk
7tfGOB5jGxI7leFYrPLfhNVfmS8NVVvmONsuP3LpSIXLuykTjx44VbnzssQwmSNOXfJIoRIM3BKQ
CZBUkQM8R+XVyWXgt0t97EfTsws+rZ7QdAAO671RrcDeLMDDav7v3Aun+kbfYNucpllQdSNpc5Oy
+fwC00fmcc4QAu4njIT/rEUNE1yDMuAlpYYsfPQS
-----END CERTIFICATE-----

Swisscom Root CA 1
==================
-----BEGIN CERTIFICATE-----
MIIF2TCCA8GgAwIBAgIQXAuFXAvnWUHfV8w/f52oNjANBgkqhkiG9w0BAQUFADBkMQswCQYDVQQG
EwJjaDERMA8GA1UEChMIU3dpc3Njb20xJTAjBgNVBAsTHERpZ2l0YWwgQ2VydGlmaWNhdGUgU2Vy
dmljZXMxGzAZBgNVBAMTElN3aXNzY29tIFJvb3QgQ0EgMTAeFw0wNTA4MTgxMjA2MjBaFw0yNTA4
MTgyMjA2MjBaMGQxCzAJBgNVBAYTAmNoMREwDwYDVQQKEwhTd2lzc2NvbTElMCMGA1UECxMcRGln
aXRhbCBDZXJ0aWZpY2F0ZSBTZXJ2aWNlczEbMBkGA1UEAxMSU3dpc3Njb20gUm9vdCBDQSAxMIIC
IjANBgkqhkiG9w0BAQEFAAOCAg8AMIICCgKCAgEA0LmwqAzZuz8h+BvVM5OAFmUgdbI9m2BtRsiM
MW8Xw/qabFbtPMWRV8PNq5ZJkCoZSx6jbVfd8StiKHVFXqrWW/oLJdihFvkcxC7mlSpnzNApbjyF
NDhhSbEAn9Y6cV9Nbc5fuankiX9qUvrKm/LcqfmdmUc/TilftKaNXXsLmREDA/7n29uj/x2lzZAe
AR81sH8A25Bvxn570e56eqeqDFdvpG3FEzuwpdntMhy0XmeLVNxzh+XTF3xmUHJd1BpYwdnP2IkC
b6dJtDZd0KTeByy2dbcokdaXvij1mB7qWybJvbCXc9qukSbraMH5ORXWZ0sKbU/Lz7DkQnGMU3nn
7uHbHaBuHYwadzVcFh4rUx80i9Fs/PJnB3r1re3WmquhsUvhzDdf/X/NTa64H5xD+SpYVUNFvJbN
cA78yeNmuk6NO4HLFWR7uZToXTNShXEuT46iBhFRyePLoW4xCGQMwtI89Tbo19AOeCMgkckkKmUp
WyL3Ic6DXqTz3kvTaI9GdVyDCW4pa8RwjPWd1yAv/0bSKzjCL3UcPX7ape8eYIVpQtPM+GP+HkM5
haa2Y0EQs3MevNP6yn0WR+Kn1dCjigoIlmJWbjTb2QK5MHXjBNLnj8KwEUAKrNVxAmKLMb7dxiNY
MUJDLXT5xp6mig/p/r+D5kNXJLrvRjSq1xIBOO0CAwEAAaOBhjCBgzAOBgNVHQ8BAf8EBAMCAYYw
HQYDVR0hBBYwFDASBgdghXQBUwABBgdghXQBUwABMBIGA1UdEwEB/wQIMAYBAf8CAQcwHwYDVR0j
BBgwFoAUAyUv3m+CATpcLNwroWm1Z9SM0/0wHQYDVR0OBBYEFAMlL95vggE6XCzcK6FptWfUjNP9
MA0GCSqGSIb3DQEBBQUAA4ICAQA1EMvspgQNDQ/NwNurqPKIlwzfky9NfEBWMXrrpA9gzXrzvsMn
jgM+pN0S734edAY8PzHyHHuRMSG08NBsl9Tpl7IkVh5WwzW9iAUPWxAaZOHHgjD5Mq2eUCzneAXQ
MbFamIp1TpBcahQq4FJHgmDmHtqBsfsUC1rxn9KVuj7QG9YVHaO+htXbD8BJZLsuUBlL0iT43R4H
VtA4oJVwIHaM190e3p9xxCPvgxNcoyQVTSlAPGrEqdi3pkSlDfTgnXceQHAm/NrZNuR55LU/vJtl
vrsRls/bxig5OgjOR1tTWsWZ/l2p3e9M1MalrQLmjAcSHm8D0W+go/MpvRLHUKKwf4ipmXeascCl
OS5cfGniLLDqN2qk4Vrh9VDlg++luyqI54zb/W1elxmofmZ1a3Hqv7HHb6D0jqTsNFFbjCYDcKF3
1QESVwA12yPeDooomf2xEG9L/zgtYE4snOtnta1J7ksfrK/7DZBaZmBwXarNeNQk7shBoJMBkpxq
nvy5JMWzFYJ+vq6VK+uxwNrjAWALXmmshFZhvnEX/h0TD/7Gh0Xp/jKgGg0TpJRVcaUWi7rKibCy
x/yP2FS1k2Kdzs9Z+z0YzirLNRWCXf9UIltxUvu3yf5gmwBBZPCqKuy2QkPOiWaByIufOVQDJdMW
NY6E0F/6MBr1mmz0DlP5OlvRHA==
-----END CERTIFICATE-----

DigiCert Assured ID Root CA
===========================
-----BEGIN CERTIFICATE-----
MIIDtzCCAp+gAwIBAgIQDOfg5RfYRv6P5WD8G/AwOTANBgkqhkiG9w0BAQUFADBlMQswCQYDVQQG
EwJVUzEVMBMGA1UEChMMRGlnaUNlcnQgSW5jMRkwFwYDVQQLExB3d3cuZGlnaWNlcnQuY29tMSQw
IgYDVQQDExtEaWdpQ2VydCBBc3N1cmVkIElEIFJvb3QgQ0EwHhcNMDYxMTEwMDAwMDAwWhcNMzEx
MTEwMDAwMDAwWjBlMQswCQYDVQQGEwJVUzEVMBMGA1UEChMMRGlnaUNlcnQgSW5jMRkwFwYDVQQL
ExB3d3cuZGlnaWNlcnQuY29tMSQwIgYDVQQDExtEaWdpQ2VydCBBc3N1cmVkIElEIFJvb3QgQ0Ew
ggEiMA0GCSqGSIb3DQEBAQUAA4IBDwAwggEKAoIBAQCtDhXO5EOAXLGH87dg+XESpa7cJpSIqvTO
9SA5KFhgDPiA2qkVlTJhPLWxKISKityfCgyDF3qPkKyK53lTXDGEKvYPmDI2dsze3Tyoou9q+yHy
UmHfnyDXH+Kx2f4YZNISW1/5WBg1vEfNoTb5a3/UsDg+wRvDjDPZ2C8Y/igPs6eD1sNuRMBhNZYW
/lmci3Zt1/GiSw0r/wty2p5g0I6QNcZ4VYcgoc/lbQrISXwxmDNsIumH0DJaoroTghHtORedmTpy
oeb6pNnVFzF1roV9Iq4/AUaG9ih5yLHa5FcXxH4cDrC0kqZWs72yl+2qp/C3xag/lRbQ/6GW6whf
GHdPAgMBAAGjYzBhMA4GA1UdDwEB/wQEAwIBhjAPBgNVHRMBAf8EBTADAQH/MB0GA1UdDgQWBBRF
66Kv9JLLgjEtUYunpyGd823IDzAfBgNVHSMEGDAWgBRF66Kv9JLLgjEtUYunpyGd823IDzANBgkq
hkiG9w0BAQUFAAOCAQEAog683+Lt8ONyc3pklL/3cmbYMuRCdWKuh+vy1dneVrOfzM4UKLkNl2Bc
EkxY5NM9g0lFWJc1aRqoR+pWxnmrEthngYTffwk8lOa4JiwgvT2zKIn3X/8i4peEH+ll74fg38Fn
SbNd67IJKusm7Xi+fT8r87cmNW1fiQG2SVufAQWbqz0lwcy2f8Lxb4bG+mRo64EtlOtCt/qMHt1i
8b5QZ7dsvfPxH2sMNgcWfzd8qVttevESRmCD1ycEvkvOl77DZypoEd+A5wwzZr8TDRRu838fYxAe
+o0bJW1sj6W3YQGx0qMmoRBxna3iw/nDmVG3KwcIzi7mULKn+gpFL6Lw8g==
-----END CERTIFICATE-----

DigiCert Global Root CA
=======================
-----BEGIN CERTIFICATE-----
MIIDrzCCApegAwIBAgIQCDvgVpBCRrGhdWrJWZHHSjANBgkqhkiG9w0BAQUFADBhMQswCQYDVQQG
EwJVUzEVMBMGA1UEChMMRGlnaUNlcnQgSW5jMRkwFwYDVQQLExB3d3cuZGlnaWNlcnQuY29tMSAw
HgYDVQQDExdEaWdpQ2VydCBHbG9iYWwgUm9vdCBDQTAeFw0wNjExMTAwMDAwMDBaFw0zMTExMTAw
MDAwMDBaMGExCzAJBgNVBAYTAlVTMRUwEwYDVQQKEwxEaWdpQ2VydCBJbmMxGTAXBgNVBAsTEHd3
dy5kaWdpY2VydC5jb20xIDAeBgNVBAMTF0RpZ2lDZXJ0IEdsb2JhbCBSb290IENBMIIBIjANBgkq
hkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA4jvhEXLeqKTTo1eqUKKPC3eQyaKl7hLOllsBCSDMAZOn
TjC3U/dDxGkAV53ijSLdhwZAAIEJzs4bg7/fzTtxRuLWZscFs3YnFo97nh6Vfe63SKMI2tavegw5
BmV/Sl0fvBf4q77uKNd0f3p4mVmFaG5cIzJLv07A6Fpt43C/dxC//AH2hdmoRBBYMql1GNXRor5H
4idq9Joz+EkIYIvUX7Q6hL+hqkpMfT7PT19sdl6gSzeRntwi5m3OFBqOasv+zbMUZBfHWymeMr/y
7vrTC0LUq7dBMtoM1O/4gdW7jVg/tRvoSSiicNoxBN33shbyTApOB6jtSj1etX+jkMOvJwIDAQAB
o2MwYTAOBgNVHQ8BAf8EBAMCAYYwDwYDVR0TAQH/BAUwAwEB/zAdBgNVHQ4EFgQUA95QNVbRTLtm
8KPiGxvDl7I90VUwHwYDVR0jBBgwFoAUA95QNVbRTLtm8KPiGxvDl7I90VUwDQYJKoZIhvcNAQEF
BQADggEBAMucN6pIExIK+t1EnE9SsPTfrgT1eXkIoyQY/EsrhMAtudXH/vTBH1jLuG2cenTnmCmr
EbXjcKChzUyImZOMkXDiqw8cvpOp/2PV5Adg06O/nVsJ8dWO41P0jmP6P6fbtGbfYmbW0W5BjfIt
tep3Sp+dWOIrWcBAI+0tKIJFPnlUkiaY4IBIqDfv8NZ5YBberOgOzW6sRBc4L0na4UU+Krk2U886
UAb3LujEV0lsYSEY1QSteDwsOoBrp+uvFRTp2InBuThs4pFsiv9kuXclVzDAGySj4dzp30d8tbQk
CAUw7C29C79Fv1C5qfPrmAESrciIxpg0X40KPMbp1ZWVbd4=
-----END CERTIFICATE-----

DigiCert High Assurance EV Root CA
==================================
-----BEGIN CERTIFICATE-----
MIIDxTCCAq2gAwIBAgIQAqxcJmoLQJuPC3nyrkYldzANBgkqhkiG9w0BAQUFADBsMQswCQYDVQQG
EwJVUzEVMBMGA1UEChMMRGlnaUNlcnQgSW5jMRkwFwYDVQQLExB3d3cuZGlnaWNlcnQuY29tMSsw
KQYDVQQDEyJEaWdpQ2VydCBIaWdoIEFzc3VyYW5jZSBFViBSb290IENBMB4XDTA2MTExMDAwMDAw
MFoXDTMxMTExMDAwMDAwMFowbDELMAkGA1UEBhMCVVMxFTATBgNVBAoTDERpZ2lDZXJ0IEluYzEZ
MBcGA1UECxMQd3d3LmRpZ2ljZXJ0LmNvbTErMCkGA1UEAxMiRGlnaUNlcnQgSGlnaCBBc3N1cmFu
Y2UgRVYgUm9vdCBDQTCCASIwDQYJKoZIhvcNAQEBBQADggEPADCCAQoCggEBAMbM5XPm+9S75S0t
Mqbf5YE/yc0lSbZxKsPVlDRnogocsF9ppkCxxLeyj9CYpKlBWTrT3JTWPNt0OKRKzE0lgvdKpVMS
OO7zSW1xkX5jtqumX8OkhPhPYlG++MXs2ziS4wblCJEMxChBVfvLWokVfnHoNb9Ncgk9vjo4UFt3
MRuNs8ckRZqnrG0AFFoEt7oT61EKmEFBIk5lYYeBQVCmeVyJ3hlKV9Uu5l0cUyx+mM0aBhakaHPQ
NAQTXKFx01p8VdteZOE3hzBWBOURtCmAEvF5OYiiAhF8J2a3iLd48soKqDirCmTCv2ZdlYTBoSUe
h10aUAsgEsxBu24LUTi4S8sCAwEAAaNjMGEwDgYDVR0PAQH/BAQDAgGGMA8GA1UdEwEB/wQFMAMB
Af8wHQYDVR0OBBYEFLE+w2kD+L9HAdSYJhoIAu9jZCvDMB8GA1UdIwQYMBaAFLE+w2kD+L9HAdSY
JhoIAu9jZCvDMA0GCSqGSIb3DQEBBQUAA4IBAQAcGgaX3NecnzyIZgYIVyHbIUf4KmeqvxgydkAQ
V8GK83rZEWWONfqe/EW1ntlMMUu4kehDLI6zeM7b41N5cdblIZQB2lWHmiRk9opmzN6cN82oNLFp
myPInngiK3BD41VHMWEZ71jFhS9OMPagMRYjyOfiZRYzy78aG6A9+MpeizGLYAiJLQwGXFK3xPkK
mNEVX58Svnw2Yzi9RKR/5CYrCsSXaQ3pjOLAEFe4yHYSkVXySGnYvCoCWw9E1CAx2/S6cCZdkGCe
vEsXCS+0yx5DaMkHJ8HSXPfqIbloEpw8nL+e/IBcm2PN7EeqJSdnoDfzAIJ9VNep+OkuE6N36B9K
-----END CERTIFICATE-----

Certplus Class 2 Primary CA
===========================
-----BEGIN CERTIFICATE-----
MIIDkjCCAnqgAwIBAgIRAIW9S/PY2uNp9pTXX8OlRCMwDQYJKoZIhvcNAQEFBQAwPTELMAkGA1UE
BhMCRlIxETAPBgNVBAoTCENlcnRwbHVzMRswGQYDVQQDExJDbGFzcyAyIFByaW1hcnkgQ0EwHhcN
OTkwNzA3MTcwNTAwWhcNMTkwNzA2MjM1OTU5WjA9MQswCQYDVQQGEwJGUjERMA8GA1UEChMIQ2Vy
dHBsdXMxGzAZBgNVBAMTEkNsYXNzIDIgUHJpbWFyeSBDQTCCASIwDQYJKoZIhvcNAQEBBQADggEP
ADCCAQoCggEBANxQltAS+DXSCHh6tlJw/W/uz7kRy1134ezpfgSN1sxvc0NXYKwzCkTsA18cgCSR
5aiRVhKC9+Ar9NuuYS6JEI1rbLqzAr3VNsVINyPi8Fo3UjMXEuLRYE2+L0ER4/YXJQyLkcAbmXuZ
Vg2v7tK8R1fjeUl7NIknJITesezpWE7+Tt9avkGtrAjFGA7v0lPubNCdEgETjdyAYveVqUSISnFO
YFWe2yMZeVYHDD9jC1yw4r5+FfyUM1hBOHTE4Y+L3yasH7WLO7dDWWuwJKZtkIvEcupdM5i3y95e
e++U8Rs+yskhwcWYAqqi9lt3m/V+llU0HGdpwPFC40es/CgcZlUCAwEAAaOBjDCBiTAPBgNVHRME
CDAGAQH/AgEKMAsGA1UdDwQEAwIBBjAdBgNVHQ4EFgQU43Mt38sOKAze3bOkynm4jrvoMIkwEQYJ
YIZIAYb4QgEBBAQDAgEGMDcGA1UdHwQwMC4wLKAqoCiGJmh0dHA6Ly93d3cuY2VydHBsdXMuY29t
L0NSTC9jbGFzczIuY3JsMA0GCSqGSIb3DQEBBQUAA4IBAQCnVM+IRBnL39R/AN9WM2K191EBkOvD
P9GIROkkXe/nFL0gt5o8AP5tn9uQ3Nf0YtaLcF3n5QRIqWh8yfFC82x/xXp8HVGIutIKPidd3i1R
TtMTZGnkLuPT55sJmabglZvOGtd/vjzOUrMRFcEPF80Du5wlFbqidon8BvEY0JNLDnyCt6X09l/+
7UCmnYR0ObncHoUW2ikbhiMAybuJfm6AiB4vFLQDJKgybwOaRywwvlbGp0ICcBvqQNi6BQNwB6SW
//1IMwrh3KWBkJtN3X3n57LNXMhqlfil9o3EXXgIvnsG1knPGTZQIy4I5p4FTUcY1Rbpsda2ENW7
l7+ijrRU
-----END CERTIFICATE-----

DST Root CA X3
==============
-----BEGIN CERTIFICATE-----
MIIDSjCCAjKgAwIBAgIQRK+wgNajJ7qJMDmGLvhAazANBgkqhkiG9w0BAQUFADA/MSQwIgYDVQQK
ExtEaWdpdGFsIFNpZ25hdHVyZSBUcnVzdCBDby4xFzAVBgNVBAMTDkRTVCBSb290IENBIFgzMB4X
DTAwMDkzMDIxMTIxOVoXDTIxMDkzMDE0MDExNVowPzEkMCIGA1UEChMbRGlnaXRhbCBTaWduYXR1
cmUgVHJ1c3QgQ28uMRcwFQYDVQQDEw5EU1QgUm9vdCBDQSBYMzCCASIwDQYJKoZIhvcNAQEBBQAD
ggEPADCCAQoCggEBAN+v6ZdQCINXtMxiZfaQguzH0yxrMMpb7NnDfcdAwRgUi+DoM3ZJKuM/IUmT
rE4Orz5Iy2Xu/NMhD2XSKtkyj4zl93ewEnu1lcCJo6m67XMuegwGMoOifooUMM0RoOEqOLl5CjH9
UL2AZd+3UWODyOKIYepLYYHsUmu5ouJLGiifSKOeDNoJjj4XLh7dIN9bxiqKqy69cK3FCxolkHRy
xXtqqzTWMIn/5WgTe1QLyNau7Fqckh49ZLOMxt+/yUFw7BZy1SbsOFU5Q9D8/RhcQPGX69Wam40d
utolucbY38EVAjqr2m7xPi71XAicPNaDaeQQmxkqtilX4+U9m5/wAl0CAwEAAaNCMEAwDwYDVR0T
AQH/BAUwAwEB/zAOBgNVHQ8BAf8EBAMCAQYwHQYDVR0OBBYEFMSnsaR7LHH62+FLkHX/xBVghYkQ
MA0GCSqGSIb3DQEBBQUAA4IBAQCjGiybFwBcqR7uKGY3Or+Dxz9LwwmglSBd49lZRNI+DT69ikug
dB/OEIKcdBodfpga3csTS7MgROSR6cz8faXbauX+5v3gTt23ADq1cEmv8uXrAvHRAosZy5Q6XkjE
GB5YGV8eAlrwDPGxrancWYaLbumR9YbK+rlmM6pZW87ipxZzR8srzJmwN0jP41ZL9c8PDHIyh8bw
RLtTcm1D9SZImlJnt1ir/md2cXjbDaJWFBM5JDGFoqgCWjBH4d1QB7wCCZAA62RjYJsWvIjJEubS
fZGL+T0yjWW06XyxV3bqxbYoOb8VZRzI9neWagqNdwvYkQsEjgfbKbYK7p2CNTUQ
-----END CERTIFICATE-----

DST ACES CA X6
==============
-----BEGIN CERTIFICATE-----
MIIECTCCAvGgAwIBAgIQDV6ZCtadt3js2AdWO4YV2TANBgkqhkiG9w0BAQUFADBbMQswCQYDVQQG
EwJVUzEgMB4GA1UEChMXRGlnaXRhbCBTaWduYXR1cmUgVHJ1c3QxETAPBgNVBAsTCERTVCBBQ0VT
MRcwFQYDVQQDEw5EU1QgQUNFUyBDQSBYNjAeFw0wMzExMjAyMTE5NThaFw0xNzExMjAyMTE5NTha
MFsxCzAJBgNVBAYTAlVTMSAwHgYDVQQKExdEaWdpdGFsIFNpZ25hdHVyZSBUcnVzdDERMA8GA1UE
CxMIRFNUIEFDRVMxFzAVBgNVBAMTDkRTVCBBQ0VTIENBIFg2MIIBIjANBgkqhkiG9w0BAQEFAAOC
AQ8AMIIBCgKCAQEAuT31LMmU3HWKlV1j6IR3dma5WZFcRt2SPp/5DgO0PWGSvSMmtWPuktKe1jzI
DZBfZIGxqAgNTNj50wUoUrQBJcWVHAx+PhCEdc/BGZFjz+iokYi5Q1K7gLFViYsx+tC3dr5BPTCa
pCIlF3PoHuLTrCq9Wzgh1SpL11V94zpVvddtawJXa+ZHfAjIgrrep4c9oW24MFbCswKBXy314pow
GCi4ZtPLAZZv6opFVdbgnf9nKxcCpk4aahELfrd755jWjHZvwTvbUJN+5dCOHze4vbrGn2zpfDPy
MjwmR/onJALJfh1biEITajV8fTXpLmaRcpPVMibEdPVTo7NdmvYJywIDAQABo4HIMIHFMA8GA1Ud
EwEB/wQFMAMBAf8wDgYDVR0PAQH/BAQDAgHGMB8GA1UdEQQYMBaBFHBraS1vcHNAdHJ1c3Rkc3Qu
Y29tMGIGA1UdIARbMFkwVwYKYIZIAWUDAgEBATBJMEcGCCsGAQUFBwIBFjtodHRwOi8vd3d3LnRy
dXN0ZHN0LmNvbS9jZXJ0aWZpY2F0ZXMvcG9saWN5L0FDRVMtaW5kZXguaHRtbDAdBgNVHQ4EFgQU
CXIGThhDD+XWzMNqizF7eI+og7gwDQYJKoZIhvcNAQEFBQADggEBAKPYjtay284F5zLNAdMEA+V2
5FYrnJmQ6AgwbN99Pe7lv7UkQIRJ4dEorsTCOlMwiPH1d25Ryvr/ma8kXxug/fKshMrfqfBfBC6t
Fr8hlxCBPeP/h40y3JTlR4peahPJlJU90u7INJXQgNStMgiAVDzgvVJT11J8smk/f3rPanTK+gQq
nExaBqXpIK1FZg9p8d2/6eMyi/rgwYZNcjwu2JN4Cir42NInPRmJX1p7ijvMDNpRrscL9yuwNwXs
vFcj4jjSm2jzVhKIT0J8uDHEtdvkyCE06UgRNe76x5JXxZ805Mf29w4LTJxoeHtxMcfrHuBnQfO3
oKfN5XozNmr6mis=
-----END CERTIFICATE-----

TURKTRUST Certificate Services Provider Root 2
==============================================
-----BEGIN CERTIFICATE-----
MIIEPDCCAySgAwIBAgIBATANBgkqhkiG9w0BAQUFADCBvjE/MD0GA1UEAww2VMOcUktUUlVTVCBF
bGVrdHJvbmlrIFNlcnRpZmlrYSBIaXptZXQgU2HEn2xhecSxY8Sxc8SxMQswCQYDVQQGEwJUUjEP
MA0GA1UEBwwGQW5rYXJhMV0wWwYDVQQKDFRUw5xSS1RSVVNUIEJpbGdpIMSwbGV0acWfaW0gdmUg
QmlsacWfaW0gR8O8dmVubGnEn2kgSGl6bWV0bGVyaSBBLsWeLiAoYykgS2FzxLFtIDIwMDUwHhcN
MDUxMTA3MTAwNzU3WhcNMTUwOTE2MTAwNzU3WjCBvjE/MD0GA1UEAww2VMOcUktUUlVTVCBFbGVr
dHJvbmlrIFNlcnRpZmlrYSBIaXptZXQgU2HEn2xhecSxY8Sxc8SxMQswCQYDVQQGEwJUUjEPMA0G
A1UEBwwGQW5rYXJhMV0wWwYDVQQKDFRUw5xSS1RSVVNUIEJpbGdpIMSwbGV0acWfaW0gdmUgQmls
acWfaW0gR8O8dmVubGnEn2kgSGl6bWV0bGVyaSBBLsWeLiAoYykgS2FzxLFtIDIwMDUwggEiMA0G
CSqGSIb3DQEBAQUAA4IBDwAwggEKAoIBAQCpNn7DkUNMwxmYCMjHWHtPFoylzkkBH3MOrHUTpvqe
LCDe2JAOCtFp0if7qnefJ1Il4std2NiDUBd9irWCPwSOtNXwSadktx4uXyCcUHVPr+G1QRT0mJKI
x+XlZEdhR3n9wFHxwZnn3M5q+6+1ATDcRhzviuyV79z/rxAc653YsKpqhRgNF8k+v/Gb0AmJQv2g
QrSdiVFVKc8bcLyEVK3BEx+Y9C52YItdP5qtygy/p1Zbj3e41Z55SZI/4PGXJHpsmxcPbe9TmJEr
5A++WXkHeLuXlfSfadRYhwqp48y2WBmfJiGxxFmNskF1wK1pzpwACPI2/z7woQ8arBT9pmAPAgMB
AAGjQzBBMB0GA1UdDgQWBBTZN7NOBf3Zz58SFq62iS/rJTqIHDAPBgNVHQ8BAf8EBQMDBwYAMA8G
A1UdEwEB/wQFMAMBAf8wDQYJKoZIhvcNAQEFBQADggEBAHJglrfJ3NgpXiOFX7KzLXb7iNcX/ntt
Rbj2hWyfIvwqECLsqrkw9qtY1jkQMZkpAL2JZkH7dN6RwRgLn7Vhy506vvWolKMiVW4XSf/SKfE4
Jl3vpao6+XF75tpYHdN0wgH6PmlYX63LaL4ULptswLbcoCb6dxriJNoaN+BnrdFzgw2lGh1uEpJ+
hGIAF728JRhX8tepb1mIvDS3LoV4nZbcFMMsilKbloxSZj2GFotHuFEJjOp9zYhys2AzsfAKRO8P
9Qk3iCQOLGsgOqL6EfJANZxEaGM7rDNvY7wsu/LSy3Z9fYjYHcgFHW68lKlmjHdxx/qR+i9Rnuk5
UrbnBEI=
-----END CERTIFICATE-----

SwissSign Gold CA - G2
======================
-----BEGIN CERTIFICATE-----
MIIFujCCA6KgAwIBAgIJALtAHEP1Xk+wMA0GCSqGSIb3DQEBBQUAMEUxCzAJBgNVBAYTAkNIMRUw
EwYDVQQKEwxTd2lzc1NpZ24gQUcxHzAdBgNVBAMTFlN3aXNzU2lnbiBHb2xkIENBIC0gRzIwHhcN
MDYxMDI1MDgzMDM1WhcNMzYxMDI1MDgzMDM1WjBFMQswCQYDVQQGEwJDSDEVMBMGA1UEChMMU3dp
c3NTaWduIEFHMR8wHQYDVQQDExZTd2lzc1NpZ24gR29sZCBDQSAtIEcyMIICIjANBgkqhkiG9w0B
AQEFAAOCAg8AMIICCgKCAgEAr+TufoskDhJuqVAtFkQ7kpJcyrhdhJJCEyq8ZVeCQD5XJM1QiyUq
t2/876LQwB8CJEoTlo8jE+YoWACjR8cGp4QjK7u9lit/VcyLwVcfDmJlD909Vopz2q5+bbqBHH5C
jCA12UNNhPqE21Is8w4ndwtrvxEvcnifLtg+5hg3Wipy+dpikJKVyh+c6bM8K8vzARO/Ws/BtQpg
vd21mWRTuKCWs2/iJneRjOBiEAKfNA+k1ZIzUd6+jbqEemA8atufK+ze3gE/bk3lUIbLtK/tREDF
ylqM2tIrfKjuvqblCqoOpd8FUrdVxyJdMmqXl2MT28nbeTZ7hTpKxVKJ+STnnXepgv9VHKVxaSvR
AiTysybUa9oEVeXBCsdtMDeQKuSeFDNeFhdVxVu1yzSJkvGdJo+hB9TGsnhQ2wwMC3wLjEHXuend
jIj3o02yMszYF9rNt85mndT9Xv+9lz4pded+p2JYryU0pUHHPbwNUMoDAw8IWh+Vc3hiv69yFGkO
peUDDniOJihC8AcLYiAQZzlG+qkDzAQ4embvIIO1jEpWjpEA/I5cgt6IoMPiaG59je883WX0XaxR
7ySArqpWl2/5rX3aYT+YdzylkbYcjCbaZaIJbcHiVOO5ykxMgI93e2CaHt+28kgeDrpOVG2Y4OGi
GqJ3UM/EY5LsRxmd6+ZrzsECAwEAAaOBrDCBqTAOBgNVHQ8BAf8EBAMCAQYwDwYDVR0TAQH/BAUw
AwEB/zAdBgNVHQ4EFgQUWyV7lqRlUX64OfPAeGZe6Drn8O4wHwYDVR0jBBgwFoAUWyV7lqRlUX64
OfPAeGZe6Drn8O4wRgYDVR0gBD8wPTA7BglghXQBWQECAQEwLjAsBggrBgEFBQcCARYgaHR0cDov
L3JlcG9zaXRvcnkuc3dpc3NzaWduLmNvbS8wDQYJKoZIhvcNAQEFBQADggIBACe645R88a7A3hfm
5djV9VSwg/S7zV4Fe0+fdWavPOhWfvxyeDgD2StiGwC5+OlgzczOUYrHUDFu4Up+GC9pWbY9ZIEr
44OE5iKHjn3g7gKZYbge9LgriBIWhMIxkziWMaa5O1M/wySTVltpkuzFwbs4AOPsF6m43Md8AYOf
Mke6UiI0HTJ6CVanfCU2qT1L2sCCbwq7EsiHSycR+R4tx5M/nttfJmtS2S6K8RTGRI0Vqbe/vd6m
Gu6uLftIdxf+u+yvGPUqUfA5hJeVbG4bwyvEdGB5JbAKJ9/fXtI5z0V9QkvfsywexcZdylU6oJxp
mo/a77KwPJ+HbBIrZXAVUjEaJM9vMSNQH4xPjyPDdEFjHFWoFN0+4FFQz/EbMFYOkrCChdiDyyJk
vC24JdVUorgG6q2SpCSgwYa1ShNqR88uC1aVVMvOmttqtKay20EIhid392qgQmwLOM7XdVAyksLf
KzAiSNDVQTglXaTpXZ/GlHXQRf0wl0OPkKsKx4ZzYEppLd6leNcG2mqeSz53OiATIgHQv2ieY2Br
NU0LbbqhPcCT4H8js1WtciVORvnSFu+wZMEBnunKoGqYDs/YYPIvSbjkQuE4NRb0yG5P94FW6Lqj
viOvrv1vA+ACOzB2+httQc8Bsem4yWb02ybzOqR08kkkW8mw0FfB+j564ZfJ
-----END CERTIFICATE-----

SwissSign Silver CA - G2
========================
-----BEGIN CERTIFICATE-----
MIIFvTCCA6WgAwIBAgIITxvUL1S7L0swDQYJKoZIhvcNAQEFBQAwRzELMAkGA1UEBhMCQ0gxFTAT
BgNVBAoTDFN3aXNzU2lnbiBBRzEhMB8GA1UEAxMYU3dpc3NTaWduIFNpbHZlciBDQSAtIEcyMB4X
DTA2MTAyNTA4MzI0NloXDTM2MTAyNTA4MzI0NlowRzELMAkGA1UEBhMCQ0gxFTATBgNVBAoTDFN3
aXNzU2lnbiBBRzEh