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
 * @package    tickets
 */

/**
 * Hook class.
 */
class Hook_addon_registry_tickets
{
    /**
     * Get a list of file permissions to set
     *
     * @param  boolean $runtime Whether to include wildcards represented runtime-created chmoddable files
     * @return array File permissions to set
     */
    public function get_chmod_array($runtime = false)
    {
        return array();
    }

    /**
     * Get the version of Composr this addon is for
     *
     * @return float Version number
     */
    public function get_version()
    {
        return cms_version_number();
    }

    /**
     * Get the description of the addon
     *
     * @return string Description of the addon
     */
    public function get_description()
    {
        return 'A support ticket system.';
    }

    /**
     * Get a list of tutorials that apply to this addon
     *
     * @return array List of tutorials
     */
    public function get_applicable_tutorials()
    {
        return array(
            'tut_support_desk',
        );
    }

    /**
     * Get a mapping of dependency types
     *
     * @return array File permissions to set
     */
    public function get_dependencies()
    {
        return array(
            'requires' => array(),
            'recommends' => array(),
            'conflicts_with' => array(),
        );
    }

    /**
     * Explicitly say which icon should be used
     *
     * @return URLPATH Icon
     */
    public function get_default_icon()
    {
        return 'themes/default/images/icons/48x48/menu/site_meta/tickets.png';
    }

    /**
     * Get a list of files that belong to this addon
     *
     * @return array List of files
     */
    public function get_file_list()
    {
        return array(
            'themes/default/images/icons/24x24/menu/site_meta/tickets.png',
            'themes/default/images/icons/48x48/menu/site_meta/tickets.png',
            'themes/default/images/icons/24x24/buttons/add_ticket.png',
            'themes/default/images/icons/48x48/buttons/add_ticket.png',
            'themes/default/images/icons/24x24/buttons/new_reply_staff_only.png',
            'themes/default/images/icons/48x48/buttons/new_reply_staff_only.png',
            'sources/hooks/systems/resource_meta_aware/ticket_type.php',
            'sources/hooks/systems/commandr_fs/ticket_types.php',
            'sources/hooks/systems/addon_registry/tickets.php',
            'sources/hooks/modules/admin_import_types/tickets.php',
            'themes/default/templates/SUPPORT_TICKET_TYPE_SCREEN.tpl',
            'themes/default/templates/SUPPORT_TICKET_SCREEN.tpl',
            'themes/default/templates/SUPPORT_TICKETS_SCREEN.tpl',
            'themes/default/templates/SUPPORT_TICKET_LINK.tpl',
            'themes/default/templates/SUPPORT_TICKETS_SEARCH_SCREEN.tpl',
            'adminzone/pages/modules/admin_tickets.php',
            'themes/default/css/tickets.css',
            'lang/EN/tickets.ini',
            'site/pages/modules/tickets.php',
            'sources/hooks/systems/change_detection/tickets.php',
            'sources/hooks/systems/page_groupings/tickets.php',
            'sources/hooks/systems/module_permissions/tickets.php',
            'sources/hooks/systems/rss/tickets.php',
            'sources/hooks/systems/cron/ticket_type_lead_times.php',
            'sources/tickets.php',
            'sources/tickets2.php',
            'sources/hooks/systems/preview/ticket.php',
            'sources/hooks/blocks/main_staff_checklist/tickets.php',
            'sources/hooks/systems/notifications/ticket_reply.php',
            'sources/hooks/systems/notifications/ticket_new_staff.php',
            'sources/hooks/systems/notifications/ticket_reply_staff.php',
            'sources/hooks/systems/notifications/ticket_assigned_staff.php',
            'sources/tickets_email_integration.php',
            'sources/hooks/systems/cron/tickets_email_integration.php',
            'sources/hooks/systems/config/ticket_forum_name.php',
            'sources/hooks/systems/config/ticket_member_forums.php',
            'sources/hooks/systems/config/ticket_text.php',
            'sources/hooks/systems/config/ticket_type_forums.php',
            'sources/hooks/systems/config/ticket_mail_on.php',
            'sources/hooks/systems/config/ticket_email_from.php',
            'sources/hooks/systems/config/ticket_mail_server.php',
            'sources/hooks/systems/config/ticket_mail_server_port.php',
            'sources/hooks/systems/config/ticket_mail_server_type.php',
            'sources/hooks/systems/config/ticket_mail_username.php',
            'sources/hooks/systems/config/ticket_mail_password.php',
            'sources/hooks/systems/config/support_operator.php',
            'sources/hooks/systems/config/ticket_auto_assign.php',
            'data/incoming_ticket_email.php',
            'sources/hooks/systems/commandr_fs_extended_member/ticket_known_emailers.php',
        );
    }

