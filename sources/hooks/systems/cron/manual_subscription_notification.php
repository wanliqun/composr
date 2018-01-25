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
 * @package    ecommerce
 */

/**
 * Hook class.
 */
class Hook_cron_manual_subscription_notification
{
    /**
     * Run function for Cron hooks. Searches for tasks to perform.
     */
    public function run()
    {
        /*
        Send staff notifications for expiring manual notifications.
        This might be used by the staff in order to get someone to send in a cheque, for example.
        */

        $_last_time = get_value('last_cron_manual_subscription_notification', null, true);
        $last_time = ($_last_time === null) ? null : intval($_last_time);
        if ($last_time !== null) {
            if ($last_time < 60 * 60 * 24) {
                return; // Only do once per day
            }
        }

        if (get_option('manual_subscription_expiry_notice') == '') {
            return;
        }
        $manual_subscription_expiry_notice = intval(get_option('manual_subscription_expiry_notice'));

        require_lang('ecommerce');

        $max = 1000;
        $start = 0;
        do {
            $subscribers = $GLOBALS['SITE_DB']->query_select('ecom_subscriptions', array('DISTINCT s_member_id'), array('s_state' => 'active'), '', $max, $start);
            foreach ($subscribers as $subscriber) {
                $member_id = $subscriber['s_member_id'];

                require_code('ecommerce_subscriptions');
                $subscriptions = find_member_subscriptions($member_id);
                foreach ($subscriptions as $subscription) {
                    $expiry_time = $subscription['expiry_time'];
                    if (($expiry_time !== null) && (($expiry_time - time()) < ($manual_subscription_expiry_notice * 24 * 60 * 60)) && ($expiry_time >= time())) {
                        if ($last_time !== null) {
                            if (($expiry_time - $last_time) < ($manual_subscription_expiry_notice * 24 * 60 * 60)) {
                                continue; // Notification already sent!
                            }
                        }

                        if (($expiry_time - time()) < ($manual_subscription_expiry_notice * 24 * 60 * 60)) {
                            $expiry_date = get_timezoned_date_time($expiry_time, false);
                            $member_name = $GLOBALS['FORUM_DRIVER']->get_username($member_id, false, USERNAME_DEFAULT_NULL);
                            if ($member_name !== null) { // If not a deleted member
                                $member_profile_url = $GLOBALS['CNS_DRIVER']->member_profile_url($member_id, false);
                                $cancel_url = build_url(array('page' => 'admin_ecommerce_logs', 'type' => 'cancel_subscription', 'subscription_id' => $subscription['subscription_id']), get_module_zone('admin_ecommerce'), array(), false, false, true);

                                $item_name = $subscription['item_name'];

                                require_code('notifications');
                                $subject = do_lang('MANUAL_SUBSCRIPTION_NOTIFICATION_MAIL_SUBJECT', $member_name, $expiry_date, array($item_name));
                                $mail = do_notification_lang('MANUAL_SUBSCRIPTION_NOTIFICATION_MAIL', comcode_escape($member_profile_url), comcode_escape($cancel_url->evaluate()), array(strval($manual_subscription_expiry_notice), comcode_escape($member_name), comcode_escape($expiry_date), comcode_escape($item_name)));

                                dispatch_notification('paid_subscription_messages', null, $subject, $mail);
                            }
                        }
                    }
                }
            }

            $start += $max;
        } while (count($subscribers) == $max);

        set_value('last_cron_manual_subscription_notification', strval(time()), true);
    }
}
