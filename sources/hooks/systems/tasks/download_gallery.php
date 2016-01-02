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
 * @package    galleries
 */

/**
 * Hook class.
 */
class Hook_task_download_gallery
{
    /**
     * Run the task hook.
     *
     * @param  ID_TEXT $cat The gallery to download
     * @return ?array A tuple of at least 2: Return mime-type, content (either Tempcode, or a string, or a filename and file-path pair to a temporary file), map of HTTP headers if transferring immediately, map of ini_set commands if transferring immediately (null: show standard success message)
     */
    public function run($cat)
    {
        require_code('galleries');
        require_lang('galleries');
        require_code('zip');

        $gallery_rows = $GLOBALS['SITE_DB']->query_select('galleries', array('*'), array('name' => $cat), '', 1);
        if (!array_key_exists(0, $gallery_rows)) {
            return array(null, do_lang_tempcode('MISSING_RESOURCE', 'gallery'));
        }
        $gallery_row = $gallery_rows[0];

        $headers = array();
        $headers['Content-Type'] = 'application/octet-stream; authoritative=true;';
        $filename = 'gallery-' . $cat . '.zip';
        $headers['Content-Disposition'] = 'attachment; filename="' . str_replace("\r", '', str_replace("\n", '', $filename)) . '"';

        $ini_set = array();
        $ini_set['ocproducts.xss_detect'] = '0';
        $ini_set['zlib.output_compression'] = 'Off';

        $rows_images = $GLOBALS['SITE_DB']->query_select('images', array('id', 'url', 'add_date'), array('cat' => $cat, 'validated' => 1));
        $rows_videos = $GLOBALS['SITE_DB']->query_select('videos', array('id', 'url', 'add_date'), array('cat' => $cat, 'validated' => 1));
        $rows_combined = array();
        foreach ($rows_images as $row) {
            $rows_combined[] = $row + array('content_type' => 'image');
        }
        foreach ($rows_videos as $row) {
            $rows_combined[] = $row + array('content_type' => 'video');
        }
        $array = array();
        foreach ($rows_combined as $row) {
            if (addon_installed('content_privacy')) {
                require_code('content_privacy');
                if (!has_privacy_access($row['content_type'], strval($row['id']))) {
                    continue;
                }
            }

            $full_path = null;
            $data = null;
            if ((url_is_local($row['url'])) && (file_exists(get_file_base() . '/' . urldecode($row['url'])))) {
                $path = urldecode($row['url']);
                $full_path = get_file_base() . '/' . $path;
                if (file_exists($full_path)) {
                    $time = filemtime($full_path);
                    $name = $path;
                } else {
                    continue;
                }
            } else {
                continue; // Actually we won't include them, if they are not local it implies it is not reasonable for them to lead to server load, and they may not even be native files

                $time = $row['add_date'];
                $name = basename(urldecode($row['url']));
                $data = http_download_file($row['url']);
            }

            $array[] = array('name' => preg_replace('#^uploads/galleries/#', '', $name), 'time' => $time, 'data' => $data, 'full_path' => $full_path);
        }

        if ($gallery_row['rep_image'] != '') {
            if ((url_is_local($gallery_row['rep_image'])) && (file_exists(get_file_base() . '/' . urldecode($gallery_row['rep_image'])))) {
                $path = urldecode($gallery_row['rep_image']);
                $full_path = get_file_base() . '/' . $path;
                if (file_exists($full_path)) {
                    $time = filemtime($full_path);
                    $name = $path;
                    $data = file_get_contents($full_path);
                }
            } else {
                $time = $gallery_row['add_date'];
                $name = basename(urldecode($gallery_row['rep_image']));
                $data = http_download_file($gallery_row['rep_image']);
            }
            $array[] = array('name' => preg_replace('#^uploads/(galleries|repimages)/#', '', $name), 'time' => $time, 'data' => $data);
        }

        $outfile_path = cms_tempnam('csv');

        create_zip_file($array, false, false, $outfile_path);

        return array('application/octet-stream', array($filename, $outfile_path), $headers, $ini_set);
    }
}
