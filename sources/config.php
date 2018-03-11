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

/**
 * Standard code module initialisation function.
 *
 * @ignore
 */
function init__config()
{
    global $CONFIG_OPTIONS_CACHE, $CONFIG_OPTIONS_FULLY_LOADED, $VALUES_FULLY_LOADED, $SMART_CACHE, $PERSISTENT_CACHE;
    $CONFIG_OPTIONS_FULLY_LOADED = false;
    $VALUES_FULLY_LOADED = false;

    global $VALUE_OPTIONS_CACHE, $IN_MINIKERNEL_VERSION;
    if (!$IN_MINIKERNEL_VERSION) {
        if (multi_lang_content()) {
            load_config_options(); // Translation will be needed, so we won't put in the smart cache because we don't know the current language yet (chicken and egg)
        } else {
            $CONFIG_OPTIONS_CACHE = array();
            if ($SMART_CACHE !== null) {
                $_cache = $SMART_CACHE->get('CONFIG_OPTIONS');
                if ($_cache !== null) {
                    foreach ($_cache as $c_key => $c_value) {
                        $CONFIG_OPTIONS_CACHE[$c_key] = array('_cached_string_value' => $c_value, 'c_value' => $c_value);
                    }
                }
            }
        }

        if ($PERSISTENT_CACHE === null) {
            $VALUE_OPTIONS_CACHE = array();
            if ($SMART_CACHE !== null) {
                $test = $SMART_CACHE->get('VALUE_OPTIONS');
                if ($test !== null) {
                    $or_list = '1=0';
                    foreach ($test as $key => $_) {
                        $or_list .= ' OR ' . db_string_equal_to('the_name', $key);
                    }
                    $_value_options = $GLOBALS['SITE_DB']->query('SELECT * FROM ' . get_table_prefix() . 'values WHERE ' . $or_list);
                    $VALUE_OPTIONS_CACHE = list_to_map('the_name', $_value_options);
                    foreach ($test as $key => $_) {
                        if (!isset($VALUE_OPTIONS_CACHE[$key])) {
                            $VALUE_OPTIONS_CACHE[$key] = null;
                        }
                    }
                }
            }
        } else {
            load_value_options();
        }
    } else {
        $CONFIG_OPTIONS_CACHE = array();
        $VALUE_OPTIONS_CACHE = array();
    }

    global $GET_OPTION_LOOP;
    $GET_OPTION_LOOP = false;

    global $MULTI_LANG_CACHE;
    $MULTI_LANG_CACHE = null;
}

/**
 * Find whether to run in multi-lang mode.
 *
 * @return boolean Whether to run in multi-lang mode
 */
function multi_lang()
{
    global $MULTI_LANG_CACHE;

    if ($MULTI_LANG_CACHE !== null) {
        return $MULTI_LANG_CACHE;
    }

    $MULTI_LANG_CACHE = persistent_cache_get('MULTI_LANG');
    if ($MULTI_LANG_CACHE !== null) {
        return $MULTI_LANG_CACHE;
    }

    $MULTI_LANG_CACHE = false;
    if (get_option('enable_language_selection') !== '1') {
        return false;
    }

    require_code('config2');
    $ret = _multi_lang();
    persistent_cache_set('MULTI_LANG', $ret);
    return $ret;
}

/**
 * Load all config options.
 */
