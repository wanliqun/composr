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
 * @package    cns_forum
 */

/**
 * Hook class.
 */
class Hook_admin_import_types_cns_forum
{
    /**
     * Get a map of valid import types.
     *
     * @return array A map from codename to the language string that names them to the user.
     */
    public function run()
    {
        return array(
            'cns_post_templates' => 'POST_TEMPLATES',
            'cns_announcements' => 'ANNOUNCEMENTS',
            'cns_forum_groupings' => 'MODULE_TRANS_NAME_admin_cns_forum_groupings',
            'cns_forums' => 'SECTION_FORUMS',
            'cns_topics' => 'FORUM_TOPICS',
            'cns_polls_and_votes' => 'TOPIC_POLLS',
            'cns_posts' => 'FORUM_POSTS',
            'cns_post_files' => 'POST_FILES',
            'cns_multi_moderations' => 'MULTI_MODERATIONS',
            'cns_private_topics' => 'PRIVATE_TOPICS',
            'cns_saved_warnings' => 'SAVED_WARNINGS',
        );
    }
}
