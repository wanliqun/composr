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
 * Block class.
 */
class Block_main_greeting
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
        $info['parameters'] = array();
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
        $forum = get_forum_type();

        $out = new Tempcode();

        if ($forum != 'none') {
            // Standard welcome back vs into greeting
            $member = get_member();
            if (is_guest($member)) {
                $redirect = get_self_url(true, true);
                $login_url = build_url(array('page' => 'login', 'type' => 'browse', 'redirect' => $redirect), get_module_zone('login'));
                $join_url = $GLOBALS['FORUM_DRIVER']->join_url();
                $join_bits = do_lang_tempcode('JOIN_OR_LOGIN', escape_html($join_url->evaluate()), escape_html(is_object($login_url) ? $login_url->evaluate() : $login_url));

                $p = do_lang_tempcode('WELCOME', $join_bits);
                $out->attach(paragraph($p, 'hhrt4dsgdsgd'));
            } else {
                $out->attach(paragraph(do_lang_tempcode('WELCOME_BACK', escape_html($GLOBALS['FORUM_DRIVER']->get_username($member, true)), escape_html($GLOBALS['FORUM_DRIVER']->get_username($member))), 'gfgdf9gjd'));
            }
        }

        $message = get_option('welcome_message');
        if (has_actual_page_access(get_member(), 'admin_config')) {
            if ($message != '') {
                $message .= ' [semihtml]<span class="associated_link"><a href="{$PAGE_LINK*,_SEARCH:admin_config:category:SITE#group_GENERAL}">' . do_lang('EDIT') . '</a></span>[/semihtml]';
            }
        }
        $out->attach(comcode_to_tempcode($message, null, true));

        return $out;
    }
}
