<?php /*

 Composr
 Copyright (c) ocProducts, 2004-2016

 See text/EN/licence.txt for full licencing information.


 NOTE TO PROGRAMMERS:
   Do not edit this file. If you need to make changes, save your changed file to the appropriate *_custom folder
   **** If you ignore this advice, then your website upgrades (e.g. for bug fixes) will likely kill your changes ****

*/

/**
 * @license    http://opensource.org/licenses/cpal_1.0 Common Public Attribution License
 * @copyright  ocProducts Ltd
 * @package    core
 */

/**
 * Standard code module initialisation function.
 *
 * @ignore
 */
function init__themes()
{
    global $THEME_IMAGES_CACHE, $CDN_CONSISTENCY_CHECK, $RECORD_THEME_IMAGES_CACHE, $RECORDED_THEME_IMAGES, $THEME_IMAGES_SMART_CACHE_LOAD;
    $THEME_IMAGES_CACHE = array();
    $CDN_CONSISTENCY_CHECK = array();
    $RECORD_THEME_IMAGES_CACHE = false;
    $RECORDED_THEME_IMAGES = array();
    $THEME_IMAGES_SMART_CACHE_LOAD = 0;
}

/**
 * Find the URL to the theme image of the specified ID. It searches various priorities, including language and theme overrides.
 *
 * @param  ID_TEXT $id The theme image ID
 * @param  boolean $silent_fail Whether to silently fail (i.e. not give out an error message when a theme image cannot be found)
 * @param  boolean $leave_local Whether to leave URLs as relative local URLs
 * @param  ?ID_TEXT $theme The theme to search in (null: users current theme)
 * @param  ?LANGUAGE_NAME $lang The language to search for (null: users current language)
 * @param  ?object $db The database to use (null: site database)
 * @param  boolean $pure_only Whether to only search the default 'images' filesystem
 * @return URLPATH The URL found (blank: not found)
 */