function load_config_options()
{
    global $CONFIG_OPTIONS_CACHE, $CONFIG_OPTIONS_FULLY_LOADED;

    $CONFIG_OPTIONS_FULLY_LOADED = true;

    if (!isset($GLOBALS['SITE_DB'])) {
        return;
    }

    if (multi_lang_content()) {
        $select = array('c_name', 'c_value', 'c_value_trans', 'c_needs_dereference');
    } else {
        $select = array('c_name', 'c_value');
    }
    $temp = $GLOBALS['SITE_DB']->query_select('config', $select, array(), '', null, 0, true);

    if ($temp === null) {
        if (running_script('install')) {
            $temp = array();
        } else {
            if ($GLOBALS['SITE_DB']->table_exists('config', true)) { // LEGACY: Has to use old naming from pre v10; also has to use $really, because of possibility of corrupt db_meta table
                $temp = $GLOBALS['SITE_DB']->query_select('config', array('the_name AS c_name', 'config_value AS c_value', 'config_value AS c_value_trans', 'if(the_type=\'transline\' OR the_type=\'transtext\' OR the_type=\'comcodeline\' OR the_type=\'comcodetext\',1,0) AS c_needs_dereference'), array(), '', null, 0, true);
                if ($temp === null) {
                    critical_error('DATABASE_FAIL');
                }
            } else {
                critical_error('DATABASE_FAIL');
            }
        }
    }

    $CONFIG_OPTIONS_CACHE = list_to_map('c_name', $temp);
}

/**
 * Load all value options.
 */
function load_value_options()
{
    global $VALUE_OPTIONS_CACHE, $VALUES_FULLY_LOADED;

    $VALUE_OPTIONS_CACHE = persistent_cache_get('VALUES');
    if (!is_array($VALUE_OPTIONS_CACHE)) {
        $_value_options = $GLOBALS['SITE_DB']->query_select('values', array('*'));
        $VALUE_OPTIONS_CACHE = list_to_map('the_name', $_value_options);
        persistent_cache_set('VALUES', $VALUE_OPTIONS_CACHE);
    }

    $VALUES_FULLY_LOADED = true;
}

/**
 * Find the value of the specified theme-overridable configuration option / theme-only option.
 *
 * @param  ID_TEXT $name The name of the option
 * @param  ?string $default Default value (null: also is a configuration option, look in that -- OR we have a hard-coded default for it)
 * @param  ?ID_TEXT $theme Theme to load for (null: active theme) (blank: non-existent theme)
 * @param  boolean $missing_ok Where to accept a missing option (and return null)
 * @return ?SHORT_TEXT The value (null: either null value, or no option found while $missing_ok set)
 */
