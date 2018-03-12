<?php /*

 Composr
 Copyright (c) ocProducts, 2004-2018

 See text/EN/licence.txt for full licensing information.


 NOTE TO PROGRAMMERS:
   Do not edit this file. If you need to make changes, save your changed file to the appropriate *_custom folder
   **** If you ignore this advice, then your website upgrades (e.g. for bug fixes) will likely kill your changes ****

*/

/**
 * @license    http://opensource.org/licenses/cpal_1.0 Common Public Attribution License
 * @copyright  ocProducts Ltd
 * @package    core
 */

$script_name = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : (isset($_ENV['SCRIPT_NAME']) ? $_ENV['SCRIPT_NAME'] : '');
if ((strpos($script_name, '/sources/') !== false) || (strpos($script_name, '/sources_custom/') !== false)) {
    header('Content-type: text/plain; charset=utf-8');
    exit('May not be included directly');
}

/**
 * This function is a very important one when coding. It allows you to include a source code file (from root/sources/ or root/sources_custom/) through the proper channels.
 * You should remember this function, and not substitute anything else for it, as that will likely make your code unstable.
 * It is key to source code modularity in Composr.
 *
 * @param  string $codename The codename for the source module to load (or a full relative path, ending with .php; if custom checking is needed, this must be the custom version)
 * @param  boolean $light_exit Whether to cleanly fail when a source file is missing
 */
