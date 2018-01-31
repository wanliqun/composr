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
 * @package    cns_forum
 */

/**
 * Hook class.
 */
class Hook_rss_cns_forumview
{
    /**
     * Run function for RSS hooks.
     *
     * @param  string $_filters A list of categories we accept from
     * @param  TIME $cutoff Cutoff time, before which we do not show results from
     * @param  string $prefix Prefix that represents the template set we use
     * @set    RSS_ ATOM_
     * @param  string $date_string The standard format of date to use for the syndication type represented in the prefix
     * @param  integer $max The maximum number of entries to return, ordering by date
     * @return ?array A pair: The main syndication section, and a title (null: error)
     */
    public function run($_filters, $cutoff, $prefix, $date_string, $max)
    {
        if (!addon_installed('cns_forum')) {
            return null;
        }

        if (get_forum_type() != 'cns') {
            return null;
        }
        if (!has_actual_page_access(get_member(), 'forumview')) {
            return null;
        }

        $filters = selectcode_to_sqlfragment($_filters, 't_forum_id', 'f_forums', 'f_parent_forum', 't_forum_id', 'id', true, true, $GLOBALS['FORUM_DB']); // Note that the parameters are fiddled here so that category-set and record-set are the same, yet SQL is returned to deal in an entirely different record-set (entries' record-set)

        $sql = 'SELECT t.*';
        if (multi_lang_content()) {
            $sql .= ',t_cache_first_post AS p_post';
        } else {
            $sql .= ',p_post,p_post__text_parsed,p_post__source_user';
        }
        $sql .= ' FROM ' . $GLOBALS['FORUM_DB']->get_table_prefix() . 'f_topics t';
        if (!multi_lang_content()) {
            $sql .= ' LEFT JOIN ' . $GLOBALS['FORUM_DB']->get_table_prefix() . 'f_posts p ON p.id=t.t_cache_first_post_id';
        }
        $sql .= ' WHERE t_cache_last_time>' . strval($cutoff) . (((!has_privilege(get_member(), 'see_unvalidated')) && (addon_installed('unvalidated'))) ? ' AND t_validated=1 ' : '') . ' AND ' . $filters;
        $sql .= ' ORDER BY t_cache_last_time DESC';
        $rows = $GLOBALS['FORUM_DB']->query($sql, $max, 0, false, true);
        $categories = collapse_2d_complexity('id', 'f_name', $GLOBALS['FORUM_DB']->query('SELECT id,f_name FROM ' . $GLOBALS['FORUM_DB']->get_table_prefix() . 'f_forums WHERE f_cache_num_posts>0'));

        $content = new Tempcode();
        foreach ($rows as $row) {
            if ($row['p_post'] === null) {
                continue;
            }

            if ((($row['t_forum_id'] !== null) || ($row['t_pt_to'] == get_member())) && (has_category_access(get_member(), 'forums', strval($row['t_forum_id'])))) {
                $id = strval($row['id']);
                $author = $row['t_cache_first_username'];

                $news_date = date($date_string, $row['t_cache_first_time']);
                $edit_date = date($date_string, $row['t_cache_last_time']);
                if ($edit_date == $news_date) {
                    $edit_date = '';
                }

                $news_title = xmlentities($row['t_cache_first_title']);
                $post_row = db_map_restrict($row, array('p_post')) + array('id' => $row['t_cache_first_post_id']);
                $_summary = get_translated_tempcode('f_posts', $post_row, 'p_post', $GLOBALS['FORUM_DB']);
                $summary = xmlentities($_summary->evaluate());
                $news = '';

                $category = array_key_exists($row['t_forum_id'], $categories) ? $categories[$row['t_forum_id']] : do_lang('NA');
                $category_raw = strval($row['t_forum_id']);

                $view_url = build_url(array('page' => 'topicview', 'id' => $row['id']), get_module_zone('forumview'), array(), false, false, true);

                if ($prefix == 'RSS_') {
                    $if_comments = do_template('RSS_ENTRY_COMMENTS', array('_GUID' => 'f5dd7ba612b989bba5e2d496da5bf161', 'COMMENT_URL' => $view_url, 'ID' => $id), null, false, null, '.xml', 'xml');
                } else {
                    $if_comments = new Tempcode();
                }

                $content->attach(do_template($prefix . 'ENTRY', array('VIEW_URL' => $view_url, 'SUMMARY' => $summary, 'EDIT_DATE' => $edit_date, 'IF_COMMENTS' => $if_comments, 'TITLE' => $news_title, 'CATEGORY_RAW' => $category_raw, 'CATEGORY' => $category, 'AUTHOR' => $author, 'ID' => $id, 'NEWS' => $news, 'DATE' => $news_date), null, false, null, '.xml', 'xml'));
            }
        }

        require_lang('cns');
        return array($content, do_lang('SECTION_FORUMS'));
    }
}