function get_theme_option($name, $default = null, $theme = null, $missing_ok = false)
{
    // Look in theme.ini, if there is one
    if ($theme === null) {
        $theme = isset($GLOBALS['FORUM_DRIVER']) ? $GLOBALS['FORUM_DRIVER']->get_theme() : 'default';
    }
    if ($theme != '') {
        $ini_path = (($theme == 'default' || $theme == 'admin') ? get_file_base() : get_custom_file_base()) . '/themes/' . filter_naughty($theme) . '/theme.ini';
        if (is_file($ini_path)) {
            static $map = array();
            if (!isset($map[$theme])) {
                require_code('files');
                $map[$theme] = better_parse_ini_file($ini_path);
            }

            if ((isset($map[$theme][$name])) && ($map[$theme][$name] != '')) {
                return $map[$theme][$name];
            }
        }
    }

    // Hard-coded $default?
    if ($default === null) {
        switch ($name) {
            // Metadata
            case 'title':
                $default = $theme;
                break;
            case 'description':
                $default = '';
                break;
            case 'author':
                $default = do_lang('UNKNOWN');
                break;

            // Setup Wizard
            case 'setupwizard__install_profile':
                $default = '';
                break;
            case 'setupwizard__provide_block_choice':
                $default = '1';
                break;
            case 'setupwizard__lock_fixed_width_choice':
                $default = '';
                break;
            case 'setupwizard__lock_addons_on':
                $default = '';
                break;
            case 'setupwizard__provide_cms_advert_choice':
                $default = '1';
                break;
            case 'setupwizard__lock_show_content_tagging':
                $default = '0'; // If 1 defers to show_content_tagging
                break;
            case 'setupwizard__lock_show_content_tagging_inline':
                $default = '0'; // If 1 defers to show_content_tagging_inline
                break;
            case 'setupwizard__lock_show_screen_actions':
                $default = '0'; // If 1 defers to show_screen_actions
                break;
            case 'setupwizard__lock_single_public_zone':
                $default = '0'; // If 1 defers to single_public_zone
                break;

            // Theme Wizard ones
            case 'enable_themewizard':
                $default = '1';
                break;
            case 'seed':
                $default = '426aa9'; // Call find_theme_seed() for a better guess
                break;
            case 'supports_themewizard_equations':
                $default = '0';
                break;
            case 'themewizard_images':
                $_default = array(
                    'background_image',
                    'big_tabs/controller_button',
                    'big_tabs/controller_button_active',
                    'big_tabs/controller_button_top',
                    'big_tabs/controller_button_top_active',
                    'block_background',
                    'boxes/arrow',
                    'boxes/boxless_title_leadin_leftcomp',
                    'boxes/boxless_title_leadin_rightcomp',
                    'button1',
                    'button2',
                    'cns_emoticons/none',
                    'comcode_editor/*',
                    'edited',
                    'gradient',
                    'icons/arrow_box/arrow_box',
                    'icons/arrow_box/arrow_box_hover',
                    'icons/breadcrumbs',
                    'icons/carousel/button_left',
                    'icons/carousel/button_left_hover',
                    'icons/carousel/button_right',
                    'icons/carousel/button_right_hover',
                    'icons/checklist/checklist_done',
                    'icons/checklist/checklist_na',
                    'icons/checklist/checklist_todo',
                    'icons/cns_general/new_posts',
                    'icons/cns_general/no_new_posts',
                    'icons/cns_general/redirect',
                    'icons/cns_topic_modifiers/involved',
                    'icons/cns_topic_modifiers/unread',
                    'icons/help',
                    'icons/helper_panel/hide',
                    'icons/helper_panel/show',
                    'icons/media_set/next',
                    'icons/media_set/previous',
                    'icons/menus/menu_bullet',
                    'icons/menus/menu_bullet_current',
                    'icons/menus/menu_bullet_hover',
                    'icons/tool_buttons/top',
                    'icons/trays/contract',
                    'icons/trays/expand',
                    'icons/trays/expcon',
                    'icons/tree_field/category',
                    'inner_background',
                    'logo/-logo',
                    'logo/default_backgrounds/banner1',
                    'logo/default_backgrounds/banner3C',
                    'logo/default_backgrounds/banner8A',
                    'logo/default_logos/logo1',
                    'logo/default_logos/logo2',
                    'logo/default_logos/logo4',
                    'logo/default_logos/logo5',
                    'logo/default_logos/logo7',
                    'logo/standalone_logo',
                    'outer_background',
                    'perm_levels/*',
                    'poll/*',
                    'quote_gradient',
                    'tab',
                );
                $default = implode(',', $_default);
                break;
            case 'themewizard_images_no_wild':
                $default = '';
                break;

            // Logo Wizard ones
            case 'enable_logowizard':
                $default = '1';
                break;
            case 'logo_x_offset':
                $default = '0';
                break;
            case 'logo_y_offset':
                $default = '0';
                break;
            case 'site_name_colour':
                $default = 'FFFFFF';
                break;
            case 'site_name_split':
                $default = '425';
                break;
            case 'site_name_split_gap':
                $default = '6';
                break;
            case 'site_name_font_size_small':
                $default = '18';
                break;
            case 'site_name_font_size':
                $default = '26';
                break;
            case 'site_name_font_size_small_non_ttf':
                $default = '4';
                break;
            case 'site_name_font_size_nonttf':
                $default = '5';
                break;
            case 'site_name_x_offset':
                $default = '110';
                break;
            case 'site_name_y_offset':
                $default = '30';
                break;
            case 'site_name_y_offset_small':
                $default = '20';
                break;
        }
    }

    // Look at supplied $default
    if ($default !== null) {
        return $default;
    }

    // Look at config option
    return get_option($name, $missing_ok);
}

