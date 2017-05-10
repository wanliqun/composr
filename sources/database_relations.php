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
 * @package    core
 */

/*
REMEMBER to keep db_export.sh updated too
*/

/**
 * Standard code module initialisation function.
 *
 * @ignore
 */
function init__database_relations()
{
    if (!defined('TABLE_PURPOSE__NORMAL')) {
        define('TABLE_PURPOSE__NORMAL', 0);
        define('TABLE_PURPOSE__NO_BACKUPS', 1); // For some reason we do not backup this
        define('TABLE_PURPOSE__FLUSHABLE', 2 + (4)); // Flushable because the contents is not HUGELY important. Should not be routinely flushed. Logs, chats, etc - not member settings, etc. Think: "stuff to do before opening a new site that has just gone through testing"
        define('TABLE_PURPOSE__FLUSHABLE_AGGRESSIVE', 4); // Flushable if we're being extra aggressive. Don't set if already has FLUSHABLE set
        define('TABLE_PURPOSE__NO_STAGING_COPY', 8); // For some special reason we don't copy this between staging to live. Don't set if already has FLUSHABLE set
        define('TABLE_PURPOSE__NON_BUNDLED', 16); // Non-bundled. Do not apply this to anything defined in this core file. Applies only to non-bundled tables injected via an override to this file
        define('TABLE_PURPOSE__AUTOGEN_STATIC', 32); // Contents is auto-generated/meta and essentially static, not for merging between sites
        define('TABLE_PURPOSE__MISC_NO_MERGE', 64); // Should not be merged between sites for other unspecified reasons
        define('TABLE_PURPOSE__SUBDATA', 128); // Data which is subsumed under other data when doing a transfer and has some importance but is totally meaningless when taken on its own
        define('TABLE_PURPOSE__AS_COMMANDER_FS_EXTENDED_CONFIG', 256); // We won't give the table full handling somewhere under a Resource-fs hook, we'll have a Commandr-fs extended config hook instead
        // -
        define('TABLE_PURPOSE__NOT_KNOWN', 512);
    }
}

/**
 * Find how tables might be ignored for backups etc.
 * This is mainly used for building unit tests that make sure things are consistently implemented.
 *
 * @return array List of tables and their status regarding being ignored for backups etc
 */
