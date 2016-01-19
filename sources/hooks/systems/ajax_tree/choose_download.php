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
 * @package    downloads
 */

/**
 * Hook class.
 */
class Hook_choose_download
{
    /**
     * Run function for ajax-tree hooks. Generates XML for a tree list, which is interpreted by JavaScript and expanded on-demand (via new calls).
     *
     * @param  ?ID_TEXT $id The ID to do under (null: root)
     * @param  array $options Options being passed through
     * @param  ?ID_TEXT $default The ID to select by default (null: none)
     * @return string XML in the special category,entry format
     */
    public function run($id, $options, $default = null)
    {
        require_code('downloads');

        if ((!is_numeric($id)) && ($id != '')) { // This code is actually for compo.sr, for the addon directory
            if (substr($id, 0, 8) == 'Version ') {
                $id_float = floatval(substr($id, 8));
                do {
                    $str = 'Version ' . float_to_raw_string($id_float, 1);
                    $_id = $GLOBALS['SITE_DB']->query_select_value_if_there('download_categories', 'id', array('parent_id' => 3, $GLOBALS['SITE_DB']->translate_field_ref('category') => $str));
                    if (is_null($_id)) {
                        $id_float -= 0.1;
                    }
                } while ((is_null($_id)) && ($id_float != 0.0));
            } else {
                $_id = $GLOBALS['SITE_DB']->query_select_value_if_there('download_categories', 'id', array($GLOBALS['SITE_DB']->translate_field_ref('category') => $id));
            }
            if (is_null($_id)) {
                warn_exit(do_lang_tempcode('MISSING_RESOURCE', 'download_category'));
            }
            $id = strval($_id);
        }

        $only_owned = array_key_exists('only_owned', $options) ? (is_null($options['only_owned']) ? null : intval($options['only_owned'])) : null;
        $shun = array_key_exists('shun', $options) ? $options['shun'] : null;
        $editable_filter = array_key_exists('editable_filter', $options) ? ($options['editable_filter']) : false;
        $tar_filter = array_key_exists('tar_filter', $options) ? ($options['original_filename']) : false;
        $tree = get_downloads_tree($only_owned, is_null($id) ? null : intval($id), null, null, $shun, (get_param_integer('full_depth', 0) == 1) ? null : (is_null($id) ? 0 : 1), false, $editable_filter, $tar_filter);

        $levels_to_expand = array_key_exists('levels_to_expand', $options) ? ($options['levels_to_expand']) : intval(get_value('levels_to_expand__' . substr(get_class($this), 5), null, true));
        $options['levels_to_expand'] = max(0, $levels_to_expand - 1);

        if (!has_actual_page_access(null, 'downloads')) {
            $tree = array();
        }

        $file_type = get_param_string('file_type', '');

        $out = '';

        $out .= '<options>' . serialize($options) . '</options>';

        foreach ($tree as $t) {
            $_id = $t['id'];
            if (($id === strval($_id)) || (get_param_integer('full_depth', 0) == 1)) { // Possible when we look under as a root
                asort($t['entries']);

                foreach ($t['entries'] as $eid => $etitle) {
                    $download_rows = $GLOBALS['SITE_DB']->query_select('download_downloads', array('*'), array('id' => $eid), '', 1);

                    if ($file_type != '') {
                        if (substr($download_rows[0]['original_filename'], -strlen($file_type) - 1) != '.' . $file_type) {
                            continue;
                        }
                    }

                    $description = get_translated_text($download_rows[0]['description']);
                    $description_html = get_translated_tempcode('download_downloads', $download_rows[0], 'description');

                    if (addon_installed('galleries')) {
                        // Images
                        $images_details = new Tempcode();
                        $_out = new Tempcode();
                        require_lang('galleries');
                        $cat = 'download_' . strval($eid);
                        $map = array('cat' => $cat);
                        if ((!has_privilege(get_member(), 'see_unvalidated')) && (addon_installed('unvalidated'))) {
                            $map['validated'] = 1;
                        }
                        $rows = $GLOBALS['SITE_DB']->query_select('images', array('*'), $map, 'ORDER BY id', 200/*Stop sillyness, could be a DOS attack*/);
                        $counter = 0;
                        $div = 2;
                        $_out = new Tempcode();
                        $_row = new Tempcode();
                        require_code('images');
                        while (array_key_exists($counter, $rows)) {
                            $row = $rows[$counter];

                            $view_url = $row['url'];
                            if (url_is_local($view_url)) {
                                $view_url = get_custom_base_url() . '/' . $view_url;
                            }
                            $thumb_url = ensure_thumbnail($row['url'], $row['thumb_url'], 'galleries', 'images', $row['id']);
                            $description_image = get_translated_tempcode('download_downloads', $row, 'description');
                            $thumb = do_image_thumb($thumb_url, '');
                            $iedit_url = new Tempcode();
                            $_content = do_template('DOWNLOAD_SCREEN_IMAGE', array('_GUID' => '45905cd5823af4b066ccbc18a39edd74', 'ID' => strval($row['id']), 'VIEW_URL' => $view_url, 'EDIT_URL' => $iedit_url, 'THUMB' => $thumb, 'DESCRIPTION' => $description_image));

                            $_row->attach(do_template('DOWNLOAD_GALLERY_IMAGE_CELL', array('_GUID' => 'e016f7655dc6519d9536aa51e4bed57b', 'CONTENT' => $_content)));

                            if (($counter % $div == 1) && ($counter != 0)) {
                                $_out->attach(do_template('DOWNLOAD_GALLERY_ROW', array('_GUID' => '59744ea8227da11901ddb3f4de04c88d', 'CELLS' => $_row)));
                                $_row = new Tempcode();
                            }

                            $counter++;
                        }
                        if (!$_row->is_empty()) {
                            $_out->attach(do_template('DOWNLOAD_GALLERY_ROW', array('_GUID' => '3f368a6baa7e544f76e66d4bd8291c4b', 'CELLS' => $_row)));
                        }
                        $description_html = do_template('DOWNLOAD_AND_IMAGES_SIMPLE_BOX', array('_GUID' => 'a273f4beb94672ee44bdfdf06bf328c8', 'DESCRIPTION' => $description_html, 'IMAGES' => $_out));
                    }

                    $out .= '<entry id="' . xmlentities(strval($eid)) . '" description="' . xmlentities(strip_comcode($description)) . '" description_html="' . xmlentities($description_html->evaluate()) . '" title="' . xmlentities($etitle) . '" selectable="true"></entry>';
                }
                continue;
            }
            $title = $t['title'];
            $has_children = ($t['child_count'] != 0) || ($t['child_entry_count'] != 0);

            $out .= '<category id="' . xmlentities(strval($_id)) . '" title="' . xmlentities($title) . '" has_children="' . ($has_children ? 'true' : 'false') . '" selectable="false"></category>';

            if ($levels_to_expand > 0) {
                $out .= '<expand>' . xmlentities(strval($_id)) . '</expand>';
            }
        }

        // Mark parent cats for pre-expansion
        if ((!is_null($default)) && ($default != '')) {
            $cat = $GLOBALS['SITE_DB']->query_select_value_if_there('download_downloads', 'category_id', array('id' => intval($default)));
            while (!is_null($cat)) {
                $out .= '<expand>' . strval($cat) . '</expand>';
                $cat = $GLOBALS['SITE_DB']->query_select_value_if_there('download_categories', 'parent_id', array('id' => $cat));
            }
        }

        return '<result>' . $out . '</result>';
    }

    /**
     * Generate a simple selection list for the ajax-tree hook. Returns a normal <select> style <option>-list, for fallback purposes
     *
     * @param  ?ID_TEXT $id The ID to do under (null: root) - not always supported
     * @param  array $options Options being passed through
     * @param  ?ID_TEXT $it The ID to select by default (null: none)
     * @return Tempcode The nice list
     */
    public function simple($id, $options, $it = null)
    {
        require_code('downloads');

        $only_owned = array_key_exists('only_owned', $options) ? (is_null($options['only_owned']) ? null : intval($options['only_owned'])) : null;
        $shun = array_key_exists('shun', $options) ? $options['shun'] : null;
        $editable_filter = array_key_exists('editable_filter', $options) ? ($options['editable_filter']) : false;
        return create_selection_list_downloads_tree(is_null($it) ? null : intval($it), $only_owned, $shun, false, $editable_filter);
    }
}
