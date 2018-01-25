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
 * @package    news
 */

/**
 * Find a news category image from a string that may have multiple interpretations.
 *
 * @param  string $nc_img URL / theme image code / blank
 * @return URLPATH URL (or blank)
 */
function get_news_category_image_url($nc_img)
{
    require_code('images');

    if ($nc_img == '') {
        $image = '';
    } elseif (looks_like_url($nc_img)) {
        $image = $nc_img;

        if (url_is_local($image)) {
            $image = get_custom_base_url() . '/' . $image;
        }
    } else {
        $image = find_theme_image($nc_img, true);
        if ($image === null) {
            $image = '';
        }
    }
    return $image;
}

/**
 * Show a news entry box.
 *
 * @param  array $row The news row
 * @param  ID_TEXT $zone The zone our news module is in
 * @param  boolean $give_context Whether to include context (i.e. say WHAT this is, not just show the actual content)
 * @param  boolean $brief Whether to use the brief styling
 * @param  ID_TEXT $guid Overridden GUID to send to templates (blank: none)
 * @return Tempcode The box
 */
function render_news_box($row, $zone = '_SEARCH', $give_context = true, $brief = false, $guid = '')
{
    if ($row === null) { // Should never happen, but we need to be defensive
        return new Tempcode();
    }

    require_lang('news');
    require_css('news');

    $just_news_row = db_map_restrict($row, array('id', 'title', 'news', 'news_article'));

    $url = build_url(array('page' => 'news', 'type' => 'view', 'id' => $row['id']), $zone);

    $title = get_translated_tempcode('news', $just_news_row, 'title');
    $title_plain = get_translated_text($row['title']);

    global $NEWS_CATS_CACHE;
    if (!isset($NEWS_CATS_CACHE)) {
        $NEWS_CATS_CACHE = array();
    }
    if (!array_key_exists($row['news_category'], $NEWS_CATS_CACHE)) {
        $_news_cats = $GLOBALS['SITE_DB']->query_select('news_categories', array('*'), array('id' => $row['news_category']), '', 1);
        if (array_key_exists(0, $_news_cats)) {
            $NEWS_CATS_CACHE[$row['news_category']] = $_news_cats[0];
        }
    }
    if ((!array_key_exists($row['news_category'], $NEWS_CATS_CACHE)) || (!array_key_exists('nc_title', $NEWS_CATS_CACHE[$row['news_category']]))) {
        $row['news_category'] = db_get_first_id();
    }
    $news_cat_row = $NEWS_CATS_CACHE[$row['news_category']];

    $category = get_translated_text($news_cat_row['nc_title']);
    if ($row['news_image'] != '') {
        require_code('images');
        $img_raw = $row['news_image'];
        if (url_is_local($img_raw)) {
            $img_raw = get_custom_base_url() . '/' . $img_raw;
        }
        $img = $img_raw;
    } else {
        $img_raw = get_news_category_image_url($news_cat_row['nc_img']);
        if ($img_raw === null) {
            $img_raw = '';
        }
        $img = $img_raw;
    }

    $news = get_translated_tempcode('news', $just_news_row, 'news');
    if ($news->is_empty()) {
        $news = get_translated_tempcode('news', $just_news_row, 'news_article');
        $truncate = true;
    } else {
        $truncate = false;
    }

    $author_url = addon_installed('authors') ? build_url(array('page' => 'authors', 'type' => 'browse', 'id' => $row['author']), get_module_zone('authors')) : new Tempcode();
    $author = $row['author'];

    $seo_bits = (get_value('no_tags') === '1') ? array('', '') : seo_meta_get_for('news', strval($row['id']));

    $map = array(
        '_GUID' => ($guid != '') ? $guid : 'jd89f893jlkj9832gr3uyg2u',
        'GIVE_CONTEXT' => $give_context,
        'TAGS' => (get_theme_option('show_content_tagging_inline') == '1') ? get_loaded_tags('news', explode(',', $seo_bits[0])) : null,
        'TRUNCATE' => $truncate,
        'AUTHOR' => $author,
        'BLOG' => false,
        'AUTHOR_URL' => $author_url,
        'CATEGORY' => $category,
        '_CATEGORY' => strval($row['news_category']),
        'IMG' => $img,
        '_IMG' => $img_raw,
        'NEWS' => $news,
        'ID' => strval($row['id']),
        'SUBMITTER' => strval($row['submitter']),
        'DATE' => get_timezoned_date_time_tempcode($row['date_and_time']),
        'DATE_RAW' => strval($row['date_and_time']),
        'FULL_URL' => $url,
        'NEWS_TITLE' => $title,
        'NEWS_TITLE_PLAIN' => $title_plain,
    );

    if ((get_option('is_on_comments') == '1') && (!has_no_forum()) && ($row['allow_comments'] >= 1)) {
        $map['COMMENT_COUNT'] = '1';
    }

    return do_template($brief ? 'NEWS_BRIEF' : 'NEWS_BOX', $map);
}