function require_code($codename, $light_exit = false)
{
    global $REQUIRED_CODE, $FILE_BASE, $SITE_INFO;
    if (isset($REQUIRED_CODE[$codename])) {
        return;
    }
    $REQUIRED_CODE[$codename] = false; // unset means no, false means in-progress, true means done

    $shorthand = (strpos($codename, '.php') === false);
    if (!$shorthand) {
        $non_custom_codename = str_replace('_custom/', '/', $codename);
        $REQUIRED_CODE[$non_custom_codename] = true;
    }

    if (strpos($codename, '..') !== false) {
        $codename = filter_naughty($codename);
    }

    if ((isset($_GET['keep_show_loading_code'])) && ($_GET['keep_show_loading_code'] === '1')) {
        $before = memory_get_usage();
    }

    $worked = false;

    $path_custom = $FILE_BASE . '/' . ($shorthand ? ('sources_custom/' . $codename . '.php') : $codename);
    $path_orig = $FILE_BASE . '/' . ($shorthand ? ('sources/' . $codename . '.php') : $non_custom_codename);

    $has_orig = null;
    if (isset($GLOBALS['PERSISTENT_CACHE'])) {
        global $CODE_OVERRIDES;
        if (!isset($CODE_OVERRIDES)) {
            $CODE_OVERRIDES = persistent_cache_get('CODE_OVERRIDES');
            if ($CODE_OVERRIDES === null) {
                $CODE_OVERRIDES = array();
            }
        }
        if (isset($CODE_OVERRIDES[$codename])) {
            $has_custom = $CODE_OVERRIDES[$codename];
            if ($has_custom) {
                $has_custom = is_file($path_custom); // Double-check still there
            }
            $has_orig = $CODE_OVERRIDES['!' . $codename];
            if ($has_orig) {
                $has_orig = is_file($path_orig); // Double-check still there
            }
        } else {
            $has_custom = is_file($path_custom);
            $has_orig = is_file($path_orig);
            $CODE_OVERRIDES[$codename] = $has_custom;
            $CODE_OVERRIDES['!' . $codename] = $has_orig;
            persistent_cache_set('CODE_OVERRIDES', $CODE_OVERRIDES);
        }
    } else {
        $has_custom = is_file($path_custom);
    }

    if ((isset($SITE_INFO['safe_mode'])) && ($SITE_INFO['safe_mode'] === '1')) {
        $has_custom = false;
    }

    if (($has_custom) && ((!function_exists('in_safe_mode')) || (!in_safe_mode()) || (!is_file($path_orig)))) {
        $done_init = false;
        $init_func = 'init__' . str_replace('/', '__', str_replace('.php', '', $codename));

        if (!isset($has_orig)) {
            $has_orig = is_file($path_orig);
        }
        if (($path_custom !== $path_orig) && ($has_orig)) {
            $orig = clean_php_file_for_eval(file_get_contents($path_orig), $path_orig);
            $a = file_get_contents($path_custom);

            if (strpos($a, '/*FORCE_ORIGINAL_LOAD_FIRST*/') === false/*e.g. Cannot do code rewrite for a module override that includes an Mx, because the extends needs the parent class already defined*/) {
                $functions_before = get_defined_functions();
                $classes_before = get_declared_classes();
                include($path_custom); // Include our custom
                $functions_after = get_defined_functions();
                $classes_after = get_declared_classes();
                $functions_diff = array_diff($functions_after['user'], $functions_before['user']); // Our custom defined these functions
                $classes_diff = array_diff($classes_after, $classes_before);

                $pure = true; // We will set this to false if it does not have all functions the main one has. If it does have all functions we know we should not run the original init, as it will almost certainly just have been the same code copy&pasted through.
                $overlaps = false;
                foreach ($functions_diff as $function) { // Go through override's functions and make sure original doesn't have them: rename original's to non_overridden__ equivs.
                    if (stripos($orig, 'function ' . $function . '(') !== false) { // NB: If this fails, it may be that "function\t" is in the file (you can't tell with a three-width proper tab)
                        $orig = str_ireplace('function ' . $function . '(', 'function non_overridden__' . $function . '(', $orig);
                        $overlaps = true;
                    } else {
                        $pure = false;
                    }
                }
                foreach ($classes_diff as $class) {
                    if (substr(strtolower($class), 0, 6) === 'module') {
                        $class = ucfirst($class);
                    }
                    if (substr(strtolower($class), 0, 4) === 'hook') {
                        $class = ucfirst($class);
                    }

                    if (strpos($orig, 'class ' . $class) !== false) {
                        $orig = str_replace('class ' . $class, 'class non_overridden__' . $class, $orig);
                        $overlaps = true;
                    } else {
                        $pure = false;
                    }
                }

                // See if we can get away with loading init function early. If we can we do a special version of it that supports fancy code modification. Our override isn't allowed to call the non-overridden init function as it won't have been loaded up by PHP in time. Instead though we will call it ourselves if it still exists (hasn't been removed by our own init function) because it likely serves a different purpose to our code-modification init function and copy&paste coding is bad.
                $doing_code_modifier_init = function_exists($init_func);
                if ($doing_code_modifier_init) {
                    $test = call_user_func_array($init_func, array($orig));
                    if (is_string($test)) {
                        $orig = $test;
                    }
                    $done_init = true;
                    if ((count($functions_diff) === 1) && (count($classes_diff) === 0)) {
                        $pure = false;
                    }
                }

                if (!$doing_code_modifier_init && !$overlaps) { // To make stack traces more helpful and help with opcode caching
                    include($path_orig);
                } else {
                    //static $log_file = null; if ($log_file === null) $log_file = fopen(get_file_base() . '/log.' . strval(time()) . '.txt', 'wb'); flock($log_file, LOCK_EX); fwrite($log_file, $path_orig . "\n"); flock($log_file, LOCK_UN);      Good for debugging errors in eval'd code
                    eval($orig); // Load up modified original
                }

                if ((!$pure) && ($doing_code_modifier_init) && (function_exists('non_overridden__init__' . str_replace('/', '__', str_replace('.php', '', $codename))))) {
                    call_user_func('non_overridden__init__' . str_replace('/', '__', str_replace('.php', '', $codename)));
                }
            } else {
                // Note we load the original and then the override. This is so function_exists can be used in the overrides (as we can't support the re-definition) OR in the case of Mx_ class derivation, so that the base class is loaded first.

                if ((isset($_GET['keep_show_parse_errors'])) && ($_GET['keep_show_parse_errors'] == '1')) {
                    $orig = clean_php_file_for_eval(file_get_contents($path_orig), $path_orig);
                    $do_sed = function_exists('push_suppress_error_death');
                    if ($do_sed) {
                        push_suppress_error_death(true);
                    }
                    $php_errormsg = '';
                    safe_ini_set('display_errors', '0');
                    $eval_result = eval($orig);
                    if ($do_sed) {
                        pop_suppress_error_death();
                    }
                    if ((php_error_has_happened($php_errormsg)) || ($eval_result === false)) {
                        if ((!function_exists('fatal_exit')) || ($codename === 'failure')) {
                            critical_error('PASSON', @strval($php_errormsg) . ' [sources/' . $codename . '.php]');
                        }
                        fatal_exit(@strval($php_errormsg) . ' [sources/' . $codename . '.php]');
                    }
                } else {
                    include($path_orig);
                }

                if ((isset($_GET['keep_show_parse_errors'])) && ($_GET['keep_show_parse_errors'] == '1')) {
                    $custom = clean_php_file_for_eval(file_get_contents($path_custom), $path_custom);
                    $do_sed = function_exists('push_suppress_error_death');
                    if ($do_sed) {
                        push_suppress_error_death(true);
                    }
                    $php_errormsg = '';
                    safe_ini_set('display_errors', '0');
                    $eval_result = eval($custom);
                    if ($do_sed) {
                        pop_suppress_error_death();
                    }
                    if ((php_error_has_happened($php_errormsg)) || ($eval_result === false)) {
                        if ((!function_exists('fatal_exit')) || ($codename === 'failure')) {
                            critical_error('PASSON', @strval($php_errormsg) . ' [sources_custom/' . $codename . '.php]');
                        }
                        fatal_exit(@strval($php_errormsg) . ' [sources_custom/' . $codename . '.php]');
                    }
                } else {
                    include($path_custom);
                }
            }
        } else {
            if ((isset($_GET['keep_show_parse_errors'])) && ($_GET['keep_show_parse_errors'] == '1')) {
                $orig = clean_php_file_for_eval(file_get_contents($path_custom), $path_custom);
                $do_sed = function_exists('push_suppress_error_death');
                if ($do_sed) {
                    push_suppress_error_death(true);
                }
                $php_errormsg = '';
                safe_ini_set('display_errors', '0');
                $eval_result = eval($orig);
                if ($do_sed) {
                    pop_suppress_error_death();
                }
                if ((php_error_has_happened($php_errormsg)) || ($eval_result === false)) {
                    if ((!function_exists('fatal_exit')) || ($codename === 'failure')) {
                        critical_error('PASSON', @strval($php_errormsg) . ' [sources_custom/' . $codename . '.php]');
                    }
                    fatal_exit(@strval($php_errormsg) . ' [sources_custom/' . $codename . '.php]');
                }
            } else {
                include($path_custom);
            }
        }

        if ((isset($_GET['keep_show_loading_code'])) && ($_GET['keep_show_loading_code'] === '1')) {
            if (function_exists('attach_message')) {
                attach_message('require_code: ' . $codename . ' (' . number_format(memory_get_usage() - $before) . ' bytes used, now at ' . number_format(memory_get_usage()) . ')', 'inform');
            } else {
                print('<!-- require_code: ' . htmlentities($codename) . ' (' . htmlentities(number_format(memory_get_usage() - $before)) . ' bytes used, now at ' . htmlentities(number_format(memory_get_usage())) . ') -->' . "\n");
                flush();
            }
        }

        if (!$done_init) {
            if (function_exists($init_func)) {
                call_user_func($init_func);
            }
        }

        $worked = true;
    } else {
        if ((isset($_GET['keep_show_parse_errors'])) && ($_GET['keep_show_parse_errors'] == '1')) {
            $contents = @file_get_contents($path_orig);
            if ($contents !== false) {
                $orig = clean_php_file_for_eval($contents, $path_orig);
                $do_sed = function_exists('push_suppress_error_death');
                if ($do_sed) {
                    push_suppress_error_death(true);
                }
                $php_errormsg = '';
                safe_ini_set('display_errors', '0');
                $eval_result = eval($orig);
                if ($do_sed) {
                    pop_suppress_error_death();
                }
                if ((php_error_has_happened($php_errormsg)) || ($eval_result === false)) {
                    if ((!function_exists('fatal_exit')) || ($codename === 'failure')) {
                        critical_error('PASSON', @strval($php_errormsg) . ' [sources/' . $codename . '.php]');
                    }
                    fatal_exit(@strval($php_errormsg) . ' [sources/' . $codename . '.php]');
                }

                $worked = true;
            }
        } else {
            $php_errormsg = '';
            @include($path_orig);
            if (!php_error_has_happened($php_errormsg)) {
                $worked = true;
            }
        }

        if ($worked) {
            if ((isset($_GET['keep_show_loading_code'])) && ($_GET['keep_show_loading_code'] === '1')) {
                if (function_exists('attach_message')) {
                    attach_message('require_code: ' . $codename . ' (' . number_format(memory_get_usage() - $before) . ' bytes used, now at ' . number_format(memory_get_usage()) . ')', 'inform');
                } else {
                    print('<!-- require_code: ' . htmlentities($codename) . ' (' . htmlentities(number_format(memory_get_usage() - $before)) . ' bytes used, now at ' . htmlentities(number_format(memory_get_usage())) . ') -->' . "\n");
                    flush();
                }
            }

            $init_func = 'init__' . str_replace(array('/', '.php'), array('__', ''), $codename);
            if (function_exists($init_func)) {
                call_user_func($init_func);
            }
        }
    }

    $REQUIRED_CODE[$codename] = true;
    if ($worked) {
        return;
    }

    if ($codename !== 'critical_errors') {
        if ($php_errormsg != '') {
            $codename .= '... "' . $php_errormsg . '"';
        }
    }
    if (!function_exists('do_lang')) {
        if ($codename === 'critical_errors') {
            exit('<!DOCTYPE html>' . "\n" . '<html lang="EN"><head><title>Critical startup error</title></head><body><h1>Composr startup error</h1><p>The Composr critical error message file, sources/critical_errors.php, could not be located. This is almost always due to an incomplete upload of the Composr system, so please check all files are uploaded correctly.</p><p>Once all Composr files are in place, Composr must actually be installed by running the installer. You must be seeing this message either because your system has become corrupt since installation, or because you have uploaded some but not all files from our manual installer package: the quick installer is easier, so you might consider using that instead.</p><p>ocProducts maintains full documentation for all procedures and tools, especially those for installation. These may be found on the <a href="http://compo.sr">Composr website</a>. If you are unable to easily solve this problem, we may be contacted from our website and can help resolve it for you.</p><hr /><p style="font-size: 0.8em">Composr is a website engine created by ocProducts.</p></body></html>');
        }
        critical_error('MISSING_SOURCE', $codename);
    }
    $error_string = (is_file($path_orig) || is_file($path_custom)) ? 'MISSING_SOURCE_FILE' : 'CORRUPT_SOURCE_FILE';
    $error_message = do_lang_tempcode($error_string, escape_html($codename), escape_html($path_orig));
    if ($light_exit) {
        warn_exit($error_message, false, true);
    }
    fatal_exit($error_message);
}