function get_table_purpose_flags()
{
    return array(
        'addons' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__AUTOGEN_STATIC,
        'addons_dependencies' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__AUTOGEN_STATIC | TABLE_PURPOSE__SUBDATA/*under addons*/,
        'addons_files' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__AUTOGEN_STATIC | TABLE_PURPOSE__SUBDATA/*under addons*/,
        'actionlogs' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__FLUSHABLE,
        'aggregate_type_instances' => TABLE_PURPOSE__NORMAL,
        'alternative_ids' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__FLUSHABLE_AGGRESSIVE | TABLE_PURPOSE__SUBDATA/*under <content>*/,
        'attachment_refs' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__SUBDATA/*under <content> (implied)*/,
        'attachments' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__SUBDATA/*under <content> (special handling)*/,
        'authors' => TABLE_PURPOSE__NORMAL,
        'autosave' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__FLUSHABLE,
        'award_archive' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__SUBDATA/*under award_types*/,
        'award_types' => TABLE_PURPOSE__NORMAL,
        'banned_ip' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__FLUSHABLE_AGGRESSIVE,
        'banner_clicks' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__FLUSHABLE | TABLE_PURPOSE__SUBDATA/*under banners*/,
        'banner_types' => TABLE_PURPOSE__NORMAL,
        'banners' => TABLE_PURPOSE__NORMAL,
        'banners_types' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__SUBDATA/*under banners*/,
        'blocks' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__AUTOGEN_STATIC,
        'bookmarks' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__SUBDATA/*under f_members*/,
        'cache' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__NO_BACKUPS | TABLE_PURPOSE__FLUSHABLE,
        'cache_on' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__NO_BACKUPS | TABLE_PURPOSE__FLUSHABLE | TABLE_PURPOSE__AUTOGEN_STATIC,
        'cached_comcode_pages' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__NO_BACKUPS | TABLE_PURPOSE__FLUSHABLE,
        'calendar_events' => TABLE_PURPOSE__NORMAL,
        'calendar_interests' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__SUBDATA/*under f_members*/,
        'calendar_jobs' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__MISC_NO_MERGE/*ephemeral*/ | TABLE_PURPOSE__SUBDATA/*under calendar_events*/,
        'calendar_reminders' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__SUBDATA/*under calendar_events*/,
        'calendar_types' => TABLE_PURPOSE__NORMAL,
        'captchas' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__NO_BACKUPS | TABLE_PURPOSE__FLUSHABLE,
        'catalogue_cat_treecache' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__NO_BACKUPS | TABLE_PURPOSE__FLUSHABLE | TABLE_PURPOSE__SUBDATA/*under catalogues*/,
        'catalogue_categories' => TABLE_PURPOSE__NORMAL,
        'catalogue_childcountcache' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__NO_BACKUPS | TABLE_PURPOSE__FLUSHABLE | TABLE_PURPOSE__SUBDATA/*under catalogues*/,
        'catalogue_efv_float' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__SUBDATA/*under catalogue_entries*/,
        'catalogue_efv_integer' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__SUBDATA/*under catalogue_entries*/,
        'catalogue_efv_long' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__SUBDATA/*under catalogue_entries*/,
        'catalogue_efv_long_trans' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__SUBDATA/*under catalogue_entries*/,
        'catalogue_efv_short' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__SUBDATA/*under catalogue_entries*/,
        'catalogue_efv_short_trans' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__SUBDATA/*under catalogue_entries*/,
        'catalogue_entries' => TABLE_PURPOSE__NORMAL,
        'catalogue_entry_linkage' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__SUBDATA/*under <content>*/,
        'catalogue_fields' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__SUBDATA/*under catalogues*/,
        'catalogues' => TABLE_PURPOSE__NORMAL,
        'chargelog' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__FLUSHABLE_AGGRESSIVE | TABLE_PURPOSE__SUBDATA/*under f_members*/,
        'chat_active' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__NO_BACKUPS | TABLE_PURPOSE__FLUSHABLE,
        'chat_blocking' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__SUBDATA/*under f_members*/,
        'chat_events' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__NO_BACKUPS | TABLE_PURPOSE__FLUSHABLE,
        'chat_friends' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__SUBDATA/*under f_members*/,
        'chat_messages' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__FLUSHABLE | TABLE_PURPOSE__SUBDATA/*under chat_rooms*/,
        'chat_rooms' => TABLE_PURPOSE__NORMAL,
        'chat_sound_effects' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__SUBDATA/*under f_members*/,
        'comcode_pages' => TABLE_PURPOSE__NORMAL,
        'commandrchat' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__FLUSHABLE,
        'config' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__NO_STAGING_COPY/*has-special-Commandr-fs-hook*/,
        'content_privacy__members' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__SUBDATA/*under <content>*/,
        'content_privacy' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__SUBDATA/*under <content>*/,
        'content_regions' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__SUBDATA/*under <content>*/,
        'content_reviews' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__SUBDATA/*under <content>*/,
        'cron_caching_requests' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__NO_BACKUPS | TABLE_PURPOSE__FLUSHABLE,
        'post_tokens' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__NO_BACKUPS | TABLE_PURPOSE__FLUSHABLE,
        'custom_comcode' => TABLE_PURPOSE__NORMAL,
        'staff_checklist_cus_tasks' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__AS_COMMANDER_FS_EXTENDED_CONFIG,
        'db_meta' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__AUTOGEN_STATIC,
        'db_meta_indices' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__AUTOGEN_STATIC,
        'digestives_consumed' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__MISC_NO_MERGE/*ephemeral*/,
        'digestives_tin' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__NO_BACKUPS | TABLE_PURPOSE__MISC_NO_MERGE/*ephemeral*/,
        'download_categories' => TABLE_PURPOSE__NORMAL,
        'download_downloads' => TABLE_PURPOSE__NORMAL,
        'download_licences' => TABLE_PURPOSE__NORMAL,
        'download_logging' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__FLUSHABLE | TABLE_PURPOSE__SUBDATA/*under download_downloads*/,
        'edit_pings' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__NO_BACKUPS | TABLE_PURPOSE__FLUSHABLE | TABLE_PURPOSE__SUBDATA/*under <content>*/,
        'email_bounces' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__FLUSHABLE,
        'f_custom_fields' => TABLE_PURPOSE__NORMAL,
        'f_emoticons' => TABLE_PURPOSE__NORMAL,
        'f_forum_groupings' => TABLE_PURPOSE__NORMAL,
        'f_forum_intro_ip' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__FLUSHABLE | TABLE_PURPOSE__SUBDATA/*under f_forums*/,
        'f_forum_intro_member' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__FLUSHABLE | TABLE_PURPOSE__SUBDATA/*under f_forums*/,
        'f_forums' => TABLE_PURPOSE__NORMAL,
        'f_group_join_log' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__FLUSHABLE | TABLE_PURPOSE__SUBDATA/*under f_groups*/,
        'f_group_member_timeouts' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__SUBDATA/*under f_members*/,
        'f_group_members' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__SUBDATA/*under f_members*/,
        'f_groups' => TABLE_PURPOSE__NORMAL,
        'f_invites' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__FLUSHABLE_AGGRESSIVE,
        'f_member_cpf_perms' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__SUBDATA/*under f_members*/,
        'f_member_custom_fields' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__SUBDATA/*under f_members*/,
        'f_member_known_login_ips' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__FLUSHABLE_AGGRESSIVE | TABLE_PURPOSE__SUBDATA/*under f_members*/,
        'f_members' => TABLE_PURPOSE__NORMAL,
        'f_moderator_logs' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__FLUSHABLE,
        'f_multi_moderations' => TABLE_PURPOSE__NORMAL,
        'f_password_history' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__FLUSHABLE | TABLE_PURPOSE__SUBDATA/*under f_members*/,
        'f_poll_answers' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__SUBDATA/*under f_topics*/,
        'f_poll_votes' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__SUBDATA/*under f_topics*/,
        'f_polls' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__SUBDATA/*f_topics*/,
        'f_post_templates' => TABLE_PURPOSE__NORMAL,
        'f_posts' => TABLE_PURPOSE__NORMAL,
        'f_read_logs' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__FLUSHABLE | TABLE_PURPOSE__SUBDATA/*under f_members*/,
        'f_saved_warnings' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__FLUSHABLE_AGGRESSIVE,
        'f_special_pt_access' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__SUBDATA/*under f_topics*/,
        'f_topics' => TABLE_PURPOSE__NORMAL,
        'f_usergroup_sub_mails' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__SUBDATA/*under f_usergroup_subs*/,
        'f_usergroup_subs' => TABLE_PURPOSE__NORMAL,
        'f_warnings' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__SUBDATA/*under f_members*/,
        'f_welcome_emails' => TABLE_PURPOSE__NORMAL,
        'failedlogins' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__FLUSHABLE,
        'feature_lifetime_monitor' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__MISC_NO_MERGE/*ephemeral*/,
        'filedump' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__SUBDATA/*subsumed within filedump hook when it finds files*/,
        'galleries' => TABLE_PURPOSE__NORMAL,
        'gifts' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__FLUSHABLE_AGGRESSIVE | TABLE_PURPOSE__SUBDATA/*under f_members*/,
        'group_category_access' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__SUBDATA/*under <content>*/,
        'group_page_access' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__AS_COMMANDER_FS_EXTENDED_CONFIG,
        'group_privileges' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__AS_COMMANDER_FS_EXTENDED_CONFIG,
        'group_zone_access' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__SUBDATA/*under zones*/,
        'hackattack' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__FLUSHABLE,
        'https_pages' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__AS_COMMANDER_FS_EXTENDED_CONFIG,
        'images' => TABLE_PURPOSE__NORMAL,
        'import_id_remap' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__FLUSHABLE | TABLE_PURPOSE__SUBDATA/*under import_session*/,
        'import_parts_done' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__FLUSHABLE | TABLE_PURPOSE__SUBDATA/*under import_session*/,
        'import_session' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__FLUSHABLE,
        'incoming_uploads' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__NO_BACKUPS | TABLE_PURPOSE__FLUSHABLE,
        'ecom_invoices' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__FLUSHABLE_AGGRESSIVE | TABLE_PURPOSE__SUBDATA/*under f_members*/,
        'ip_country' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__NO_BACKUPS | TABLE_PURPOSE__FLUSHABLE | TABLE_PURPOSE__AUTOGEN_STATIC,
        'leader_board' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__FLUSHABLE,
        'link_tracker' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__FLUSHABLE,
        'logged_mail_messages' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__FLUSHABLE,
        'match_key_messages' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__AS_COMMANDER_FS_EXTENDED_CONFIG,
        'member_category_access' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__SUBDATA/*under <content>*/,
        'member_page_access' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__AS_COMMANDER_FS_EXTENDED_CONFIG,
        'member_privileges' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__AS_COMMANDER_FS_EXTENDED_CONFIG,
        'member_tracking' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__FLUSHABLE | TABLE_PURPOSE__SUBDATA/*under <content>*/,
        'member_zone_access' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__SUBDATA/*under zones*/,
        'menu_items' => TABLE_PURPOSE__NORMAL,
        'messages_to_render' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__NO_BACKUPS | TABLE_PURPOSE__FLUSHABLE,
        'modules' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__AUTOGEN_STATIC,
        'news' => TABLE_PURPOSE__NORMAL,
        'news_categories' => TABLE_PURPOSE__NORMAL,
        'news_category_entries' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__SUBDATA/*under news*/,
        'news_rss_cloud' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__MISC_NO_MERGE/*too-site-tied*/ | TABLE_PURPOSE__SUBDATA/*under news*/,
        'newsletter_archive' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__SUBDATA/*under newsletters*/,
        'newsletter_drip_send' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__FLUSHABLE,
        'newsletter_periodic' => TABLE_PURPOSE__NORMAL,
        'newsletter_subscribe' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__SUBDATA/*under newsletters*/,
        'newsletter_subscribers' => TABLE_PURPOSE__NORMAL,
        'newsletters' => TABLE_PURPOSE__NORMAL,
        'notification_lockdown' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__AS_COMMANDER_FS_EXTENDED_CONFIG,
        'notifications_enabled' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__FLUSHABLE_AGGRESSIVE | TABLE_PURPOSE__SUBDATA/*under f_members*/,
        'poll' => TABLE_PURPOSE__NORMAL,
        'poll_votes' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__FLUSHABLE | TABLE_PURPOSE__SUBDATA/*under poll*/,
        'ecom_prods_prices' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__AS_COMMANDER_FS_EXTENDED_CONFIG,
        'privilege_list' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__AUTOGEN_STATIC,
        'ecom_prods_custom' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__AS_COMMANDER_FS_EXTENDED_CONFIG,
        'ecom_prods_permissions' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__AS_COMMANDER_FS_EXTENDED_CONFIG,
        'quiz_entries' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__SUBDATA/*under quizzes*/,
        'quiz_entry_answer' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__SUBDATA/*under quizzes*/,
        'quiz_member_last_visit' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__FLUSHABLE | TABLE_PURPOSE__SUBDATA/*under quizzes*/,
        'quiz_question_answers' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__SUBDATA/*under quizzes*/,
        'quiz_questions' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__SUBDATA/*under quizzes*/,
        'quiz_winner' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__SUBDATA/*under quizzes*/,
        'quizzes' => TABLE_PURPOSE__NORMAL,
        'rating' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__FLUSHABLE_AGGRESSIVE | TABLE_PURPOSE__SUBDATA/*under <content>*/,
        'redirects' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__AS_COMMANDER_FS_EXTENDED_CONFIG,
        'review_supplement' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__FLUSHABLE_AGGRESSIVE | TABLE_PURPOSE__SUBDATA/*under <content>*/,
        'revisions' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__FLUSHABLE | TABLE_PURPOSE__SUBDATA/*under <lots>*/,
        'ecom_sales' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__FLUSHABLE_AGGRESSIVE,
        'searches_logged' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__FLUSHABLE,
        'searches_saved' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__MISC_NO_MERGE/*too-site-tied*/ | TABLE_PURPOSE__FLUSHABLE_AGGRESSIVE | TABLE_PURPOSE__SUBDATA/*under f_members*/,
        'seo_meta' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__SUBDATA/*under <content>*/,
        'seo_meta_keywords' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__SUBDATA/*under <content>*/,
        'sessions' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__NO_BACKUPS | TABLE_PURPOSE__FLUSHABLE,
        'shopping_cart' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__FLUSHABLE_AGGRESSIVE | TABLE_PURPOSE__MISC_NO_MERGE/*too-site-tied*/,
        'shopping_logging' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__FLUSHABLE,
        'shopping_orders' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__FLUSHABLE_AGGRESSIVE | TABLE_PURPOSE__MISC_NO_MERGE/*too-site-tied*/,
        'ecom_trans_addresses' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__FLUSHABLE_AGGRESSIVE | TABLE_PURPOSE__MISC_NO_MERGE/*too-site-tied*/ | TABLE_PURPOSE__SUBDATA/*under shopping_orders*/,
        'shopping_order_details' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__FLUSHABLE_AGGRESSIVE | TABLE_PURPOSE__MISC_NO_MERGE/*too-site-tied*/ | TABLE_PURPOSE__SUBDATA/*under shopping_orders*/,
        'sitemap_cache' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__NO_BACKUPS | TABLE_PURPOSE__FLUSHABLE,
        'staff_website_monitoring' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__AS_COMMANDER_FS_EXTENDED_CONFIG,
        'sms_log' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__FLUSHABLE,
        'staff_tips_dismissed' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__FLUSHABLE,
        'staff_links' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__AS_COMMANDER_FS_EXTENDED_CONFIG,
        'stats' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__NO_BACKUPS | TABLE_PURPOSE__FLUSHABLE,
        'ecom_subscriptions' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__FLUSHABLE_AGGRESSIVE | TABLE_PURPOSE__SUBDATA/*under f_members*/,
        'task_queue' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__MISC_NO_MERGE/*ephemeral*/,
        'temp_block_permissions' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__NO_BACKUPS | TABLE_PURPOSE__FLUSHABLE,
        'theme_images' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__NO_STAGING_COPY/*as can deal in files*/,
        'ticket_extra_access' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__SUBDATA/*under f_topics*/,
        'ticket_known_emailers' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__FLUSHABLE_AGGRESSIVE | TABLE_PURPOSE__SUBDATA/*under f_members*/,
        'ticket_types' => TABLE_PURPOSE__NORMAL,
        'tickets' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__SUBDATA/*under f_topics*/,
        'trackbacks' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__FLUSHABLE_AGGRESSIVE | TABLE_PURPOSE__SUBDATA/*under <content>*/,
        'ecom_trans_expecting' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__FLUSHABLE_AGGRESSIVE | TABLE_PURPOSE__MISC_NO_MERGE/*too-site-tied*/,
        'ecom_transactions' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__FLUSHABLE_AGGRESSIVE,
        'translate' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__SUBDATA/*under <lots>*/,
        'tutorial_links' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__FLUSHABLE | TABLE_PURPOSE__AUTOGEN_STATIC,
        'unbannable_ip' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__AS_COMMANDER_FS_EXTENDED_CONFIG,
        'url_id_monikers' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__MISC_NO_MERGE/*too-site-tied*/ | TABLE_PURPOSE__SUBDATA/*under <content>*/,
        'url_title_cache' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__NO_BACKUPS | TABLE_PURPOSE__FLUSHABLE,
        'urls_checked' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__NO_BACKUPS | TABLE_PURPOSE__FLUSHABLE,
        'usersonline_track' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__FLUSHABLE,
        'usersubmitban_member' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__FLUSHABLE_AGGRESSIVE | TABLE_PURPOSE__SUBDATA/*under f_members*/,
        'values' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__MISC_NO_MERGE/*too-site-tied*/,
        'values_elective' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__MISC_NO_MERGE/*too-site-tied*/,
        'video_transcoding' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__MISC_NO_MERGE/*too-site-tied*/,
        'videos' => TABLE_PURPOSE__NORMAL,
        'webstandards_checked_once' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__FLUSHABLE,
        'wiki_children' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__SUBDATA/*under wiki_pages*/,
        'wiki_pages' => TABLE_PURPOSE__NORMAL,
        'wiki_posts' => TABLE_PURPOSE__NORMAL,
        'wordfilter' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__AS_COMMANDER_FS_EXTENDED_CONFIG,
        'zones' => TABLE_PURPOSE__NORMAL,
        'ecom_sales_expecting' => TABLE_PURPOSE__NORMAL | TABLE_PURPOSE__FLUSHABLE_AGGRESSIVE | TABLE_PURPOSE__MISC_NO_MERGE/*too-site-tied*/,
    );
}

