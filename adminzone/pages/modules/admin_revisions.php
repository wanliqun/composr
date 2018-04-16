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
 * @package    actionlog
 */

/*
Revision management. Note that this is a simple browse UI with some actions.
Full revision details are actually shown in the action log, against the associated revision action.
*/

/**
 * Module page class.
 */
class Module_admin_revisions
{
    /**
     * Find details of the module.
     *
     * @return ?array Map of module info (null: module is disabled)
     */
    public function info()
    {
        $info = array();
        $info['author'] = 'Chris Graham';
        $info['organisation'] = 'ocProducts';
        $info['hacked_by'] = null;
        $info['hack_version'] = null;
        $info['version'] = 2;
        $info['locked'] = false;
        return $info;
    }

    /**
     * Uninstall the module.
     */
    public function uninstall()
    {
        $GLOBALS['SITE_DB']->drop_table_if_exists('revisions');

        delete_privilege('view_revisions');
        delete_privilege('undo_revisions');
        delete_privilege('delete_revisions');
    }

    /**
     * Install the module.
     *
     * @param  ?integer $upgrade_from What version we're upgrading from (null: new install)
     * @param  ?integer $upgrade_from_hack What hack version we're upgrading from (null: new-install/not-upgrading-from-a-hacked-version)
     */
    public function install($upgrade_from = null, $upgrade_from_hack = null)
    {
        add_privilege('SUBMISSION', 'view_revisions', false);
        add_privilege('SUBMISSION', 'undo_revisions', false);
        add_privilege('SUBMISSION', 'delete_revisions', false);

        $GLOBALS['SITE_DB']->create_table('revisions', array(
            'id' => '*AUTO',
            'r_resource_type' => 'ID_TEXT',
            'r_resource_id' => 'ID_TEXT',
            'r_category_id' => 'ID_TEXT',
            'r_original_title' => 'SHORT_TEXT',
            'r_original_text' => 'LONG_TEXT',
            'r_original_content_owner' => 'MEMBER',
            'r_original_content_timestamp' => 'TIME',
            'r_original_resource_fs_path' => 'LONG_TEXT',
            'r_original_resource_fs_record' => 'LONG_TEXT',
            'r_actionlog_id' => '?AUTO_LINK',
            'r_moderatorlog_id' => '?AUTO_LINK',
        ));
        $GLOBALS['SITE_DB']->create_index('revisions', 'lookup_by_id', array('r_resource_type', 'r_resource_id'));
        $GLOBALS['SITE_DB']->create_index('revisions', 'lookup_by_cat', array('r_resource_type', 'r_category_id'));
        $GLOBALS['SITE_DB']->create_index('revisions', 'actionlog_link', array('r_actionlog_id'));
        $GLOBALS['SITE_DB']->create_index('revisions', 'moderatorlog_link', array('r_moderatorlog_id'));
    }

    /**
     * Find entry-points available within this module.
     *
     * @param  boolean $check_perms Whether to check permissions
     * @param  ?MEMBER $member_id The member to check permissions as (null: current user)
     * @param  boolean $support_crosslinks Whether to allow cross links to other modules (identifiable via a full-page-link rather than a screen-name)
     * @param  boolean $be_deferential Whether to avoid any entry-point (or even return null to disable the page in the Sitemap) if we know another module, or page_group, is going to link to that entry-point. Note that "!" and "browse" entry points are automatically merged with container page nodes (likely called by page-groupings) as appropriate.
     * @return ?array A map of entry points (screen-name=>language-code/string or screen-name=>[language-code/string, icon-theme-image]) (null: disabled)
     */
    public function get_entry_points($check_perms = true, $member_id = null, $support_crosslinks = true, $be_deferential = false)
    {
        if (!addon_installed('actionlog')) {
            return null;
        }

        return array(
            'browse' => array('REVISIONS', 'admin/revisions'),
        );
    }

    public $title;

    /**
     * Module pre-run function. Allows us to know metadata for <head> before we start streaming output.
     *
     * @return ?Tempcode Tempcode indicating some kind of exceptional output (null: none)
     */
    public function pre_run()
    {
        $error_msg = new Tempcode();
        if (!addon_installed__messaged('actionlog', $error_msg)) {
            return $error_msg;
        }

        require_lang('actionlog');

        $type = get_param_string('type', 'browse');

        if ($type == 'browse') {
            $this->title = get_screen_title('REVISIONS');
        }

        if ($type == 'delete') {
            $this->title = get_screen_title('DELETE_REVISION');
        }

        /*if ($type == 'undo') {
            $this->title = get_screen_title('UNDO_REVISION');
        }*/

        return null;
    }

    /**
     * Execute the module.
     *
     * @return Tempcode The result of execution
     */
    public function run()
    {
        check_privilege('view_revisions');

        require_css('adminzone');

        $type = get_param_string('type', 'browse');

        if ($type == 'browse') {
            return $this->gui();
        }

        if ($type == 'delete') {
            return $this->delete();
        }

        /*if ($type == 'undo') {
            return $this->undo();
        }*/

        return new Tempcode();
    }

