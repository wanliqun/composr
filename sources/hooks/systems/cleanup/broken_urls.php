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
 * @package    core_cleanup_tools
 */

/**
 * Hook class.
 */
class Hook_cleanup_broken_urls
{
    /**
     * Find details about this cleanup hook.
     *
     * @return ?array Map of cleanup hook info (null: hook is disabled).
     */
    public function info()
    {
        $skip_hooks = find_all_hooks('systems', 'non_active_urls');
        $dbs_bak = $GLOBALS['NO_DB_SCOPE_CHECK'];
        $GLOBALS['NO_DB_SCOPE_CHECK'] = true;
        $urlpaths = $GLOBALS['SITE_DB']->query('SELECT m_name,m_table FROM ' . $GLOBALS['SITE_DB']->get_table_prefix() . 'db_meta WHERE m_type LIKE \'' . db_encode_like('%URLPATH%') . '\'');
        $count = 0;
        foreach ($urlpaths as $urlpath) {
            if ($urlpath['m_table'] == 'hackattack') {
                continue;
            }
            if ($urlpath['m_table'] == 'incoming_uploads') {
                continue;
            }
            if ($urlpath['m_table'] == 'url_title_cache') {
                continue;
            }
            if ($urlpath['m_table'] == 'theme_images') {
                continue;
            }
            if (array_key_exists($urlpath['m_table'], $skip_hooks)) {
                continue;
            }
            $count += $GLOBALS['SITE_DB']->query_select_value($urlpath['m_table'], 'COUNT(*)');
            if ($count > 10000) {
                return null; // Too much!
            }
        }
        $GLOBALS['NO_DB_SCOPE_CHECK'] = $dbs_bak;

        $info = array();
        $info['title'] = do_lang_tempcode('BROKEN_URLS');
        $info['description'] = do_lang_tempcode('DESCRIPTION_BROKEN_URLS');
        $info['type'] = 'optimise';

        return $info;
    }

    /**
     * Run the cleanup hook action.
     *
     * @return Tempcode Results
     */
    public function run()
    {
        require_code('tasks');
        return call_user_func_array__long_task(do_lang('BROKEN_URLS'), null, 'find_broken_urls');
    }
}