/**
 * Get Tempcode for a news category 'feature box' for the given row.
 *
 * @param  array $row The database field row of it
 * @param  ID_TEXT $zone The zone to use
 * @param  boolean $give_context Whether to include context (i.e. say WHAT this is, not just show the actual content)
 * @param  boolean $attach_to_url_filter Whether to copy through any filter parameters in the URL, under the basis that they are associated with what this box is browsing
 * @param  ?integer $blogs What to show (null: news and blogs, 0: news, 1: blogs)
 * @param  ID_TEXT $guid Overridden GUID to send to templates (blank: none)
 * @return Tempcode A box for it, linking to the full page
 */
function render_news_category_box($row, $zone = '_SEARCH', $give_context = true, $attach_to_url_filter = false, $blogs = null, $guid = '')
{
    if ($row === null) { // Should never happen, but we need to be defensive
        return new Tempcode();
    }

    require_lang('news');

    // URL
    $map = array('page' => ($zone == '_SELF' && running_script('index')) ? get_page_name() : 'news', 'type' => 'browse', 'id' => $row['id']);
    if ($attach_to_url_filter) {
        if (get_param_string('type', 'browse') == 'cat_select') {
            $map['blog'] = '0';
        } elseif (get_param_string('type', 'browse') == 'blog_select') {
            $map['blog'] = '1';
        }

        $map += propagate_filtercode();
    }
    $url = build_url($map, $zone);

    // Title
    $_title = get_translated_text($row['nc_title']);
    $title = $give_context ? do_lang('CONTENT_IS_OF_TYPE', do_lang('NEWS_CATEGORY'), $_title) : $_title;

    // Metadata
    $num_entries = $GLOBALS['SITE_DB']->query_select_value('news', 'COUNT(*)', array('validated' => 1, 'news_category' => $row['id']));
    $num_entries += $GLOBALS['SITE_DB']->query_select_value('news n JOIN ' . get_table_prefix() . 'news_category_entries c ON c.news_entry=n.id', 'COUNT(*)', array('validated' => 1, 'news_entry_category' => $row['id']));
    $entry_details = do_lang_tempcode('CATEGORY_SUBORDINATE_2', escape_html(integer_format($num_entries)));

    // Image
    $img = get_news_category_image_url($row['nc_img']);
    if ($blogs === 1) {
        $_img = $GLOBALS['FORUM_DRIVER']->get_member_avatar_url($row['nc_owner']);
        if ($_img != '') {
            $img = $_img;
        }
    }
    $rep_image = null;
    $_rep_image = null;
    if ($img != '') {
        require_code('images');
        $_rep_image = $img;
        $rep_image = do_image_thumb($img, $_title, false);
    }

    // Render
    return do_template('SIMPLE_PREVIEW_BOX', array(
        '_GUID' => ($guid != '') ? $guid : '49e9c7022f9171fdff02d84ee968bb52',
        'ID' => strval($row['id']),
        'TITLE' => $title,
        'TITLE_PLAIN' => $_title,
        '_REP_IMAGE' => $_rep_image,
        'REP_IMAGE' => $rep_image,
        'OWNER' => ($row['nc_owner'] === null) ? '' : strval($row['nc_owner']),
        'SUMMARY' => '',
        'ENTRY_DETAILS' => $entry_details,
        'URL' => $url,
        'FRACTIONAL_EDIT_FIELD_NAME' => $give_context ? null : 'title',
        'FRACTIONAL_EDIT_FIELD_URL' => $give_context ? null : '_SEARCH:cms_news:__edit_category:' . strval($row['id']),
        'RESOURCE_TYPE' => 'news_category',
    ));
}

