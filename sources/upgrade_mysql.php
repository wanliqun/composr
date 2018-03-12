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
 * @package    core_upgrader
 */

/**
 * Standard code module initialisation function.
 *
 * @ignore
 */
function init__upgrade_perms()
{
    require_lang('upgrade');
}

/**
 * Do upgrader screen: repair MySQL tables.
 *
 * @ignore
 * @return string Output messages
 */
function upgrader_mysql_repair_screen()
{
    if (strpos(get_db_type(), 'mysql') === false) {
        warn_exit(do_lang_tempcode('INTERNAL_ERROR'));
    }

    require_code('database_repair');
    return static_evaluate_tempcode(database_repair_inbuilt());
}

/**
 * Do upgrader screen: find what's wrong with the database structure.
 *
 * @ignore
 * @return string Output messages
 */
function upgrader_criticise_mysql_fields_screen()
{
    if (strpos(get_db_type(), 'mysql') === false) {
        warn_exit(do_lang_tempcode('INTERNAL_ERROR'));
    }

    $out = '<p>' . do_lang('DESCRIPTION_CORRECT_MYSQL_SCHEMA_ISSUES') . '</p>';

    require_code('database_repair');
    $out .= static_evaluate_tempcode(database_repair_wrap());

    return $out;
}
