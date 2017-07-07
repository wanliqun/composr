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
 * @package    ecommerce
 */

/**
 * Hook class.
 */
class Hook_config_credit_card_cleanup_days
{
    /**
     * Gets the details relating to the config option.
     *
     * @return ?array The details (null: disabled)
     */
    public function get_details()
    {
        return array(
            'human_name' => 'CREDIT_CARD_CLEANUP_DAYS',
            'type' => 'integer',
            'category' => 'ECOMMERCE',
            'group' => 'GENERAL',
            'explanation' => 'CONFIG_OPTION_credit_card_cleanup_days',
            'shared_hosting_restricted' => '0',
            'list_options' => '',
            'order_in_category_group' => 7,

            'addon' => 'ecommerce',
        );
    }

    /**
     * Gets the default value for the config option.
     *
     * @return ?string The default value (null: option is disabled)
     */
    public function get_default()
    {
        return '365';
    }
}
