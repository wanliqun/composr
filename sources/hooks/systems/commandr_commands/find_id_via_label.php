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
 * @package    commandr
 */

/**
 * Hook class.
 */
class Hook_commandr_command_find_id_via_label
{
    /**
     * Run function for Commandr hooks.
     *
     * @param  array $options The options with which the command was called
     * @param  array $parameters The parameters with which the command was called
     * @param  object $commandr_fs A reference to the Commandr filesystem object
     * @return array Array of stdcommand, stdhtml, stdout, and stderr responses
     */
    public function run($options, $parameters, &$commandr_fs)
    {
        if ((array_key_exists('h', $options)) || (array_key_exists('help', $options))) {
            return array('', do_command_help('find_id_via_label', array('h'), array(true, true)), '', '');
        } else {
            if (!array_key_exists(0, $parameters)) {
                return array('', '', '', do_lang('MISSING_PARAM', '1', 'find_id_via_label'));
            }
            if (!array_key_exists(1, $parameters)) {
                return array('', '', '', do_lang('MISSING_PARAM', '2', 'find_id_via_label'));
            }

            require_code('resource_fs');

            $result = find_id_via_label($parameters[0], $parameters[1], array_key_exists(2, $parameters) ? $parameters[2] : null);
            if ($result !== null) {
                return array('', '', $result, '');
            } else {
                return array('', '', '', do_lang('MISSING_RESOURCE'));
            }
        }
    }
}