/**
 * Find the value of the specified configuration option.
 *
 * @param  ID_TEXT $name The name of the option
 * @param  boolean $missing_ok Where to accept a missing option (and return null)
 * @return ?SHORT_TEXT The value (null: either null value, or no option found while $missing_ok set)
 */
function get_option($name, $missing_ok = false)
{
    global $CONFIG_OPTIONS_CACHE, $CONFIG_OPTIONS_FULLY_LOADED, $SMART_CACHE;

    // Maybe missing a DB row, or has an old null one, so we need to auto-create from hook
    if (!isset($CONFIG_OPTIONS_CACHE[$name]['c_value'])) {
        if ((!$CONFIG_OPTIONS_FULLY_LOADED) && (!array_key_exists($name, $CONFIG_OPTIONS_CACHE))) {
            load_config_options();

            $value = get_option($name, $missing_ok);

            if ($value !== null) {
                global $SMART_CACHE;
                if ($SMART_CACHE !== null) {
                    $SMART_CACHE->append('CONFIG_OPTIONS', $name, $value);
                }
            }

            return $value;
        }

        if ((running_script('upgrader')) || (running_script('execute_temp'))) {
            $missing_ok = true; // Upgrade scenario, probably can't do this robustly
        }

        global $GET_OPTION_LOOP;
        $GET_OPTION_LOOP = true;

        require_code('config2');
        $value = get_default_option($name);

        if ($value === null) {
            if (!$missing_ok) {
                if (function_exists('do_lang')) {
                    trigger_error(do_lang('MISSING_OPTION', escape_html($name)), E_USER_NOTICE);
                } else {
                    critical_error('PASSON', 'Missing option: ' . $name);
                }
            }

            $GET_OPTION_LOOP = false;

            return null;
        }

        set_option($name, $value, 0);

        $GET_OPTION_LOOP = false;
    }

    // Load up row
    $option = &$CONFIG_OPTIONS_CACHE[$name];

    // The master of redundant quick exit points
    if (isset($option['_cached_string_value'])) {
        $value = $option['_cached_string_value'];

        if ($CONFIG_OPTIONS_FULLY_LOADED) {
            if ($SMART_CACHE !== null) {
                $SMART_CACHE->append('CONFIG_OPTIONS', $name, $value);
            }
        }

        return $value;
    }

    // Non-translated
    if (empty($option['c_needs_dereference'])) {
        $value = $option['c_value'];
        $option['_cached_string_value'] = $value; // Allows slightly better code path next time (see "The master of redundant quick exit points")

        if ($CONFIG_OPTIONS_FULLY_LOADED) {
            if ($SMART_CACHE !== null) {
                $SMART_CACHE->append('CONFIG_OPTIONS', $name, $value);
            }
        }

        return $value;
    }

    // Translated...
    $value = is_string($option['c_value_trans']) ? /*LEGACY*/get_translated_text(multi_lang_content() ? intval($option['c_value_trans']) : $option['c_value_trans']) : (($option['c_value_trans'] === null) ? '' : get_translated_text($option['c_value_trans']));
    $option['_cached_string_value'] = $value; // Allows slightly better code path next time (see "The master of redundant quick exit points")

    if ($CONFIG_OPTIONS_FULLY_LOADED) {
        if ($SMART_CACHE !== null) {
            $SMART_CACHE->append('CONFIG_OPTIONS', $name, $value);
        }
    }

    return $value;
}

/**
 * Find a specified value. Values are set with set_value.
 *
 * @param  ID_TEXT $name The name of the value
 * @param  ?ID_TEXT $default Value to return if value not found (null: return null)
 * @param  boolean $elective_or_lengthy Whether this value is an elective/lengthy one. Use this for getting & setting if you don't want it to be loaded up in advance for every page view (in bulk alongside other values), or if the value may be more than 255 characters. Performance tradeoff: frequently used values should not be elective, infrequently used values should be elective.
 * @param  boolean $env_also Whether to also check server environmental variables. Only use if $elective_or_lengthy is set to false
 * @return ?SHORT_TEXT The value (null: value not found and default is null)
 */
