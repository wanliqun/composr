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
 * @package    backup
 */

/**
 * Hook class.
 */
class Hook_addon_registry_backup
{
    /**
     * Get a list of file permissions to set
     *
     * @param  boolean $runtime Whether to include wildcards represented runtime-created chmoddable files
     * @return array File permissions to set
     */
    public function get_chmod_array($runtime = false)
    {
        return array();
    }

    /**
     * Get the version of Composr this addon is for
     *
     * @return float Version number
     */
    public function get_version()
    {
        return cms_version_number();
    }

    /**
     * Get the description of the addon
     *
     * @return string Description of the addon
     */
    public function get_description()
    {
        return 'Perform incremental or full backups of files and the database. Supports scheduling.';
    }

    /**
     * Get a list of tutorials that apply to this addon
     *
     * @return array List of tutorials
     */
    public function get_applicable_tutorials()
    {
        return array(
            'tut_backup',
        );
    }

    /**
     * Get a mapping of dependency types
     *
     * @return array File permissions to set
     */
    public function get_dependencies()
    {
        return array(
            'requires' => array(),
            'recommends' => array(),
            'conflicts_with' => array(),
        );
    }

    /**
     * Explicitly say which icon should be used
     *
     * @return URLPATH Icon
     */
    public function get_default_icon()
    {
        return 'themes/default/images/icons/48x48/menu/adminzone/tools/bulk_content_actions/backups.png';
    }

    /**
     * Get a list of files that belong to this addon
     *
     * @return array List of files
     */
    public function get_file_list()
    {
        return array(
            'themes/default/images/icons/24x24/menu/adminzone/tools/bulk_content_actions/backups.png',
            'themes/default/images/icons/48x48/menu/adminzone/tools/bulk_content_actions/backups.png',
            'sources/hooks/systems/config/backup_overwrite.php',
            'sources/hooks/systems/config/backup_server_hostname.php',
            'sources/hooks/systems/config/backup_server_password.php',
            'sources/hooks/systems/config/backup_server_path.php',
            'sources/hooks/systems/config/backup_server_port.php',
            'sources/hooks/systems/config/backup_server_user.php',
            'sources/hooks/systems/config/backup_time.php',
            'data/modules/admin_backup/.htaccess',
            'data_custom/modules/admin_backup/.htaccess',
            'sources/hooks/systems/addon_registry/backup.php',
            'themes/default/templates/RESTORE_HTML_WRAP.tpl',
            'exports/backups/index.html',
            'themes/default/templates/BACKUP_LAUNCH_SCREEN.tpl',
            'adminzone/pages/modules/admin_backup.php',
            'data/modules/admin_backup/index.html',
            'data/modules/admin_backup/restore.php.pre',
            'data_custom/modules/admin_backup/index.html',
            'lang/EN/backups.ini',
            'sources/backup.php',
            'sources/hooks/blocks/main_staff_checklist/backup.php',
            'sources/hooks/systems/cron/backups.php',
            'sources/hooks/systems/page_groupings/backup.php',
            'sources/hooks/systems/snippets/backup_size.php',
            'exports/backups/.htaccess',
            'sources/hooks/systems/tasks/make_backup.php',
        );
    }

    /**
     * Get mapping between template names and the method of this class that can render a preview of them
     *
     * @return array The mapping
     */
    public function tpl_previews()
    {
        return array(
            'templates/RESTORE_HTML_WRAP.tpl' => 'administrative__restore_wrap',
            'templates/BACKUP_LAUNCH_SCREEN.tpl' => 'administrative__backup_launch_screen'
        );
    }

    /**
     * Get a preview(s) of a (group of) template(s), as a full standalone piece of HTML in Tempcode format.
     * Uses sources/lorem.php functions to place appropriate stock-text. Should not hard-code things, as the code is intended to be declaritive.
     * Assumptions: You can assume all Lang/CSS/JavaScript files in this addon have been pre-required.
     *
     * @return array Array of previews, each is Tempcode. Normally we have just one preview, but occasionally it is good to test templates are flexible (e.g. if they use IF_EMPTY, we can test with and without blank data).
     */
    public function tpl_preview__administrative__backup_launch_screen()
    {
        return array(
            lorem_globalise(do_lorem_template('BACKUP_LAUNCH_SCREEN', array(
                'TITLE' => lorem_title(),
                'TEXT' => lorem_sentence(),
                'RESULTS' => lorem_phrase(),
                'FORM' => placeholder_form_with_field('submit_button'),
            )), null, '', true)
        );
    }

    /**
     * Get a preview(s) of a (group of) template(s), as a full standalone piece of HTML in Tempcode format.
     * Uses sources/lorem.php functions to place appropriate stock-text. Should not hard-code things, as the code is intended to be declaritive.
     * Assumptions: You can assume all Lang/CSS/JavaScript files in this addon have been pre-required.
     *
     * @return array Array of previews, each is Tempcode. Normally we have just one preview, but occasionally it is good to test templates are flexible (e.g. if they use IF_EMPTY, we can test with and without blank data).
     */
    public function tpl_preview__administrative__restore_wrap()
    {
        // This preview inevitably looks ugly because the install CSS can't be shown (its loaded via self-reference to a non-existent file)

        return array(
            lorem_globalise(do_lorem_template('RESTORE_HTML_WRAP', array(
                'MESSAGE' => lorem_sentence_html(),
                'CSS_NOCACHE' => '',
                'SUCCESS' => '1',
            )), null, '', true)
        );
    }
}