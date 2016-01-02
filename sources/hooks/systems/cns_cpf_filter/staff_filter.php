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
 * @package    staff
 */

/**
 * Hook class.
 */
class Hook_cns_cpf_filter_staff_filter
{
    /**
     * Find which special CPFs to enable.
     *
     * @return array A list of CPFs to enable
     */
    public function to_enable()
    {
        global $SITE_INFO;
        $cpf = array();
        if (($SITE_INFO['forum_type'] != 'cns') || (get_db_forums() != get_db_site()) || ($GLOBALS['FORUM_DRIVER']->get_drivered_table_prefix() != get_table_prefix())) {
            $cpf['role'] = true;
            $cpf['sites'] = true;
            $cpf['firstname'] = true;
            $cpf['lastname'] = true;
        }
        return $cpf;
    }
}
