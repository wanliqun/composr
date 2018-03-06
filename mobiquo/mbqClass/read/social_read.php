<?php /*

 Composr
 Copyright (c) ocProducts, 2004-2018

 See text/EN/licence.txt for full licensing information.

*/

/**
 * @license    http://opensource.org/licenses/cpal_1.0 Common Public Attribution License
 * @copyright  ocProducts Ltd
 * @package    cns_tapatalk
 */

/*EXTRA FUNCTIONS: render_activity*/

/**
 * Composr API helper class.
 */
class CMSSocialRead
{
    /**
     * Get members being followed by current member.
     *
     * @return array Details of members
     */
    public function get_following()
    {
        cms_verify_parameters_phpdoc();

        require_code('users2');

        if (is_guest()) {
            return array();
        }

        if (!addon_installed('chat')) {
            return array();
        }

        $rows = $GLOBALS['SITE_DB']->query_select('chat_friends', array('member_liked'), array('member_likes' => get_member()));
        $followers = array();
        foreach ($rows as $row) {
            $username = $GLOBALS['FORUM_DRIVER']->get_username($row['member_liked'], false, USERNAME_DEFAULT_NULL);
            if ($username === null) {
                continue;
            }

            $arr = array(
                'user_id' => $row['member_liked'],
                'username' => $username,
                'display_text' => $GLOBALS['FORUM_DRIVER']->get_username($row['member_liked'], true),
                'is_online' => member_is_online($row['member_liked']),
            );

            $display_text = $GLOBALS['FORUM_DRIVER']->get_username($row['member_liked'], true);
            if ($display_text != $username) {
                $arr += array(
                    'display_text' => mobiquo_val($display_text, 'base64'),
                );
            }

            $followers[] = $arr;
        }
        return $followers;
    }

    /**
     * Get members following current member.
     *
     * @return array Details of members
     */
    public function get_followers()
    {
        cms_verify_parameters_phpdoc();

        require_code('users2');

        if (is_guest()) {
            return array();
        }

        if (!addon_installed('chat')) {
            return array();
        }

        $rows = $GLOBALS['SITE_DB']->query_select('chat_friends', array('member_likes'), array('member_liked' => get_member()));
        $followers = array();
        foreach ($rows as $row) {
            $username = $GLOBALS['FORUM_DRIVER']->get_username($row['member_likes'], false, USERNAME_DEFAULT_NULL);
            if ($username === null) {
                continue;
            }

            $arr = array(
                'user_id' => $row['member_likes'],
                'username' => $username,
                'display_text' => $GLOBALS['FORUM_DRIVER']->get_username($row['member_likes'], true),
                'is_online' => member_is_online($row['member_likes']),
            );

            $display_text = $GLOBALS['FORUM_DRIVER']->get_username($row['member_likes'], true);
            if ($display_text != $username) {
                $arr += array(
                    'display_text' => mobiquo_val($display_text, 'base64'),
                );
            }

            $followers[] = $arr;
        }
        return $followers;
    }

    /**
     * Get details of notifications.
     *
     * @param  integer $start Start position
     * @param  integer $max Maximum results
     * @return array A pair: total notifications, notifications
     */
    public function get_alerts($start, $max)
    {
        cms_verify_parameters_phpdoc();

        if (is_guest()) {
            return array(0, array());
        }

        $where = array(
            'd_frequency' => A_WEB_NOTIFICATION,
            'd_to_member_id' => get_member(),
        );
        $rows = $GLOBALS['SITE_DB']->query_select('digestives_tin', array('*'), $where, '', $max, $start);
        $total = $GLOBALS['SITE_DB']->query_select_value('digestives_tin', 'COUNT(*)', $where);

        $items = array();
        foreach ($rows as $row) {
            if (is_guest($row['d_from_member_id'])) {
                $username = do_lang('SYSTEM');
            } else {
                $username = $GLOBALS['FORUM_DRIVER']->get_username($row['d_from_member_id']);
            }

            $arr = array(
                'user_id' => $row['d_from_member_id'],
                'username' => $username,
                'icon_url' => $GLOBALS['FORUM_DRIVER']->get_member_avatar_url($row['d_from_member_id']),
                'message' => $row['d_subject'],
                'timestamp' => $row['d_date_and_time'],
                'content_type' => $row['d_notification_code'],
                'content_id' => $row['d_code_category'],
                'unread' => ($row['d_read'] == 0),
            );

            // Try and extract topic from URL in notification
            $matches = array();
            $num_matches = preg_match_all('#\[url[^\[\]]*\](.*)\[/url\]#U', $row['d_message'], $matches);
            for ($i = 0; $i < $num_matches; $i++) {
                $test = get_id_by_url($matches[1][$i]);
                if ($test !== null) {
                    $arr['topic_id'] = strval($test['topic_id']);
                    $arr['position'] = $GLOBALS['FORUM_DB']->query_value_if_there('SELECT COUNT(*) FROM ' . $GLOBALS['FORUM_DB']->get_table_prefix() . 'f_posts WHERE id<=' . strval($test['post_id']));
                    break;
                }
            }

            $items[] = $arr;
        }

        return array($total, $items);
    }

    /**
     * Get details of activity.
     *
     * @param  integer $start Start position
     * @param  integer $max Maximum results
     * @return array A pair: total activity, activities
     */
    public function get_activity($start, $max)
    {
        cms_verify_parameters_phpdoc();

        require_code('activities');

        // Right now Tapatalk only supports topic and post activity

        $where_str = ' WHERE a_language_string_code IN (\'cns:ACTIVITY_ADD_TOPIC\',\'cns:ACTIVITY_ADD_POST_IN\')'; // ,\'ACTIVITY_LIKES\'

        $total = $GLOBALS['SITE_DB']->query_value_if_there('SELECT COUNT(*) FROM ' . get_table_prefix() . 'activities' . $where_str);
        $rows = $GLOBALS['SITE_DB']->query('SELECT * FROM ' . get_table_prefix() . 'activities' . $where_str . ' ORDER BY a_time DESC', $max, $start);

        $items = array();
        foreach ($rows as $row) {
            $username = $GLOBALS['FORUM_DRIVER']->get_username($row['a_member_id']);

            list($message, $memberpic, $date, $member_url) = render_activity($row, false);

            switch ($row['a_language_string_code']) {
                case 'cns:ACTIVITY_ADD_TOPIC':
                    $content_type = 'thread';
                    $content_id = preg_replace('#^.*:topicview:browse:(\d+).*$#', '$1', $row['a_pagelink_1']);
                    break;
                case 'cns:ACTIVITY_ADD_POST_IN':
                    $content_type = 'post';
                    $content_id = preg_replace('#^.*:topicview:browse:(\d+)\#post_(\d+).*$#', '$2', $row['a_pagelink_1']);
                    break;
                /*case 'ACTIVITY_LIKES':    No likes actually
                    $content_type = 'like';
                    $content_id = preg_replace('#:topicview:findpost:(\d+)#', '$1', $row['a_pagelink_1']);
                    break;*/
            }

            $items[] = array(
                'user_id' => $row['a_member_id'],
                'username' => $username,
                'icon_url' => $memberpic,
                'message' => strip_html($message->evaluate()),
                'timestamp' => $date,
                'content_type' => $content_type,
                'content_id' => $content_id,
            );
        }

        return array($total, $items);
    }
}
