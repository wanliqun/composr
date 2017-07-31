<?php /*

 Composr
 Copyright (c) ocProducts, 2004-2017

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
function init__content2()
{
    if (!defined('METADATA_HEADER_NO')) {
        define('METADATA_HEADER_NO', 0);
        define('METADATA_HEADER_YES', 1);
        define('METADATA_HEADER_FORCE', 2);

        define('ORDER_AUTOMATED_CRITERIA', 2147483647); // lowest order, shared for all who care not about order, so other SQL ordering criterias take precedence
    }
}

/**
 * Define page metadata.
 * This function is intended for programmers, writing upgrade scripts for a custom site (dev>staging>live).
 *
 * @param  array $page_metadata Page metadata for multiple pages (see function code for an example; description and keywords go to SEO metadata, rest goes to custom fields which will auto-create as needed)
 * @param  string $zone The zone to do this in
 */
function define_page_metadata($page_metadata, $zone = '')
{
    /*
        CALLING SAMPLE:

        $page_metadata = array(
            'start' => array(
                'Title' => 'Page title goes here',
                'description' => 'Page description goes here.',
                'keywords' => 'page, keywords, go here',
            ),
        );
        define_page_metadata($page_metadata);
    }
    */

    foreach ($page_metadata as $page_name => $metadata) {
        $catalogue_entry_id = null;
        $order = 0;

        foreach ($metadata as $key => $val) {
            if (in_array($key, array('description', 'keywords'))) {
                continue;
            }

            require_code('fields');

            if ($catalogue_entry_id === null) {
                $catalogue_entry_id = get_bound_content_entry_wrap('comcode_page', ':' . $page_name);
            }

            $field_id = define_custom_field($key, '', $order);
            $order++;

            $GLOBALS['SITE_DB']->query_delete('catalogue_efv_short', array('cf_id' => $field_id, 'ce_id' => $catalogue_entry_id));
            $GLOBALS['SITE_DB']->query_insert('catalogue_efv_short', array('cf_id' => $field_id, 'ce_id' => $catalogue_entry_id, 'cv_value' => $val));
        }

        seo_meta_set_for_explicit('comcode_page', ':' . $page_name, isset($metadata['keywords']) ? $metadata['keywords'] : '', isset($metadata['description']) ? $metadata['description'] : '');
    }
}

/**
 * Get an order inputter.
 *
 * @param  ID_TEXT $entry_type The type of resource being ordered
 * @param  ?ID_TEXT $category_type The type of resource being ordered within (null: no categories involved)
 * @param  ?integer $current_order The current order (null: new, so add to end)
 * @param  ?integer $max Maximum order field (null: work out from content type metadata)
 * @param  ?integer $total Number of entries, alternative to supplying $max (null: work out from content type metadata)
 * @param  ID_TEXT $order_field The POST field to save under
 * @param  ?Tempcode $description Description for field input (null: {!ORDER})
 * @return Tempcode Ordering field
 */
function get_order_field($entry_type, $category_type, $current_order, $max = null, $total = null, $order_field = 'order', $description = null)
{
    $new = ($current_order === null);

    $min = 0;

    require_code('content');
    $ob = get_content_object($entry_type);
    $info = $ob->info();

    $db_order_field = isset($info['order_field']) ? $info['order_field'] : 'order';

    if ($max === null) {
        $max = $info['db']->query_value_if_there('SELECT MAX(' . $db_order_field . ') FROM ' . $info['db']->get_table_prefix() . $info['table'] . ' WHERE ' . $db_order_field . '<>' . strval(ORDER_AUTOMATED_CRITERIA));
        if ($max === null) {
            $max = 0;
        }
    }

    if ($total === null) {
        $total = $info['db']->query_select_value($info['table'], 'COUNT(*)');
    }

    if ($total > $max) {
        // Need to make sure there's always enough slots to pick from
        $max = $total - 1;
    }

    if ($new) {
        $test = $info['db']->query_value_if_there('SELECT COUNT(' . $db_order_field . ') FROM ' . $info['db']->get_table_prefix() . $info['table'] . ' WHERE ' . $db_order_field . '=' . strval(ORDER_AUTOMATED_CRITERIA));

        if ($test > 0) {
            $current_order = ORDER_AUTOMATED_CRITERIA; // Ah, we are already in the habit of automated ordering here
        } else {
            $max++; // Space for new one on end
            $current_order = $max;
        }
    }

    if ($description === null) {
        if ($category_type === null) {
            $description = do_lang_tempcode('DESCRIPTION_ORDER_NO_CATS', escape_html($entry_type));
        } else {
            $description = do_lang_tempcode('DESCRIPTION_ORDER', escape_html($entry_type), escape_html($category_type));
        }
    }

    if ($max > 100) {
        // Too much for a list, so do a typed integer input
        return form_input_integer(do_lang_tempcode('ORDER'), $description, $order_field, $current_order, false);
    }

    // List input
    $order_list = new Tempcode();
    for ($i = $min; $i <= $max; $i++) {
        $selected = ($i === $current_order);
        $order_list->attach(form_input_list_entry(strval($i), $selected, integer_format($i + 1)));
    }
    $order_list->attach(form_input_list_entry('', $current_order == ORDER_AUTOMATED_CRITERIA, do_lang_tempcode('ORDER_AUTOMATED_CRITERIA')));
    return form_input_list(do_lang_tempcode('ORDER'), $description, $order_field, $order_list, null, false, false);
}