function find_theme_image($id, $silent_fail = false, $leave_local = false, $theme = null, $lang = null, $db = null, $pure_only = false)
{
    global $THEME_IMAGES_CACHE, $USER_LANG_CACHED, $THEME_IMAGES_SMART_CACHE_LOAD, $RECORD_THEME_IMAGES_CACHE, $SMART_CACHE, $SITE_INFO;

    if ((substr($id, 0, 4) === 'cns_') && (is_file(get_file_base() . '/themes/default/images/avatars/index.html'))) { // Allow debranding of theme img dirs
        $id = substr($id, 4);
    }

    if ((isset($_GET['keep_theme_seed'])) && (get_param_string('keep_theme_seed', null) !== null) && (function_exists('has_privilege')) && (has_privilege(get_member(), 'view_profiling_modes'))) {
        require_code('themewizard');
        $test = find_theme_image_themewizard_preview($id, $silent_fail);
        if ($test !== null) {
            return $test;
        }
    }

    if ($db === null) {
        $db = $GLOBALS['SITE_DB'];
    }

    $true_theme = isset($GLOBALS['FORUM_DRIVER']) ? $GLOBALS['FORUM_DRIVER']->get_theme() : 'default';
    if ($theme === null) {
        $theme = $true_theme;
    }

    $true_lang = ($USER_LANG_CACHED === null) ? user_lang() : $USER_LANG_CACHED;
    if ($lang === null) {
        $lang = $true_lang;
    }

    $truism = ($theme === $true_theme) && ($lang === $true_lang);

    $site = ($GLOBALS['SITE_DB'] === $db) ? 'site' : 'forums';

    if (!isset($THEME_IMAGES_CACHE[$site])) {
        load_theme_image_cache($db, $site, $true_theme, $true_lang);
    }

    if ((!$truism) && (!$pure_only)) { // Separate lookup, cannot go through $THEME_IMAGES_CACHE
        $path = $db->query_select_value_if_there('theme_images', 'path', array('theme' => $theme, 'lang' => $lang, 'id' => $id));
        if ($path !== null) {
            if ((url_is_local($path)) && (!$leave_local)) {
                if (is_forum_db($db)) {
                    $path = get_forum_base_url() . '/' . $path;
                } else {
                    if ((substr($path, 0, 22) === 'themes/default/images/') || (!is_file(get_custom_file_base() . '/' . rawurldecode($path)))) {
                        $path = get_base_url() . '/' . $path;
                    } else {
                        $path = get_custom_base_url() . '/' . $path;
                    }
                }
            }

            $ret = cdn_filter($path);
            if ($THEME_IMAGES_SMART_CACHE_LOAD >= 2) {
                $SMART_CACHE->append('theme_images_' . $theme . '_' . $lang, $id, $ret);
            }
            return $ret;
        }
    }

    if ((!$pure_only) && ($site === 'site') && (!array_key_exists($id, $THEME_IMAGES_CACHE[$site])) && ($THEME_IMAGES_SMART_CACHE_LOAD < 2)) {
        // Smart cache update
        load_theme_image_cache($db, $site, $true_theme, $true_lang);
        find_theme_image($id, true, true, $theme, $lang, $db, $pure_only);
    }

    if (($pure_only) || (!isset($THEME_IMAGES_CACHE[$site][$id])) || (!$truism)) {
        // Disk search...

        $path = null;

        $priorities = array();
        if (!$pure_only) { // Should do "images_custom" first, as this will also do a DB search
            $priorities = array_merge($priorities, array(
                array($theme, $lang, 'images_custom'),
                array($theme, '', 'images_custom'),
                ($lang === fallback_lang()) ? null : array($theme, fallback_lang(), 'images_custom'),
            ));
        }
        // This will not do a DB search, just a filesystem search. The Theme Wizard makes these though
        $priorities = array_merge($priorities, array(
            array($theme, $lang, 'images'),
            array($theme, '', 'images'),
            ($lang === fallback_lang()) ? null : array($theme, fallback_lang(), 'images'),
        ));
        if ($theme !== 'default') {
            if (!$pure_only) {
                $priorities = array_merge($priorities, array(
                    array('default', $lang, 'images_custom'),
                    array('default', '', 'images_custom'),
                    ($lang === fallback_lang()) ? null : array('default', fallback_lang(), 'images_custom'),
                ));
            }
            $priorities = array_merge($priorities, array(
                array('default', $lang, 'images'),
                array('default', '', 'images'),
                ($lang === fallback_lang()) ? null : array('default', fallback_lang(), 'images'),
            ));
        }

        foreach ($priorities as $i => $priority) {
            if ($priority === null) {
                continue;
            }

            if (($priority[2] === 'images_custom') && ($priority[1] !== '')) { // Likely won't auto find
                $smap = array('id' => $id, 'theme' => $priority[0], 'lang' => $priority[1]);
                $nql_backup = $GLOBALS['NO_QUERY_LIMIT'];
                $GLOBALS['NO_QUERY_LIMIT'] = true;
                $truism_b = ($priority[0] === $true_theme) && ((!multi_lang()) || ($priority[1] === '') || ($priority[1] === $true_lang));
                $path = $truism_b ? null : $db->query_select_value_if_there('theme_images', 'path', $smap);
                $GLOBALS['NO_QUERY_LIMIT'] = $nql_backup;

                if ($path !== null) { // Make sure this isn't just the result file we should find at a lower priority
                    if (strpos($path, '/images/' . $id . '.') !== false) {
                        continue;
                    }
                    if ((array_key_exists('lang', $smap)) && (strpos($path, '/images/' . $smap['lang'] . '/' . $id . '.') !== false)) {
                        continue;
                    }
                    break;
                }
            }

            $test = _search_img_file($priority[0], $priority[1], $id, $priority[2]);
            if ($test !== null) {
                $path_bits = explode('/', $test);
                $path = '';
                foreach ($path_bits as $bit) {
                    if ($path !== '') {
                        $path .= '/';
                    }
                    $path .= rawurlencode($bit);
                }
                break;
            }
        }

        if (!is_forum_db($db)) { // If guard is here because a MSN site can't make assumptions about the file system of the central site
            if ((($path !== null) && ($path !== '')) || (($silent_fail) && (!$GLOBALS['SEMI_DEV_MODE']))) {
                $nql_backup = $GLOBALS['NO_QUERY_LIMIT'];
                $GLOBALS['NO_QUERY_LIMIT'] = true;
                $db->query_delete('theme_images', array('id' => $id, 'theme' => $theme, 'lang' => $lang)); // Allow for race conditions
                $db->query_insert('theme_images', array('id' => $id, 'theme' => $theme, 'path' => ($path === null) ? '' : $path, 'lang' => $lang), false, true); // Allow for race conditions
                $GLOBALS['NO_QUERY_LIMIT'] = $nql_backup;
                Self_learning_cache::erase_smart_cache();
            }
        }

        if ($path === null) {
            if (!$silent_fail) {
                require_code('site');
                attach_message(do_lang_tempcode('NO_SUCH_THEME_IMAGE', escape_html($id)), 'warn');
            }
            if ($THEME_IMAGES_SMART_CACHE_LOAD >= 2) {
                $SMART_CACHE->append('theme_images_' . $theme . '_' . $lang, $id, '');
            }
            return '';
        }
        if ($truism) {
            $THEME_IMAGES_CACHE[$site][$id] = $path; // only cache if we are looking up for our own theme/lang
        }
    } else {
        $path = $THEME_IMAGES_CACHE[$site][$id];

        // Decache if file has disappeared
        if (($path !== '') && ((!isset($SITE_INFO['disable_smart_decaching'])) || ($SITE_INFO['disable_smart_decaching'] !== '1')) && (url_is_local($path)) && ((!isset($SITE_INFO['no_disk_sanity_checks'])) || ($SITE_INFO['no_disk_sanity_checks'] === '0')) && (!is_file(get_file_base() . '/' . rawurldecode($path))) && (!is_file(get_custom_file_base() . '/' . rawurldecode($path)))) { // Missing image, so erase to re-search for it
            unset($THEME_IMAGES_CACHE[$site][$id]);
            $ret = find_theme_image($id, $silent_fail, $leave_local, $theme, $lang, $db, $pure_only);
            if ($THEME_IMAGES_SMART_CACHE_LOAD >= 2) {
                $SMART_CACHE->append('theme_images_' . $theme . '_' . $lang, $id, $ret);
            }
            return $ret;
        }
    }

    // Add to cache
    if ($THEME_IMAGES_SMART_CACHE_LOAD >= 2) {
        $SMART_CACHE->append('theme_images_' . $theme . '_' . $lang, $id, $path);
    }

    // Make absolute
    if ((url_is_local($path)) && (!$leave_local) && ($path !== '')) {
        if (is_forum_db($db)) {
            $base_url = get_forum_base_url();
        } else {
            global $SITE_INFO;
            $missing = (!$pure_only) && (((!isset($SITE_INFO['disable_smart_decaching'])) || ($SITE_INFO['disable_smart_decaching'] !== '1')) && (!is_file(get_custom_file_base() . '/' . rawurldecode($path))));
            if ((substr($path, 0, 22) === 'themes/default/images/') || ($missing) || ((!isset($SITE_INFO['no_disk_sanity_checks'])) || ($SITE_INFO['no_disk_sanity_checks'] === '0')) && (!is_file(get_custom_file_base() . '/' . rawurldecode($path)))) { // Not found, so throw away custom theme image and look in default theme images to restore default
                if (($missing) && (!is_file(get_file_base() . '/' . rawurldecode($path)))) {
                    $ret = find_theme_image($id, $silent_fail, $leave_local, $theme, $lang, $db, true);
                    if ($THEME_IMAGES_SMART_CACHE_LOAD >= 2) {
                        $SMART_CACHE->append('theme_images_' . $theme . '_' . $lang, $id, $ret);
                    }
                    return $ret;
                }

                $base_url = get_base_url();
            } else {
                $base_url = get_custom_base_url();
            }
        }

        $path = $base_url . '/' . $path;
    }

    $ret = cdn_filter($path);

    if ($RECORD_THEME_IMAGES_CACHE) {
        global $RECORDED_THEME_IMAGES;
        if (!is_on_multi_site_network()) {
            $RECORDED_THEME_IMAGES[serialize(array($id, $theme, $lang))] = true;
        }
    }

    return $ret;
}

