<?php /*

 Composr
 Copyright (c) ocProducts, 2004-2018

 See text/EN/licence.txt for full licensing information.


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
 * Block class.
 */
class Block_main_contact_simple
{
    /**
     * Find details of the block.
     *
     * @return ?array Map of block info (null: block is disabled)
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
        $info['parameters'] = array('param', 'title', 'private', 'email_optional', 'subject', 'subject_prefix', 'subject_suffix', 'body_prefix', 'body_suffix', 'redirect', 'guid', 'attachments');
        return $info;
    }

    /**
     * Execute the block.
     *
     * @param  array $map A map of parameters
     * @return Tempcode The result of execution
     */
    public function run($map)
    {
        require_code('feedback');

        require_code('mail');
        require_code('mail_forms');

        $block_id = get_block_id($map);

        $message = new Tempcode();

        // Options...

        if (addon_installed('captcha')) {
            require_code('captcha');
            $use_captcha = ((get_option('captcha_on_feedback') == '1') && (use_captcha()));
        } else {
            $use_captcha = false;
        }

        $subject = array_key_exists('subject', $map) ? $map['subject'] : '';
        $subject_prefix = array_key_exists('subject_prefix', $map) ? $map['subject_prefix'] : '';
        $subject_suffix = array_key_exists('subject_suffix', $map) ? $map['subject_suffix'] : '';
        $body_prefix = array_key_exists('body_prefix', $map) ? $map['body_prefix'] : '';
        $body_suffix = array_key_exists('body_suffix', $map) ? $map['body_suffix'] : '';

        $to_email = empty($map['param']) ? get_option('staff_address') : $map['param'];
        $box_title = array_key_exists('title', $map) ? $map['title'] : do_lang('CONTACT_US');
        $private = (array_key_exists('private', $map)) && ($map['private'] == '1');
        $email_optional = array_key_exists('email_optional', $map) ? (intval($map['email_optional']) == 1) : true;
        $support_attachments = array_key_exists('attachments', $map) ? (intval($map['attachments']) == 1) : false;

        $block_id = md5(serialize($map));

        // Submission...

        if ((post_param_integer('_comment_form_post', 0) == 1) && (post_param_string('_block_id', '') == $block_id)) {
            $message = new Tempcode();/*Used to be written out here*/

            // Check CAPTCHA
            if ($use_captcha) {
                enforce_captcha();
            }

            // Send e-mail
            form_to_email(null, $subject_prefix, $subject_suffix, $body_prefix, $body_suffix, null, $to_email, true);

            // Redirect/messaging
            $redirect = array_key_exists('redirect', $map) ? $map['redirect'] : '';
            if ($redirect != '') {
                $redirect = page_link_to_url($redirect);
                require_code('site2');
                assign_refresh($redirect, 0.0);
            } else {
                attach_message(do_lang_tempcode('MESSAGE_SENT'), 'inform');
            }
        }

        // Form...

        $emoticons = $GLOBALS['FORUM_DRIVER']->get_emoticon_chooser();

        require_javascript('editing');
        require_javascript('checking');

        $comment_url = get_self_url();

        if (addon_installed('captcha')) {
            require_code('captcha');
            $use_captcha = ((get_option('captcha_on_feedback') == '1') && (use_captcha()));
            if ($use_captcha) {
                generate_captcha();
            }
        } else {
            $use_captcha = false;
        }

        $hidden = new Tempcode();
        $hidden->attach(form_input_hidden('_block_id', $block_id));

        $guid = isset($map['guid']) ? $map['guid'] : 'd35227903b5f786331f6532bce1765e4';

        if ($support_attachments) {
            require_code('form_templates');
            list($attachments, $attach_size_field) = get_attachments('post', false);
        } else
        {
            $attachments = null;
            $attach_size_field = null;
        }

        $comment_details = do_template('COMMENTS_POSTING_FORM', array(
            '_GUID' => $guid,
            'TITLE' => $box_title,
            'HIDDEN' => $hidden,
            'USE_CAPTCHA' => $use_captcha,
            'GET_EMAIL' => !$private,
            'EMAIL_OPTIONAL' => $email_optional,
            'GET_TITLE' => !$private,
            'TITLE_OPTIONAL' => false,
            'DEFAULT_TITLE' => $subject,
            'POST_WARNING' => '',
            'RULES_TEXT' => '',
            'ATTACHMENTS' => $attachments,
            'ATTACH_SIZE_FIELD' => $attach_size_field,
            'TRUE_ATTACHMENT_UI' => false,
            'EMOTICONS' => $emoticons,
            'DISPLAY' => 'block',
            'FIRST_POST_URL' => '',
            'FIRST_POST' => '',
            'COMMENT_URL' => $comment_url,
            'SUBMIT_NAME' => do_lang_tempcode('SEND'),
            'SUBMIT_ICON' => 'buttons/send',
            'SKIP_PREVIEW' => true,
            'ANALYTIC_EVENT_CATEGORY' => do_lang('CONTACT_US'),
        ));

        $out = do_template('BLOCK_MAIN_CONTACT_SIMPLE', array(
            '_GUID' => $guid,
            'BLOCK_ID' => $block_id,
            'COMMENT_DETAILS' => $comment_details,
            'MESSAGE' => $message,
        ));

        return $out;
    }
}