    /**
     * Get mapping between template names and the method of this class that can render a preview of them
     *
     * @return array The mapping
     */
    public function tpl_previews()
    {
        return array(
            'templates/SUPPORT_TICKET_LINK.tpl' => 'support_tickets_screen',
            'templates/SUPPORT_TICKETS_SCREEN.tpl' => 'support_tickets_screen',
            'templates/SUPPORT_TICKET_SCREEN.tpl' => 'support_ticket_screen',
            'templates/SUPPORT_TICKETS_SEARCH_SCREEN.tpl' => 'support_tickets_search_screen',
            'templates/SUPPORT_TICKET_TYPE_SCREEN.tpl' => 'support_ticket_type_screen'
        );
    }

    /**
     * Get a preview(s) of a (group of) template(s), as a full standalone piece of HTML in Tempcode format.
     * Uses sources/lorem.php functions to place appropriate stock-text. Should not hard-code things, as the code is intended to be declaritive.
     * Assumptions: You can assume all Lang/CSS/JavaScript files in this addon have been pre-required.
     *
     * @return array Array of previews, each is Tempcode. Normally we have just one preview, but occasionally it is good to test templates are flexible (e.g. if they use IF_EMPTY, we can test with and without blank data).
     */
    public function tpl_preview__support_tickets_screen()
    {
        $links = new Tempcode();
        foreach (placeholder_array() as $k => $v) {
            $links->attach(do_lorem_template('SUPPORT_TICKET_LINK', array(
                'NUM_POSTS' => placeholder_number(),
                'CLOSED' => lorem_phrase(),
                'URL' => placeholder_url(),
                'TITLE' => lorem_phrase(),
                'EXTRA_DETAILS' => '',
                'TICKET_TYPE_NAME' => lorem_phrase(),
                'TICKET_TYPE_ID' => placeholder_id(),
                'FIRST_DATE' => placeholder_date(),
                'FIRST_DATE_RAW' => placeholder_date_raw(),
                'FIRST_POSTER_PROFILE_URL' => placeholder_url(),
                'FIRST_POSTER' => lorem_phrase(),
                'FIRST_POSTER_ID' => placeholder_id(),
                'LAST_POSTER_PROFILE_URL' => placeholder_url(),
                'LAST_POSTER' => lorem_phrase(),
                'LAST_POSTER_ID' => placeholder_id(),
                'LAST_DATE' => placeholder_date(),
                'LAST_DATE_RAW' => placeholder_date_raw(),
                'ID' => placeholder_id(),
                'ASSIGNED' => array(),
            )));
        }

        return array(
            lorem_globalise(do_lorem_template('SUPPORT_TICKETS_SCREEN', array(
                'TITLE' => lorem_title(),
                'MESSAGE' => lorem_phrase(),
                'LINKS' => $links,
                'TICKET_TYPE_ID' => placeholder_id(),
                'NAME' => lorem_word_2(),
                'SELECTED' => true,
                'ADD_TICKET_URL' => placeholder_url(),
                'TYPES' => placeholder_array(),
                'LEAD_TIME' => placeholder_number(),
            )), null, '', true)
        );
    }