/**
 * Get submitted order value.
 *
 * @param  ID_TEXT $order_field The POST field
 * @return integer The order value
 */
function post_param_order_field($order_field = 'order')
{
    $ret = post_param_integer($order_field, null);
    if ($ret === null) {
        $ret = ORDER_AUTOMATED_CRITERIA;
    }
    return $ret;
}

/**
 * Get template fields to insert into a form page, for manipulation of metadata.
 *
 * @param  ID_TEXT $content_type The type of resource (e.g. download)
 * @param  ?ID_TEXT $content_id The ID of the resource (null: adding)
 * @param  boolean $allow_no_owner Whether to allow owner to be left blank (meaning no owner)
 * @param  array $fields_to_skip List of fields to NOT take in
 * @param  integer $show_header Whether to show a header (a METADATA_HEADER_* constant)
 * @return Tempcode Form page Tempcode fragment
 */
function metadata_get_fields($content_type, $content_id, $allow_no_owner = false, $fields_to_skip = array(), $show_header = 1)
{
    require_lang('metadata');

    $fields = new Tempcode();

    if (has_privilege(get_member(), 'edit_meta_fields')) {
        require_code('content');
        $ob = get_content_object($content_type);
        $info = $ob->info();

        require_code('content');
        $content_row = mixed();
        if ($content_id !== null) {
            list(, , , $content_row) = content_get_details($content_type, $content_id);
        }

        $views_field = in_array('views', $fields_to_skip) ? null : $info['views_field'];
        if ($views_field !== null) {
            $views = ($content_row === null) ? 0 : $content_row[$views_field];
            $fields->attach(form_input_integer(do_lang_tempcode('COUNT_VIEWS'), do_lang_tempcode('DESCRIPTION_META_VIEWS'), 'meta_views', null, false));
        }

        $submitter_field = in_array('submitter', $fields_to_skip) ? null : $info['submitter_field'];
        if ($submitter_field !== null) {
            $submitter = ($content_row === null) ? get_member() : $content_row[$submitter_field];
            $username = $GLOBALS['FORUM_DRIVER']->get_username($submitter, false, USERNAME_DEFAULT_NULL);
            if ($username === null) {
                $username = $GLOBALS['FORUM_DRIVER']->get_username(get_member());
            }
            $fields->attach(form_input_username(do_lang_tempcode('OWNER'), do_lang_tempcode('DESCRIPTION_OWNER'), 'meta_submitter', $username, !$allow_no_owner));
        }

        $add_time_field = in_array('add_time', $fields_to_skip) ? null : $info['add_time_field'];
        if ($add_time_field !== null) {
            $add_time = ($content_row === null) ? null : $content_row[$add_time_field];
            $fields->attach(form_input_date(do_lang_tempcode('ADD_TIME'), do_lang_tempcode('DESCRIPTION_META_ADD_TIME'), 'meta_add_time', $content_row !== null, ($content_row === null), true, $add_time, 40, intval(date('Y')) - 20, null));
        }

        if ($content_id !== null) {
            $edit_time_field = in_array('edit_time', $fields_to_skip) ? null : $info['edit_time_field'];
            if ($edit_time_field !== null) {
                $edit_time = ($content_row === null) ? null : (($content_row[$edit_time_field] === null) ? time() : max(time(), $content_row[$edit_time_field]));
                $fields->attach(form_input_date(do_lang_tempcode('EDIT_TIME'), do_lang_tempcode('DESCRIPTION_META_EDIT_TIME'), 'meta_edit_time', false, ($edit_time === null), true, $edit_time, 10, null, null));
            }
        }

        if (($info['support_url_monikers']) && (!in_array('url_moniker', $fields_to_skip))) {
            $url_moniker = mixed();
            if ($content_id !== null) {
                if ($content_type == 'comcode_page') {
                    list($zone, $_content_id) = explode(':', $content_id);
                    $attributes = array();
                    $url_moniker = find_id_moniker(array('page' => $_content_id) + $attributes, $zone);
                } else {
                    $_content_id = $content_id;
                    list($zone, $attributes,) = page_link_decode($info['view_page_link_pattern']);
                    $url_moniker = find_id_moniker(array('id' => $_content_id) + $attributes, $zone);
                }

                if ($url_moniker === null) {
                    $url_moniker = '';
                }

                $moniker_where = array(
                    'm_manually_chosen' => 1,
                    'm_resource_page' => ($content_type == 'comcode_page') ? $_content_id : $attributes['page'],
                    'm_resource_type' => ($content_type == 'comcode_page') ? '' : (isset($attributes['type']) ? $attributes['type'] : ''),
                    'm_resource_id' => ($content_type == 'comcode_page') ? $zone : $_content_id,
                );
                $manually_chosen = ($GLOBALS['SITE_DB']->query_select_value_if_there('url_id_monikers', 'm_moniker', $moniker_where) !== null);
            } else {
                $url_moniker = '';
                $manually_chosen = false;
            }
            $fields->attach(form_input_codename(do_lang_tempcode('URL_MONIKER'), do_lang_tempcode('DESCRIPTION_META_URL_MONIKER', escape_html($url_moniker)), 'meta_url_moniker', $manually_chosen ? $url_moniker : '', false, null, null, array('/')));
        }
    } else {
        if ($show_header != METADATA_HEADER_FORCE) {
            return new Tempcode();
        }
    }

    if ((!$fields->is_empty()) && ($show_header != METADATA_HEADER_NO)) {
        $_fields = new Tempcode();
        $_fields->attach(do_template('FORM_SCREEN_FIELD_SPACER', array(
            '_GUID' => 'adf2a2cda231619243763ddbd0cc9d4e',
            'SECTION_HIDDEN' => true,
            'TITLE' => do_lang_tempcode('METADATA'),
            'HELP' => do_lang_tempcode('DESCRIPTION_METADATA', ($content_id === null) ? do_lang_tempcode('RESOURCE_NEW') : $content_id),
        )));
        $_fields->attach($fields);
        return $_fields;
    }

    return $fields;
}

