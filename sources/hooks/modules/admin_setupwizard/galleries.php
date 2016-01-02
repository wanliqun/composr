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
 * @package    galleries
 */

/**
 * Hook class.
 */
class Hook_sw_galleries
{
    /**
     * Run function for features in the setup wizard.
     *
     * @return array Current settings.
     */
    public function get_current_settings()
    {
        $settings = array();
        $test = $GLOBALS['SITE_DB']->query_select_value('group_privileges', 'COUNT(*)', array('privilege' => 'have_personal_category', 'the_page' => 'cms_galleries'));
        $settings['keep_personal_galleries'] = ($test == 0) ? '0' : '1';
        return $settings;
    }

    /**
     * Run function for features in the setup wizard.
     *
     * @param  array $field_defaults Default values for the fields, from the install-profile.
     * @return Tempcode An input field.
     */
    public function get_fields($field_defaults)
    {
        if (!addon_installed('galleries')) {
            return new Tempcode();
        }

        $field_defaults += $this->get_current_settings(); // $field_defaults will take precedence, due to how "+" operator works in PHP

        require_lang('galleries');
        return form_input_tick(do_lang_tempcode('KEEP_PERSONAL_GALLERIES'), do_lang_tempcode('DESCRIPTION_KEEP_PERSONAL_GALLERIES'), 'keep_personal_galleries', $field_defaults['keep_personal_galleries'] == '1');
    }

    /**
     * Run function for setting features from the setup wizard.
     */
    public function set_fields()
    {
        if (!addon_installed('galleries')) {
            return;
        }

        $admin_groups = $GLOBALS['FORUM_DRIVER']->get_super_admin_groups();
        $groups = $GLOBALS['FORUM_DRIVER']->get_usergroup_list(false, true);
        $GLOBALS['SITE_DB']->query_delete('group_privileges', array('privilege' => 'have_personal_category', 'the_page' => 'cms_galleries'));
        if (post_param_integer('keep_personal_galleries', 0) == 1) {
            foreach (array_keys($groups) as $group_id) {
                if (!in_array($group_id, $admin_groups)) {
                    $GLOBALS['SITE_DB']->query_insert('group_privileges', array('privilege' => 'have_personal_category', 'group_id' => $group_id, 'module_the_name' => '', 'category_name' => '', 'the_page' => 'cms_galleries', 'the_value' => 1));
                }
            }
        }
    }

    /**
     * Run function for blocks in the setup wizard.
     *
     * @return array Map of block names, to display types.
     */
    public function get_blocks()
    {
        if (!addon_installed('galleries')) {
            return array();
        }

        return array(array('main_image_fader' => array('NO', 'NO')), array('side_galleries' => array('PANEL_NONE', 'PANEL_NONE')));
    }
}
