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
 * @package    core_cns
 */

/**
 * Hook class.
 */
class Hook_cron_cns_birthdays
{
    /**
     * Run function for Cron hooks. Searches for tasks to perform.
     */
    public function run()
    {
        $this_birthday_day = date('d/m/Y', tz_time(time(), get_site_timezone()));
        if (get_value('last_birthday_day', null, true) !== $this_birthday_day) {
            set_value('last_birthday_day', $this_birthday_day, true);

            require_lang('cns');

            require_code('cns_general');
            $_birthdays = cns_find_birthdays();

            $combined_birthdays_mail = '';
            foreach ($_birthdays as $_birthday) {
                $member_url = $GLOBALS['CNS_DRIVER']->member_profile_url($_birthday['id'], true);

                $username = $_birthday['username'];
                $displayname = $GLOBALS['CNS_DRIVER']->get_displayname($username);

                $birthday_url = build_url(array('page' => 'topics', 'type' => 'birthday', 'id' => $_birthday['username']), get_module_zone('topics'));

                require_code('notifications');

                $subject = do_lang('BIRTHDAY_NOTIFICATION_MAIL_SUBJECT', get_site_name(), $displayname, $username);
                $mail = do_notification_lang(
                    'BIRTHDAY_NOTIFICATION_MAIL',
                    comcode_escape(get_site_name()),
                    comcode_escape($username),
                    array(
                        $member_url->evaluate(),
                        $birthday_url->evaluate(),
                        comcode_escape($displayname),
                    )
                );

                $combined_birthdays_mail .= do_lang(
                    'COMBINED_BIRTHDAY_NOTIFICATION_MAIL_ITEM',
                    comcode_escape(get_site_name()),
                    comcode_escape($username),
                    array(
                        $member_url->evaluate(),
                        $birthday_url->evaluate(),
                        comcode_escape($displayname),
                    )
                );

                if (addon_installed('chat')) {
                    $friends = $GLOBALS['SITE_DB']->query_select('chat_friends', array('member_likes'), array('member_liked' => $_birthday['id']));
                    dispatch_notification('cns_friend_birthday', null, $subject, $mail, collapse_1d_complexity('member_likes', $friends));
                }

                if (count($_birthdays) == 1) {
                    dispatch_notification('cns_birthday', null, $subject, $mail);
                }
            }

            if (count($_birthdays) > 1) {
                $combined_birthdays_subject = do_lang('COMBINED_BIRTHDAY_NOTIFICATION_MAIL_SUBJECT', get_site_name(), integer_format(count($_birthdays)));
                $combined_birthdays_mail = do_notification_lang('COMBINED_BIRTHDAY_NOTIFICATION_MAIL', comcode_escape(get_site_name()), $combined_birthdays_mail, comcode_escape(integer_format(count($_birthdays))));

                dispatch_notification('cns_birthday', null, $combined_birthdays_subject, $combined_birthdays_mail);
            }
        }
    }
}