/**
 * Get field values for metadata.
 *
 * @param  ID_TEXT $content_type The type of resource (e.g. download)
 * @param  ?ID_TEXT $content_id The old ID of the resource (null: adding)
 * @param  array $fields_to_skip List of fields to NOT take in
 * @param  ?ID_TEXT $new_content_id The new ID of the resource (null: not being renamed)
 * @return array A map of standard metadata fields (name to value). If adding, this map is accurate for adding. If editing, nulls mean do-not-edit or non-editable.
 */
function actual_metadata_get_fields($content_type, $content_id, $fields_to_skip = array(), $new_content_id = null)
{
    require_lang('metadata');

    if (fractional_edit()) {
        return array(
            'views' => INTEGER_MAGIC_NULL,
            'submitter' => INTEGER_MAGIC_NULL,
            'add_time' => INTEGER_MAGIC_NULL,
            'edit_time' => INTEGER_MAGIC_NULL,
            /*'url_moniker' => null, was handled internally*/
        );
    }

    if (!has_privilege(get_member(), 'edit_meta_fields')) { // Pass through as how an edit would normally function (things left alone except edit time)
        return array(
            'views' => ($content_id === null) ? 0 : INTEGER_MAGIC_NULL,
            'submitter' => ($content_id === null) ? get_member() : INTEGER_MAGIC_NULL,
            'add_time' => ($content_id === null) ? time() : INTEGER_MAGIC_NULL,
            'edit_time' => time(),
            /*'url_moniker' => null, was handled internally*/
        );
    }

    require_code('content');
    $ob = get_content_object($content_type);
    $info = $ob->info();

    $views = mixed();
    $views_field = in_array('views', $fields_to_skip) ? null : $info['views_field'];
    if ($views_field !== null) {
        $views = post_param_integer('meta_views', null);
        if ($views === null) {
            if ($content_id === null) {
                $views = 0;
            } else {
                $views = INTEGER_MAGIC_NULL;
            }
        }
    }

    $submitter = mixed();
    $submitter_field = in_array('submitter', $fields_to_skip) ? null : $info['submitter_field'];
    if ($submitter_field !== null) {
        $_submitter = post_param_string('meta_submitter', '');
        if ($_submitter == '') {
            if ($content_id === null) {
                $submitter = get_member();
            } else {
                $submitter = INTEGER_MAGIC_NULL;
            }
        } else {
            $submitter = $GLOBALS['FORUM_DRIVER']->get_member_from_username($_submitter);
            if ($submitter === null) {
                $submitter = null; // Leave alone, we did not recognise the user
                attach_message(do_lang_tempcode('_MEMBER_NO_EXIST', escape_html($_submitter)), 'warn'); // ...but attach an error at least
            }
            if ($submitter === null) {
                if ($content_id === null) {
                    $submitter = get_member();
                }
            }
        }
    }

    $add_time = mixed();
    $add_time_field = in_array('add_time', $fields_to_skip) ? null : $info['add_time_field'];
    if ($add_time_field !== null) {
        $add_time = post_param_date('meta_add_time');
        if ($add_time === null) {
            if ($content_id === null) {
                $add_time = time();
            } else {
                $add_time = INTEGER_MAGIC_NULL;
            }
        } else {
            $add_time = min($add_time, 4294967295); // TODO #3046
        }
    }

    $edit_time = mixed();
    $edit_time_field = in_array('edit_time', $fields_to_skip) ? null : $info['edit_time_field'];
    if ($edit_time_field !== null) {
        $edit_time = post_param_date('meta_edit_time');
        if ($edit_time === null) {
            if ($content_id === null) {
                $edit_time = null; // No edit time
            } else {
                $edit_time = null; // Edit time explicitly wiped out
            }
        } else {
            $edit_time = min($edit_time, 4294967295); // TODO #3046
        }
    }

    if ($content_id !== null) {
        set_url_moniker($content_type, $content_id, $fields_to_skip, $new_content_id);
    }

    return array(
        'views' => $views,
        'submitter' => $submitter,
        'add_time' => $add_time,
        'edit_time' => $edit_time,
        /*'url_moniker' => $url_moniker, was handled internally*/
    );
}

