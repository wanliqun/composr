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
 * @package    core
 */

/**
 * Hook class.
 */
class Hook_cron_mail_queue
{
    /**
     * Run function for CRON hooks. Searches for tasks to perform.
     */
    public function run()
    {
        if (get_option('mail_queue_debug') == '0') {
            // Implement basic locking
            if (get_value_newer_than('mailer_currently_dripping', time() - 60 * 5, true) === '1') {
                return;
            }
            set_value('mailer_currently_dripping', '1', true);

            $mails = $GLOBALS['SITE_DB']->query_select(
                'logged_mail_messages',
                array('*'),
                array('m_queued' => 1),
                '',
                100
            );

            if (count($mails) != 0) {
                require_code('mail');

                foreach ($mails as $row) {
                    $subject = $row['m_subject'];
                    $message = $row['m_message'];
                    $to_email = @unserialize($row['m_to_email']);
                    $extra_cc_addresses = ($row['m_extra_cc_addresses'] == '') ? array() : @unserialize($row['m_extra_cc_addresses']);
                    $extra_bcc_addresses = ($row['m_extra_bcc_addresses'] == '') ? array() : @unserialize($row['m_extra_bcc_addresses']);
                    $to_name = @unserialize($row['m_to_name']);
                    $from_email = $row['m_from_email'];
                    $from_name = $row['m_from_name'];
                    $join_time = $row['m_join_time'];

                    if (!is_array($to_email)) {
                        continue;
                    }

                    mail_wrap($subject, $message, $to_email, $to_name, $from_email, $from_name, $row['m_priority'], unserialize($row['m_attachments']), $row['m_no_cc'] == 1, $row['m_as'], $row['m_as_admin'] == 1, $row['m_in_html'] == 1, true, $row['m_template'], false, $extra_cc_addresses, $extra_bcc_addresses, $join_time);

                    $GLOBALS['SITE_DB']->query_update('logged_mail_messages', array('m_queued' => 0), array('id' => $row['id']), '', 1);
                }

                decache('main_staff_checklist');
            }

            set_value('mailer_currently_dripping', '0', true);
        }
    }
}
