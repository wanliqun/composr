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
 * @package    core_cleanup_tools
 */

/**
 * Hook class.
 */
class Hook_task_reorganise_uploads
{
    /**
     * Run the task hook.
     *
    * @return ?array A tuple of at least 2: Return mime-type, content (either Tempcode, or a string, or a filename and file-path pair to a temporary file), map of HTTP headers if transferring immediately, map of ini_set commands if transferring immediately (null: show standard success message)
     */
    public function run()
    {
        $hooks = find_all_hooks('systems', 'reorganise_uploads'); // TODO: Fix in v11
        foreach (array_keys($hooks) as $hook) {
            require_code('hooks/systems/reorganise_uploads/' . $hook);
            $ob = object_factory('Hook_reorganise_uploads_' . $hook);
            if ($ob !== null) {
                $ob->run();
            }
        }

        return null;
    }
}