/**
 * Make a PHP file evaluable.
 *
 * @param  string $c File contents
 * @param  string $path File path
 * @return string Cleaned up file
 */
function clean_php_file_for_eval($c, $path)
{
    $reps = array();
    $reps['?' . '>'] = '';
    $reps['<' . '?php'] = '';
    $reps['__FILE__'] = "'" . addslashes($path) . "'";
    $reps['__DIR__'] = "'" . addslashes(dirname($path)) . "'";

    return str_replace(array_keys($reps), array_values($reps), $c);
}

/**
 * Find whether a PHP error has happened.
 *
 * @param  string $errormsg Error message
 * @return boolean Whether a PHP error has happened
 */
function php_error_has_happened($errormsg)
{
    return ($errormsg != '') && (stripos($errormsg, 'deprecated') === false/*deprecated errors can leak through because even though we return true in our error handler, error handlers won't run recursively, so if this code is loaded during an error it'll stream through deprecated stuff here*/);
}

/**
 * Require code, but without looking for sources_custom overrides.
 *
 * @param  string $codename The codename for the source module to load
 */
function require_code_no_override($codename)
{
    global $REQUIRED_CODE;
    if (array_key_exists($codename, $REQUIRED_CODE)) {
        return;
    }
    $REQUIRED_CODE[$codename] = true;
    require_once(get_file_base() . '/sources/' . filter_naughty($codename) . '.php');
    if (function_exists('init__' . str_replace('/', '__', $codename))) {
        call_user_func('init__' . str_replace('/', '__', $codename));
    }
}

