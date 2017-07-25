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
 * @package    authors
 */

/**
 * Render an author box.
 *
 * @param  array $row Author row
 * @param  ID_TEXT $zone Zone to link through to
 * @param  boolean $give_context Whether to include context (i.e. say WHAT this is, not just show the actual content)
 * @param  ID_TEXT $guid Overridden GUID to send to templates (blank: none)
 * @return Tempcode The author box
 */
function render_author_box($row, $zone = '_SEARCH', $give_context = true, $guid = '')
{
    if (is_null($row)) { // Should never happen, but we need to be defensive
        return new Tempcode();
    }

    require_lang('authors');

    $url = build_url(array('page' => 'authors', 'type' => 'browse', 'id' => $row['author']), $zone);

    $title = $give_context ? do_lang('CONTENT_IS_OF_TYPE', do_lang('AUTHOR'), $row['author']) : $row['author'];

    return do_template('SIMPLE_PREVIEW_BOX', array(
        '_GUID' => ($guid != '') ? $guid : 'e597aef1818f5610402d6e5f478735a1',
        'ID' => $row['author'],
        'TITLE' => $title,
        'SUMMARY' => get_translated_tempcode('author', $row, 'description'),
        'URL' => $url,
        'RESOURCE_TYPE' => 'author',
    ));
}

/**
 * Shows an HTML page of all authors clickably.
 */
function authors_script()
{
    require_lang('authors');
    require_css('authors');

    inform_non_canonical_parameter('max');

    $start = get_param_integer('start', 0);
    $max = get_param_integer('max', 300);

    $author_fields = $GLOBALS['SITE_DB']->query('SELECT m_name,m_table FROM ' . $GLOBALS['SITE_DB']->get_table_prefix() . 'db_meta WHERE m_name LIKE \'' . db_encode_like('%author') . '\'');
    $rows = array();
    foreach ($author_fields as $field) {
        if (($field['m_table'] != 'addons') && ($field['m_table'] != 'blocks') && ($field['m_table'] != 'modules')) {
            $rows_new = $GLOBALS['SITE_DB']->query('SELECT DISTINCT ' . $field['m_name'] . ' AS author FROM ' . $GLOBALS['SITE_DB']->get_table_prefix() . $field['m_table'] . ' WHERE ' . db_string_not_equal_to($field['m_name'], '') . ' ORDER BY ' . $field['m_name'], $max + $start);
            foreach ($rows_new as $a) {
                if ((!array_key_exists($a['author'], $rows)) || ($field['m_table'] == 'authors')) {
                    $rows[$a['author']] = $field['m_table'];
                }
            }
        }
    }

    $rows = array_unique($rows);

    $field_name = filter_naughty_harsh(get_param_string('field_name'));

    $authors = array();
    $i = 0;
    foreach ($rows as $author => $table) {
        if (($i >= $start) && ($i < $start + $max)) {
            if ($table == 'authors') {
                $authors[] = array('_GUID' => 'cffa9926cebd3ec2920677266a3299ea', 'DEFINED' => true, 'FIELD_NAME' => $field_name, 'AUTHOR' => $author);
            } else {
                $authors[] = array('_GUID' => '6210be6d1eef4bc2bda7f49947301f97', 'DEFINED' => false, 'FIELD_NAME' => $field_name, 'AUTHOR' => $author);
            }
        }

        $i++;
    }

    if ($i >= $start + $max) {
        $keep = symbol_tempcode('KEEP');
        $next_url = find_script('authors') . '?field_name=' . urlencode($field_name) . '&start=' . strval($start + $max) . '&max=' . strval($max) . $keep->evaluate();
    } else {
        $next_url = null;
    }

    $content = do_template('AUTHOR_POPUP', array('_GUID' => 'e18411d1bf24c6ed945b4d9064774884', 'AUTHORS' => $authors, 'NEXT_URL' => $next_url));

    require_code('site');
    attach_to_screen_header('<meta name="robots" content="noindex" />'); // XHTMLXHTML

    $echo = do_template('STANDALONE_HTML_WRAP', array('_GUID' => 'ab8d8c9d276530d82ddd84202aacf32f', 'TITLE' => do_lang_tempcode('CHOOSE_AUTHOR'), 'CONTENT' => $content, 'POPUP' => true));
    $echo->handle_symbol_preprocessing();
    $echo->evaluate_echo();
}

/**
 * Get a member ID from an author name. First by trying authors table, second by trying forum usernames.
 *
 * @param  ID_TEXT $author The name of an author
 * @return ?MEMBER The member ID (null: none found)
 */
function get_author_id_from_name($author)
{
    $handle = $GLOBALS['SITE_DB']->query_select_value_if_there('authors', 'member_id', array('author' => $author));
    if (is_null($handle)) {
        $handle = $GLOBALS['FORUM_DRIVER']->get_member_from_username($author);
    }
    return $handle;
}

/**
 * Adds an author (re-creating them if they already exist - thus it also serves to edit; the reason for this is the fluidity of authors - members are automatically authors even before an author profile is made)
 *
 * @param  ID_TEXT $author The name of an author
 * @param  URLPATH $url The URL to the authors home page
 * @param  ?MEMBER $member_id The member ID of the author (null: no forum profile)
 * @param  LONG_TEXT $description A description of the author
 * @param  LONG_TEXT $skills A terse string showing author skills
 * @param  ?SHORT_TEXT $meta_keywords Meta keywords for this resource (null: do not edit) (blank: implicit)
 * @param  ?LONG_TEXT $meta_description Meta description for this resource (null: do not edit) (blank: implicit)
 */
