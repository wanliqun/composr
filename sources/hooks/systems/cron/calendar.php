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
 * @package    calendar
 */

/**
 * Hook class.
 */
class Hook_cron_calendar
{
    /**
     * Run function for Cron hooks. Searches for tasks to perform.
     */
    public function run()
    {
        if (!addon_installed('calendar')) {
            return;
        }

        require_code('calendar');
        require_lang('calendar');
        require_code('notifications');

        $start = 0;
        do {
            $jobs = $GLOBALS['SITE_DB']->query('SELECT *,j.id AS id FROM ' . $GLOBALS['SITE_DB']->get_table_prefix() . 'calendar_jobs j LEFT JOIN ' . $GLOBALS['SITE_DB']->get_table_prefix() . 'calendar_events e ON e.id=j.j_event_id LEFT JOIN ' . $GLOBALS['SITE_DB']->get_table_prefix() . 'calendar_reminders n ON n.id=j.j_reminder_id WHERE validated=1 AND j_time<' . strval(time()), 300, $start);
            $or_list = '';
            foreach ($jobs as $job) {
                // Build up OR list of the jobs
                if ($or_list != '') {
                    $or_list .= ' OR ';
                }
                $or_list .= 'id=' . strval($job['id']);

                $recurrences = find_periods_recurrence($job['e_timezone'], 1, $job['e_start_year'], $job['e_start_month'], $job['e_start_day'], $job['e_start_monthly_spec_type'], ($job['e_start_hour'] === null) ? find_timezone_start_hour_in_utc($job['e_timezone'], $job['e_start_year'], $job['e_start_month'], $job['e_start_day'], $job['e_start_monthly_spec_type']) : $job['e_start_hour'], ($job['e_start_minute'] === null) ? find_timezone_start_minute_in_utc($job['e_timezone'], $job['e_start_year'], $job['e_start_month'], $job['e_start_day'], $job['e_start_monthly_spec_type']) : $job['e_start_minute'], $job['e_end_year'], $job['e_end_month'], $job['e_end_day'], $job['e_end_monthly_spec_type'], ($job['e_end_hour'] === null) ? find_timezone_end_hour_in_utc($job['e_timezone'], $job['e_end_year'], $job['e_end_month'], $job['e_end_day'], $job['e_end_monthly_spec_type']) : $job['e_end_hour'], ($job['e_end_minute'] === null) ? find_timezone_end_minute_in_utc($job['e_timezone'], $job['e_end_year'], $job['e_end_month'], $job['e_end_day'], $job['e_end_monthly_spec_type']) : $job['e_end_minute'], $job['e_recurrence'], min(1, $job['e_recurrences']));

                $start_day_of_month = find_concrete_day_of_month($job['e_start_year'], $job['e_start_month'], $job['e_start_day'], $job['e_start_monthly_spec_type'], ($job['e_start_hour'] === null) ? find_timezone_start_hour_in_utc($job['e_timezone'], $job['e_start_year'], $job['e_start_month'], $job['e_start_day'], $job['e_start_monthly_spec_type']) : $job['e_start_hour'], ($job['e_start_minute'] === null) ? find_timezone_start_minute_in_utc($job['e_timezone'], $job['e_start_year'], $job['e_start_month'], $job['e_start_day'], $job['e_start_monthly_spec_type']) : $job['e_start_minute'], $job['e_timezone'], $job['e_do_timezone_conv'] == 1);

                // Dispatch
                if ($job['j_reminder_id'] === null) { // It's code/URL
                    //if (!has_actual_page_access($job['e_submitter'], 'admin_commandr')) continue; // Someone was admin but isn't anymore          Actually, really ex-admins could have placed lots of other kinds of traps. It's the responsibility of the staff to check this on a wider basis. There's no use creating tangental management complexity for just one case.
                    if ($job['e_type'] != db_get_first_id()) {
                        continue; // Very strange
                    }

                    $job_text = get_translated_text($job['e_content']);
                    if (substr($job_text, 0, 7) == 'http://' || substr($job_text, 0, 8) == 'https://') { // It's a URL
                        require_code('character_sets');
                        $http_result = cms_http_request($job_text);
                        echo convert_to_internal_encoding($http_result->data, $http_result->charset);
                    } elseif (addon_installed('commandr')) { // It's code
                        if ($GLOBALS['CURRENT_SHARE_USER'] === null) {
                            // Backwards-compatibility for pure PHP code (if its creation date was before the time of writing this comment [Wednesday 22nd Match, 14:58])
                            if ($job['e_add_date'] < 1143046670) {
                                safe_ini_set('ocproducts.xss_detect', '0');
                                $to_echo = eval($job_text);
                                if ($to_echo === false) {
                                    fatal_exit(@strval($php_errormsg));
                                }
                            } else {
                                $GLOBALS['_EVENT_TIMESTAMP'] = array_key_exists(0, $recurrences) ? usertime_to_utctime($recurrences[0][0]) : mktime($job['e_start_hour'], $job['e_start_minute'], 0, $job['e_start_month'], $start_day_of_month, $job['e_start_year']);
                                $GLOBALS['event_timestamp'] = $GLOBALS['_EVENT_TIMESTAMP']; // LEGACY with ocPortal go-live dates

                                // Commandr code
                                require_code('commandr');
                                $temp = new Virtual_shell($job_text);
                                $output = $temp->output_html(true);
                                if (is_object($output)) {
                                    echo $output->evaluate();
                                }
                            }
                        }
                    }

                    $job['n_seconds_before'] = 0;
                } else {
                    // Send notification
                    if (!has_category_access($job['n_member_id'], 'calendar', strval($job['e_type']))) {
                        continue;
                    }
                    $title = get_translated_text($job['e_title']);
                    $timestamp = array_key_exists(0, $recurrences) ? usertime_to_utctime($recurrences[0][0]) : mktime($job['e_start_hour'], $job['e_start_minute'], 0, $job['e_start_month'], $start_day_of_month, $job['e_start_year']);
                    $date = get_timezoned_date_time($timestamp, true, false, $job['n_member_id']);
                    $_url = build_url(array('page' => 'calendar', 'type' => 'view', 'id' => $job['j_event_id']), get_module_zone('calendar'), array(), false, false, true);
                    $url = $_url->evaluate();
                    $subject_line = do_lang('EVENT_REMINDER_SUBJECT', $title, null, null, get_lang($job['n_member_id']));
                    $message_raw = do_notification_lang('EVENT_REMINDER_CONTENT', comcode_escape($date), comcode_escape($url), get_translated_text($job['e_content']), get_lang($job['n_member_id']));
                    dispatch_notification('calendar_reminder', strval($job['e_type']), $subject_line, $message_raw, array($job['n_member_id']), $job['e_submitter']);
                }

                // Recreate job for when next reminder due (if appropriate)
                if (array_key_exists(1, $recurrences)) {
                    $GLOBALS['SITE_DB']->query_insert('calendar_jobs', array(
                        'j_time' => usertime_to_utctime($recurrences[1][0]) - $job['n_seconds_before'],
                        'j_reminder_id' => $job['j_reminder_id'],
                        'j_member_id' => $job['j_member_id'],
                        'j_event_id' => $job['j_event_id'],
                    ));
                }
            }

            // Delete jobs just run
            if ($or_list != '') {
                $GLOBALS['SITE_DB']->query('DELETE FROM ' . $GLOBALS['SITE_DB']->get_table_prefix() . 'calendar_jobs WHERE ' . $or_list, null, 0, false, true);
            }

            //$start += 300;    No, we just deleted, so offsets would have changed
        } while (array_key_exists(0, $jobs));
    }
}
