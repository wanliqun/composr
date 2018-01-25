<?php /*

 Composr
 Copyright (c) ocProducts, 2004-2018

 See text/EN/licence.txt for full licencing information.


 NOTE TO PROGRAMMERS:
   Do not edit this file. If you need to make changes, save your changed file to the appropriate *_custom folder
   **** If you ignore this advice, then your website upgrades (e.g. for bug fixes) will likely kill your changes ****

*/

/**
 * @license    http://opensource.org/licenses/cpal_1.0 Common Public Attribution License
 * @copyright  ocProducts Ltd
 * @package    core_notifications
 */

/**
 * Get a map of notification types available to our member.
 *
 * @param  ?MEMBER $member_id_of Member this is for (null: just check globally)
 * @return array Map of notification types (integer code to language string ID)
 * @ignore
 */
function _get_available_notification_types($member_id_of = null)
{
    $__notification_types = array(
        A_INSTANT_EMAIL => 'INSTANT_EMAIL',
        A_INSTANT_PT => 'INSTANT_PT',
        A_INSTANT_SMS => 'INSTANT_SMS',
        A_DAILY_EMAIL_DIGEST => 'DAILY_EMAIL_DIGEST',
        A_WEEKLY_EMAIL_DIGEST => 'WEEKLY_EMAIL_DIGEST',
        A_MONTHLY_EMAIL_DIGEST => 'MONTHLY_EMAIL_DIGEST',
        A_WEB_NOTIFICATION => 'WEB_NOTIFICATION',
    );
    $_notification_types = array();
    foreach ($__notification_types as $possible => $ntype) {
        if (_notification_setting_available($possible, $member_id_of)) {
            $_notification_types[$possible] = $ntype;
        }
    }

    global $HOOKS_NOTIFICATION_TYPES_EXTENDED;
    foreach ($HOOKS_NOTIFICATION_TYPES_EXTENDED as $hook => $ob) {
        $_notification_types += $ob->_get_available_notification_types($member_id_of);
    }

    return $_notification_types;
}

/**
 * Put out a user interface for managing notifications overall.
 *
 * @param  MEMBER $member_id_of Member this is for
 * @return Tempcode UI
 */