function get_value($name, $default = null, $elective_or_lengthy = false, $env_also = false)
{
    if ($elective_or_lengthy) {
        static $cache = array();
        if (!array_key_exists($name, $cache)) {
            if (!isset($GLOBALS['SITE_DB'])) {
                return null;
            }
            $cache[$name] = $GLOBALS['SITE_DB']->query_select_value_if_there('values_elective', 'the_value', array('the_name' => $name), '', running_script('install') || running_script('upgrader'));
        }
        return $cache[$name];
    }

    global $IN_MINIKERNEL_VERSION, $VALUE_OPTIONS_CACHE, $SMART_CACHE;

    if ($IN_MINIKERNEL_VERSION) {
        return $default;
    }

    if (isset($VALUE_OPTIONS_CACHE[$name])) {
        return $VALUE_OPTIONS_CACHE[$name]['the_value'];
    }

    if ($SMART_CACHE !== null) {
        $SMART_CACHE->append('VALUE_OPTIONS', $name); // Mark that we will need this in future, even if just null
    }

    global $VALUES_FULLY_LOADED;
    if (!$VALUES_FULLY_LOADED) {
        load_value_options();
        $ret = get_value($name, $default, $env_also);
        return $ret;
    }

    if ($env_also) {
        $value = getenv($name);
        if (($value !== false) && ($value != '')) {
            return $value;
        }
    }

    return $default;
}

/**
 * Find the specified configuration option if it is younger than a specified time.
 *
 * @param  ID_TEXT $name The name of the value
 * @param  TIME $cutoff The cutoff time (an absolute time, not a relative "time ago")
 * @param  boolean $elective_or_lengthy Whether this value is an elective/lengthy one. Use this for getting & setting if you don't want it to be loaded up in advance for every page view (in bulk alongside other values), or if the value may be more than 255 characters. Performance tradeoff: frequently used values should not be elective, infrequently used values should be elective.
 * @return ?SHORT_TEXT The value (null: value newer than not found)
 */
function get_value_newer_than($name, $cutoff, $elective_or_lengthy = false)
{
    if ($elective_or_lengthy) {
        return $GLOBALS['SITE_DB']->query_value_if_there('SELECT the_value FROM ' . $GLOBALS['SITE_DB']->get_table_prefix() . 'values_elective WHERE date_and_time>' . strval($cutoff) . ' AND ' . db_string_equal_to('the_name', $name));
    }

    global $VALUE_OPTIONS_CACHE, $SMART_CACHE;

    $cutoff -= mt_rand(0, 200); // Bit of scattering to stop locking issues if lots of requests hit this at once in the middle of a hit burst (whole table is read each page requests, and mysql will lock the table on set_value - causes horrible out-of-control buildups)

    if (isset($VALUE_OPTIONS_CACHE[$name])) {
        if ($VALUE_OPTIONS_CACHE[$name]['date_and_time'] > $cutoff) {
            return $VALUE_OPTIONS_CACHE[$name]['the_value'];
        }
        return null;
    }

    if ($SMART_CACHE !== null) {
        $SMART_CACHE->append('VALUE_OPTIONS', $name); // Mark that we will need this in future, even if just null
    }

    global $VALUES_FULLY_LOADED;
    if (!$VALUES_FULLY_LOADED) {
        load_value_options();
        $ret = get_value_newer_than($name, $cutoff);
        return $ret;
    }

    return null;
}

/**
 * Set the specified situational value to the specified value.
 *
 * @param  ID_TEXT $name The name of the value
 * @param  ?SHORT_TEXT $value The value (null: delete)
 * @param  boolean $elective_or_lengthy Whether this value is an elective/lengthy one. Use this for getting & setting if you don't want it to be loaded up in advance for every page view (in bulk alongside other values), or if the value may be more than 255 characters. Performance tradeoff: frequently used values should not be elective, infrequently used values should be elective.
 * @return SHORT_TEXT The value just set, same as $value (just as a niceity so that Commandr users can see something "happen")
 */
