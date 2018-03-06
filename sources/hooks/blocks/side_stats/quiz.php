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
 * @package    quizzes
 */

/**
 * Hook class.
 */
class Hook_stats_quiz
{
    /**
     * Show a stats section.
     *
     * @return Tempcode The result of execution
     */
    public function run()
    {
        if (!addon_installed('quizzes')) {
            return new Tempcode();
        }

        require_lang('quiz');

        $bits = new Tempcode();
        if (get_option('quiz_show_stats_count_total_open') == '1') {
            $bits->attach(do_template('BLOCK_SIDE_STATS_SUBLINE', array('_GUID' => '736e5008b15c984768234dde7586adf7', 'KEY' => do_lang_tempcode('QUIZZES'), 'VALUE' => integer_format($GLOBALS['SITE_DB']->query_select_value('quizzes', 'COUNT(*)')))));
        }
        if ($bits->is_empty_shell()) {
            return new Tempcode();
        }
        $section = do_template('BLOCK_SIDE_STATS_SECTION', array('_GUID' => '88c7eb369ee73af200f71d029b84baf5', 'SECTION' => do_lang_tempcode('QUIZZES'), 'CONTENT' => $bits));

        return $section;
    }
}