/**
 * Get a nice formatted XHTML list of news categories.
 *
 * @param  ?mixed $it The selected news category. Array or AUTO_LINK (null: personal)
 * @param  boolean $show_all_personal_categories Whether to add all personal categories into the list (for things like the adminzone, where all categories must be shown, regardless of permissions)
 * @param  boolean $addable_filter Whether to only show for what may be added to by the current member
 * @param  boolean $only_existing Whether to limit to only existing cats (otherwise we dynamically add unstarted blogs)
 * @param  ?boolean $only_blogs Whether to limit to only show blog categories (null: don't care, true: blogs only, false: no blogs)
 * @param  boolean $prefer_not_blog_selected Whether to prefer to choose a non-blog category as the default
 * @param  ?TIME $updated_since Time from which content must be updated (null: no limit)
 * @return Tempcode The Tempcode for the news category select list
 */
function create_selection_list_news_categories($it = null, $show_all_personal_categories = false, $addable_filter = false, $only_existing = false, $only_blogs = null, $prefer_not_blog_selected = false, $updated_since = null)
{
    if (!is_array($it)) {
        $it = array($it);
    }

    if ($only_blogs === true) {
        $where = 'WHERE nc_owner IS NOT NULL';
    } elseif ($only_blogs === false) {
        $where = 'WHERE nc_owner IS NULL';
    } else {
        $where = 'WHERE 1=1';
    }
    if ($updated_since !== null) {
        $extra_join = '';
        $extra_where = '';
        if (addon_installed('content_privacy')) {
            require_code('content_privacy');
            list($extra_join, $extra_where) = get_privacy_where_clause('news', 'n', $GLOBALS['FORUM_DRIVER']->get_guest_id());
        }
        if (get_option('filter_regions') == '1') {
            require_code('locations');
            $extra_where .= sql_region_filter('news', 'n.id');
        }
        $where .= ' AND EXISTS(SELECT * FROM ' . get_table_prefix() . 'news n LEFT JOIN ' . get_table_prefix() . 'news_category_entries ON news_entry=id' . $extra_join . ' WHERE validated=1 AND date_and_time>' . strval($updated_since) . $extra_where . ')';
    }

    static $query_cache = array();
    if (isset($query_cache[$where])) {
        list($count, $_cats) = $query_cache[$where];
    } else {
        $count = $GLOBALS['SITE_DB']->query_value_if_there('SELECT COUNT(*) FROM ' . get_table_prefix() . 'news_categories c ' . $where);
        if ($count > 500) { // Uh oh, loads, need to limit things more
            $where .= ' AND (nc_owner IS NULL OR nc_owner=' . strval(get_member()) . ')';
        }
        $_cats = $GLOBALS['SITE_DB']->query('SELECT *,c.id as n_id FROM ' . get_table_prefix() . 'news_categories c ' . $where . ' ORDER BY c.id', null, 0, false, true, array('nc_title' => 'SHORT_TRANS'));

        foreach ($_cats as $i => $cat) {
            $_cats[$i]['nice_title'] = get_translated_text($cat['nc_title']);
        }
        sort_maps_by($_cats, 'nice_title');

        // Sort
        $title_ordered_cats = $_cats;
        $_cats = array();
        foreach ($title_ordered_cats as $cat) {
            $_cats[] = $cat;
        }

        $query_cache[$where] = array($count, $_cats);
    }

    $categories_non_blogs = new Tempcode();
    $categories_blogs = new Tempcode();
    $add_cat = true;

    $may_blog = has_privilege(get_member(), 'have_personal_category', 'cms_news');

    foreach ($_cats as $cat) {
        if ($cat['nc_owner'] == get_member()) {
            $add_cat = false;
        }

        if (!has_category_access(get_member(), 'news', strval($cat['n_id']))) {
            continue;
        }
        if ($addable_filter) {
            if ($cat['nc_owner'] !== get_member()) {
                if (!has_submit_permission('high', get_member(), get_ip_address(), 'cms_news', array('news', $cat['id']))) {
                    continue;
                }
            } else {
                if (!$may_blog) {
                    continue;
                }
            }
        }

        if ($cat['nc_owner'] === null) {
            $li = form_input_list_entry(strval($cat['n_id']), ($it != array(null)) && in_array($cat['n_id'], $it), $cat['nice_title'] . ' (#' . strval($cat['n_id']) . ')');
            $categories_non_blogs->attach($li);
        } else {
            if (((($cat['nc_owner'] !== null) && ($may_blog)) || (($cat['nc_owner'] == get_member()) && (!is_guest()))) || ($show_all_personal_categories)) {
                $li = form_input_list_entry(strval($cat['n_id']), (($cat['nc_owner'] == get_member()) && ((!$prefer_not_blog_selected) && (in_array(null, $it)))) || (in_array($cat['n_id'], $it)), $cat['nice_title']/*Performance do_lang('MEMBER_CATEGORY', $GLOBALS['FORUM_DRIVER']->get_username($cat['nc_owner'], true))*/ . ' (#' . strval($cat['n_id']) . ')');
                $categories_blogs->attach($li);
            }
        }
    }

    if ((!$only_existing) && (has_privilege(get_member(), 'have_personal_category', 'cms_news')) && ($add_cat) && (!is_guest())) {
        $categories_blogs->attach(form_input_list_entry('personal', (!$prefer_not_blog_selected) && in_array(null, $it), do_lang_tempcode('MEMBER_CATEGORY', do_lang_tempcode('_NEW', escape_html($GLOBALS['FORUM_DRIVER']->get_username(get_member(), true))))));
    }

    $categories = new Tempcode();
    if ($categories_blogs->is_empty()) {
        $categories = $categories_non_blogs;
    } else {
        $categories->attach(form_input_list_group(do_lang('JUST_NEWS_CATEGORIES'), $categories_non_blogs));
        $categories->attach(form_input_list_group(do_lang('BLOGS'), $categories_blogs));
    }

    return $categories;
}

