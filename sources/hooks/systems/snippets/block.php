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
class Hook_snippet_block
{
    /**
     * Run function for snippet hooks. Generates XHTML to insert into a page using AJAX.
     *
     * @return Tempcode The snippet
     */
    public function run()
    {
        $sup = get_param_string('block_map_sup', '', INPUT_FILTER_GET_COMPLEX);
        $_map = get_param_string('block_map', false, INPUT_FILTER_GET_COMPLEX);
        if ($sup != '') {
            $_map .= ',' . $sup;
        }

        require_code('blocks');

        $map = block_params_str_to_arr($_map);

        if (!array_key_exists('block', $map)) {
            return new Tempcode();
        }

        $pass = false;

        $_whitelisted_blocks = get_value('whitelisted_blocks');
        if (!empty($_whitelisted_blocks)) {
            $whitelisted_blocks = explode(',', $_whitelisted_blocks);
            if (in_array($map['block'], $whitelisted_blocks)) {
                $pass = true;
            }
        }

        if (!$pass) {
            $auth_key = get_param_integer('auth_key');

            // Check permissions
            $test = $GLOBALS['SITE_DB']->query_select_value_if_there('temp_block_permissions', 'p_block_constraints', array('p_session_id' => get_session_id(), 'id' => $auth_key));
            if (($test === null) || (!block_signature_check(block_params_str_to_arr($test), $map))) {
                require_lang('permissions');
                return do_template('RED_ALERT',array('_GUID' => 'wtoaz4b4yp5rwe4wcmyknihps8ujoguv', 'TEXT' => do_lang_tempcode('ACCESS_DENIED__ACCESS_DENIED', escape_html($map['block']))));
            }
        }

        // Cleanup
        if (mt_rand(0, 100) == 1) {
            if (!$GLOBALS['SITE_DB']->table_is_locked('temp_block_permissions')) {
                $sql = 'DELETE FROM ' . get_table_prefix() . 'temp_block_permissions WHERE p_time<' . strval(time() - intval(60.0 * 60.0 * floatval(get_option('session_expiry_time'))));
                $sql .= ' AND NOT EXISTS(SELECT * FROM ' . get_table_prefix() . 'sessions WHERE the_session=p_session_id)';
                $GLOBALS['SITE_DB']->query($sql, 500/*to reduce lock times*/);
            }
        }

        // We need to minimise the dependency stuff that comes out, we don't need any default values
        push_output_state(false, true);

        if (get_param_integer('raw', 0) == 1) {
            $map['raw'] = '1';
        }

        // Cleanup dependencies that will already have been handled
        global $CSSS, $JAVASCRIPTS;
        unset($CSSS['global']);
        unset($CSSS['no_cache']);
        unset($JAVASCRIPTS['global']);
        unset($JAVASCRIPTS['staff']);

        // And, go
        $out = new Tempcode();
        $_eval = do_block($map['block'], $map);
        $eval = $_eval->evaluate();
        if (get_param_integer('no_web_resources', 0) == 0) {
            $out->attach(symbol_tempcode('CSS_TEMPCODE'));
        }
        $out->attach($eval);
        if (get_param_integer('no_web_resources', 0) == 0) {
            $out->attach(symbol_tempcode('JS_TEMPCODE'));
        }
        return $out;
    }
}