function notifications_ui($member_id_of)
{
    push_query_limiting(false);

    require_css('notifications');
    require_code('notifications');
    require_lang('notifications');
    require_all_lang();

    if (is_guest($member_id_of)) {
        access_denied('NOT_AS_GUEST');
    }

    // UI fields
    $fields = new Tempcode();

    $_notification_types = _get_available_notification_types($member_id_of);
    if (count($_notification_types) == 0) {
        return new Tempcode();
    }

    $lockdown = collapse_2d_complexity('l_notification_code', 'l_setting', $GLOBALS['SITE_DB']->query_select('notification_lockdown', array('*')));

    $has_interesting_post_fields = has_interesting_post_fields();

    $notification_sections = array();
    $hooks = find_all_hooks('systems', 'notifications');
    foreach (array_keys($hooks) as $hook) {
        if (array_key_exists($hook, $lockdown)) {
            continue;
        }

        if ((substr($hook, 0, 4) == 'cns_') && (get_forum_type() != 'cns')) {
            continue;
        }
        require_code('hooks/systems/notifications/' . filter_naughty_harsh($hook));
        $ob = object_factory('Hook_notification_' . filter_naughty_harsh($hook));
        $_notification_codes = $ob->list_handled_codes();
        foreach ($_notification_codes as $notification_code => $notification_details) {
            if (array_key_exists($notification_code, $lockdown)) {
                continue;
            }

            if ($ob->member_could_potentially_enable($notification_code, $member_id_of)) {
                $current_setting = notifications_setting($notification_code, null, $member_id_of);
                if ($current_setting == A__STATISTICAL) {
                    $current_setting = _find_member_statistical_notification_type($member_id_of, $notification_code);
                }
                $allowed_setting = $ob->allowed_settings($notification_code);

                $supports_categories = $ob->supports_categories($notification_code);

                if ($supports_categories) {
                    $if_there_query = 'SELECT l_setting FROM ' . get_table_prefix() . 'notifications_enabled WHERE l_member_id=' . strval($member_id_of) . ' AND ' . db_string_equal_to('l_notification_code', $notification_code) . ' AND ' . db_string_not_equal_to('l_code_category', '');
                    $is_there_test = $GLOBALS['SITE_DB']->query($if_there_query);
                }

                $notification_types = array();
                foreach ($_notification_types as $possible => $ntype) {
                    $available = (($possible & $allowed_setting) != 0);

                    if ($has_interesting_post_fields) {
                        $checked = post_param_integer('notification_' . $notification_code . '_' . $ntype, 0);
                    } else {
                        $checked = (($possible & $current_setting) != 0) ? 1 : 0;
                    }

                    $type_has_children_set = false;
                    if (($supports_categories) && ($available)) {
                        foreach ($is_there_test as $_is) {
                            if (($_is['l_setting'] & $possible) != 0) {
                                $type_has_children_set = true;
                            }
                        }
                    }

                    $notification_types[] = array(
                        'NTYPE' => $ntype,
                        'LABEL' => do_lang_tempcode('ENABLE_NOTIFICATIONS_' . $ntype),
                        'CHECKED' => ($checked == 1),
                        'RAW' => strval($possible),
                        'AVAILABLE' => $available,
                        'SCOPE' => $notification_code,
                        'TYPE_HAS_CHILDREN_SET' => $type_has_children_set,
                    );
                }

                if (!isset($notification_sections[$notification_details[0]])) {
                    $notification_sections[$notification_details[0]] = array(
                        'NOTIFICATION_SECTION' => $notification_details[0],
                        'NOTIFICATION_CODES' => array(),
                    );
                }
                $notification_sections[$notification_details[0]]['NOTIFICATION_CODES'][] = array(
                    'NOTIFICATION_CODE' => $notification_code,
                    'NOTIFICATION_LABEL' => $notification_details[1],
                    'NOTIFICATION_TYPES' => $notification_types,
                    'SUPPORTS_CATEGORIES' => $supports_categories,
                );
            }
        }
    }
    if (count($notification_sections) == 0) {
        return new Tempcode();
    }

    // Sort labels
    ksort($notification_sections, SORT_NATURAL | SORT_FLAG_CASE);
    foreach (array_keys($notification_sections) as $i) {
        sort_maps_by($notification_sections[$i]['NOTIFICATION_CODES'], 'NOTIFICATION_LABEL');
    }

    // Save via form post
    if (has_interesting_post_fields()) {
        foreach ($notification_sections as $notification_section) {
            foreach ($notification_section['NOTIFICATION_CODES'] as $notification_code) {
                $new_setting = A_NA;
                foreach ($notification_code['NOTIFICATION_TYPES'] as $notification_type) {
                    $ntype = $notification_type['NTYPE'];
                    if (post_param_integer('notification_' . $notification_code['NOTIFICATION_CODE'] . '_' . $ntype, 0) == 1) {
                        $new_setting = $new_setting | intval($notification_type['RAW']);
                    }
                }
                enable_notifications($notification_code['NOTIFICATION_CODE'], null, $member_id_of, $new_setting);
            }
        }
    }

    // Main UI...

    $notification_types_titles = array();
    foreach ($_notification_types as $possible => $ntype) {
        $notification_types_titles[] = array(
            'NTYPE' => $ntype,
            'LABEL' => do_lang_tempcode('ENABLE_NOTIFICATIONS_' . $ntype),
            'RAW' => strval($possible),
        );
    }

    $css_path = get_custom_file_base() . '/themes/' . $GLOBALS['FORUM_DRIVER']->get_theme() . '/templates_cached/' . user_lang() . '/global.css';
    $color = 'FF00FF';
    if (file_exists($css_path)) {
        $tmp_file = cms_file_get_contents_safe($css_path);
        $matches = array();
        if (preg_match('#(\s|\})th[\s,][^\}]*(\s|\{)background-color:\s*\#([\dA-Fa-f]*);color:\s*\#([\dA-Fa-f]*);#sU', $tmp_file, $matches) != 0) {
            $color = $matches[3] . '&fg_color=' . urlencode($matches[4]);
        }
    }

    $auto_monitor_contrib_content = null;
    if (get_forum_type() == 'cns') {
        $auto_monitor_contrib_content = strval($GLOBALS['FORUM_DRIVER']->get_member_row_field($member_id_of, 'm_auto_monitor_contrib_content'));
    }

    $custom_fields = $GLOBALS['FORUM_DRIVER']->get_custom_fields($member_id_of);
    $smart_topic_notification_content = (array_key_exists('smart_topic_notification', $custom_fields)) && ($custom_fields['smart_topic_notification'] == '1');

    return do_template('NOTIFICATIONS_MANAGE', array(
        '_GUID' => '838165ca739c45c2dcf994bed6fefe3e',
        'COLOR' => $color,
        'AUTO_NOTIFICATION_CONTRIB_CONTENT' => $auto_monitor_contrib_content,
        'NOTIFICATION_TYPES_TITLES' => $notification_types_titles,
        'NOTIFICATION_SECTIONS' => $notification_sections,
        'SMART_TOPIC_NOTIFICATION_CONTENT' => $smart_topic_notification_content,
        'MEMBER_ID' => strval($member_id_of),
        'ADVANCED_COLUMN' => true,
    ));
}

