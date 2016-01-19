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
 * @package    chat
 */

require_code('crud_module');

/**
 * Module page class.
 */
class Module_admin_chat extends Standard_crud_module
{
    public $lang_type = 'CHATROOM';
    public $select_name = 'NAME';
    public $author = 'Philip Withnall';
    public $archive_entry_point = '_SEARCH:chat';
    public $archive_label = 'CHAT_LOBBY';
    public $view_entry_point = '_SEARCH:chat:room:_ID';
    public $permission_module = 'chat';
    public $menu_label = 'SECTION_CHAT';

    /**
     * Find entry-points available within this module.
     *
     * @param  boolean $check_perms Whether to check permissions.
     * @param  ?MEMBER $member_id The member to check permissions as (null: current user).
     * @param  boolean $support_crosslinks Whether to allow cross links to other modules (identifiable via a full-page-link rather than a screen-name).
     * @param  boolean $be_deferential Whether to avoid any entry-point (or even return null to disable the page in the Sitemap) if we know another module, or page_group, is going to link to that entry-point. Note that "!" and "browse" entry points are automatically merged with container page nodes (likely called by page-groupings) as appropriate.
     * @return ?array A map of entry points (screen-name=>language-code/string or screen-name=>[language-code/string, icon-theme-image]) (null: disabled).
     */
    public function get_entry_points($check_perms = true, $member_id = null, $support_crosslinks = true, $be_deferential = false)
    {
        $ret = array(
            'browse' => array('MANAGE_CHATROOMS', 'menu/social/chat/chat'),
        );
        $ret += parent::get_entry_points();
        $ret += array(
            'delete_all' => array('DELETE_ALL_CHATROOMS', 'menu/_generic_admin/delete'),
        );
        return $ret;
    }

    public $title;

    /**
     * Module pre-run function. Allows us to know metadata for <head> before we start streaming output.
     *
     * @param  boolean $top_level Whether this is running at the top level, prior to having sub-objects called.
     * @param  ?ID_TEXT $type The screen type to consider for metadata purposes (null: read from environment).
     * @return ?Tempcode Tempcode indicating some kind of exceptional output (null: none).
     */
    public function pre_run($top_level = true, $type = null)
    {
        $type = get_param_string('type', 'browse');

        require_lang('chat');

        set_helper_panel_tutorial('tut_chat');

        if ($type == 'browse') {
            $also_url = build_url(array('page' => 'cms_chat'), get_module_zone('cms_chat'));
            attach_message(do_lang_tempcode('menus:ALSO_SEE_CMS', escape_html($also_url->evaluate())), 'inform', true);
        }

        if ($type == 'delete_all' || $type == '_delete_all') {
            $this->title = get_screen_title('DELETE_ALL_CHATROOMS');
        }

        return parent::pre_run($top_level);
    }

    /**
     * Standard crud_module run_start.
     *
     * @param  ID_TEXT $type The type of module execution
     * @return Tempcode The output of the run
     */
    public function run_start($type)
    {
        $this->extra_donext_entries = array(
            array('menu/_generic_admin/delete', array('_SELF', array('type' => 'delete_all'), '_SELF'), do_lang('DELETE_ALL_CHATROOMS')),
        );

        require_code('chat');
        require_code('chat2');
        require_css('chat');

        if ($type == 'browse') {
            return $this->browse();
        }
        if ($type == 'delete_all') {
            return $this->delete_all();
        }
        if ($type == '_delete_all') {
            return $this->_delete_all();
        }
        return new Tempcode();
    }

    /**
     * The do-next manager for before content management.
     *
     * @return Tempcode The UI
     */
    public function browse()
    {
        $this->add_one_label = do_lang_tempcode('ADD_CHATROOM');
        $this->edit_this_label = do_lang_tempcode('EDIT_THIS_CHATROOM');
        $this->edit_one_label = do_lang_tempcode('EDIT_CHATROOM');

        require_code('templates_donext');
        return do_next_manager(get_screen_title('MANAGE_CHATROOMS'), comcode_lang_string('DOC_CHAT'),
            array(
                array('menu/_generic_admin/add_one', array('_SELF', array('type' => 'add'), '_SELF'), do_lang('ADD_CHATROOM')),
                array('menu/_generic_admin/edit_one', array('_SELF', array('type' => 'edit'), '_SELF'), do_lang('EDIT_CHATROOM')),
                array('menu/_generic_admin/delete', array('_SELF', array('type' => 'delete_all'), '_SELF'), do_lang('DELETE_ALL_CHATROOMS')),
            ),
            do_lang('MANAGE_CHATROOMS')
        );
    }

    /**
     * Get Tempcode for a adding/editing form.
     *
     * @return array A pair: The input fields, Hidden fields
     */
    public function get_form_fields()
    {
        list($fields, $hidden) = get_chatroom_fields();

        // Permissions
        $fields->attach($this->get_permission_fields(null, null, true));

        return array($fields, $hidden);
    }

    /**
     * Standard crud_module list function.
     *
     * @return Tempcode The selection list
     */
    public function create_selection_list_entries()
    {
        require_code('chat_lobby');

        $rows = $GLOBALS['SITE_DB']->query_select('chat_rooms', array('*'), array('is_im' => 0), 'ORDER BY room_name DESC', 500);
        if (count($rows) == 500) {
            warn_exit(do_lang_tempcode('TOO_MANY_TO_CHOOSE_FROM'));
        }
        $fields = new Tempcode();
        foreach ($rows as $row) {
            if (!handle_chatroom_pruning($row)) {
                $fields->attach(form_input_list_entry(strval($row['id']), false, $row['room_name']));
            }
        }

        return $fields;
    }