/**
 * Replace a limited number of occurrences of the search string with the replacement string.
 * If there are the wrong number of occurrences (including zero) an error is put out, as this indicates an override is broken.
 * The phrase "<ditto>" will repeat the original $search string back into $replace.
 *
 * @param  mixed $search What's being replaced (string or array)
 * @param  mixed $replace What's being replaced with (string or array)
 * @param  mixed $subject Subject (string or array)
 * @param  integer $times Number of times to replace (to expect to replace)
 * @return mixed Result (string or array)
 */
function override_str_replace_exactly($search, $replace, $subject, $times = 1)
{
    $cnt = substr_count($subject, $search);

    if ($cnt != $times) {
        $lines = debug_backtrace();
        critical_error('CORRUPT_OVERRIDE', preg_replace('#^' . preg_quote(get_file_base() . '/') . '#', '', $lines[0]['file']) . ':' . strval($lines[0]['line']));
    }

    $replace = str_replace('<ditto>', $search, $replace);

    return str_replace($search, $replace, $subject);
}

/**
 * Find if we are running on a live Google App Engine application.
 *
 * @return boolean If it is running as a live Google App Engine application
 */
function appengine_is_live()
{
    return (GOOGLE_APPENGINE) && (!is_writable(get_file_base() . '/sources/global.php'));
}

