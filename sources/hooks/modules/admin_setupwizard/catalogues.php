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
 * @package    catalogues
 */

/**
 * Hook class.
 */
class Hook_sw_catalogues
{
    /**
     * Run function for features in the setup wizard.
     *
     * @return array Current settings.
     */
    public function get_current_settings()
    {
        $settings = array();
        $test = $GLOBALS['SITE_DB']->query_select_value_if_there('catalogues', 'c_name', array('c_name' => 'projects'));
        $settings['have_default_catalogues_projects'] = is_null($test) ? '0' : '1';
        $test = $GLOBALS['SITE_DB']->query_select_value_if_there('catalogues', 'c_name', array('c_name' => 'faqs'));
        $settings['have_default_catalogues_faqs'] = is_null($test) ? '0' : '1';
        $test = $GLOBALS['SITE_DB']->query_select_value_if_there('catalogues', 'c_name', array('c_name' => 'links'));
        $settings['have_default_catalogues_links'] = is_null($test) ? '0' : '1';
        $test = $GLOBALS['SITE_DB']->query_select_value_if_there('catalogues', 'c_name', array('c_name' => 'contacts'));
        $settings['have_default_catalogues_contacts'] = is_null($test) ? '0' : '1';
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
        if (!addon_installed('catalogues') || post_param_integer('addon_catalogues', null) === 0) {
            return new Tempcode();
        }

        $current_settings = $this->get_current_settings();
        $field_defaults += $current_settings; // $field_defaults will take precedence, due to how "+" operator works in PHP

        require_lang('catalogues');
        $fields = new Tempcode();
        if ($current_settings['have_default_catalogues_projects'] == '1') {
            $fields->attach(form_input_tick(do_lang_tempcode('HAVE_DEFAULT_CATALOGUES_PROJECTS'), do_lang_tempcode('DESCRIPTION_HAVE_DEFAULT_CATALOGUES_PROJECTS'), 'have_default_catalogues_projects', $field_defaults['have_default_catalogues_projects'] == '1'));
        }
        if ($current_settings['have_default_catalogues_faqs'] == '1') {
            $fields->attach(form_input_tick(do_lang_tempcode('HAVE_DEFAULT_CATALOGUES_FAQS'), do_lang_tempcode('DESCRIPTION_HAVE_DEFAULT_CATALOGUES_FAQS'), 'have_default_catalogues_faqs', $field_defaults['have_default_catalogues_faqs'] == '1'));
        }
        if ($current_settings['have_default_catalogues_links'] == '1') {
            $fields->attach(form_input_tick(do_lang_tempcode('HAVE_DEFAULT_CATALOGUES_LINKS'), do_lang_tempcode('DESCRIPTION_HAVE_DEFAULT_CATALOGUES_LINKS'), 'have_default_catalogues_links', $field_defaults['have_default_catalogues_links'] == '1'));
        }
        if ($current_settings['have_default_catalogues_contacts'] == '1') {
            $fields->attach(form_input_tick(do_lang_tempcode('HAVE_DEFAULT_CATALOGUES_CONTACTS'), do_lang_tempcode('DESCRIPTION_HAVE_DEFAULT_CATALOGUES_CONTACTS'), 'have_default_catalogues_contacts', $field_defaults['have_default_catalogues_contacts'] == '1'));
        }
        return $fields;
    }

    /**
     * Run function for setting features from the setup wizard.
     */
    public function set_fields()
    {
        if (!addon_installed('catalogues') || post_param_integer('addon_catalogues', null) === 0) {
            return;
        }

        if (post_param_integer('have_default_catalogues_projects', 0) == 0) {
            $test = $GLOBALS['SITE_DB']->query_select_value_if_there('catalogues', 'c_name', array('c_name' => 'projects'));
            if (!is_null($test)) {
                require_code('catalogues2');
                actual_delete_catalogue('projects');
                require_lang('catalogues');
                require_code('menus2');
                delete_menu_item_simple(do_lang('DEFAULT_CATALOGUE_PROJECTS_TITLE'));
                delete_menu_item_simple('_SEARCH:cms_catalogues:add_entry:catalogue_name=projects');
                delete_menu_item_simple('_SEARCH:catalogues:index:projects');
            }
        }
        if (post_param_integer('have_default_catalogues_faqs', 0) == 0) {
            $test = $GLOBALS['SITE_DB']->query_select_value_if_there('catalogues', 'c_name', array('c_name' => 'faqs'));
            if (!is_null($test)) {
                require_code('catalogues2');
                actual_delete_catalogue('faqs');
                require_code('menus2');
                delete_menu_item_simple('_SEARCH:catalogues:index:faqs');
            }
        }
        if (post_param_integer('have_default_catalogues_links', 0) == 0) {
            $test = $GLOBALS['SITE_DB']->query_select_value_if_there('catalogues', 'c_name', array('c_name' => 'links'));
            if (!is_null($test)) {
                require_code('catalogues2');
                actual_delete_catalogue('links');
                require_code('menus2');
                delete_menu_item_simple('_SEARCH:catalogues:index:links');
            }
        }
        if (post_param_integer('have_default_catalogues_contacts', 0) == 0) {
            $test = $GLOBALS['SITE_DB']->query_select_value_if_there('catalogues', 'c_name', array('c_name' => 'contacts'));
            if (!is_null($test)) {
                require_code('catalogues2');
                actual_delete_catalogue('contacts');
                require_code('menus2');
                delete_menu_item_simple('_SEARCH:catalogues:index:contacts');
            }
        }
    }

    /**
     * Run function for blocks in the setup wizard.
     *
     * @return array A pair: Main blocks and Side blocks (each is a map of block names to display types).
     */
    public function get_blocks()
    {
        if (!addon_installed('catalogues')) {
            return array();
        }

        return array(array(), array());
    }
}
