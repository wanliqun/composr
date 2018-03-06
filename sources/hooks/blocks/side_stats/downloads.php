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
 * @package    downloads
 */

/**
 * Hook class.
 */
class Hook_stats_downloads
{
    /**
     * Show a stats section.
     *
     * @return Tempcode The result of execution
     */
    public function run()
    {
        if (!addon_installed('downloads')) {
            return new Tempcode();
        }

        require_code('files');
        require_lang('downloads');
        require_code('downloads_stats');
        $bits = new Tempcode();

        if (get_option('downloads_show_stats_count_total') == '1') {
            $bits->attach(do_template('BLOCK_SIDE_STATS_SUBLINE', array('_GUID' => 'ff2bd884d88ddc8c5a81cff897f99a5a', 'KEY' => do_lang_tempcode('COUNT_TOTAL'), 'VALUE' => integer_format(get_num_archive_downloads()))));
        }
        if (get_option('downloads_show_stats_count_archive') == '1') {
            $bits->attach(do_template('BLOCK_SIDE_STATS_SUBLINE', array('_GUID' => '3d221f2145581a3af51c8948f28b7ac7', 'KEY' => do_lang_tempcode('COUNT_ARCHIVE'), 'VALUE' => get_download_archive_size())));
        }
        if (get_option('downloads_show_stats_count_downloads') == '1') {
            $bits->attach(do_template('BLOCK_SIDE_STATS_SUBLINE', array('_GUID' => '47c544ef053f9be47e2c48c3a694da1c', 'KEY' => do_lang_tempcode('COUNT_DOWNLOADS'), 'VALUE' => integer_format(get_num_downloads_downloaded()))));
        }
        if (get_option('downloads_show_stats_count_bandwidth') == '1') {
            $bits->attach(do_template('BLOCK_SIDE_STATS_SUBLINE', array('_GUID' => 'b2589ae83652953ece220267043d75c9', 'KEY' => do_lang_tempcode('COUNT_BANDWIDTH'), 'VALUE' => clean_file_size(get_download_bandwidth()))));
        }
        if ($bits->is_empty_shell()) {
            return new Tempcode();
        }

        $files = do_template('BLOCK_SIDE_STATS_SECTION', array('_GUID' => '99ae3f35b3e5eda18901e97ac385d99c', 'SECTION' => do_lang_tempcode('SECTION_DOWNLOADS'), 'CONTENT' => $bits));

        return $files;
    }
}
