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
 * @package    downloads
 */

/**
 * Hook class.
 */
class Hook_preview_download
{
    /**
     * Find whether this preview hook applies.
     *
     * @return array Triplet: Whether it applies, the attachment ID type, whether the forum DB is used [optional]
     */
    public function applies()
    {
        $applies = (get_page_name() == 'cms_downloads') && ((get_param_string('type', '') == 'add') || (get_param_string('type', '') == '_edit'));
        return array($applies, null, false);
    }

    /**
     * Run function for preview hooks.
     *
     * @return array A pair: The preview, the updated post Comcode
     */
    public function run()
    {
        require_code('downloads');
        require_lang('downloads');

        $validated = post_param_integer('validated', 0);

        $category_id = post_param_integer('category_id');
        $name = post_param_string('name');
        $out_mode_id = post_param_integer('out_mode_id', -1);
        if ($out_mode_id == -1) {
            $out_mode_id = null;
        }
        $licence = post_param_integer('licence', -1);
        if ($licence == -1) {
            $licence = null;
        }

        $description = post_param_string('description', '');
        $author = post_param_string('author', get_site_name());
        $comments = post_param_string('comments', '');
        $default_pic = post_param_integer('default_pic', 0);
        $allow_rating = post_param_integer('allow_rating', 0);
        $allow_comments = post_param_integer('allow_comments', 0);
        $notes = post_param_string('notes', '');
        $file_size = post_param_integer('file_size', 0);
        $cost = post_param_integer('cost', 0);
        $submitter_gets_points = post_param_integer('submitter_gets_points', 0);
        $original_filename = post_param_string('original_filename', '');
        $allow_trackbacks = post_param_integer('allow_trackbacks', 0);

        $map = array();

        require_code('uploads');
        is_plupload(true);
        $id = post_param_integer('id', null);

        $views = 0;
        $submitter = get_member();
        $num_downloads = 0;
        $add_date = time();
        if ((array_key_exists('file', $_FILES)) && ($_FILES['file']['tmp_name'] != '')) {
            $original_filename = $_FILES['file']['name'];
            $file_size = $_FILES['file']['size'];

            if (!is_null($id)) {
                attach_message(do_lang_tempcode('UPLOADED_FILE_NOT_DOWNLOADABLE_YET'), 'notice');
            }
        }
        if (!is_null($id)) {
            $rows = $GLOBALS['SITE_DB']->query_select('download_downloads', array('*'), array('id' => $id));
            if (array_key_exists(0, $rows)) {
                $map['id'] = $id;

                $views = $rows[0]['download_views'];
                $submitter = $rows[0]['submitter'];
                $num_downloads = $rows[0]['num_downloads'];
                $add_date = $rows[0]['add_date'];
            }
        }

        $map += array(
            'download_data_mash' => '',
            'download_licence' => $licence,
            'rep_image' => '',
            'edit_date' => is_null($id) ? null : time(),
            'download_submitter_gets_points' => $submitter_gets_points,
            'download_cost' => $cost,
            'original_filename' => $original_filename,
            'download_views' => $views,
            'allow_rating' => $allow_rating,
            'allow_comments' => $allow_comments,
            'allow_trackbacks' => $allow_trackbacks,
            'notes' => post_param_string('notes'),
            'submitter' => $submitter,
            'default_pic' => 1,
            'num_downloads' => $num_downloads,
            'out_mode_id' => $out_mode_id,
            'category_id' => $category_id,
            'name' => $name,
            'url' => '',
            'description' => post_param_string('description'),
            'author' => $author,
            'comments' => $comments,
            'validated' => $validated,
            'add_date' => $add_date,
            'file_size' => $file_size,
        );

        $output = render_download_box($map);

        return array($output, null);
    }
}
