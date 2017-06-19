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
 * @package    cns_forum
 */

/**
 * Hook class.
 */
class Hook_whatsnew_cns_forumview
{
    /**
     * Find selectable (filterable) categories.
     *
     * @param  TIME $updated_since The time that there must be entries found newer than
     * @return ?array Tuple of result details: HTML list of all types that can be choosed, title for selection list (null: disabled)
     */
    public function choose_categories($updated_since)
    {
        if (get_forum_type() != 'cns') {
            return null;
        }

        require_code('cns_forums2');
        $cats = create_selection_list_forum_tree(null, null, null, false, null, $updated_since);
        return array($cats, do_lang('SECTION_FORUMS'));
    }

    /**
     * Run function for newsletter hooks.
     *
     * @param  TIME $cutoff_time The time that the entries found must be newer than
     * @param  LANGUAGE_NAME $lang The language the entries found must be in
     * @param  string $filter Category filter to apply
     * @return array Tuple of result details
     */
    public function run($cutoff_time, $lang, $filter)
    {
        $max = intval(get_option('max_newsletter_whatsnew'));

        $new = new Tempcode();

        if (get_forum_type() != 'cns') {
            return array();
        }

        require_code('selectcode');
        $or_list = selectcode_to_sqlfragment($filter, 't_forum_id');
        $rows = $GLOBALS['FORUM_DB']->query('SELECT * FROM ' . $GLOBALS['FORUM_DB']->get_table_prefix() . 'f_topics WHERE t_cache_last_time>' . strval($cutoff_time) . ' AND t_validated=1 AND t_pt_to IS NULL AND t_pt_from IS NULL AND (' . $or_list . ') ORDER BY t_cache_last_time DESC', $max);
        if (count($rows) == $max) {
            return array();
        }
        foreach ($rows as $row) {
            if (has_category_access($GLOBALS['FORUM_DRIVER']->get_guest_id(), 'forums', strval($row['t_forum_id']))) {
                $id = $row['id'];
                $_url = build_url(array('page' => 'topicview', 'type' => 'browse', 'id' => $row['id']), get_module_zone('topicview'), array(), false, false, true);
                $url = $_url->evaluate();
                $name = $row['t_cache_first_title'];
                $member_id = (is_guest($row['t_cache_first_member_id'])) ? null : strval($row['t_cache_first_member_id']);
                $new->attach(do_template('NEWSLETTER_WHATSNEW_RESOURCE_FCOMCODE', array('_GUID' => '14a328f973ac44eb54aa9b31e5a4ae34', 'MEMBER_ID' => $member_id, 'URL' => $url, 'NAME' => $name, 'CONTENT_TYPE' => 'topic', 'CONTENT_ID' => strval($id)), null, false, null, '.txt', 'text'));

                handle_has_checked_recently($url); // We know it works, so mark it valid so as to not waste CPU checking within the generated Comcode
            }
        }

        return array($new, do_lang('SECTION_FORUMS', '', '', '', $lang));
    }
}
