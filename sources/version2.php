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

/*
We have many standardised ways of writing version numbers for different situations. For example...

Dotted: 10.3.beta4        (processable cleanly)
Pretty: 10.3 beta4        (human-readable)
Basis dotted: 10.3        (same as dotted or pretty, except for the end bit missing)
Long dotted 10.3.0.beta4  (precision/specificity)
General: 10.3             (simple float)
Branch: 10.x              (when talking about development paths)
Break downs:              (if long dotted was exploded)
 Major: 10
 Minor: 3
 Patch: 0
 Qualifier: beta
 Qualifier number: 4
PHP: 10.3.0.beta4         (only used when interfacing with PHP, not our standard)
*/

/**
 * Get information about new versions of Composr (or more accurately, what's wrong with this version).
 *
 * @return Tempcode Information about the installed Composr version
 */
function get_future_version_information()
{
    require_lang('version');

    $version_dotted = get_param_string('keep_test_version', get_version_dotted()); // E.g. ?keep_test_version=10.RC29&keep_cache_blocks=0 to test
    $url = 'http://compo.sr/uploads/website_specific/compo.sr/scripts/version.php?version=' . rawurlencode($version_dotted) . '&lang=' . rawurlencode(user_lang());

    static $http_result = null; // Cache
    if ($http_result === null) {
        require_code('http');
        $http_result = cache_and_carry('cms_http_request', array($url, array('trigger_error' => false)), ($version_dotted == get_version_dotted()) ? 5/*5 minute cache*/ : 0);
    }
    if (is_object($http_result) && ($http_result->data !== null)) {
        $data = $http_result->data;
        $data = str_replace('"../upgrader.php"', '"' . get_base_url() . '/upgrader.php"', $data);

        if ($GLOBALS['XSS_DETECT']) {
            ocp_mark_as_escaped($data);
        }

        require_code('character_sets');
        $data = convert_to_internal_encoding($data, $http_result[8]);

        $table = make_string_tempcode($data);
    } else {
        $table = paragraph(do_lang_tempcode('CANNOT_CONNECT_HOME'), 'dfsdff32ffd');
    }

    require_code('xhtml');
    return make_string_tempcode(xhtmlise_html($table->evaluate()));
}

/**
 * Get branch version number for a Composr version.
 * This is not used for much, it's a very special case.
 *
 * @param  ?float $general General version number (null: on disk version)
 * @return string Branch version number
 */
function get_version_branch($general = null)
{
    if ($general === null) {
        $general = cms_version_number();
    }

    return float_to_raw_string($general, 10, true) . '.x';
}

/**
 * Get dotted version from given Composr-version-registry (version.php) supplied components.
 *
 * @param  ?integer $main Main version number (null: on disk version)
 * @param  ?string $minor Minor version number (null: on disk version)
 * @return string Dotted version number
 */
function get_version_dotted($main = null, $minor = null)
{
    if ($main === null) {
        $main = cms_version();
    }
    if ($minor === null) {
        $minor = cms_version_minor(); // May be a qualifier
    }

    return strval($main) . (($minor == '0') ? '' : ('.' . $minor));
}

/**
 * Gets any random way of writing a version number (in all of Composr's history) and makes it a dotted style like "3.2.beta2".
 * Note that the dotted format is not compatible with PHP's version_compare function directly but $long_dotted_number_with_qualifier from get_version_components__from_dotted() is.
 *
 * @param  string $any_format Any reasonable input
 * @return string Dotted version number
 */
function get_version_dotted__from_anything($any_format)
{
    $dotted = $any_format;

    // Strip useless bits
    $dotted = preg_replace('#[-\s]*(final|gold)#i', '', $dotted);
    $dotted = preg_replace('#(Composr |version )*#i', '', $dotted);
    $dotted = trim($dotted);

    // Change dashes and spaces to dots
    $dotted = str_replace(array('-', ' '), array('.', '.'), $dotted);

    foreach (array('alpha', 'beta', 'RC') as $qualifier) {
        $dotted = preg_replace('#\.?' . preg_quote($qualifier, '#') . '\.?#i', '.' . $qualifier, $dotted);
    }

    // Canonical to not have extra .0's on end. Don't really care about what Composr stores as we clean this up in our server's version.php - it is crucial that news post and download names are canonical though so version.php works. NB: Latest recommended versions are done via download name and description labelling.
    $dotted = preg_replace('#(\.0)+($|\.alpha|\.beta|\.RC)#', '$2', $dotted);

    return $dotted;
}

