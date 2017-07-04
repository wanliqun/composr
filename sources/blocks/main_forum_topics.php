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
 * @package    forum_blocks
 */

/**
 * Block class.
 */
class Block_main_forum_topics
{
    /**
     * Find details of the block.
     *
     * @return ?array Map of block info (null: block is disabled).
     */
    public function info()
    {
        $info = array();
        $info['author'] = 'Chris Graham';
        $info['organisation'] = 'ocProducts';
        $info['hacked_by'] = null;
        $info['hack_version'] = null;
        $info['version'] = 2;
        $info['locked'] = false;
        $info['parameters'] = array('param', 'limit', 'hot', 'date_key', 'username_key', 'title', 'check');
        return $info;
    }

    /**
     * Find caching details for the block.
     *
     * @return ?array Map of cache details (cache_on and ttl) (null: block is disabled).
     */
    public function caching_environment()
    {
        $info = array();
        $info['cache_on'] = 'array(array_key_exists(\'check\',$map)?$map[\'check\']:\'0\',array_key_exists(\'title\',$map)?$map[\'title\']:\'\',array_key_exists(\'hot\',$map)?$map[\'hot\']:\'0\',array_key_exists(\'param\',$map)?$map[\'param\']:do_lang(\'DEFAULT_FORUM_TITLE\'),array_key_exists(\'limit\',$map)?$map[\'limit\']:6,array_key_exists(\'date_key\',$map)?$map[\'date_key\']:\'lasttime\',array_key_exists(\'username_key\',$map)?$map[\'username_key\']:\'firstusername\')';
        $info['ttl'] = (get_value('no_block_timeout') === '1') ? 60 * 60 * 24 * 365 * 5/*5 year timeout*/ : 10;
        return $info;
    }