/**
 * Set a URL moniker for a resource.
 *
 * @param  ID_TEXT $content_type The type of resource (e.g. download)
 * @param  ID_TEXT $content_id The old ID of the resource
 * @param  array $fields_to_skip List of fields to NOT take in
 * @param  ?ID_TEXT $new_content_id The new ID of the resource (null: not being renamed)
 */
function set_url_moniker($content_type, $content_id, $fields_to_skip = array(), $new_content_id = null)
{
    require_lang('metadata');

    require_code('content');
    $ob = get_content_object($content_type);
    $info = $ob->info();

    $url_moniker = mixed();
    if (($info['support_url_monikers']) && (!in_array('url_moniker', $fields_to_skip))) {
        $url_moniker = post_param_string('meta_url_moniker', '');
        if ($url_moniker == '') {
            if ($content_type == 'comcode_page') {
                $url_moniker = '';
                $parent = post_param_string('parent_page', '');
                while ($parent != '') {
                    $url_moniker = str_replace('_', '-', $parent) . (($url_moniker != '') ? ('/' . $url_moniker) : '');

                    $parent = $GLOBALS['SITE_DB']->query_select_value_if_there('comcode_pages', 'p_parent_page', array('the_page' => $parent));
                    if ($parent === null) {
                        $parent = '';
                    }
                }
                if ($url_moniker != '') {
                    $url_moniker .= '/' . preg_replace('#^.*:#', '', str_replace('_', '-', $content_id));
                } else {
                    $url_moniker = null;
                }
            } else {
                $url_moniker = null;
            }
        }

        if ($url_moniker !== null) {
            require_code('type_sanitisation');
            if (!is_alphanumeric(str_replace('/', '', $url_moniker))) {
                attach_message(do_lang_tempcode('BAD_CODENAME'), 'warn');
                $url_moniker = null;
            }

            if ($url_moniker !== null) {
                if ($content_type == 'comcode_page') {
                    list($zone, $page) = explode(':', $content_id);
                    $type = '';
                    $_content_id = $zone;

                    if ($new_content_id !== null) {
                        $GLOBALS['SITE_DB']->query_update('url_id_monikers', array(
                            'm_resource_page' => $new_content_id,
                        ), array('m_resource_page' => $page, 'm_resource_type' => '', 'm_resource_id' => $zone));
                    }
                } else {
                    list($zone, $attributes,) = page_link_decode($info['view_page_link_pattern']);
                    $page = $attributes['page'];
                    $type = $attributes['type'];
                    $_content_id = $content_id;

                    if ($new_content_id !== null) {
                        $GLOBALS['SITE_DB']->query_update('url_id_monikers', array(
                            'm_resource_id' => $new_content_id,
                        ), array('m_resource_page' => $page, 'm_resource_type' => $type, 'm_resource_id' => $content_id));
                    }
                }

                $ok = true;

                // Test for conflicts
                $conflict_test_map = array(
                    'm_moniker' => $url_moniker,
                    'm_deprecated' => 0,
                );
                if (substr($url_moniker, 0, 1) != '/') { // Can narrow the conflict-check scope if it's relative to a module rather than a zone ('/' prefix)
                    $conflict_test_map += array(
                        'm_resource_page' => $page,
                        'm_resource_type' => $type,
                    );
                }
                $test = $GLOBALS['SITE_DB']->query_select_value_if_there('url_id_monikers', 'm_resource_id', $conflict_test_map);
                if (($test !== null) && ($test !== $_content_id)) {
                    $test_page = $GLOBALS['SITE_DB']->query_select_value_if_there('url_id_monikers', 'm_resource_page', $conflict_test_map);
                    if ($content_type == 'comcode_page') {
                        if (_request_page($test_page, $test, null, get_site_default_lang(), true) !== false) {
                            $ok = false;
                        } else { // Deleted, so clean up
                            $GLOBALS['SITE_DB']->query_delete('url_id_monikers', $conflict_test_map);
                        }
                    } else {
                        $test2 = content_get_details(convert_composr_type_codes('module', $test_page, 'content_type'), $test);
                        if ($test2[0] !== null) {
                            $ok = false;
                        } else { // Deleted, so clean up
                            $GLOBALS['SITE_DB']->query_delete('url_id_monikers', $conflict_test_map);
                        }
                    }
                    if (!$ok) {
                        if ($content_type == 'comcode_page') {
                            $competing_page_link = $test . ':' . $page;
                        } else {
                            $competing_page_link = '_WILD' . ':' . $page;
                            if ($type != '' || $test != '') {
                                $competing_page_link .= ':' . $type;
                            }
                            if ($test != '') {
                                $competing_page_link .= ':' . $test;
                            }
                        }
                        attach_message(do_lang_tempcode('URL_MONIKER_TAKEN', escape_html($competing_page_link), escape_html($url_moniker)), 'warn');
                    }
                }

                if (substr($url_moniker, 0, 1) == '/') { // ah, relative to zones, better run some anti-conflict tests!
                    $parts = explode('/', substr($url_moniker, 1), 3);

                    if ($ok) {
                        // Test there are no zone conflicts
                        if ((file_exists(get_file_base() . '/' . $parts[0])) || (file_exists(get_custom_file_base() . '/' . $parts[0]))) {
                            $ok = false;
                            attach_message(do_lang_tempcode('URL_MONIKER_CONFLICT_ZONE'), 'warn');
                        }
                    }

                    if ($ok) {
                        // Test there are no page conflicts, from perspective of welcome zone
                        require_code('site');
                        $test1 = (count($parts) < 2) ? _request_page($parts[0], '') : false;
                        $test2 = false;
                        if (isset($parts[1])) {
                            $test2 = (count($parts) < 3) ? _request_page($parts[1], $parts[0]) : false;
                        }
                        if (($test1 !== false) || ($test2 !== false)) {
                            $ok = false;
                            attach_message(do_lang_tempcode('URL_MONIKER_CONFLICT_PAGE'), 'warn');
                        }
                    }

                    if ($ok) {
                        // Test there are no page conflicts, from perspective of deep zones
                        require_code('site');
                        $start = 0;
                        $zones = array();
                        do {
                            $zones = find_all_zones(false, false, false, $start, 50);
                            foreach ($zones as $zone_name) {
                                $test1 = (count($parts) < 2) ? _request_page($parts[0], $zone_name) : false;
                                if ($test1 !== false) {
                                    $ok = false;
                                    attach_message(do_lang_tempcode('URL_MONIKER_CONFLICT_PAGE'), 'warn');
                                    break 2;
                                }
                            }
                            $start += 50;
                        } while (count($zones) != 0);
                    }
                }

                if ($ok) {
                    // Insert
                    require_code('urls2');
                    suggest_new_idmoniker_for($page, $type, $_content_id, ($content_type == 'comcode_page') ? $zone : '', $url_moniker, false, $url_moniker);
                }
            }
        }
    }
}

