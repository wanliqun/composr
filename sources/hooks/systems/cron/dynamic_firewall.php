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
class Hook_cron_dynamic_firewall
{
    /**
     * Get info from this hook.
     *
     * @param  ?TIME $last_run Last time run (null: never)
     * @param  boolean $calculate_num_queued Calculate the number of items queued, if possible
     * @return ?array Return a map of info about the hook (null: disabled)
     */
    public function info($last_run, $calculate_num_queued)
    {
        if (get_option('dynamic_firewall') == '0') {
            return null;
        }

        return array(
            'label' => 'Update dynamic firewall',
            'num_queued' => null,
            'minutes_between_runs' => 60 * 6,
        );
    }

    /**
     * Run function for system scheduler scripts. Searches for things to do. ->info(..., true) must be called before this method.
     *
     * @param  ?TIME $last_run Last time run (null: never)
     */
    public function run($last_run)
    {
        $rules_path = get_custom_file_base() . '/data_custom/firewall_rules.txt';

        if (cms_is_writable($rules_path)) {
            require_code('version2');

            $new_contents = @http_get_contents('https://compo.sr/data_custom/firewall_rules.txt?version=' . rawurlencode(get_version_dotted()), array('trigger_error' => false));

            if (!empty($new_contents)) {
                require_code('files');
                cms_file_put_contents_safe($rules_path, $new_contents, FILE_WRITE_FIX_PERMISSIONS | FILE_WRITE_SYNC_FILE);
            }
        }
    }
}
