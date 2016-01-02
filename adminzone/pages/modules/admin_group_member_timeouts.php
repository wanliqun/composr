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
 * @package    core
 */

/**
 * Module page class.
 */
class Module_admin_group_member_timeouts
{
    /**
     * Find details of the module.
     *
     * @return ?array Map of module info (null: module is disabled).
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
        if (get_forum_type() != 'cns') {
            return null;
        }

        return array(
            'browse' => array('GROUP_MEMBER_TIMEOUTS', 'menu/adminzone/security/usergroups_temp'),
        );
    }

    public $title;

    /**
     * Module pre-run function. Allows us to know meta-data for <head> before we start streaming output.
     *
     * @return ?Tempcode Tempcode indicating some kind of exceptional output (null: none).
     */
    public function pre_run()
    {
        if (!cron_installed()) {
            attach_message(do_lang_tempcode('CRON_NEEDED_TO_WORK', escape_html(get_tutorial_url('tut_configuration'))), 'warn');
        }

        $type = get_param_string('type', 'browse');

        require_lang('group_member_timeouts');

        $this->title = get_screen_title('GROUP_MEMBER_TIMEOUTS');

        return null;
    }

    /**
     * Execute the module.
     *
     * @return Tempcode The result of execution.
     */
    public function run()
    {
        $type = get_param_string('type', 'browse');

        if ($type == 'browse') {
            return $this->manage();
        }
        if ($type == 'save') {
            return $this->save();
        }

        return new Tempcode();
    }

    /**
     * The UI to manage group member timeouts.
     *
     * @return Tempcode The UI
     */
    public function manage()
    {
        require_code('form_templates');
        require_code('templates_pagination');

        $start = get_param_integer('start', 0);
        $max = get_param_integer('max', 100);
        $max_rows = $GLOBALS[(get_forum_type() == 'cns') ? 'FORUM_DB' : 'SITE_DB']->query_select_value('f_group_member_timeouts', 'COUNT(*)');

        if (get_forum_type() == 'cns') {
            $num_usergroups = $GLOBALS['FORUM_DB']->query_select_value('f_groups', 'COUNT(*)');
            if ($num_usergroups > 50) {
                $_usergroups = $GLOBALS['FORUM_DB']->query_select('f_usergroup_subs s JOIN ' . $GLOBALS['FORUM_DB']->get_table_prefix() . 'f_groups g ON g.id=s.s_group_id', array('g.id', 'g.g_name'), null, 'ORDER BY g_order,' . $GLOBALS['FORUM_DB']->translate_field_ref('g_name'), 1);
                $usergroups = array();
                foreach ($_usergroups as $g) {
                    $usergroups[$g['id']] = get_translated_text($g['g_name'], $GLOBALS['FORUM_DB']);
                }
            } else {
                $usergroups = $GLOBALS['FORUM_DRIVER']->get_usergroup_list();
            }
        } else {
            $usergroups = $GLOBALS['FORUM_DRIVER']->get_usergroup_list();
        }
        unset($usergroups[db_get_first_id()]);

        single_field__start();

        $rows = $GLOBALS[(get_forum_type() == 'cns') ? 'FORUM_DB' : 'SITE_DB']->query_select('f_group_member_timeouts', array('member_id', 'group_id', 'timeout'), null, '', $max, $start);
        $timeouts = array();
        foreach ($rows as $i => $row) {
            // Cleanup disassociated data
            if (!isset($usergroups[$row['group_id']])) {
                $GLOBALS['FORUM_DB']->query_delete('f_group_member_timeouts', array('group_id' => $row['group_id']));
                continue;
            }

            $timeouts[] = array(
                'USERNAME' => $GLOBALS['FORUM_DRIVER']->get_username($row['member_id']),
                'MEMBER_ID' => strval($row['member_id']),
                'GROUP_ID' => strval($row['group_id']),
                'DATE_INPUT' => form_input_date(do_lang_tempcode('DATE'), new Tempcode(), 'gmt_time_' . strval($i), true, false, true, $row['timeout'], 10, null, null),
            );
        }

        $url = build_url(array('page' => '_SELF', 'type' => 'save'), '_SELF');

        $pagination = pagination(do_lang('GROUP_MEMBER_TIMEOUTS'), $start, 'start', $max, 'max', $max_rows);

        require_code('form_templates');
        list($warning_details, $ping_url) = handle_conflict_resolution();

        $ret = do_template('GROUP_MEMBER_TIMEOUT_MANAGE_SCREEN', array(
            'TITLE' => $this->title,
            'TIMEOUTS' => $timeouts,
            'GROUPS' => $usergroups,
            'DATE_INPUT' => form_input_date(do_lang_tempcode('DATE'), new Tempcode(), 'gmt_time_new', true, false, true, null, 10, null, null),
            'URL' => $url,
            'PAGINATION' => $pagination,
            'PING_URL' => $ping_url,
            'WARNING_DETAILS' => $warning_details,
        ));

        single_field__end();

        return $ret;
    }