/**
 * Find if a table must be ignored for backups or whatever flag(s).
 *
 * @param string $table The table name
 * @param integer $flag A particular flag(s)
 * @return boolean Whether the flag(s) exists (at least one)
 */
function table_has_purpose_flag($table, $flag)
{
    static $flags = null;
    if ($flags === null) {
        $flags = get_table_purpose_flags();
    }

    if (!isset($flags[$table])) {
        return ($flag & TABLE_PURPOSE__NOT_KNOWN) != 0;
    }

    $real_flag = $flags[$table];
    return ($real_flag & $flag) != 0;
}

/**
 * Get a map of table descriptions.
 *
 * @return array Map of table descriptions
 */
function get_table_descriptions()
{
    return array(
        'actionlogs' => 'stores logs of actions performed on the website',
        'alternative_ids' => 'different sets of IDs for a database ID, allowing more robust cross-site or label based content referencing',
        'attachments' => 'attachments referenced by Comcode (images, downloads, videos, etc)',
        'attachment_refs' => 'stores references of what content uses what attachments (it allows attachment permissions to work, as it tells Composr what \'owner\' content to verify permissions against)',
        'autosave' => 'stores unsaved form data in case of browser crashes, called by AJAX',
        'banned_ip' => 'list of banned IP addresses (Composr will use .htaccess also if it can, to improve performance)',
        'blocks' => 'a registry of all installed blocks',
        'cache' => 'data caching, especially block caching',
        'cache_on' => 'a registry of what cacheable things are cached by what parameters',
        'captchas' => 'stores CAPTCHA image expectations, so Composr can check what they entered against what they were asked to enter',
        'config' => 'all the configuration settings that have been saved',
        'content_privacy__members' => 'sets content privacy',
        'content_regions' => 'sets the regions content may be viewed from',
        'edit_pings' => 'used to stop people editing the same thing at the same time (AJAX)',
        'failedlogins' => 'a log of all failed logins',
        'group_category_access' => 'defines what groups may access what categories',
        'group_page_access' => 'defines what groups may access what pages',
        'group_privileges' => 'defines what groups have what privileges',
        'group_zone_access' => 'defines what groups may access what zones',
        'https_pages' => 'lists pages that the webmaster has decided need to run over SSL',
        'incoming_uploads' => 'temporary storage of uploaded files, before main form submission',
        'link_tracker' => 'outgoing click tracking (not really used much)',
        'logged_mail_messages' => 'logged emails (so you can check incorrect emails aren\'t going out) / email queuing',
        'member_category_access' => 'defines what members may access what categories (rarely used, no admin UI)',
        'member_page_access' => 'defines what members may access what pages (rarely used, no admin UI)',
        'member_privileges' => 'defines what members have what privileges (rarely used, no admin UI)',
        'member_tracking' => 'tracks the locations of online users',
        'member_zone_access' => 'defines what members may access what zones (rarely used, no admin UI)',
        'menu_items' => 'stores all the items shown on menus (except auto-generated ones)',
        'messages_to_render' => 'stores messages that have been queued up for display on a members browser (e.g. if they have just been redirected after completing something, so a status message will be queued for display after they\'ve been redirected)',
        'modules' => 'registry of all installed modules',
        'notifications_enabled' => 'what notifications members receive',
        'privilege_list' => 'a list of all the privileges available (aka privileges)',
        'rating' => 'stores ratings for all kinds of content (rating_for_type determines what kind of content, rating_for_id determines what ID of content within that type)',
        'review_supplement' => 'stores reviews for all kinds of content',
        'revisions' => 'used to store old versions of content (any content type that chooses to support revisions)',
        'seo_meta' => 'stores meta descriptions for all kinds of content',
        'seo_meta_keywords' => 'stores meta keywords for all kinds of content',
        'sessions' => 'stores user sessions, for guests and members (session ID\'s are treated with high security)',
        'sitemap_cache' => 'a cache of all addressable sitemap nodes for building out the full XML sitemaps across multiple files iteratively, which is extremely intensive on large sites',
        'sms_log' => 'logs what SMS messages were sent out on behalf of what users and when',
        'staff_tips_dismissed' => 'stores what webmaster tips (Admin Zone front page) have been read so far',
        'trackbacks' => 'stores trackbacks for all kinds of content',
        'translate' => 'very important table, stores most of the text; this table exists to internationalise content and also to store compiled Comcode',
        'tutorial_links' => 'used by the Composr documentation, don\'t worry about this table',
        'urls_checked' => 'stores whether URLs exists, may be used by any system within Composr',
        'url_id_monikers' => 'stores search-engine-friendly URL codes for all kinds of content (we call these "monikers")',
        'url_title_cache' => 'stores the HTML titles for URLs, used in particular by the Comcode parser when it auto-detects links, and the media rendering system',
        'usersubmitban_member' => 'list of banned members',
        'values' => 'arbitrary store of data values (mapping of keys to values)',
        'values_elective' => 'arbitrary store of lengthy/elective data values (mapping of keys to values)',
        'webstandards_checked_once' => 'this is used by the inbuilt XHTML checker to know what markup it has already checked, so it doesn\'t waste a lot of time re-checking the same stuff; it uses a hash-signature-check so it doesn\'t need to store all data in the table',
        'zones' => 'details of all zones on the website',
        'ecom_sales_expecting' => 'stores details of an in-progress purchase',
    );
}