    /**
     * Standard crud_module edit form filler.
     *
     * @param  ID_TEXT $id The entry being edited
     * @return array A pair: The input fields, Hidden fields
     */
    public function fill_in_edit_form($id)
    {
        $rows = $GLOBALS['SITE_DB']->query_select('chat_rooms', array('*'), array('id' => intval($id)), '', 1);
        if (!array_key_exists(0, $rows)) {
            warn_exit(do_lang_tempcode('MISSING_RESOURCE', 'chat'));
        }
        $row = $rows[0];

        $allow2 = $row['allow_list'];
        $allow2_groups = $row['allow_list_groups'];
        $disallow2 = $row['disallow_list'];
        $disallow2_groups = $row['disallow_list_groups'];
        $username = $GLOBALS['FORUM_DRIVER']->get_username($row['room_owner']);
        if (is_null($username)) {
            $username = '';
        }//do_lang('UNKNOWN');

        list($fields, $hidden) = get_chatroom_fields(intval($id), false, $row['room_name'], get_translated_text($row['c_welcome']), $username, $allow2, $allow2_groups, $disallow2, $disallow2_groups);

        // Permissions
        $fields->attach($this->get_permission_fields($id));

        $delete_fields = new Tempcode();
        $logs_url = build_url(array('page' => 'chat', 'type' => 'download_logs', 'id' => $id), get_module_zone('chat'));
        $delete_fields->attach(form_input_tick(do_lang_tempcode('DELETE'), do_lang_tempcode('DESCRIPTION_DELETE_CHATROOM', escape_html($logs_url->evaluate())), 'delete', false));

        return array($fields, $hidden, $delete_fields, null, true);
    }

    /**
     * Standard crud_module add actualiser.
     *
     * @return ID_TEXT The entry added
     */
    public function add_actualisation()
    {
        list($allow2, $allow2_groups, $disallow2, $disallow2_groups) = read_in_chat_perm_fields();

        $metadata = actual_metadata_get_fields('chat', null);

        $id = add_chatroom(post_param_string('c_welcome'), post_param_string('room_name'), $GLOBALS['FORUM_DRIVER']->get_member_from_username(post_param_string('room_owner')), $allow2, $allow2_groups, $disallow2, $disallow2_groups, post_param_string('room_lang', user_lang()));

        set_url_moniker('chat', strval($id));

        $this->set_permissions($id);

        if (addon_installed('content_reviews')) {
            content_review_set('chat', strval($id));
        }

        return strval($id);
    }

    /**
     * Standard crud_module edit actualiser.
     *
     * @param  ID_TEXT $id The entry being edited
     */
    public function edit_actualisation($id)
    {
        $_room_owner = post_param_string('room_owner', STRING_MAGIC_NULL);
        $room_owner = ($_room_owner == STRING_MAGIC_NULL) ? INTEGER_MAGIC_NULL : $GLOBALS['FORUM_DRIVER']->get_member_from_username($_room_owner);
        if ($_room_owner != STRING_MAGIC_NULL) {
            list($allow2, $allow2_groups, $disallow2, $disallow2_groups) = read_in_chat_perm_fields();
        } else {
            $allow2 = STRING_MAGIC_NULL;
            $allow2_groups = STRING_MAGIC_NULL;
            $disallow2 = STRING_MAGIC_NULL;
            $disallow2_groups = STRING_MAGIC_NULL;
        }

        $metadata = actual_metadata_get_fields('chat', $id);

        edit_chatroom(intval($id), post_param_string('c_welcome', STRING_MAGIC_NULL), post_param_string('room_name'), $room_owner, $allow2, $allow2_groups, $disallow2, $disallow2_groups, post_param_string('room_lang', STRING_MAGIC_NULL));

        $this->set_permissions($id);

        if (addon_installed('content_reviews')) {
            content_review_set('chat', $id);
        }
    }

    /**
     * Standard crud_module delete actualiser.
     *
     * @param  ID_TEXT $id The entry being deleted
     */
    public function delete_actualisation($id)
    {
        delete_chatroom(intval($id));
    }

    /**
     * The UI to delete all chatrooms.
     *
     * @return Tempcode The UI
     */
    public function delete_all()
    {
        $fields = new Tempcode();
        require_code('form_templates');
        $fields->attach(form_input_tick(do_lang_tempcode('PROCEED'), do_lang_tempcode('Q_SURE'), 'continue_delete', false));
        $posting_name = do_lang_tempcode('PROCEED');
        $posting_url = build_url(array('page' => '_SELF', 'type' => '_delete_all'), '_SELF');
        $text = paragraph(do_lang_tempcode('CONFIRM_DELETE_ALL_CHATROOMS'));
        return do_template('FORM_SCREEN', array('_GUID' => 'fdf02f5b3a3b9ce6d1abaccf0970ed73', 'SKIP_WEBSTANDARDS' => true, 'HIDDEN' => '', 'TITLE' => $this->title, 'FIELDS' => $fields, 'SUBMIT_ICON' => 'menu___generic_admin__delete', 'SUBMIT_NAME' => $posting_name, 'URL' => $posting_url, 'TEXT' => $text));
    }

    /**
     * The actualiser to delete all chatrooms.
     *
     * @return Tempcode The UI
     */
    public function _delete_all()
    {
        $delete = post_param_integer('continue_delete', 0);
        if ($delete != 1) {
            $url = build_url(array('page' => '_SELF', 'type' => 'browse'), '_SELF');
            return redirect_screen($this->title, $url, do_lang_tempcode('CANCELLED'));
        } else {
            delete_all_chatrooms();

            return $this->do_next_manager($this->title, do_lang_tempcode('SUCCESS'), null);
        }
    }
}