/**
 * Get a nice formatted XHTML list of news.
 *
 * @param  ?AUTO_LINK $it The selected news entry (null: none)
 * @param  ?MEMBER $only_owned Limit news to those submitted by this member (null: show all)
 * @param  boolean $editable_filter Whether to only show for what may be edited by the current member
 * @param  boolean $only_in_blog Whether to only show blog posts
 * @return Tempcode The list
 */
function create_selection_list_news($it, $only_owned = null, $editable_filter = false, $only_in_blog = false)
{
    $where = ($only_owned === null) ? '1' : 'submitter=' . strval($only_owned);
    if ($only_in_blog) {
        $rows = $GLOBALS['SITE_DB']->query('SELECT n.* FROM ' . get_table_prefix() . 'news n JOIN ' . get_table_prefix() . 'news_categories c ON c.id=n.news_category AND ' . $where . ' AND nc_owner IS NOT NULL ORDER BY date_and_time DESC', intval(get_option('general_safety_listing_limit'))/*reasonable limit*/);
    } else {
        $rows = $GLOBALS['SITE_DB']->query('SELECT * FROM ' . get_table_prefix() . 'news WHERE ' . $where . ' ORDER BY date_and_time DESC', intval(get_option('general_safety_listing_limit'))/*reasonable limit*/, 0, false, true);
    }

    if (count($rows) == intval(get_option('general_safety_listing_limit'))) {
        attach_message(do_lang_tempcode('TOO_MUCH_CHOOSE__RECENT_ONLY', escape_html(integer_format(intval(get_option('general_safety_listing_limit'))))), 'warn');
    }

    $out = new Tempcode();
    foreach ($rows as $myrow) {
        if (!has_category_access(get_member(), 'news', strval($myrow['news_category']))) {
            continue;
        }
        if (($editable_filter) && (!has_edit_permission($only_in_blog ? 'mid' : 'high', get_member(), $myrow['submitter'], $only_in_blog ? 'cms_blogs' : 'cms_news', array('news', $myrow['news_category'])))) {
            continue;
        }

        $selected = ($myrow['id'] == $it);

        $out->attach(form_input_list_entry(strval($myrow['id']), $selected, get_translated_text($myrow['title'])));
    }

    return $out;
}