/**
 * Are we currently running HTTPS.
 *
 * @return boolean If we are
 */
function tacit_https()
{
    static $tacit_https = null;
    if ($tacit_https === null) {
        $tacit_https = (($_SERVER['HTTPS'] != '') && ($_SERVER['HTTPS'] != 'off')) || ($_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https');
    }
    return $tacit_https;
}

/**
 * Make an object of the given class.
 *
 * @param  string $class The class name
 * @param  boolean $failure_ok Whether to return null if there is no such class
 * @return ?object The object (null: no such class)
 */
function object_factory($class, $failure_ok = false)
{
    if (!class_exists($class)) {
        if ($failure_ok) {
            return null;
        }
        fatal_exit(escape_html('Missing class: ' . $class));
    }
    return new $class;
}

/**
 * Find whether a particular PHP function is blocked.
 *
 * Note that you still need to put "@" before set_time_limit, as some web host(s) have their own non-detectable block:
 *  "Cannot set max execution time limit due to system policy".
 *
 * @param  string $function Function name
 * @return boolean Whether it is
 */
function php_function_allowed($function)
{
    static $cache = array();
    if (isset($cache[$function])) {
        return $cache[$function];
    }

    if (!in_array($function, /*These are actually language constructs rather than functions*/array('eval', 'exit', 'include', 'include_once', 'isset', 'require', 'require_once', 'unset', 'empty', 'print',))) {
        if (!function_exists($function)) {
            $cache[$function] = false;
            return false;
        }
    }
    $cache[$function] = (@preg_match('#(\s|,|^)' . preg_quote($function, '#') . '(\s|$|,)#', strtolower(ini_get('disable_functions') . ',' . ini_get('suhosin.executor.func.blacklist') . ',' . ini_get('suhosin.executor.include.blacklist') . ',' . ini_get('suhosin.executor.eval.blacklist'))) == 0);
    return $cache[$function];
}

/**
 * Sets the value of a configuration option, if the PHP environment allows it.
 *
 * @param  string $var Config option
 * @param  string $value New value of option
 * @return ~string Old value of option (false: error)
 */
function safe_ini_set($var, $value)
{
    if (!php_function_allowed('ini_set')) {
        return false;
    }

    return @ini_set($var, $value);
}

/**
 * Get the file base for your installation of Composr.
 *
 * @return PATH The file base, without a trailing slash
 */
function get_file_base()
{
    global $FILE_BASE;
    return $FILE_BASE;
}

/**
 * Get the file base for your installation of Composr.  For a shared install, or a GAE-install, this is different to the file-base.
 *
 * @return PATH The file base, without a trailing slash
 */
function get_custom_file_base()
{
    global $FILE_BASE, $SITE_INFO;
    if (!empty($SITE_INFO['custom_file_base'])) {
        return $SITE_INFO['custom_file_base'];
    }
    if (!empty($SITE_INFO['custom_file_base_stub'])) {
        require_code('shared_installs');
        $u = current_share_user();
        if ($u !== null) {
            return $SITE_INFO['custom_file_base_stub'] . '/' . $u;
        }
    }
    return $FILE_BASE;
}

/**
 * Get the parameter put into it, with no changes. If it detects that the parameter is naughty (i.e malicious, and probably from a hacker), it will log the hack-attack and output an error message.
 * This function is designed to be called on parameters that will be embedded in a path, and defines malicious as trying to reach a parent directory using '..'. All file paths in Composr should be absolute.
 *
 * @param  string $in String to test
 * @param  boolean $preg Whether to just filter out the naughtyness
 * @return string Same as input string
 */
function filter_naughty($in, $preg = false)
{
    if ((function_exists('ctype_alnum')) && (ctype_alnum($in))) {
        return $in;
    }

    if (strpos($in, "\0") !== false) {
        log_hack_attack_and_exit('PATH_HACK');
    }

    if (strpos($in, '..') !== false) {
        if ($preg) {
            return str_replace('.', '', $in);
        }

        $in = str_replace('...', '', $in);
        if (strpos($in, '..') !== false) {
            log_hack_attack_and_exit('PATH_HACK');
        }
        warn_exit(do_lang_tempcode('INVALID_URL'));
    }
    return $in;
}

/**
 * This function is similar to filter_naughty, except it requires the parameter to be strictly alphanumeric. It is intended for use on text that will be put into an eval.
 *
 * @param  string $in String to test
 * @param  boolean $preg Whether to just filter out the naughtyness
 * @return string Same as input string
 */
function filter_naughty_harsh($in, $preg = false)
{
    if ((function_exists('ctype_alnum')) && (ctype_alnum($in))) {
        return $in;
    }
    if (preg_match('#^[' . URL_CONTENT_REGEXP . ']*$#', $in) !== 0) {
        return $in;
    }
    if (preg_match('#^[\w\-]*/#', $in) !== 0) {
        warn_exit(do_lang_tempcode('MISSING_RESOURCE')); // Probably a relative URL underneath a URL Scheme short URL, should not really happen
    }

    if ($preg) {
        return preg_replace('#[^' . URL_CONTENT_REGEXP . ']#', '', $in);
    }
    log_hack_attack_and_exit('EVAL_HACK', $in);
    return ''; // trick to make Zend happy
}

/**
 * Find if an IP address is within a CIDR range. Based on comment in PHP manual: http://php.net/manual/en/ref.network.php.
 *
 * @param  IP $ip IP address
 * @param  SHORT_TEXT $cidr CIDR range (e.g. 204.93.240.0/24)
 * @return boolean Whether it is
 */
function ip_cidr_check($ip, $cidr)
{
    if ((strpos($ip, ':') === false) !== (strpos($cidr, ':') === false)) {
        return false; // Different IP address type
    }

    if (strpos($ip, ':') === false) {
        // IPv4...

        list($net, $maskbits) = explode('/', $cidr, 2);

        $ip_net = ip2long($net);
        $ip_mask = ~((1 << (32 - intval($maskbits))) - 1);

        $ip_ip = ip2long($ip);

        return (($ip_ip & $ip_mask) == $ip_net);
    }

    // IPv6...

    $unpacked = unpack('A16', _inet_pton($ip));
    $binaryip = '';
    for ($i = 0; $i < strlen($unpacked[1]); $i++) {
        $char = $unpacked[1][$i];
        $binaryip .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
    }

    list($net, $maskbits) = explode('/', $cidr, 2);
    $unpacked = unpack('A16', _inet_pton($net));
    $binarynet = '';
    for ($i = 0; $i < strlen($unpacked[1]); $i++) {
        $char = $unpacked[1][$i];
        $binarynet .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
    }

    $ip_net_bits = substr($binaryip, 0, intval($maskbits));
    $net_bits = substr($binarynet, 0, intval($maskbits));
    return ($ip_net_bits == $net_bits);
}

// Useful for basic profiling
global $PAGE_START_TIME;
$PAGE_START_TIME = microtime(true);

// Are we in a special version of PHP?
define('GOOGLE_APPENGINE', isset($_SERVER['APPLICATION_ID']));

define('URL_CONTENT_REGEXP', '\w\-\x80-\xFF'); // PHP is done using ASCII (don't use the 'u' modifier). Note this doesn't include dots, this is intentional as they can cause problems in filenames
define('URL_CONTENT_REGEXP_JS', '\w\-\u0080-\uFFFF'); // JavaScript is done using Unicode

// Sanitise the PHP environment some more
safe_ini_set('track_errors', '1'); // so $php_errormsg is available
if (!GOOGLE_APPENGINE) {
    safe_ini_set('include_path', '');
    safe_ini_set('allow_url_fopen', '0');
}
safe_ini_set('suhosin.executor.disable_emodifier', '1'); // Extra security if suhosin is available
safe_ini_set('suhosin.executor.multiheader', '1'); // Extra security if suhosin is available
safe_ini_set('suhosin.executor.disable_eval', '0');
safe_ini_set('suhosin.executor.eval.whitelist', '');
safe_ini_set('suhosin.executor.func.whitelist', '');
safe_ini_set('auto_detect_line_endings', '0');
safe_ini_set('default_socket_timeout', '60');
safe_ini_set('html_errors', '1');
safe_ini_set('docref_root', 'http://php.net/manual/en/');
safe_ini_set('docref_ext', '.php');

// Get ready for some global variables
global $REQUIRED_CODE, $CURRENT_SHARE_USER, $PURE_POST, $IN_MINIKERNEL_VERSION;
/** Details of what code files have been loaded up.
 *
 * @global array $REQUIRED_CODE
 */
$REQUIRED_CODE = array();
/** If running on a shared-install, this is the identifying name of the site that is being called up.
 *
 * @global ?ID_TEXT $CURRENT_SHARE_USER
 */
if ((!isset($CURRENT_SHARE_USER)) || (isset($_SERVER['REQUEST_METHOD']))) {
    $CURRENT_SHARE_USER = null;
}
/** A copy of the POST parameters, as passed initially to PHP (needed for hash checks with some IPN systems).
 *
 * @global array $PURE_POST
 */
$PURE_POST = $_POST;
$IN_MINIKERNEL_VERSION = false;

// Critical error reporting system
global $FILE_BASE;
if (is_file($FILE_BASE . '/sources_custom/critical_errors.php')) {
    require($FILE_BASE . '/sources_custom/critical_errors.php');
} else {
    $php_errormsg = '';
    @include($FILE_BASE . '/sources/critical_errors.php');
    if ($php_errormsg != '') {
        exit('<!DOCTYPE html>' . "\n" . '<html lang="EN"><head><title>Critical startup error</title></head><body><h1>Composr startup error</h1><p>The third most basic Composr startup file, sources/critical_errors.php, could not be located. This is almost always due to an incomplete upload of the Composr system, so please check all files are uploaded correctly.</p><p>Once all Composr files are in place, Composr must actually be installed by running the installer. You must be seeing this message either because your system has become corrupt since installation, or because you have uploaded some but not all files from our manual installer package: the quick installer is easier, so you might consider using that instead.</p><p>ocProducts maintains full documentation for all procedures and tools, especially those for installation. These may be found on the <a href="http://compo.sr">Composr website</a>. If you are unable to easily solve this problem, we may be contacted from our website and can help resolve it for you.</p><hr /><p style="font-size: 0.8em">Composr is a website engine created by ocProducts.</p></body></html>');
    }
}

// Load up config file
global $SITE_INFO;
/** Site base configuration settings.
 *
 * @global array $SITE_INFO
 */
$SITE_INFO = array();
@include($FILE_BASE . '/_config.php');
if (count($SITE_INFO) == 0) {
    // LEGACY
    if ((!is_file($FILE_BASE . '/_config.php')) && (is_file($FILE_BASE . '/info.php'))) {
        @copy($FILE_BASE . '/info.php', $FILE_BASE . '/_config.php');
        if (is_file($FILE_BASE . '/_config.php')) {
            $new_config_file = file_get_contents($FILE_BASE . '/_config.php');
            $new_config_file = str_replace(array('ocf_table_prefix', 'use_mem_cache', 'ocp_member_id', 'ocp_member_hash', 'ocf', 'admin_password'), array('cns_table_prefix', 'use_persistent_cache', 'cms_member_id', 'cms_member_hash', 'cns', 'master_password'), $new_config_file);
            $new_config_file = str_replace(']=\'', '] = \'', $new_config_file); // Clean up formatting to new convention
            file_put_contents($FILE_BASE . '/_config.php', $new_config_file, LOCK_EX);
        } else {
            exit('Error, cannot rename info.php to _config.php: check the Composr upgrade instructions');
        }
        @include($FILE_BASE . '/_config.php');
    }
}
if (count($SITE_INFO) == 0) {
    if (!is_file($FILE_BASE . '/_config.php')) {
        critical_error('_CONFIG.PHP_MISSING');
    } elseif (strlen(trim(file_get_contents($FILE_BASE . '/_config.php'))) == 0) {
        critical_error('_CONFIG.PHP_EMPTY');
    } else {
        critical_error('_CONFIG.PHP_CORRUPTED');
    }
}

// Make sure we have the correct IP address in REMOTE_ADDR
if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    if (empty($SITE_INFO['trusted_proxies'])) {
        $trusted_proxies = '103.21.244.0/22,103.22.200.0/22,103.31.4.0/22,104.16.0.0/12,108.162.192.0/18,131.0.72.0/22,141.101.64.0/18,162.158.0.0/15,172.64.0.0/13,173.245.48.0/20,188.114.96.0/20,190.93.240.0/20,197.234.240.0/22,198.41.128.0/17,2400:cb00::/32,2405:8100::/32,2405:b500::/32,2606:4700::/32,2803:f800::/32,2c0f:f248::/32,2a06:98c0::/29';
    } else {
        $trusted_proxies = $SITE_INFO['trusted_proxies'];
    }
    foreach (explode(',', $trusted_proxies) as $proxy) {
        if (((strpos($proxy, '/') !== false) && (ip_cidr_check($_SERVER['REMOTE_ADDR'], $proxy))) || ($_SERVER['REMOTE_ADDR'] == $proxy)) {
            if (ip_cidr_check($_SERVER['REMOTE_ADDR'], $proxy)) {
                $_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_X_FORWARDED_FOR'];
                break;
            }
        }
    }
}

