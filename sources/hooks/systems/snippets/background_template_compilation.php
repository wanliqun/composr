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
 * @package    core
 */

/**
 * Hook class.
 */
class Hook_snippet_background_template_compilation
{
    /**
     * Run function for snippet hooks. Generates XHTML to insert into a page using AJAX.
     *
     * @return Tempcode The snippet
     */
    public function run()
    {
        if (((get_value('setupwizard_completed') !== '1') && (!$GLOBALS['DEV_MODE'])) || (get_param_string('page', null) === 'admin_config')) { // Don't want to do this prematurely!
            return new Tempcode();
        }

        require_code('themes3');
        compile_all_templates();

        return new Tempcode();
    }
}