/**
 * Put out a user interface for managing notifications for a notification-category supporting content type. Also toggle notifications if an ID is passed.
 *
 * @param  ID_TEXT $notification_code The notification code to work with
 * @param  ?Tempcode $enable_message Special message to output if we have toggled to enable (null: use standard)
 * @param  ?Tempcode $disable_message Special message to output if we have toggled to disable (null: use standard)
 * @return Tempcode UI
 */
function notifications_ui_advanced($notification_code, $enable_message = null, $disable_message = null)
{
    require_css('notifications');
    require_code('notifications');
    require_lang('notifications');
    require_javascript('core_notifications');
    require_all_lang();

    $test = $GLOBALS['SITE_DB']->query_select_value_if_there('notification_lockdown', 'l_setting', array(
        'l_notification_code' => substr($notification_code, 0, 80),
    ));
    if ($test !== null) {
        warn_exit(do_lang_tempcode('NOTIFICATION_CODE_LOCKED_DOWN'));
    }

    $ob = _get_notification_ob_for_code($notification_code);
    if ($ob === null) {
        warn_exit(do_lang_tempcode('INTERNAL_ERROR'));
    }
    $info_details = $ob->list_handled_codes();

    $title = get_screen_title('NOTIFICATION_MANAGEMENT_FOR', true, array(escape_html($info_details[$notification_code][1])));

    if (is_guest()) {
        access_denied('NOT_AS_GUEST');
    }

    $db = (substr($notification_code, 0, 4) == 'cns_') ? $GLOBALS['FORUM_DB'] : $GLOBALS['SITE_DB'];

    if ($enable_message === null) {
        $enable_message = do_lang_tempcode('NOW_ENABLED_NOTIFICATIONS');
    }
    if ($disable_message === null) {
        $disable_message = do_lang_tempcode('NOW_DISABLED_NOTIFICATIONS');
    }

    $_notification_types = _get_available_notification_types(get_member());

    $notification_category = get_param_string('id', null);
    if ($notification_category === null) {
        if (has_interesting_post_fields()) { // If we've just saved via form POST
            enable_notifications($notification_code, null, null, A_NA); // Make it clear we've overridden the general value by doing this

            foreach (array_keys($_POST) as $key) {
                $matches = array();
                if (preg_match('#^notification_' . preg_quote($notification_code) . '_category_(.*)#', $key, $matches) != 0) {
                    $notification_category = $matches[1];

                    $new_setting = A_NA;
                    foreach ($_notification_types as $possible => $ntype) {
                        if (post_param_integer('notification_' . $notification_category . '_' . $ntype, 0) == 1) {
                            $new_setting = $new_setting | $possible;
                        }
                    }

                    enable_notifications($notification_code, $notification_category, null, $new_setting);
                }
            }

            attach_message(do_lang_tempcode('SUCCESS'), 'inform');

            // Redirect them back
            $redirect = get_param_string('redirect', '', INPUT_FILTER_URL_INTERNAL);
            if ($redirect != '') {
                return redirect_screen($title, $redirect, do_lang_tempcode('SUCCESS'));
            }
        }
    } else {
        // Put in content title to message
        $_tree = $ob->create_category_tree($notification_code, $notification_category); // Save via GET may happen within here
        foreach ($_tree as $tree_pos) {
            $_notification_category = (is_integer($tree_pos['id']) ? strval($tree_pos['id']) : $tree_pos['id']);

            if ($_notification_category == $notification_category) {
                $disable_message = protect_from_escaping(str_replace('{1}', escape_html($tree_pos['title']), $disable_message->evaluate()));
                $enable_message = protect_from_escaping(str_replace('{1}', escape_html($tree_pos['title']), $enable_message->evaluate()));
                break;
            }
        }
        $disable_message = protect_from_escaping(str_replace('{1}', do_lang('UNKNOWN'), $disable_message->evaluate()));
        $enable_message = protect_from_escaping(str_replace('{1}', do_lang('UNKNOWN'), $enable_message->evaluate()));

        if (notifications_enabled($notification_code, $notification_category)) {
            attach_message($disable_message, 'warn');
        } else {
            attach_message($enable_message, 'inform');
        }
    }

    $done_get_change = false;
    $tree = _notifications_build_category_tree($_notification_types, $notification_code, $ob, null, 0, null, $done_get_change);
    $notification_category_being_changed = get_param_string('id', null);
    if ($notification_category_being_changed !== null && !$done_get_change) {
        // The tree has been pruned due to over-sizeness issue (too much content to list), so we have to set a notification here rather than during render.
        enable_notifications($notification_code, $notification_category_being_changed);

        // Re-render too
        $tree = _notifications_build_category_tree($_notification_types, $notification_code, $ob, null, 0, null, $done_get_change);
    }

    $notification_types_titles = array();
    foreach ($_notification_types as $possible => $ntype) {
        $notification_types_titles[] = array(
            'NTYPE' => $ntype,
            'LABEL' => do_lang_tempcode('ENABLE_NOTIFICATIONS_' . $ntype),
            'RAW' => strval($possible),
        );
    }

    $css_path = get_custom_file_base() . '/themes/' . $GLOBALS['FORUM_DRIVER']->get_theme() . '/templates_cached/' . user_lang() . '/global.css';
    $color = 'FF00FF';
    if (file_exists($css_path)) {
        $tmp_file = cms_file_get_contents_safe($css_path);
        $matches = array();
        if (preg_match('#(\s|\})th[\s,][^\}]*(\s|\{)background-color:\s*\#([\dA-Fa-f]*);color:\s*\#([\dA-Fa-f]*);#sU', $tmp_file, $matches) != 0) {
            $color = $matches[3] . '&fg_color=' . urlencode($matches[4]);
        }
    }

    return do_template('NOTIFICATIONS_MANAGE_ADVANCED_SCREEN', array(
        '_GUID' => '21337e54cc87d82269bec89e70690543',
        'TITLE' => $title,
        '_TITLE' => $info_details[$notification_code][1],
        'COLOR' => $color,
        'ACTION_URL' => get_self_url(false, false, array('id' => null)),
        'NOTIFICATION_TYPES_TITLES' => $notification_types_titles,
        'TREE' => $tree,
        'NOTIFICATION_CODE' => $notification_code,
    ));
}

