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
 * @package    newsletter
 */

/**
 * Hook class.
 */
class Hook_config_newsletter_smtp_from_address
{
    /**
     * Gets the details relating to the config option.
     *
     * @return ?array The details (null: disabled)
     */
    public function get_details()
    {
        return array(
            'human_name' => 'EMAIL_ADDRESS',
            'type' => 'line',
            'category' => 'MESSAGES',
            'group' => 'NEWSLETTER_SMTP',
            'explanation' => 'CONFIG_OPTION_smtp_from_address',
            'shared_hosting_restricted' => '1',
            'list_options' => '',
            'order_in_category_group' => 25,
            'required' => false,

            'public' => false,

            'addon' => 'newsletter',
        );
    }

    /**
     * Gets the default value for the config option.
     *
     * @return ?string The default value (null: option is disabled)
     */
    public function get_default()
    {
        if (!php_function_allowed('fsockopen')) {
            return null;
        }
        return '';
    }
}
