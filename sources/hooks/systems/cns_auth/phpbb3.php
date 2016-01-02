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
 * @package    core_cns
 */

/**
 * Hook class.
 */
class Hook_cns_auth_phpbb3
{
    /**
     * Try and authenticate for our password compatibility scheme.
     *
     * @param  ?SHORT_TEXT $username The member username (null: don't use this in the authentication - but look it up using the ID if needed)
     * @param  ?MEMBER $userid The member ID (null: use member name)
     * @param  SHORT_TEXT $password_hashed The md5-hashed password
     * @param  string $password_raw The raw password
     * @param  boolean $cookie_login Whether this is a cookie login
     * @param  array $row Row of Conversr account
     * @return ?Tempcode Error message (null: none)
     */
    public function auth($username, $userid, $password_hashed, $password_raw, $cookie_login, $row)
    {
        if ($cookie_login) {
            if ($row['m_pass_hash_salted'] != $password_hashed) {
                return do_lang_tempcode('MEMBER_BAD_PASSWORD');
            }
        } else {
            require_code('forum/phpbb3');
            $itoa64 = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
            if (_hash_crypt_private($password_raw, $row['m_pass_hash_salted'], $itoa64) != $row['m_pass_hash_salted']) {
                return do_lang_tempcode('MEMBER_BAD_PASSWORD');
            }
        }

        return null;
    }
}