    /**
     * The UI to show the revision history for anything matching the query.
     * More details are shown in the actionlog, which is linked from here.
     *
     * @return Tempcode The UI
     */
    public function gui()
    {
        require_all_lang();

        $resource_types = get_param_string('resource_types', '');
        if ($resource_types == '') {
            $resource_types = null;
        }
        $resource_id = get_param_string('resource_id', '');
        if ($resource_id == '') {
            $resource_id = null;
        }
        $category_id = get_param_string('category_id', '');
        if ($category_id == '') {
            $category_id = null;
        }
        $username = get_param_string('username', '');
        if ($username == '') {
            $username = null;
        }
        $member_id = null;
        if ($username !== null) {
            $member_id = $GLOBALS['FORUM_DRIVER']->get_member_from_username($username);
        }

        $row_renderer = array($this, '_render_revision');
        $_header_row = array(
            do_lang_tempcode('VIEW'),
            do_lang_tempcode('MEMBER'),
            do_lang_tempcode('DATE'),
            do_lang_tempcode('CONTENT_OWNER'),
            do_lang_tempcode('CONTENT_DATE_TIME'),
            do_lang_tempcode('ACTION'),
        );
        if (has_privilege(get_member(), 'delete_revisions')) {
            $_header_row[] = do_lang_tempcode('DELETE');
        }

        require_code('revisions_engine_database');
        $revision_engine = new RevisionEngineDatabase();
        return $revision_engine->ui_browse_revisions($this->title, $_header_row, ($resource_types === null) ? null : explode(',', $resource_types), $row_renderer, $resource_id, $category_id, $member_id, null, true);
    }

    /**
     * Render a revision.
     *
     * @param  array $revision A revision map
     * @return ?Tempcode A rendered revision row (null: won't render)
     */
    public function _render_revision($revision)
    {
        require_code('content');
        list($content_title, , , , $content_url) = content_get_details($revision['r_resource_type'], $revision['r_resource_id']);
        if (empty($content_title)) {
            $content_title = $revision['r_original_title'];
        }
        if ($content_url === null) {
            $view_link = do_lang_tempcode('NA_EM');
        } else {
            $view_link = hyperlink($content_url, $content_title, false, true);
        }

        $member_link = $GLOBALS['FORUM_DRIVER']->member_profile_hyperlink($revision['log_member_id']);

        $date = get_timezoned_date_time($revision['log_time']);

        $action = do_lang_tempcode($revision['log_action']);
        $do_actionlog = has_actual_page_access(get_member(), 'admin_actionlog');
        if ($do_actionlog) {
            $actionlog_url = build_url(array('page' => 'admin_actionlog', 'type' => 'view', 'id' => ($revision['r_actionlog_id'] === null) ? $revision['r_moderatorlog_id'] : $revision['r_actionlog_id'], 'mode' => ($revision['r_actionlog_id'] === null) ? 'cns' : 'cms'), get_module_zone('admin_actionlog'));
            $action = hyperlink($actionlog_url, $action, false, false, '#' . strval(($revision['r_actionlog_id'] === null) ? $revision['r_moderatorlog_id'] : $revision['r_actionlog_id']));
        }

        $_revision = array(
            $view_link,
            $member_link,
            escape_html($date),
            $GLOBALS['FORUM_DRIVER']->member_profile_hyperlink($revision['r_original_content_owner']),
            escape_html(get_timezoned_date_time($revision['r_original_content_timestamp'])),
            $action,
        );

        if (has_privilege(get_member(), 'delete_revisions')) {
            $delete_url = get_self_url(false, false, array('type' => 'delete', 'id' => $revision['id']));
            $delete = do_template('BUTTON_SCREEN_ITEM', array(
                '_GUID' => 'fff6126f163c05a6966208a6f1244e08',
                'REL' => 'delete',
                'IMMEDIATE' => true,
                'URL' => $delete_url,
                'FULL_TITLE' => do_lang_tempcode('DELETE_REVISION'),
                'TITLE' => do_lang_tempcode('DELETE'),
                'IMG' => 'admin/delete3',
            ));
            $_revision[] = $delete;
        }

        /*if (has_privilege(get_member(), 'undo_revisions')) {
            $undo_url = build_url(array('page' => '_SELF', 'type' => 'undo', 'id' => $revision['id']), '_SELF');
            $delete = do_template('BUTTON_SCREEN_ITEM', array(
                '_GUID' => '8b3b9bc19246045bf1537ee0f6b67fe1',
                'REL' => 'undo',
                'IMMEDIATE' => true,
                'URL' => $undo_url,
                'FULL_TITLE' => do_lang_tempcode('UNDO_REVISION'),
                'TITLE' => do_lang_tempcode('UNDO'),
                'IMG' => 'buttons/undo',
            ));
            $_revision[] = $delete;
        }*/

        require_code('templates_results_table');
        return results_entry($_revision, false);
    }

    /**
     * The actualiser to delete a revision.
     *
     * @return Tempcode The UI
     */
    public function delete()
    {
        check_privilege('delete_revisions');

        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            warn_exit(do_lang_tempcode('IMPROPERLY_FILLED_IN'));
        }

        $revision_type = get_param_string('revision_type', 'database', INPUT_FILTER_GET_COMPLEX);
        $id = get_param_integer('id');

        if ($revision_type == 'database') {
            require_code('revisions_engine_database');
            $revision_engine_database = new RevisionEngineDatabase();

            $revision_engine_database->delete_revision($id);
        } else {
            require_code('revisions_engine_files');
            $revision_engine_files = new RevisionEngineFiles();

            list($directory, $filename_id, $ext) = unserialize($revision_type);

            $revision_engine_files->delete_revision($directory, $filename_id, $ext, $id);
        }

        $url = get_param_string('redirect', get_self_url(true, false, array('type' => 'browse')), INPUT_FILTER_URL_INTERNAL);

        return redirect_screen($this->title, $url, do_lang_tempcode('SUCCESS'));
    }

    /* *
     * The actualiser to undo a revision. NOT CURRENTLY IMPLEMENTED
     *
     * @return Tempcode The UI
     */
    /*public function undo()
    {
        check_privilege('undo_revisions');
        ...
    }*/
}
