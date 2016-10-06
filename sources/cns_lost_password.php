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
 * @package    core_cns
 */

/**
 * Find what password reset process will be used
 *
 * @return ID_TEXT Password reset process codename
 */
function get_password_reset_process()
{
    $password_reset_process = get_option('password_reset_process');
    if ($password_reset_process == 'ultra' && get_option('smtp_sockets_host') != '') {
        $password_reset_process = 'emailed';
    }
    return $password_reset_process;
}

/**
 * Send out a lost password e-mail
 *
 * @param  string $username Username to reset for (may be blank if other is not)
 * @param  string $email_address E-mail address to set for (may be blank if other is not)
 * @return array A tuple: e-mail address, obfuscated e-mail address that is safe(ish) to display, member ID
 */
function lost_password_emailer_step($username, $email_address)
{
    if (($username == '') && ($email_address == '')) {
        warn_exit(do_lang_tempcode('PASSWORD_RESET_ERROR'));
    }

    if ($username != '') {
        $member_id = $GLOBALS['FORUM_DRIVER']->get_member_from_username($username);
    } else {
        $member_id = $GLOBALS['FORUM_DRIVER']->get_member_from_email_address($email_address);
    }
    if ($member_id === null) {
        warn_exit(do_lang_tempcode('PASSWORD_RESET_ERROR_2'));
    }
    $username = $GLOBALS['FORUM_DRIVER']->get_username($member_id);
    if (($GLOBALS['FORUM_DRIVER']->get_member_row_field($member_id, 'm_password_compat_scheme') == '') && (has_privilege($member_id, 'disable_lost_passwords')) && (!$GLOBALS['IS_ACTUALLY_ADMIN'])) {
        warn_exit(do_lang_tempcode('NO_RESET_ACCESS'));
    }
    if ($GLOBALS['FORUM_DRIVER']->get_member_row_field($member_id, 'm_password_compat_scheme') == 'httpauth') {
        warn_exit(do_lang_tempcode('NO_PASSWORD_RESET_HTTPAUTH'));
    }
    $is_ldap = cns_is_ldap_member($member_id);
    $is_httpauth = cns_is_httpauth_member($member_id);
    if (($is_ldap)/* || ($is_httpauth  Actually covered more explicitly above - over mock-httpauth, like Facebook, may have passwords reset to break the integrations)*/) {
        warn_exit(do_lang_tempcode('EXT_NO_PASSWORD_CHANGE'));
    }

    $password_reset_process = get_password_reset_process();

    require_code('crypt');
    $code = $GLOBALS['FORUM_DRIVER']->get_member_row_field($member_id, 'm_password_change_code'); // Re-use existing code if possible, so that overlapping reset emails don't cause chaos
    if ($code != '') {
        if ($password_reset_process == 'ultra') {
            list($code, $session_id) = explode('__', $code);
        }
    }
    if (($code == '') || ($password_reset_process == 'ultra') && ($session_id != get_session_id())) {
        $code = get_rand_password();
        if ($password_reset_process == 'ultra') {
            $GLOBALS['FORUM_DB']->query_update('f_members', array('m_password_change_code' => $code . '__' . get_session_id()), array('id' => $member_id), '', 1);
        } else {
            $GLOBALS['FORUM_DB']->query_update('f_members', array('m_password_change_code' => $code), array('id' => $member_id), '', 1);
        }
    }

    $email = $GLOBALS['FORUM_DRIVER']->get_member_row_field($member_id, 'm_email_address');
    if ($email == '') {
        warn_exit(do_lang_tempcode('MEMBER_NO_EMAIL_ADDRESS_RESET_TO'));
    }

    log_it('LOST_PASSWORD', strval($member_id), $code);

    $join_time = $GLOBALS['FORUM_DRIVER']->get_member_row_field($member_id, 'm_join_time');

    $temporary_passwords = ($password_reset_process != 'emailed');

    // Send confirm mail
    if ($password_reset_process != 'ultra') {
        $zone = get_module_zone('lost_password');
        $_url = build_url(array('page' => 'lost_password', 'type' => 'step3', 'code' => $code, 'member' => $member_id), $zone, null, false, false, true);
        $url = $_url->evaluate();
        $_url_simple = build_url(array('page' => 'lost_password', 'type' => 'step3', 'code' => null, 'username' => null, 'member' => null), $zone, null, false, false, true);
        $url_simple = $_url_simple->evaluate();
        $message = do_lang($temporary_passwords ? 'LOST_PASSWORD_TEXT_TEMPORARY' : 'LOST_PASSWORD_TEXT', comcode_escape(get_site_name()), comcode_escape($username), array($url, comcode_escape($url_simple), strval($member_id), $code), get_lang($member_id));
        require_code('mail');
        dispatch_mail(do_lang('LOST_PASSWORD_CONFIRM', null, null, null, get_lang($member_id)), $message, array($email), $GLOBALS['FORUM_DRIVER']->get_username($member_id, true), '', '', array('bypass_queue' => true, 'require_recipient_valid_since' => $join_time));
    } else {
        $old_php_self = cms_srv('PHP_SELF');
        $old_server_name = cms_srv('SERVER_NAME');

        // Fiddle to try and anonymise details of the e-mail
        $_SERVER['PHP_SELF'] = "/";
        $_SERVER['SERVER_NAME'] = cms_srv('SERVER_ADDR');

        $from_email = get_option('website_email');
        //$from_email = 'noreply@' . $_SERVER['SERVER_ADDR'];  Won't work on most hosting
        $from_name = do_lang('PASSWORD_RESET_ULTRA_FROM');
        $subject = do_lang('PASSWORD_RESET_ULTRA_SUBJECT', $code);
        $body = do_lang('PASSWORD_RESET_ULTRA_BODY', $code);
        mail($email, $subject, $body, 'From: ' . $from_name . ' <' . $from_email . '>' . "\r\n" . 'Reply-To: ' . $from_name . ' <' . $from_email . '>');

        // Put env details back to how they should be
        $_SERVER['PHP_SELF'] = $old_php_self;
        $_SERVER['SERVER_NAME'] = $old_server_name;
    }

    $email_address_masked = preg_replace('#^(\w).*@.*(\w\.\w+)$#', '${1}...@...${2}', $email);

    return array($email, $email_address_masked, $member_id);
}
