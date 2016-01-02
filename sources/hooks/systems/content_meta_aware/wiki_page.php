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
 * @package    wiki
 */

/**
 * Hook class.
 */
class Hook_content_meta_aware_wiki_page
{
    /**
     * Get content type details. Provides information to allow task reporting, randomisation, and add-screen linking, to function.
     *
     * @param  ?ID_TEXT $zone The zone to link through to (null: autodetect).
     * @return ?array Map of award content-type info (null: disabled).
     */
    public function info($zone = null)
    {
        return array(
            'support_custom_fields' => true,

            'content_type_label' => 'wiki:_WIKI_PAGE',

            'connection' => $GLOBALS['SITE_DB'],
            'table' => 'wiki_pages',
            'id_field' => 'id',
            'id_field_numeric' => true,
            'parent_category_field' => null,
            'parent_category_meta_aware_type' => 'wiki_page',
            'is_category' => true,
            'is_entry' => false,
            'category_field' => 'id', // For category permissions
            'category_type' => 'wiki_page', // For category permissions
            'parent_spec__table_name' => 'wiki_children',
            'parent_spec__parent_name' => 'parent_id',
            'parent_spec__field_name' => 'child_id',
            'category_is_string' => false,

            'title_field' => 'title',
            'title_field_dereference' => true,
            'description_field' => 'description',
            'thumb_field' => null,
            'thumb_field_is_theme_image' => false,

            'view_page_link_pattern' => '_SEARCH:wiki:browse:_WILD',
            'edit_page_link_pattern' => '_SEARCH:cms_wiki:edit_page:_WILD',
            'view_category_page_link_pattern' => '_SEARCH:wiki:browse:_WILD',
            'add_url' => (function_exists('has_submit_permission') && has_submit_permission('cat_low', get_member(), get_ip_address(), 'cms_wiki')) ? (get_module_zone('cms_wiki') . ':cms_wiki:add_page') : null,
            'archive_url' => ((!is_null($zone)) ? $zone : get_module_zone('wiki')) . ':wiki',

            'support_url_monikers' => false,

            'views_field' => 'wiki_views',
            'order_field' => null,
            'submitter_field' => 'submitter',
            'author_field' => null,
            'add_time_field' => 'add_date',
            'edit_time_field' => null,
            'date_field' => 'add_date',
            'validated_field' => null,

            'seo_type_code' => 'wiki_page',

            'feedback_type_code' => null,

            'permissions_type_code' => null, // null if has no permissions

            'search_hook' => 'wiki_pages',
            'rss_hook' => 'wiki',
            'attachment_hook' => 'wiki_page',
            'unvalidated_hook' => null,
            'notification_hook' => 'wiki',
            'sitemap_hook' => 'wiki_page',

            'addon_name' => 'wiki',

            'cms_page' => 'wiki',
            'module' => 'wiki',

            'commandr_filesystem_hook' => 'wiki',
            'commandr_filesystem__is_folder' => true,

            'support_revisions' => true,

            'support_privacy' => false,

            'support_content_reviews' => true,

            'actionlog_regexp' => '\w+_WIKI_PAGE',
        );
    }

    /**
     * Run function for content hooks. Renders a content box for an award/randomisation.
     *
     * @param  array $row The database row for the content
     * @param  ID_TEXT $zone The zone to display in
     * @param  boolean $give_context Whether to include context (i.e. say WHAT this is, not just show the actual content)
     * @param  boolean $include_breadcrumbs Whether to include breadcrumbs (if there are any)
     * @param  ?ID_TEXT $root Virtual root to use (null: none)
     * @param  boolean $attach_to_url_filter Whether to copy through any filter parameters in the URL, under the basis that they are associated with what this box is browsing
     * @param  ID_TEXT $guid Overridden GUID to send to templates (blank: none)
     * @return Tempcode Results
     */
    public function run($row, $zone, $give_context = true, $include_breadcrumbs = true, $root = null, $attach_to_url_filter = false, $guid = '')
    {
        require_code('wiki');

        return render_wiki_page_box($row, $zone, $give_context, $include_breadcrumbs, is_null($root) ? null : intval($root), $guid);
    }
}
