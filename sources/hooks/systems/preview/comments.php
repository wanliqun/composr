<?php /*

 Composr
 Copyright (c) ocProducts, 2004-2015

 See text/EN/licence.txt for full licencing information.


 NOTE TO PROGRAMMERS:
   Do not edit this file. If you need to make changes, save your changed file to the appropriate *_custom folder
   **** If you ignore this advice, then your website upgrades (e.g. for bug fixes) will likely kill your changes ****

*/

/**
 * @license    http://opensource.org/licenses/cpal_1.0 Common Public Attribution License
 * @copyright  ocProducts Ltd
 * @package    core_feedback_features
 */

/**
 * Hook class.
 */
class Hook_preview_comments
{
    /**
     * Find whether this preview hook applies.
     *
     * @return array Triplet: Whether it applies, the attachment ID type, whether the forum DB is used [optional]
     */
    public function applies()
    {
        $applies = ((addon_installed('cns_forum')) && (get_page_name() != 'topicview') && (post_param_integer('_comment_form_post', 0) == 1) && (is_null(post_param_string('hidFileID_file0', null))) && (is_null(post_param_string('file0', null))));
        return array($applies, null, false);
    }

    /**
     * Run function for preview hooks.
     *
     * @return array A pair: The preview, the updated post Comcode
     */
    public function run()
    {
        // Find review, if there is one
        $individual_review_ratings = array();
        $review_rating = post_param_string('review_rating', '');
        if ($review_rating != '') {
            $individual_review_ratings[''] = array(
                'REVIEW_TITLE' => '',
                'REVIEW_RATING' => $review_rating,
            );
        }

        $poster_name = $GLOBALS['FORUM_DRIVER']->get_username(get_member());
        $post = comcode_to_tempcode(post_param_string('post'));

        // Conversr renderings of poster
        static $hooks = null;
        if (is_null($hooks)) {
            $hooks = find_all_hooks('modules', 'topicview');
        }
        static $hook_objects = null;
        if (is_null($hook_objects)) {
            $hook_objects = array();
            foreach (array_keys($hooks) as $hook) {
                require_code('hooks/modules/topicview/' . filter_naughty_harsh($hook));
                $object = object_factory('Hook_topicview_' . filter_naughty_harsh($hook), true);
                if (is_null($object)) {
                    continue;
                }
                $hook_objects[$hook] = $object;
            }
        }
        if (!is_guest()) {
            require_code('cns_members2');
            $poster_details = render_member_box(get_member(), false, $hooks, $hook_objects, false, null, false);
        } else {
            $custom_fields = new Tempcode();
            $poster_details = new Tempcode();
        }
        if (addon_installed('cns_forum')) {
            if (is_guest()) {
                $poster = do_template('CNS_POSTER_MEMBER', array('_GUID' => 'adbfe268015ca904c3f61020a7b0adde', 'ONLINE' => true, 'ID' => strval(get_member()), 'POSTER_DETAILS' => $poster_details, 'PROFILE_URL' => $GLOBALS['FORUM_DRIVER']->member_profile_url(get_member(), false, true), 'POSTER_USERNAME' => $poster_name));
            } else {
                $poster = do_template('CNS_POSTER_GUEST', array('_GUID' => '3992f4e69ac72a5b57289e5e802f5f48', 'IP_LINK' => '', 'POSTER_DETAILS' => $poster_details, 'POSTER_USERNAME' => $poster_name));
            }
        } else {
            $poster = make_string_tempcode(escape_html($poster_name)); // Should never happen actually, as applies discounts hook from even running
        }

        $highlight = false;
        $datetime_raw = time();
        $datetime = get_timezoned_date(time());
        $poster_url = $GLOBALS['FORUM_DRIVER']->member_profile_url(get_member());
        $title = post_param_string('title', '');
        $tpl = do_template('POST', array(
            '_GUID' => 'fe6913829896c0f0a615ecdb11fc5271',
            'INDIVIDUAL_REVIEW_RATINGS' => $individual_review_ratings,
            'HIGHLIGHT' => $highlight,
            'TITLE' => $title,
            'TIME_RAW' => strval($datetime_raw),
            'TIME' => $datetime,
            'POSTER_URL' => $poster_url,
            'POSTER_NAME' => $poster_name,
            'POST' => $post,
            'POSTER_ID' => strval(get_member()),
            'POSTER' => $poster,
            'POSTER_DETAILS' => $poster_details,
            'ID' => '',
            'CHILDREN' => '',
            'RATING' => '',
            'EMPHASIS' => '',
            'BUTTONS' => '',
            'TOPIC_ID' => '',
            'UNVALIDATED' => '',
            'IS_SPACER_POST' => false,
            'NUM_TO_SHOW_LIMIT' => '0',
            'LAST_EDITED_RAW' => '',
            'LAST_EDITED' => '',
        ));
        return array($tpl, null);
    }
}