    /**
     * Save group member timeouts.
     *
     * @return Tempcode The UI
     */
    public function save()
    {
        require_code('group_member_timeouts');

        // Main edits
        foreach (array_keys($_POST) as $key) {
            $matches = array();
            if (preg_match('#^gmt_username_(\d+)$#', $key, $matches) != 0) {
                $old_group_id = post_param_integer('gmt_old_group_id_' . $matches[1], null);
                $group_id = post_param_integer('gmt_group_id_' . $matches[1], null);
                $username = post_param_string('gmt_username_' . $matches[1], '');
                $time = post_param_date('gmt_time_' . $matches[1]);

                $this->_save_group_member_timeout($old_group_id, $group_id, $username, $time);
            }
        }

        // Add new

        $group_id = post_param_integer('gmt_group_id_new', null);
        $username = post_param_string('gmt_username_new', '');
        $time = post_param_date('gmt_time_new');

        if ((!is_null($group_id)) && ($username != '') && (!is_null($time))) {
            $this->_save_group_member_timeout(null, $group_id, $username, $time);
        }

        // Clean up

        cleanup_member_timeouts();

        // Redirect

        $url = build_url(array('page' => '_SELF', 'type' => 'browse'), '_SELF');

        return redirect_screen($this->title, $url, do_lang_tempcode('SUCCESS'));
    }

    /**
     * Save group member timeouts.
     *
     * @param  ?GROUP $old_group_id The usergroup ID before edit (null: N/A)
     * @param  GROUP $group_id The usergroup ID
     * @param  ID_TEXT $username The username
     * @param  TIME $time The expiry time
     */
    public function _save_group_member_timeout($old_group_id, $group_id, $username, $time)
    {
        $prefer_for_primary_group = false;//(post_param_integer('prefer_for_primary_group',0)==1); Don't promote this bad choice

        if (!$GLOBALS['FORUM_DRIVER']->is_super_admin(get_member())) { // Security issue, don't allow privilege elevation
            $admin_groups = $GLOBALS['FORUM_DRIVER']->get_super_admin_groups();
            if (in_array($group_id, $admin_groups)) {
                warn_exit(do_lang_tempcode('INTERNAL_ERROR'));
            }
        }

        $member_id = $GLOBALS['FORUM_DRIVER']->get_member_from_username($username);
        if (is_null($member_id)) {
            attach_message(do_lang_tempcode('_MEMBER_NO_EXIST', escape_html($username)), 'warn');
        } else {
            if (!is_null($old_group_id)) {
                $GLOBALS[(get_forum_type() == 'cns') ? 'FORUM_DB' : 'SITE_DB']->query_delete('f_group_member_timeouts', array(
                    'member_id' => $member_id,
                    'group_id' => $old_group_id,
                ), '', 1);
            }
            set_member_group_timeout($member_id, $group_id, $time, $prefer_for_primary_group);
        }
    }
}