/**
 * Load up theme image cache.
 *
 * @param  object $db The database to load from (used for theme images running across multi-site-networks)
 * @param  ID_TEXT $site The internal name of the database to load from (used for theme images running across multi-site-networks)
 * @param  ID_TEXT $true_theme Theme0
 * @param  LANGUAGE_NAME $true_lang Language
 */
function load_theme_image_cache($db, $site, $true_theme, $true_lang)
{
    global $THEME_IMAGES_CACHE, $THEME_IMAGES_SMART_CACHE_LOAD, $SMART_CACHE;

    if ($THEME_IMAGES_SMART_CACHE_LOAD === 0) {
        $THEME_IMAGES_CACHE[$site] = $SMART_CACHE->get('theme_images_' . $true_theme . '_' . $true_lang);
        if (is_null($THEME_IMAGES_CACHE[$site])) {
            $THEME_IMAGES_CACHE[$site] = array();
        }
    } elseif ($THEME_IMAGES_SMART_CACHE_LOAD === 1) {
        $test = $db->query_select('theme_images', array('id', 'path'), array('theme' => $true_theme, 'lang' => $true_lang));
        $THEME_IMAGES_CACHE[$site] = collapse_2d_complexity('id', 'path', $test);
    }

    $THEME_IMAGES_SMART_CACHE_LOAD++;
}