/**
 * Analyse a dotted version number into components.
 *
 * @param  string $dotted Dotted version number
 * @return array Tuple of components: dotted basis version (i.e. with no alpha/beta/RC component and no trailing zeros), qualifier (blank, or alpha, or beta, or RC), qualifier number (null if not an alpha/beta/RC), dotted version number with trailing zeros to always cover 3 components, general version number (i.e. float, no patch release and qualifier information, like cms_version_number), dotted version number to cover 3 or 4 components (i.e. with qualifier if present)
 */
function get_version_components__from_dotted($dotted)
{
    // Now split it up version number
    $qualifier = null;
    $qualifier_number = null;
    $basis_dotted_number = null;
    foreach (array('RC', 'beta', 'alpha') as $type) {
        if (strpos($dotted, '.' . $type) !== false) {
            $qualifier = $type;
            $qualifier_number = intval(substr($dotted, strrpos($dotted, '.' . $type) + strlen('.' . $type)));
            $basis_dotted_number = substr($dotted, 0, strrpos($dotted, '.' . $type));
            break;
        }
    }
    if ($basis_dotted_number === null) {
        $basis_dotted_number = $dotted;
    }

    $long_dotted_number = $basis_dotted_number . str_repeat('.0', max(0, 2 - substr_count($basis_dotted_number, '.')));

    $general_number = floatval(preg_replace('#\.\d+$#', '', $long_dotted_number)); // No third dot component

    $long_dotted_number_with_qualifier = $long_dotted_number;
    if ($qualifier !== null) {
        $long_dotted_number_with_qualifier .= '.' . $qualifier . strval($qualifier_number);
    }

    return array(
        $basis_dotted_number,
        $qualifier,
        $qualifier_number,
        $long_dotted_number,
        $general_number,
        $long_dotted_number_with_qualifier,
    );
}

/**
 * Get a pretty version number for a Composr version.
 * This pretty style is not used in Composr code per se, but is shown to users and hence Composr may need to recognise it when searching news posts, download databases, etc.
 *
 * @param  string $dotted Dotted version number (optionally in long-dotted format)
 * @return string Pretty version number
 */
function get_version_pretty__from_dotted($dotted)
{
    return preg_replace('#(\.0)*\.(alpha|beta|RC)#', ' ${2}', $dotted);
}

/**
 * Whether it is a substantial release (i.e. major new version).
 *
 * @param  string $dotted Dotted version number
 * @return boolean Whether it is
 */
function is_substantial_release($dotted)
{
    list(, , , $long_dotted_number) = get_version_components__from_dotted($dotted);

    return (substr($long_dotted_number, -2) == '.0') || (strpos($long_dotted_number, 'beta1') !== false) || (strpos($long_dotted_number, 'RC1') !== false);
}

/**
 * Find whether a PHP version is still supported by the PHP developers.
 *
 * @param  string $v The version
 * @return ?boolean Whether it is (null: some kind of error)
 */
function is_php_version_supported($v)
{
    require_code('http');
    if (function_exists('set_option')) {
        $data = cache_and_carry('http_get_contents', array('https://raw.githubusercontent.com/php/web-php/master/include/branches.inc', array('trigger_error' => false)), 60 * 60 * 24 * 7);
    } else {
        $data = http_get_contents('https://raw.githubusercontent.com/php/web-php/master/include/branches.inc', array('trigger_error' => false));
    }

    $matches = array();

    // Corruption?
    if (preg_match('#\'\d+\.\d+\' => array\([^\(\)]*\'security\' => \'(\d\d\d\d-\d\d-\d\d)\'#Us', $data, $matches) == 0) {
        return null;
    }

    // Do we have actual data?
    $matches = array();
    if (preg_match('#\'' . preg_quote($v, '#') . '\' => array\([^\(\)]*\'security\' => \'(\d\d\d\d)-(\d\d)-(\d\d)\'#is', $data, $matches) != 0) {
        $eol = mktime(0, 0, 0, intval($matches[2]), intval($matches[3]), intval($matches[1]));
        return ($eol > time());
    }

    // Is it older than all releases provided?
    $matches = array();
    $min_version = null;
    $num_matches = preg_match_all('#\'(\d+\.\d+)\' => array\(#', $data, $matches);
    for ($i = 0; $i < $num_matches; $i++) {
        $version = floatval($matches[1][$i]);
        if ($version != 3.0/*special case*/) {
            if (($min_version === null) || ($version < $min_version)) {
                $min_version = $version;
            }
        }
    }
    if (floatval($v) < $min_version) {
        return false;
    }

    // If gets here we assume it is newer than releases provided
    return true;
}
