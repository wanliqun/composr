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
 * @package    core_configuration
 */

/**
 * Hook class.
 */
class Hook_config_site_message_usergroup_select
{
    /**
     * Gets the details relating to the config option.
     *
     * @return ?array The details (null: disabled)
     */
    public function get_details()
    {
        $guest_groups = $GLOBALS['FORUM_DRIVER']->get_members_groups($GLOBALS['FORUM_DRIVER']->get_guest_id());
        $guest_group_id = $guest_groups[0];

        return array(
            'human_name' => 'SITE_MESSAGE_USERGROUP_SELECT',
            'type' => 'line',
            'category' => 'MESSAGES',
            'group' => 'SITE_MESSAGING',
            'explanation' => 'CONFIG_OPTION_site_message_usergroup_select',
            'explanation_param_a' => escape_html(strval($guest_group_id)),
            'explanation_param_b' => escape_html(get_tutorial_url('tut_selectcode')),
            'shared_hosting_restricted' => '0',
            'list_options' => '',
            'order_in_category_group' => 5,

            'addon' => 'core_configuration',
        );
    }

    /**
     * Gets the default value for the config option.
     *
     * @return ?string The default value (null: option is disabled)
     */
    public function get_default()
    {
        return '';
    }
}