/**
 * Read in an additional metadata field, specific to a resource type.
 *
 * @param  array $metadata metadata already collected
 * @param  ID_TEXT $key The parameter name
 * @param  mixed $default The default if it was not set
 */
function actual_metadata_get_fields__special(&$metadata, $key, $default)
{
    $metadata[$key] = $default;
    if (has_privilege(get_member(), 'edit_meta_fields')) {
        if (is_integer($default)) {
            switch ($default) {
                case 0:
                case INTEGER_MAGIC_NULL:
                    $metadata[$key] = post_param_integer('meta_' . $key, $default);
                    break;
            }
        } else {
            switch ($default) {
                case '':
                case STRING_MAGIC_NULL:
                    $metadata[$key] = post_param_string('meta_' . $key, $default);
                    if ($metadata[$key] == '') {
                        $metadata[$key] = $default;
                    }
                    break;

                case null:
                    $metadata[$key] = post_param_integer('meta_' . $key, null);
                    break;
            }
        }
    }
}

/**
 * Clear caching for a particular seo entry.
 *
 * @param  ID_TEXT $type The type of resource (e.g. download)
 * @param  ID_TEXT $id The ID of the resource
 */
function seo_meta_clear_caching($type, $id)
{
    if (function_exists('delete_cache_entry')) {
        delete_cache_entry('side_tag_cloud');
    }

    if (function_exists('persistent_cache_delete')) {
        persistent_cache_delete(array('seo', $type, $id));
    }
}