function set_value($name, $value, $elective_or_lengthy = false)
{
    if ($elective_or_lengthy) {
        $GLOBALS['SITE_DB']->query_delete('values_elective', array('the_name' => $name), '', 1);
        if ($value !== null) {
            $GLOBALS['SITE_DB']->query_insert('values_elective', array('date_and_time' => time(), 'the_value' => $value, 'the_name' => $name), false, true); // Allow failure, if there is a race condition
        }
        return $value;
    }

    global $VALUE_OPTIONS_CACHE;
    $existed_before = isset($VALUE_OPTIONS_CACHE[$name]);
    $VALUE_OPTIONS_CACHE[$name]['the_value'] = $value;
    $VALUE_OPTIONS_CACHE[$name]['date_and_time'] = time();
    if ($existed_before) {
        $GLOBALS['SITE_DB']->query_update('values', array('date_and_time' => time(), 'the_value' => $value), array('the_name' => $name), '', 1, 0, false, true); // Errors suppressed in case DB write access broken
    } else {
        $GLOBALS['SITE_DB']->query_insert('values', array('date_and_time' => time(), 'the_value' => $value, 'the_name' => $name), false, true); // Allow failure, if there is a race condition
    }
    if (function_exists('persistent_cache_set')) {
        persistent_cache_set('VALUES', $VALUE_OPTIONS_CACHE);
    }
    return $value;
}

/**
 * Delete a situational value.
 *
 * @param  ID_TEXT $name The name of the value
 * @param  boolean $elective_or_lengthy Whether this value is an elective/lengthy one. Use this for getting & setting if you don't want it to be loaded up in advance for every page view (in bulk alongside other values), or if the value may be more than 255 characters. Performance tradeoff: frequently used values should not be elective, infrequently used values should be elective.
 */
function delete_value($name, $elective_or_lengthy = false)
{
    if ($elective_or_lengthy) {
        $GLOBALS['SITE_DB']->query_delete('values_elective', array('the_name' => $name), '', 1);
        return;
    }

    $GLOBALS['SITE_DB']->query_delete('values', array('the_name' => $name), '', 1);
    if (function_exists('persistent_cache_delete')) {
        persistent_cache_delete('VALUES');
    }
    global $VALUE_OPTIONS_CACHE;
    unset($VALUE_OPTIONS_CACHE[$name]);
}

/**
 * Delete situational values.
 *
 * @param  array $values List of names of the values
 */
function delete_values($values)
{
    if ($values === array()) {
        return;
    }
    global $VALUE_OPTIONS_CACHE;
    $sql = 'DELETE FROM ' . get_table_prefix() . 'values WHERE 1=0';
    foreach ($values as $name) {
        $sql .= ' OR ' . db_string_equal_to('the_name', $name);
        unset($VALUE_OPTIONS_CACHE[$name]);
    }
    $GLOBALS['SITE_DB']->query($sql);
    if (function_exists('persistent_cache_delete')) {
        persistent_cache_delete('VALUES');
    }
}

/**
 * Increment the specified stored value, by the specified amount.
 *
 * @param  ID_TEXT $stat The codename for the stat
 * @param  integer $increment What to increment the statistic by
 */
function update_stat($stat, $increment)
{
    if (running_script('stress_test_loader')) {
        return;
    }

    $current = get_value($stat);
    if ($current === null) {
        $current = '0';
    }
    $new = intval($current) + $increment;
    set_value($stat, strval($new));
}

/**
 * Very simple function to invert the meaning of an old hidden option. We often use this when we've promoted a hidden option into a new proper option but inverted the meaning in the process - we use this in the default value generation code, as an in-line aid to preserve existing hidden option settings.
 *
 * @param  ID_TEXT $old The old value
 * @set 0 1
 * @return ID_TEXT The inverted value
 */
function invert_value($old)
{
    if ($old == '1') {
        return '0';
    }
    return '1';
}