    /**
     * Get a preview(s) of a (group of) template(s), as a full standalone piece of HTML in Tempcode format.
     * Uses sources/lorem.php functions to place appropriate stock-text. Should not hard-code things, as the code is intended to be declaritive.
     * Assumptions: You can assume all Lang/CSS/JavaScript files in this addon have been pre-required.
     *
     * @return array Array of previews, each is Tempcode. Normally we have just one preview, but occasionally it is good to test templates are flexible (e.g. if they use IF_EMPTY, we can test with and without blank data).
     */
    public function tpl_preview__support_ticket_screen()
    {
        require_javascript('ajax');
        require_lang('cns');

        $comments = new Tempcode();

        $comment_form = do_lorem_template('COMMENTS_POSTING_FORM', array(
            'TITLE' => lorem_phrase(),
            'JOIN_BITS' => lorem_phrase_html(),
            'USE_CAPTCHA' => false,
            'GET_EMAIL' => true,
            'EMAIL_OPTIONAL' => true,
            'GET_TITLE' => true,
            'TITLE_OPTIONAL' => true,
            'DEFAULT_TITLE' => '',
            'POST_WARNING' => '',
            'RULES_TEXT' => '',
            'ATTACHMENTS' => null,
            'ATTACH_SIZE_FIELD' => null,
            'TRUE_ATTACHMENT_UI' => false,
            'EMOTICONS' => placeholder_emoticon_chooser(),
            'DISPLAY' => 'block',
            'FIRST_POST_URL' => '',
            'FIRST_POST' => '',
            'COMMENT_URL' => '',
        ));

        $other_tickets = new Tempcode();
        foreach (placeholder_array() as $k => $v) {
            $other_tickets->attach(do_lorem_template('SUPPORT_TICKET_LINK', array(
                'NUM_POSTS' => placeholder_number(),
                'CLOSED' => lorem_phrase(),
                'URL' => placeholder_url(),
                'TITLE' => lorem_phrase(),
                'EXTRA_DETAILS' => '',
                'TICKET_TYPE_NAME' => lorem_phrase(),
                'TICKET_TYPE_ID' => placeholder_id(),
                'DATE' => placeholder_date(),
                'DATE_RAW' => placeholder_date_raw(),
                'PROFILE_URL' => placeholder_url(),
                'FIRST_POSTER_PROFILE_URL' => placeholder_url(),
                'FIRST_POSTER' => lorem_phrase(),
                'FIRST_POSTER_ID' => placeholder_id(),
                'LAST_POSTER_PROFILE_URL' => placeholder_url(),
                'LAST_POSTER' => lorem_phrase(),
                'LAST_POSTER_ID' => placeholder_id(),
                'LAST_DATE' => placeholder_date(),
                'LAST_DATE_RAW' => placeholder_date_raw(),
                'UNCLOSED' => lorem_word(),
                'ID' => placeholder_id(),
                'ASSIGNED' => array(),
            )));
        }

        return array(
            lorem_globalise(do_lorem_template('SUPPORT_TICKET_SCREEN', array(
                'ID' => placeholder_id(),
                'TOGGLE_TICKET_CLOSED_URL' => placeholder_url(),
                'CLOSED' => lorem_phrase(),
                'USERNAME' => lorem_word(),
                'PING_URL' => placeholder_url(),
                'WARNING_DETAILS' => '',
                'NEW' => lorem_phrase(),
                'TICKET_TYPE' => null,
                'TICKET_PAGE_TEXT' => lorem_sentence_html(),
                'POST_TEMPLATES' => '',
                'TYPES' => placeholder_array(),
                'STAFF_ONLY' => true,
                'POSTER' => lorem_phrase(),
                'TITLE' => lorem_title(),
                'COMMENTS' => $comments,
                'COMMENT_FORM' => $comment_form,
                'STAFF_DETAILS' => placeholder_url(),
                'URL' => placeholder_url(),
                'ADD_TICKET_URL' => placeholder_url(),
                'OTHER_TICKETS' => $other_tickets,
                'TYPE_ACTIVITY_OVERVIEW' => array(
                    array(
                        'OVERVIEW_TYPE' => lorem_phrase(),
                        'OVERVIEW_COUNT' => placeholder_number(),
                    ),
                ),
                'SET_TICKET_EXTRA_ACCESS_URL' => placeholder_url(),
                'ASSIGNED' => array(),
                'EXTRA_DETAILS' => lorem_phrase(),
            )), null, '', true)
        );
    }

    /**
     * Get a preview(s) of a (group of) template(s), as a full standalone piece of HTML in Tempcode format.
     * Uses sources/lorem.php functions to place appropriate stock-text. Should not hard-code things, as the code is intended to be declaritive.
     * Assumptions: You can assume all Lang/CSS/JavaScript files in this addon have been pre-required.
     *
     * @return array Array of previews, each is Tempcode. Normally we have just one preview, but occasionally it is good to test templates are flexible (e.g. if they use IF_EMPTY, we can test with and without blank data).
     */
    public function tpl_preview__support_tickets_search_screen()
    {
        return array(
            lorem_globalise(do_lorem_template('SUPPORT_TICKETS_SEARCH_SCREEN', array(
                'TITLE' => lorem_title(),
                'URL' => placeholder_url(),
                'POST_FIELDS' => '',
                'RESULTS' => lorem_phrase(),
            )), null, '', true)
        );
    }

    /**
     * Get a preview(s) of a (group of) template(s), as a full standalone piece of HTML in Tempcode format.
     * Uses sources/lorem.php functions to place appropriate stock-text. Should not hard-code things, as the code is intended to be declaritive.
     * Assumptions: You can assume all Lang/CSS/JavaScript files in this addon have been pre-required.
     *
     * @return array Array of previews, each is Tempcode. Normally we have just one preview, but occasionally it is good to test templates are flexible (e.g. if they use IF_EMPTY, we can test with and without blank data).
     */
    public function tpl_preview__support_ticket_type_screen()
    {
        return array(
            lorem_globalise(do_lorem_template('SUPPORT_TICKET_TYPE_SCREEN', array(
                'TITLE' => lorem_title(),
                'TPL' => placeholder_form(),
                'ADD_FORM' => placeholder_form(),
            )), null, '', true)
        );
    }
}