/**
 * Erase a seo entry... as these shouldn't be left hanging around once content is deleted.
 *
 * @param  ID_TEXT $type The type of resource (e.g. download)
 * @param  ID_TEXT $id The ID of the resource
 * @param  boolean $do_decache Whether to clear caching for this too
 */
function seo_meta_erase_storage($type, $id, $do_decache = true)
{
    $rows = $GLOBALS['SITE_DB']->query_select('seo_meta', array('meta_description'), array('meta_for_type' => $type, 'meta_for_id' => $id), '', 1);
    if (array_key_exists(0, $rows)) {
        delete_lang($rows[0]['meta_description']);
        $GLOBALS['SITE_DB']->query_delete('seo_meta', array('meta_for_type' => $type, 'meta_for_id' => $id), '', 1);
    }

    $rows = $GLOBALS['SITE_DB']->query_select('seo_meta_keywords', array('meta_keyword'), array('meta_for_type' => $type, 'meta_for_id' => $id));
    foreach ($rows as $row) {
        delete_lang($row['meta_keyword']);
    }
    $GLOBALS['SITE_DB']->query_delete('seo_meta_keywords', array('meta_for_type' => $type, 'meta_for_id' => $id));

    if ($do_decache) {
        seo_meta_clear_caching($type, $id);
    }
}

/**
 * Get template fields to insert into a form page, for manipulation of seo fields.
 *
 * @param  ID_TEXT $type The type of resource (e.g. download)
 * @param  ?ID_TEXT $id The ID of the resource (null: adding)
 * @param  boolean $show_header Whether to show a header
 * @return Tempcode Form page Tempcode fragment
 */
function seo_get_fields($type, $id = null, $show_header = true)
{
    require_code('form_templates');
    if ($id === null) {
        list($keywords, $description) = array('', '');
    } else {
        list($keywords, $description) = seo_meta_get_for($type, $id);
    }

    $fields = new Tempcode();
    if ((get_option('enable_seo_fields') != 'no') && ((get_option('enable_seo_fields') != 'only_on_edit') || ($id !== null))) {
        if ($show_header) {
            $fields->attach(do_template('FORM_SCREEN_FIELD_SPACER', array(
                '_GUID' => '545aefd48d73cf01bdec7226dc6d93fb',
                'SECTION_HIDDEN' => $keywords == '' && $description == '',
                'TITLE' => do_lang_tempcode('SEO'),
                'HELP' => (get_option('show_docs') === '0') ? null : do_lang_tempcode('TUTORIAL_ON_THIS', get_tutorial_url('tut_seo')),
            )));
        }
        $fields->attach(form_input_line_multi(do_lang_tempcode('KEYWORDS'), do_lang_tempcode('DESCRIPTION_META_KEYWORDS'), 'meta_keywords[]', array_map('trim', explode(',', preg_replace('#,+#', ',', $keywords))), 0));
        $fields->attach(form_input_line(do_lang_tempcode('META_DESCRIPTION'), do_lang_tempcode('DESCRIPTION_META_DESCRIPTION'), 'meta_description', $description, false));
    }
    return $fields;
}