/**
 * Get a map of foreign key relations.
 *
 * @param string $table A particular table
 * @return array Map of foreign key relations
 */
function get_relation_map_for_table($table)
{
    $relation_map = get_relation_map();
    $new_relation_map = array();
    foreach ($relation_map as $from => $to) {
        if ($to !== null) {
            list($from_table, $from_field) = explode('.', $from, 2);
            if ($table == $from_table) {
                list($to_table, $to_field) = explode('.', $to, 2);
                $new_relation_map[$from_field] = array($to_table, $to_field);
            }
        }
    }
    return $new_relation_map;
}

/**
 * Get a map of foreign key relations.
 *
 * @return array Map of foreign key relations
 */
function get_relation_map()
{
    return array(
        'attachment_refs.a_id' => 'attachments.id',
        'attachment_refs.r_referer_id' => null,
        'award_archive.a_type_id' => 'award_types.id',
        'banners.b_type' => 'banner_types.id',
        'banners_types.name' => 'banners.name',
        'banners_types.b_type' => 'banner_types.id',
        'cached_comcode_pages.the_zone' => 'zones.zone_name',
        'calendar_events.e_type' => 'calendar_types.id',
        'calendar_interests.t_type' => 'calendar_types.id',
        'calendar_jobs.j_event_id' => 'calendar_events.id',
        'calendar_jobs.j_reminder_id' => 'calendar_reminders.id',
        'calendar_reminders.e_id' => 'calendar_events.id',
        'catalogue_categories.cc_move_target' => 'catalogue_categories.id',
        'catalogue_categories.cc_parent_id' => 'catalogue_categories.id',
        'catalogue_categories.c_name' => 'catalogues.c_name',
        'catalogue_cat_treecache.cc_ancestor_id' => 'catalogue_categories.id',
        'catalogue_cat_treecache.cc_id' => 'catalogue_categories.id',
        'catalogue_childcountcache.cc_id' => 'catalogue_categories.id',
        'catalogue_efv_float.ce_id' => 'catalogue_entries.id',
        'catalogue_efv_float.cf_id' => 'catalogue_fields.id',
        'catalogue_efv_integer.ce_id' => 'catalogue_entries.id',
        'catalogue_efv_integer.cf_id' => 'catalogue_fields.id',
        'catalogue_efv_long.ce_id' => 'catalogue_entries.id',
        'catalogue_efv_long.cf_id' => 'catalogue_fields.id',
        'catalogue_efv_long_trans.ce_id' => 'catalogue_entries.id',
        'catalogue_efv_long_trans.cf_id' => 'catalogue_fields.id',
        'catalogue_efv_short.ce_id' => 'catalogue_entries.id',
        'catalogue_efv_short.cf_id' => 'catalogue_fields.id',
        'catalogue_efv_short_trans.ce_id' => 'catalogue_entries.id',
        'catalogue_efv_short_trans.cf_id' => 'catalogue_fields.id',
        'catalogue_entries.cc_id' => 'catalogue_categories.id',
        'catalogue_entries.c_name' => 'catalogues.c_name',
        'catalogue_entry_linkage.catalogue_entry_id' => 'catalogue_entries.id',
        'catalogue_fields.c_name' => 'catalogues.c_name',
        'chat_active.room_id' => 'chat_rooms.id',
        'chat_events.e_room_id' => 'chat_rooms.id',
        'chat_messages.room_id' => 'chat_rooms.id',
        'comcode_pages.the_zone' => 'zones.zone_name',
        'download_categories.parent_id' => 'download_categories.id',
        'download_downloads.category_id' => 'download_categories.id',
        'download_downloads.download_licence' => 'download_licences.id',
        'download_downloads.out_mode_id' => 'download_downloads.id',
        'download_logging.id' => 'download_downloads.id',
        'f_forums.f_cache_last_forum_id' => 'f_forums.id',
        'f_forums.f_cache_last_topic_id' => 'f_topics.id',
        'f_forums.f_forum_grouping_id' => 'f_forum_groupings.id',
        'f_forums.f_parent_forum' => 'f_forums.id',
        'f_forum_intro_ip.i_forum_id' => 'f_forums.id',
        'f_forum_intro_member.i_forum_id' => 'f_forums.id',
        'f_group_join_log.usergroup_id' => 'f_groups.id',
        'f_member_cpf_perms.field_id' => 'f_custom_fields.id',
        'f_multi_moderations.mm_move_to' => 'f_forums.id',
        'f_poll_answers.pa_poll_id' => 'f_polls.id',
        'f_poll_votes.pv_answer_id' => 'f_poll_answers.id',
        'f_poll_votes.pv_poll_id' => 'f_polls.id',
        'f_posts.p_cache_forum_id' => 'f_forums.id',
        'f_posts.p_parent_id' => 'f_posts.id',
        'f_posts.p_topic_id' => 'f_topics.id',
        'f_read_logs.l_topic_id' => 'f_topics.id',
        'f_special_pt_access.s_topic_id' => 'f_topics.id',
        'f_topics.t_cache_first_post_id' => 'f_posts.id',
        'f_topics.t_cache_last_post_id' => 'f_posts.id',
        'f_topics.t_forum_id' => 'f_forums.id',
        'f_topics.t_poll_id' => 'f_polls.id',
        'f_usergroup_sub_mails.m_usergroup_sub_id' => 'f_usergroup_subs.id',
        'f_warnings.p_silence_from_forum' => 'f_forums.id',
        'f_warnings.p_silence_from_topic' => 'f_topics.id',
        'f_welcome_emails.w_newsletter' => 'newsletters.id',
        'f_welcome_emails.w_usergroup' => 'f_groups.id',
        'galleries.g_owner' => 'f_members.id',
        'galleries.parent_id' => 'galleries.name',
        'group_category_access.category_name' => null,
        'group_page_access.zone_name' => 'zones.zone_name',
        'group_privileges.category_name' => null,
        'group_privileges.privilege' => 'privilege_list.the_name',
        'group_privileges.the_page' => 'modules.module_the_name',
        'group_zone_access.zone_name' => 'zones.zone_name',
        'images.cat' => 'galleries.name',
        'import_id_remap.id_new' => null,
        'import_id_remap.id_old' => null,
        'import_id_remap.id_session' => 'import_session.imp_session',
        'import_parts_done.imp_session' => 'import_session.imp_session',
        'member_category_access.category_name' => null,
        'member_page_access.page_name' => 'modules.module_the_name',
        'member_page_access.zone_name' => 'zones.zone_name',
        'member_privileges.category_name' => null,
        'member_privileges.privilege' => 'privilege_list.the_name',
        'member_privileges.the_page' => 'modules.module_the_name',
        'member_zone_access.zone_name' => 'zones.zone_name',
        'menu_items.i_parent' => 'menu_items.id',
        'messages_to_render.r_session_id' => 'sessions.the_session',
        'news.news_category' => 'news_categories.id',
        'newsletter_subscribe.newsletter_id' => 'newsletters.id',
        'news_category_entries.news_entry' => 'news.id',
        'news_category_entries.news_entry_category' => 'news_categories.id',
        'notifications_enabled.l_code_category' => null,
        'poll_votes.v_poll_id' => 'poll.id',
        'ecom_prods_permissions.p_category' => null,
        'ecom_prods_permissions.p_page' => 'modules.module_the_name',
        'ecom_prods_permissions.p_privilege' => 'privilege_list.the_name',
        'ecom_prods_permissions.p_zone' => 'zones.zone_name',
        'quizzes.q_tied_newsletter' => 'newsletters.id',
        'quiz_entries.q_quiz' => 'quizzes.id',
        'quiz_entry_answer.q_entry' => 'quiz_entries.id',
        'quiz_entry_answer.q_question' => 'quiz_questions.id',
        'quiz_member_last_visit.v_quiz_id' => 'quizzes.id',
        'quiz_questions.q_quiz' => 'quizzes.id',
        'quiz_question_answers.q_question' => 'quiz_questions.id',
        'quiz_winner.q_entry' => 'quiz_entries.id',
        'quiz_winner.q_quiz' => 'quizzes.id',
        'rating.rating_for_id' => 'modules.module_the_name',
        'redirects.r_from_zone' => 'zones.zone_name',
        'redirects.r_to_zone' => 'zones.zone_name',
        'review_supplement.r_post_id' => 'f_posts.id',
        'review_supplement.r_rating_for_id' => 'modules.module_the_name',
        'review_supplement.r_topic_id' => 'f_topics.id',
        'revisions.r_actionlog_id' => 'actionlogs.id',
        'revisions.r_moderatorlog_id' => 'f_moderator_logs.id',
        'seo_meta.meta_for_id' => null,
        'sessions.the_zone' => 'zones.zone_name',
        'shopping_cart.ordered_by' => 'f_members.id',
        'shopping_cart.type_code' => 'catalogue_entries.id',
        'ecom_trans_expecting.e_session_id' => 'sessions.the_session',
        'ecom_trans_addresses.a_trans_expecting_id' => 'ecom_trans_expecting.id',
        'ecom_trans_addresses.a_txn_id' => 'ecom_transactions.id',
        'shopping_order_details.p_order_id' => 'shopping_orders.id',
        'shopping_order_details.p_type_code' => 'catalogue_entries.id',
        'temp_block_permissions.p_session_id' => 'sessions.the_session',
        'tickets.forum_id' => 'f_forums.id',
        'tickets.ticket_type' => 'ticket_types.id',
        'tickets.topic_id' => 'f_topics.id',
        'trackbacks.trackback_for_id' => null,
        'ecom_transactions.t_parent_txn_id' => 'ecom_transactions.id',
        'ecom_transactions.t_session_id' => 'sessions.the_session',
        'url_id_monikers.m_resource_id' => null,
        'url_id_monikers.m_resource_page' => 'modules.module_the_name',
        'videos.cat' => 'galleries.name',
        'video_transcoding.t_local_id' => 'videos.id',
        'wiki_children.child_id' => 'wiki_pages.id',
        'wiki_children.parent_id' => 'wiki_pages.id',
        'wiki_posts.page_id' => 'wiki_pages.id',
    );
}
