<?php /*

 Composr
 Copyright (c) ocProducts, 2004-2018

 See text/EN/licence.txt for full licensing information.

*/

/**
 * @license    http://opensource.org/licenses/cpal_1.0 Common Public Attribution License
 * @copyright  ocProducts Ltd
 * @package    bantr
 */

/**
 * Hook class.
 */
class Hook_cron_insults
{
    /**
     * Get info from this hook.
     *
     * @param  ?TIME $last_run Last time run (null: never)
     * @param  boolean $calculate_num_queued Calculate the number of items queued, if possible
     * @return ?array Return a map of info about the hook (null: disabled)
     */
    public function info($last_run, $calculate_num_queued)
    {
        if (!addon_installed('bantr')) {
            return null;
        }

        return array(
            'label' => 'Bantr',
            'num_queued' => null,
            'minutes_between_runs' => 24 * 60,
        );
    }

    /**
     * Run function for system scheduler scripts. Searches for things to do. ->info(..., true) must be called before this method.
     *
     * @param  ?TIME $last_run Last time run (null: never)
     */
    public function run($last_run)
    {
        require_lang('insults');

        // How many points a correct response will give
        $_insult_points = get_option('insult_points', true);
        $insult_points = (isset($_insult_points) && is_numeric($_insult_points)) ? intval($_insult_points) : 10;

        // Who to insult?
        $sql = 'SELECT id FROM ' . $GLOBALS['FORUM_DB']->get_table_prefix() . 'f_members WHERE id<>' . strval($GLOBALS['FORUM_DRIVER']->get_guest_id()) . ' AND ' . db_string_equal_to('m_validated_email_confirm_code', '');
        if (addon_installed('unvalidated')) {
            $sql .= ' AND m_validated=1';
        }
        $sql .= ' ORDER BY ' . db_function('RAND');
        $selected_members = $GLOBALS['FORUM_DB']->query($sql, 2);
        $selected_member1 = (isset($selected_members[0]['id']) && $selected_members[0]['id'] > 0) ? $selected_members[0]['id'] : 0;
        $selected_member2 = (isset($selected_members[1]['id']) && $selected_members[1]['id'] > 0) ? $selected_members[1]['id'] : 0;

        // Send insult to picked members
        if ($selected_member1 != 0 && $selected_member2 != 0) {
            $get_insult = '';
            if (is_file(get_file_base() . '/text_custom/' . user_lang() . '/insults.txt')) {
                $insults = file(get_file_base() . '/text_custom/' . user_lang() . '/insults.txt');
                // TODO: #3467
                $insults_array = array();
                foreach ($insults as $insult) {
                    $x = explode('=', $insult);
                    $insults_array[] = $x[0];
                }

                $rand_key = array_rand($insults_array, 1);
                $rand_key = is_array($rand_key) ? $rand_key[0] : $rand_key;

                $get_insult = $insults_array[$rand_key];
            }

            if ($get_insult != '') {
                global $SITE_INFO;

                $displayname1 = $GLOBALS['FORUM_DRIVER']->get_username($selected_member1, true);
                $displayname2 = $GLOBALS['FORUM_DRIVER']->get_username($selected_member2, true);
                $username1 = $GLOBALS['FORUM_DRIVER']->get_username($selected_member1);
                $username2 = $GLOBALS['FORUM_DRIVER']->get_username($selected_member2);

                $insult_pt_topic_post = do_lang('INSULT_EXPLANATION', get_site_name(), $get_insult, array(integer_format($insult_points), $displayname2, $displayname1, $username2, $username1));

                $subject = do_lang('INSULT_PT_TOPIC', $displayname2, $displayname1, array($username2, $username1));

                require_code('cns_topics_action');
                $topic_id = cns_make_topic(null, '', '', 1, 1, 0, 0, $selected_member2, $selected_member1, true, 0, null, '');

                require_code('cns_posts_action');
                $post_id = cns_make_post($topic_id, $subject, $insult_pt_topic_post, 0, true, 1, 0, do_lang('SYSTEM'), null, null, $GLOBALS['FORUM_DRIVER']->get_guest_id(), null, null, null, false, true, null, true, $subject, null, true, true, true);

                require_code('cns_topics_action2');
                send_pt_notification($post_id, $subject, $topic_id, $selected_member2, $selected_member1);
            }
        }
    }
}