    /**
     * Execute the block.
     *
     * @param  array $map A map of parameters.
     * @return Tempcode The result of execution.
     */
    public function run($map)
    {
        if (has_no_forum()) {
            return new Tempcode();
        }

        require_css('news');

        $block_id = get_block_id($map);

        // Read in variables
        $forum_name = empty($map['param']) ? do_lang('DEFAULT_FORUM_TITLE') : $map['param'];
        $limit = array_key_exists('limit', $map) ? intval($map['limit']) : 6;
        $hot = array_key_exists('hot', $map) ? intval($map['hot']) : 0;
        $date_key = array_key_exists('date_key', $map) ? $map['date_key'] : 'lasttime';
        if (($date_key != 'lasttime') && ($date_key != 'firsttime')) {
            $date_key = 'firsttime';
        }
        $username_key = array_key_exists('username_key', $map) ? $map['username_key'] : 'firstusername';
        if (($username_key != 'lastusername') && ($username_key != 'firstusername')) {
            $username_key = 'firstusername';
        }
        $memberid_key = ($username_key == 'firstusername') ? 'firstmemberid' : 'lastmemberid';

        // Work out exactly what forums we're reading
        $forum_ids = array();
        if ((get_forum_type() == 'cns') && ((strpos($forum_name, ',') !== false) || (strpos($forum_name, '*') !== false) || (preg_match('#\d[-\*\+]#', $forum_name) != 0) || (is_numeric($forum_name)))) {
            require_code('selectcode');
            $forum_names = selectcode_to_idlist_using_db($forum_name, 'id', 'f_forums', 'f_forums', 'f_parent_forum', 'f_parent_forum', 'id', true, true, $GLOBALS['FORUM_DB']);
        } else {
            $forum_names = explode(',', $forum_name);
        }

        foreach ($forum_names as $forum_name) {
            if (!is_string($forum_name)) {
                $forum_name = strval($forum_name);
            }

            $forum_name = trim($forum_name);

            if ($forum_name == '<announce>') {
                $forum_id = null;
            } else {
                $forum_id = is_numeric($forum_name) ? intval($forum_name) : $GLOBALS['FORUM_DRIVER']->forum_id_from_name($forum_name);
            }

            if ((get_forum_type() == 'cns') && (array_key_exists('check', $map)) && ($map['check'] == '1')) {
                if (!has_category_access(get_member(), 'forums', strval($forum_id))) {
                    continue;
                }
            }

            if ($forum_id !== null) {
                $forum_ids[$forum_id] = $forum_name;
            }
        }

        // Block title
        if ((is_numeric($forum_name)) && (get_forum_type() == 'cns')) {
            $forum_name = $GLOBALS['FORUM_DB']->query_select_value_if_there('f_forums', 'f_name', array('id' => intval($forum_name)));
            if ($forum_name === null) {
                return paragraph(do_lang_tempcode('MISSING_RESOURCE', 'topic'), '', 'red_alert');
            }
        }
        $_title = do_lang_tempcode('ACTIVE_TOPICS_IN', escape_html($forum_name));
        if ((array_key_exists('title', $map)) && ($map['title'] != '')) {
            $_title = protect_from_escaping(escape_html($map['title']));
        }

        // Add topic link
        if ((count($forum_names) == 1) && (get_forum_type() == 'cns') && ($forum_id !== null)) {
            $submit_url = build_url(array('page' => 'topics', 'type' => 'new_topic', 'id' => $forum_id), get_module_zone('topics'));
            $add_name = do_lang_tempcode('ADD_TOPIC');
        } else {
            $submit_url = new Tempcode();
            $add_name = new Tempcode();
        }

        // Show all topics
        if (get_forum_type() == 'cns') {
            $forum_names_map = collapse_2d_complexity('id', 'f_name', $GLOBALS['FORUM_DB']->query('SELECT id,f_name FROM ' . $GLOBALS['FORUM_DB']->get_table_prefix() . 'f_forums WHERE f_cache_num_posts>0'));
        } else {
            $forum_names_map = null;
        }
        if (!has_no_forum()) {
            $max_rows = 0;
            $topics = $GLOBALS['FORUM_DRIVER']->show_forum_topics($forum_ids, $limit, 0, $max_rows, '', true, $date_key, $hot == 1);

            $_topics = array();
            if ($topics !== null) {
                sort_maps_by($topics, $date_key);
                $topics = array_reverse($topics, false);

                if ((count($topics) < $limit) && ($hot == 1)) {
                    $more_topics = $GLOBALS['FORUM_DRIVER']->show_forum_topics($forum_ids, $limit, 0, $max_rows, '', true, $date_key);
                    if ($more_topics === null) {
                        $more_topics = array();
                    }
                    $topics = array_merge($topics, $more_topics);
                }

                $done = 0;
                $seen = array();
                foreach ($topics as $topic) {
                    if (array_key_exists($topic['id'], $seen)) {
                        continue;
                    }
                    $seen[$topic['id']] = 1;

                    $topic_url = $GLOBALS['FORUM_DRIVER']->topic_url($topic['id'], $forum_name, true);
                    $topic_url_unread = mixed();
                    if (get_forum_type() == 'cns') {
                        $topic_url_unread = build_url(array('page' => 'topicview', 'type' => 'first_unread', 'id' => $topic['id']), get_module_zone('topicview'), array(), false, false, false, 'first_unread');
                    }
                    $title = escape_html($topic['title']);
                    $date = get_timezoned_date_time_tempcode($topic[$date_key]);
                    $username = $topic[$username_key];
                    $member_id = array_key_exists($memberid_key, $topic) ? $topic[$memberid_key] : null;
                    if (($forum_names_map !== null) && (!array_key_exists($topic['forum_id'], $forum_names_map))) {
                        continue; // Maybe Private Topic, slipped in via reference to a missing forum
                    }
                    $forum_name = ($forum_names_map === null) ? null : $forum_names_map[$topic['forum_id']];

                    $_topics[] = array(
                        'POST' => $topic['firstpost'],
                        'FORUM_ID' => ($forum_names_map === null) ? null : strval($topic['forum_id']),
                        'FORUM_NAME' => $forum_name,
                        'TOPIC_URL' => $topic_url,
                        'TOPIC_URL_UNREAD' => $topic_url_unread,
                        'TITLE' => $title,
                        'DATE' => $date,
                        'DATE_RAW' => strval($topic[$date_key]),
                        'USERNAME' => $username,
                        'MEMBER_ID' => ($member_id === null) ? '' : strval($member_id),
                        'NUM_POSTS' => integer_format($topic['num']),
                    );

                    $done++;

                    if ($done == $limit) {
                        break;
                    }
                }
            }
            if ($_topics === array()) {
                return do_template('BLOCK_NO_ENTRIES', array(
                    '_GUID' => 'c76ab018a0746c2875c6cf69c92a01fb',
                    'BLOCK_ID' => $block_id,
                    'HIGH' => false,
                    'FORUM_NAME' => array_key_exists('param', $map) ? $map['param'] : do_lang('DEFAULT_FORUM_TITLE'),
                    'TITLE' => $_title,
                    'MESSAGE' => do_lang_tempcode(($hot == 1) ? 'NO_TOPICS_HOT' : 'NO_TOPICS'),
                    'ADD_NAME' => $add_name,
                    'SUBMIT_URL' => $submit_url,
                ));
            }

            return do_template('BLOCK_MAIN_FORUM_TOPICS', array(
                '_GUID' => '368b80c49a335ad035b00510681d5008',
                'BLOCK_ID' => $block_id,
                'TITLE' => $_title,
                'TOPICS' => $_topics,
                'FORUM_NAME' => array_key_exists('param', $map) ? $map['param'] : do_lang('DEFAULT_FORUM_TITLE'),
                'SUBMIT_URL' => $submit_url,
            ));
        } else {
            return new Tempcode();
        }
    }
}