/**
 * Build a tree UI for all categories available.
 *
 * @param  array $_notification_types Notification types
 * @param  ID_TEXT $notification_code The notification code to work with
 * @param  object $ob Notificiation hook object
 * @param  ?ID_TEXT $id Category we're looking under (null: root)
 * @param  integer $depth Recursion depth
 * @param  ?boolean $force_change_children_to Value to change setting to (null: do not change)
 * @param  boolean $done_get_change Whether we have made a change to the settings
 * @return Tempcode UI
 *
 * @ignore
 */
function _notifications_build_category_tree($_notification_types, $notification_code, $ob, $id, $depth, $force_change_children_to, &$done_get_change)
{
    $_notification_categories = $ob->create_category_tree($notification_code, $id);

    $notification_categories = array();
    foreach ($_notification_categories as $c) {
        $notification_category = (is_integer($c['id']) ? strval($c['id']) : $c['id']);

        $current_setting = notifications_setting($notification_code, $notification_category);
        if ($current_setting == A__STATISTICAL) {
            $current_setting = _find_member_statistical_notification_type(get_member(), $notification_code);
        }

        $notification_category_being_changed = get_param_string('id', null);
        if (($notification_category_being_changed === $notification_category) || ($force_change_children_to !== null)) {
            if (!$done_get_change) {
                if (($force_change_children_to === false/*If recursively disabling*/) || (($force_change_children_to === null) && ($current_setting != A_NA)/*If explicitly toggling this one to disabled*/)) {
                    enable_notifications($notification_code, $notification_category, null, A_NA);
                    $force_change_children_to_children = false;
                } else {
                    enable_notifications($notification_code, $notification_category);
                    $force_change_children_to_children = true;
                }

                $done_get_change = true;
            } else {
                $force_change_children_to_children = false;
            }
        } else {
            $force_change_children_to_children = $force_change_children_to;
        }

        $current_setting = notifications_setting($notification_code, $notification_category);
        if ($current_setting == A__STATISTICAL) {
            $current_setting = _find_member_statistical_notification_type(get_member(), $notification_code);
        }

        $notification_types = array();
        foreach ($_notification_types as $possible => $ntype) {
            $current_setting = notifications_setting($notification_code, $notification_category);
            if ($current_setting == A__STATISTICAL) {
                $current_setting = _find_member_statistical_notification_type(get_member(), $notification_code);
            }
            $allowed_setting = $ob->allowed_settings($notification_code);

            $available = (($possible & $allowed_setting) != 0);

            if (has_interesting_post_fields()) {
                $checked = post_param_integer('notification_' . $notification_category . '_' . $ntype, 0);
            } else {
                $checked = (($possible & $current_setting) != 0) ? 1 : 0;
            }

            $notification_types[] = array(
                'NTYPE' => $ntype,
                'LABEL' => do_lang_tempcode('ENABLE_NOTIFICATIONS_' . $ntype),
                'CHECKED' => ($checked == 1),
                'RAW' => strval($possible),
                'AVAILABLE' => $available,
                'SCOPE' => $notification_category,
            );
        }

        if ((!array_key_exists('num_children', $c)) && (array_key_exists('child_count', $c))) {
            $c['num_children'] = $c['child_count'];
        }
        if ((!array_key_exists('num_children', $c)) && (array_key_exists('children', $c))) {
            $c['num_children'] = count($c['children']);
        }
        $children = new Tempcode();
        if ((array_key_exists('num_children', $c)) && ($c['num_children'] != 0)) {
            $children = _notifications_build_category_tree($_notification_types, $notification_code, $ob, $notification_category, $depth + 1, $force_change_children_to_children, $done_get_change);
        }

        $notification_categories[] = array(
            'NUM_CHILDREN' => strval(array_key_exists('num_children', $c) ? $c['num_children'] : 0),
            'DEPTH' => strval($depth),
            'NOTIFICATION_CATEGORY' => $notification_category,
            'NOTIFICATION_TYPES' => $notification_types,
            'CATEGORY_TITLE' => $c['title'],
            'CHECKED' => notifications_enabled($notification_code, $notification_category),
            'CHILDREN' => $children,
        );
    }

    $tree = do_template('NOTIFICATIONS_TREE', array(
        '_GUID' => 'a370837b5ffb3d80989a34ad2a71b6c1',
        'NOTIFICATION_CODE' => $notification_code,
        'NOTIFICATION_CATEGORIES' => $notification_categories,
    ));

    return $tree;
}

/**
 * Copy notification settings from a parent category to a child category.
 *
 * @param  ID_TEXT $notification_code Parent category type
 * @param  ID_TEXT $id Parent category ID
 * @param  ID_TEXT $child_id Child category ID
 */
function copy_notifications_to_new_child($notification_code, $id, $child_id)
{
    // Copy notifications over to new children
    $_start = 0;
    do {
        $notifications_to = $GLOBALS['SITE_DB']->query_select('notifications_enabled', array('l_member_id', 'l_setting'), array('l_notification_code' => substr($notification_code, 0, 80), 'l_code_category' => $id), '', 100, $_start);

        foreach ($notifications_to as $notification_to) {
            $GLOBALS['SITE_DB']->query_insert('notifications_enabled', array(
                'l_member_id' => $notification_to['l_member_id'],
                'l_notification_code' => substr($notification_code, 0, 80),
                'l_code_category' => $child_id,
                'l_setting' => $notification_to['l_setting'],
            ));
        }

        $_start += 100;
    } while (count($notifications_to) != 0);
}
