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
 * @package    setupwizard
 */

/**
 * Hook class.
 */
class Hook_preview_setupwizard
{
    /**
     * Find whether this preview hook applies.
     *
     * @return array Triplet: Whether it applies, the attachment ID type (may be null), whether the forum DB is used [optional]
     */
    public function applies()
    {
        $applies = (!addon_installed('setupwizard')) && (get_page_name() == 'admin_setupwizard') && (get_param_string('type', '') == 'step8');
        return array($applies, null, false);
    }

    /**
     * Run function for preview hooks.
     *
     * @return array A pair: The preview, the updated post Comcode (may be null)
     */
    public function run()
    {
        $_GET['keep_theme_seed'] = post_param_string('seed_hex');
        $_GET['keep_theme_dark'] = post_param_string('dark', '0');
        $_GET['keep_theme_source'] = 'default';
        $_GET['keep_theme_algorithm'] = 'equations';

        $preview = request_page($GLOBALS['SITE_DB']->query_select_value('zones', 'zone_default_page'), true, '');

        return array($preview, null);
    }
}