// Rate limiter, to stop aggressive bots
global $SITE_INFO;
$rate_limiting = empty($SITE_INFO['rate_limiting']) ? false : ($SITE_INFO['rate_limiting'] == '1');
if ($rate_limiting) {
    if ((!empty($_SERVER['REMOTE_ADDR'])) && (basename($_SERVER['SCRIPT_NAME']) == 'index.php')) {
        // Basic context
        $ip = $_SERVER['REMOTE_ADDR'];
        $time = time();

        if (!(((!empty($_SERVER['SERVER_ADDR'])) && ($ip == $_SERVER['SERVER_ADDR'])) || ((!empty($_SERVER['LOCAL_ADDR'])) && ($ip == $_SERVER['LOCAL_ADDR'])))) {
            global $RATE_LIMITING_DATA;
            $RATE_LIMITING_DATA = array();

            // Read in state
            $rate_limiter_path = dirname(__DIR__) . '/data_custom/rate_limiter.php';
            if (is_file($rate_limiter_path)) {
                $fp = fopen($rate_limiter_path, 'rb');
                flock($fp, LOCK_SH);
                include($rate_limiter_path);
                flock($fp, LOCK_UN);
                fclose($fp);
            }

            // Filter to just times within our window
            $pertinent = array();
            $rate_limit_time_window = empty($SITE_INFO['rate_limit_time_window']) ? 10 : intval($SITE_INFO['rate_limit_time_window']);
            if (isset($RATE_LIMITING_DATA[$ip])) {
                foreach ($RATE_LIMITING_DATA[$ip] as $i => $old_time) {
                    if ($old_time >= $time - $rate_limit_time_window) {
                        $pertinent[] = $old_time;
                    }
                }
            }

            // Do we have to block?
            $rate_limit_hits_per_window = empty($SITE_INFO['rate_limit_hits_per_window']) ? 5 : intval($SITE_INFO['rate_limit_hits_per_window']);
            if (count($pertinent) >= $rate_limit_hits_per_window) {
                header('HTTP/1.0 429 Too Many Requests');
                header('Content-Type: text/plain');
                exit('We only allow ' . strval($rate_limit_hits_per_window - 1) . ' page hits every ' . strval($rate_limit_time_window) . ' seconds. You\'re at ' . strval(count($pertinent)) . '.');
            }

            // Remove any old hits from other IPs
            foreach ($RATE_LIMITING_DATA as $_ip => $times) {
                if ($_ip != $ip) {
                    foreach ($times as $i => $old_time) {
                        if ($old_time < $time - $rate_limit_time_window) {
                            unset($RATE_LIMITING_DATA[$_ip][$i]);
                        }
                    }
                    if (count($RATE_LIMITING_DATA[$_ip]) == 0) {
                        unset($RATE_LIMITING_DATA[$_ip]);
                    }
                }
            }

            // Write out new state
            $RATE_LIMITING_DATA[$ip] = $pertinent;
            $RATE_LIMITING_DATA[$ip][] = $time;
            file_put_contents($rate_limiter_path, '<' . '?php' . "\n\n" . '$RATE_LIMITING_DATA=' . var_export($RATE_LIMITING_DATA, true) . ';', LOCK_EX);
            //sync_file($rate_limiter_path); Not done. Each server should rate limit separately. Synching this data across servers would be too slow and not scalable

            // Save some memory
            unset($RATE_LIMITING_DATA);
        }
    }
}

get_custom_file_base(); // Make sure $CURRENT_SHARE_USER is set if it is a shared site, so we can use CURRENT_SHARE_USER as an indicator of it being one.

// Pass on to next bootstrap level
if (GOOGLE_APPENGINE) {
    require_code('google_appengine');
}
require_code('global2');
