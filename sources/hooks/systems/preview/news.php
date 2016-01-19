<?php /*

 Composr
 Copyright (c) ocProducts, 2004-2016

 See text/EN/licence.txt for full licencing information.


 NOTE TO PROGRAMMERS:
   Do not edit this file. If you need to make changes, save your changed file to the appropriate *_custom folder
   **** If you ignore this advice, then your website upgrades (e.g. for bug fixes) will likely kill your changes ****

*/

/**
 * @license    http://opensource.org/licenses/cpal_1.0 Common Public Attribution License
 * @copyright  ocProducts Ltd
 * @package    news
 */

/**
 * Hook class.
 */
class Hook_preview_news
{
    /**
     * Find whether this preview hook applies.
     *
     * @return array Triplet: Whether it applies, the attachment ID type, whether the forum DB is used [optional]
     */
    public function applies()
    {
        $applies = ((get_page_name() == 'cms_news') || (get_page_name() == 'cms_blogs')) && ((get_param_string('type', '') == 'add') || (get_param_string('type', '') == '_edit'));
        return array($applies, 'news', false);
    }

    /**
     * Run function for preview hooks.
     *
     * @return array A pair: The preview, the updated post Comcode
     */
    public function run()
    {
        $original_comcode = post_param_string('post');

        $posting_ref_id = post_param_integer('posting_ref_id', mt_rand(0, mt_getrandmax() - 1));
        $post_bits = do_comcode_attachments($original_comcode, 'news', strval(-$posting_ref_id), true, $GLOBALS['SITE_DB']);
        $post_comcode = $post_bits['comcode'];
        $post_html = $post_bits['tempcode'];

        $map_table_map = array();
        $map_table_map[post_param_string('label_for__title')] = escape_html(post_param_string('title'));
        $map_table_map[post_param_string('label_for__post')] = $post_html;
        $map_table_map[post_param_string('label_for__news')] = comcode_to_tempcode(post_param_string('news', ''));

        require_code('templates_map_table');
        $map_table_fields = new Tempcode();
        foreach ($map_table_map as $key => $val) {
            $map_table_fields->attach(map_table_field($key, $val, true));
        }
        $output = do_template('MAP_TABLE', array('_GUID' => '780aeedc08a960750fa4634e26db56d5', 'FIELDS' => $map_table_fields));

        return array($output, $post_comcode);
    }
}