/**
 * Filter a path so it runs through a CDN.
 *
 * @param  URLPATH $path Input URL
 * @return URLPATH Output URL
 */
function cdn_filter($path)
{
    static $cdn = null;
    if ($cdn === null) {
        $cdn = get_option('cdn');
    }
    static $knm = null;
    if ($knm === null) {
        $knm = get_param_integer('keep_no_minify', 0);
    }

    if (($cdn != '') && ($knm === 0)) {
        if ($cdn === '<autodetect>') {
            $cdn = get_value('cdn');
            if ($cdn === null) {
                require_code('themes2');
                $cdn = autoprobe_cdns();
            }
        }
        if ($cdn === '') {
            return $path;
        }

        global $CDN_CONSISTENCY_CHECK;

        if (isset($CDN_CONSISTENCY_CHECK[$path])) {
            return $CDN_CONSISTENCY_CHECK[$path];
        }

        static $cdn_parts = null;
        if ($cdn_parts === null) {
            $cdn_parts = explode(',', $cdn);
        }

        $sum_asc = 0;
        $basename = basename($path);
        $path_len = strlen($basename);
        for ($i = 0; $i < $path_len; $i++) {
            $sum_asc += ord($basename[$i]);
        }

        $cdn_part = $cdn_parts[$sum_asc % count($cdn_parts)]; // To make a consistent but fairly even distribution we do some modular arithmetic against the total of the ascii values
        static $normal_suffix = null;
        if ($normal_suffix === null) {
            $normal_suffix = '#(^https?://)' . str_replace('#', '#', preg_quote(get_domain())) . '(/)#';
        }
        $out = preg_replace($normal_suffix, '${1}' . $cdn_part . '${2}', $path);
        $CDN_CONSISTENCY_CHECK[$path] = $out;
        return $out;
    }

    return $path;
}

/**
 * Search for a specified image file within a theme for a specified language.
 *
 * @param  ID_TEXT $theme The theme
 * @param  ?LANGUAGE_NAME $lang The language (null: try generally, under no specific language)
 * @param  ID_TEXT $id The theme image ID
 * @param  ID_TEXT $dir Directory to search
 * @return ?string The path to the image (null: was not found)
 * @ignore
 */
function _search_img_file($theme, $lang, $id, $dir = 'images')
{
    $extensions = array('png', 'jpg', 'jpeg', 'gif', 'ico', 'svg');
    $url_base = 'themes/';
    foreach (array(get_custom_file_base(), get_file_base()) as $_base) {
        $base = $_base . '/themes/';

        foreach ($extensions as $extension) {
            $file_path = $base . $theme . '/';
            if ($dir != '') {
                $file_path .= $dir . '/';
            }
            if (($lang !== null) && ($lang != '')) {
                $file_path .= $lang . '/';
            }
            $file_path .= $id . '.' . $extension;
            if (is_file($file_path)) { // Theme+Lang
                $path = $url_base . rawurlencode($theme) . '/' . $dir . '/';
                if (($lang !== null) && ($lang != '')) {
                    $path .= rawurlencode($lang) . '/';
                }
                $path .= $id . '.' . $extension;
                return $path;
            }
        }
    }
    return null;
}