/**
 * Explictly sets the meta information for the specified resource.
 *
 * @param  ID_TEXT $type The type of resource (e.g. download)
 * @param  ID_TEXT $id The ID of the resource
 * @param  SHORT_TEXT $keywords The keywords to use
 * @param  SHORT_TEXT $description The description to use
 */
function seo_meta_set_for_explicit($type, $id, $keywords, $description)
{
    if ($description == STRING_MAGIC_NULL) {
        return;
    }
    if ($keywords == STRING_MAGIC_NULL) {
        return;
    }

    seo_meta_erase_storage($type, $id, false);

    $description = str_replace("\n", ' ', $description);

    $map_general = array(
        'meta_for_type' => $type,
        'meta_for_id' => $id,
    );

    $map = $map_general;
    $map += insert_lang('meta_description', $description, 2);
    $GLOBALS['SITE_DB']->query_insert('seo_meta', $map);

    foreach (array_unique(explode(',', $keywords)) as $keyword) {
        if (trim($keyword) == '') {
            continue;
        }

        $map = $map_general;
        $map += insert_lang('meta_keyword', $keyword, 2);
        $GLOBALS['SITE_DB']->query_insert('seo_meta_keywords', $map);
    }

    seo_meta_clear_caching($type, $id);
}

/**
 * Automatically extracts meta information from some source data.
 *
 * @param  array $keyword_sources Array of content strings to summarise from
 * @param  SHORT_TEXT $description The description to use
 * @return array A pair: Keyword string generated, Description generated
 *
 * @ignore
 */