function add_author($author, $url, $member_id, $description, $skills, $meta_keywords = '', $meta_description = '')
{
    log_it('DEFINE_AUTHOR', $author, is_null($member_id) ? '' : strval($member_id));

    $rows = $GLOBALS['SITE_DB']->query_select('authors', array('description', 'skills'), array('author' => $author), '', 1);
    if (array_key_exists(0, $rows)) {
        $_description = $rows[0]['description'];
        $_skills = $rows[0]['skills'];

        require_code('attachments2');
        require_code('attachments3');

        $map = array(
            'url' => $url,
            'member_id' => $member_id,
        );
        $map += lang_remap('skills', $_skills, $skills);
        $map += update_lang_comcode_attachments('description', $_description, $description, 'author', $author, null, $member_id);

        $GLOBALS['SITE_DB']->query_update('authors', $map, array('author' => $author), '', 1);
    } else {
        require_code('attachments2');

        $map = array(
            'author' => $author,
            'url' => $url,
            'member_id' => $member_id,
        );
        $map += insert_lang_comcode_attachments('description', 3, $description, 'author', $author, null, false, $member_id);
        $map += insert_lang_comcode('skills', $skills, 3);
        $GLOBALS['SITE_DB']->query_insert('authors', $map);

        if ((addon_installed('commandr')) && (!running_script('install'))) {
            require_code('resource_fs');
            generate_resource_fs_moniker('author', $author, null, null, true);
        }

        require_code('sitemap_xml');
        notify_sitemap_node_add('SEARCH:authors:browse:' . $author, null, null, SITEMAP_IMPORTANCE_LOW, 'yearly', false);
    }

    require_code('seo2');
    if (($meta_keywords == '') && ($meta_description == '')) {
        seo_meta_set_for_implicit('authors', $author, array($author, $description, $skills), $description);
    } else {
        seo_meta_set_for_explicit('authors', $author, $meta_keywords, $meta_description);
    }
}

/**
 * Delete an author
 *
 * @param  ID_TEXT $author The name of an author
 */
function delete_author($author)
{
    $rows = $GLOBALS['SITE_DB']->query_select('authors', array('description', 'skills'), array('author' => $author), '', 1);
    if (array_key_exists(0, $rows)) {
        require_code('attachments2');
        require_code('attachments3');
        delete_lang_comcode_attachments($rows[0]['description'], 'author', $author);

        delete_lang($rows[0]['skills']);

        $GLOBALS['SITE_DB']->query_delete('authors', array('author' => $author), '', 1);
    } else {
        warn_exit(do_lang_tempcode('MISSING_RESOURCE', 'author'));
    }

    if (addon_installed('catalogues')) {
        update_catalogue_content_ref('author', $author, '');
    }

    log_it('DELETE_AUTHOR', $author);

    if ((addon_installed('commandr')) && (!running_script('install'))) {
        require_code('resource_fs');
        expunge_resource_fs_moniker('author', $author);
    }

    require_code('sitemap_xml');
    notify_sitemap_node_delete('SEARCH:authors:browse:' . $author);
}

/**
 * Find if a member's usergroup has permission to edit an author
 *
 * @param  MEMBER $member The member being checked whether to have the access
 * @param  ID_TEXT $author An author
 * @return boolean Whether the member can edit this author
 */
function has_edit_author_permission($member, $author)
{
    if (is_guest($member)) {
        return false;
    }
    if ((get_author_id_from_name($author) == $member) && (has_privilege($member, 'set_own_author_profile'))) {
        return true;
    }
    if (has_privilege($member, 'edit_midrange_content', 'cms_authors')) {
        return true;
    }
    return false;
}

/**
 * Find if a member's usergroup has permission to delete an author
 *
 * @param  MEMBER $member The member being checked whether to have the access
 * @param  ID_TEXT $author An author
 * @return boolean Whether the member can edit this author
 */
function has_delete_author_permission($member, $author)
{
    if (is_guest($member)) {
        return false;
    }
    if ((get_author_id_from_name($author) == $member) && (has_privilege($member, 'delete_own_midrange_content'))) {
        return true;
    }
    if (has_privilege($member, 'delete_midrange_content', 'cms_authors')) {
        return true;
    }
    return false;
}

/**
 * Merge two authors.
 *
 * @param  ID_TEXT $from The first author (being removed effectively)
 * @param  ID_TEXT $to The second author (subsuming the first)
 */
function merge_authors($from, $to)
{
    $author_fields = $GLOBALS['SITE_DB']->query('SELECT m_name,m_table FROM ' . $GLOBALS['SITE_DB']->get_table_prefix() . 'db_meta WHERE m_name LIKE \'' . db_encode_like('%author') . '\'');
    foreach ($author_fields as $field) {
        if ($field['m_table'] != 'authors') {
            $GLOBALS['SITE_DB']->query_update($field['m_table'], array($field['m_name'] => $to), array($field['m_name'] => $from));
        }
    }
    if ($from != $to) {
        $GLOBALS['SITE_DB']->query_delete('authors', array('author' => $from), '', 1);
    }

    log_it('MERGE_AUTHORS', $from, $to);
}