function _seo_meta_find_data($keyword_sources, $description = '')
{
    // These characters are considered to be word-characters
    require_code('textfiles');
    $word_chars = explode("\n", read_text_file('word_characters', '')); // We use this, as we have no easy multi-language way of detecting if something is a word character in non-latin alphabets (as they don't usually have upper/lower case which would be our detection technique)
    foreach ($word_chars as $i => $word_char) {
        $word_chars[$i] = trim($word_char);
    }
    $common_words = explode("\n", read_text_file('too_common_words', ''));
    foreach ($common_words as $i => $common_word) {
        $common_words[$i] = trim(cms_mb_strtolower($common_word));
    }

    $word_chars_flip = array_flip($word_chars);
    $common_words_flip = array_flip($common_words);

    $min_word_length = 3;

    $keywords = array(); // This will be filled
    $keywords_must_use = array(); // ...and/or this

    $this_word = '';

    $source = mixed();
    foreach ($keyword_sources as $source) { // Look in all our sources
        $must_use = false;
        if (is_array($source)) {
            list($source, $must_use) = $source;
        }

        $source = strip_comcode($source);
        if (cms_mb_strtoupper($source) == $source) {
            $source = cms_mb_strtolower($source); // Don't leave in all caps, as is ugly, and also would break our Proper Noun detection
        }

        $i = 0;
        $len_a = strlen($source);
        $len_b = cms_mb_strlen($source);
        $len = $len_a;
        $unicode = false;
        if ($len_b > $len_a) {
            $len = $len_b;
            $unicode = true;
        }
        $from = 0;
        $in_word = false;
        $word_is_caps = false;
        while ($i < $len) {
            if ($unicode) { // Slower :(
                $at = cms_mb_substr($source, $i, 1);
                $is_word_char = array_key_exists($at, $word_chars_flip) || cms_mb_strtolower($at) != cms_mb_strtoupper($at);

                if ($in_word) {
                    // Exiting word
                    if (($i == $len - 1) || ((!$is_word_char) && ((!$word_is_caps) || ($at != ' ') || (/*continuation of Proper Noun*/cms_mb_strtolower(cms_mb_substr($source, $i + 1, 1)) == cms_mb_substr($source, $i + 1, 1))))) {
                        while ((cms_mb_strlen($this_word) != 0) && (cms_mb_substr($this_word, -1) == '\'' || cms_mb_substr($this_word, -1) == '-' || cms_mb_substr($this_word, -1) == '.')) {
                            $this_word = cms_mb_substr($this_word, 0, cms_mb_strlen($this_word) - 1);
                        }
                        if (($i - $from) >= $min_word_length) {
                            if (!array_key_exists(cms_mb_strtolower($this_word), $common_words_flip)) {
                                if (!array_key_exists($this_word, $keywords)) {
                                    $keywords[$this_word] = 0;
                                }
                                if ($must_use) {
                                    $keywords_must_use[$this_word]++;
                                } else {
                                    $keywords[$this_word]++;
                                }
                            }
                        }
                        $in_word = false;
                    } else {
                        $this_word .= $at;
                    }
                } else {
                    // Entering word
                    if (($is_word_char) && ($at != '\'') && ($at != '-') && ($at != '.')/*Special latin cases, cannot start a word with a symbol*/) {
                        $word_is_caps = (cms_mb_strtolower($at) != $at);
                        $from = $i;
                        $in_word = true;
                        $this_word = $at;
                    }
                }
            } else {
                $at = $source[$i];
                $is_word_char = array_key_exists($at, $word_chars_flip);

                if ($in_word) {
                    // Exiting word
                    if (($i == $len - 1) || ((!$is_word_char) && ((!$word_is_caps) || ($at != ' ') || (/*continuation of Proper Noun*/strtolower(substr($source, $i + 1, 1)) == substr($source, $i + 1, 1))))) {
                        $this_word = substr($source, $from, $i - $from);
                        while ((strlen($this_word) != 0) && (substr($this_word, -1) == '\'' || substr($this_word, -1) == '-' || substr($this_word, -1) == '.')) {
                            $this_word = substr($this_word, 0, strlen($this_word) - 1);
                        }
                        if (($i - $from) >= $min_word_length) {
                            if (!array_key_exists($this_word, $common_words_flip)) {
                                if (!array_key_exists($this_word, $keywords)) {
                                    $keywords[$this_word] = 0;
                                }
                                if ($must_use) {
                                    $keywords_must_use[$this_word]++;
                                } else {
                                    $keywords[$this_word]++;
                                }
                            }
                        }
                        $in_word = false;
                    }
                } else {
                    // Entering word
                    if ($is_word_char) {
                        $word_is_caps = (strtolower($at) != $at);
                        $from = $i;
                        $in_word = true;
                    }
                }
            }
            $i++;
        }
    }

    arsort($keywords, SORT_NATURAL | SORT_FLAG_CASE);

    $imp = '';
    foreach (array_keys($keywords_must_use) as $keyword) {
        if ($imp != '') {
            $imp .= ',';
        }
        $imp .= $keyword;
    }
    foreach (array_keys($keywords) as $i => $keyword) {
        if ($imp != '') {
            $imp .= ',';
        }
        $imp .= $keyword;
        if ($i == 10) {
            break;
        }
    }

    require_code('xhtml');
    $description = strip_comcode($description, true);
    $description = trim(preg_replace('#\s+---+\s+#', ' ', $description));
    $description = preg_replace('#\n+#', ' ', $description);

    if (cms_mb_strlen($description) > 160) {
        if (get_charset() == 'utf-8') {
            $description = cms_mb_substr($description, 0, 159);
            $description .= '…';
        } else {
            $description = cms_mb_substr($description, 0, 157);
            $description .= '...';
        }
    }

    return array($imp, $description);
}

/**
 * Sets the meta information for the specified resource, by auto-summarisation from the given parameters.
 *
 * @param  ID_TEXT $type The type of resource (e.g. download)
 * @param  ID_TEXT $id The ID of the resource
 * @param  array $keyword_sources Array of content strings to summarise from
 * @param  SHORT_TEXT $description The description to use
 * @return SHORT_TEXT Keyword string generated (it's also saved in the DB, so usually you won't want to collect this)
 */
function seo_meta_set_for_implicit($type, $id, $keyword_sources, $description)
{
    if ((post_param_string('meta_keywords', null) !== null) && ((post_param_string('meta_keywords') != '') || (post_param_string('meta_description') != ''))) {
        seo_meta_set_for_explicit($type, $id, post_param_string('meta_keywords'), post_param_string('meta_description'));
        return '';
    }

    if (get_option('automatic_meta_extraction') == '0') {
        return '';
    }

    list($imp, $description) = _seo_meta_find_data($keyword_sources, $description);

    seo_meta_set_for_explicit($type, $id, $imp, $description);

    if (function_exists('delete_cache_entry')) {
        delete_cache_entry('side_tag_cloud');
    }

    return $imp;
}
