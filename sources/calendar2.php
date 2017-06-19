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
 * @package    calendar
 */

/**
 * Add a calendar event.
 *
 * @param  AUTO_LINK $type The event type
 * @param  SHORT_TEXT $recurrence The recurrence code (set to 'none' for no recurrences: blank means infinite and will basically time-out Composr)
 * @param  ?integer $recurrences The number of recurrences (null: none/infinite)
 * @param  BINARY $seg_recurrences Whether to segregate the comment-topics/rating/trackbacks per-recurrence
 * @param  SHORT_TEXT $title The title of the event
 * @param  LONG_TEXT $content The full text describing the event
 * @param  integer $priority The priority
 * @range  1 5
 * @param  integer $start_year The year the event starts at
 * @param  integer $start_month The month the event starts at
 * @param  integer $start_day The day the event starts at
 * @param  ID_TEXT $start_monthly_spec_type In-month specification type for start date
 * @set day_of_month day_of_month_backwards dow_of_month dow_of_month_backwards
 * @param  integer $start_hour The hour the event starts at
 * @param  integer $start_minute The minute the event starts at
 * @param  ?integer $end_year The year the event ends at (null: not a multi day event)
 * @param  ?integer $end_month The month the event ends at (null: not a multi day event)
 * @param  ?integer $end_day The day the event ends at (null: not a multi day event)
 * @param  ID_TEXT $end_monthly_spec_type In-month specification type for end date
 * @set day_of_month day_of_month_backwards dow_of_month dow_of_month_backwards
 * @param  ?integer $end_hour The hour the event ends at (null: not a multi day event)
 * @param  ?integer $end_minute The minute the event ends at (null: not a multi day event)
 * @param  ?ID_TEXT $timezone The timezone for the event (null: current user's timezone)
 * @param  BINARY $do_timezone_conv Whether the time should be presented in the viewer's own timezone
 * @param  ?MEMBER $member_calendar The member's calendar it will be on (null: not on a specific member's calendar)
 * @param  BINARY $validated Whether the event has been validated
 * @param  BINARY $allow_rating Whether the event may be rated
 * @param  SHORT_INTEGER $allow_comments Whether comments are allowed (0=no, 1=yes, 2=review style)
 * @param  BINARY $allow_trackbacks Whether the event may be trackbacked
 * @param  LONG_TEXT $notes Hidden notes pertaining to the event
 * @param  ?MEMBER $submitter The event submitter (null: current member)
 * @param  integer $views The number of views so far
 * @param  ?TIME $add_time The add time (null: now)
 * @param  ?TIME $edit_time The edit time (null: never)
 * @param  ?AUTO_LINK $id Force an ID (null: don't force an ID)
 * @param  ?SHORT_TEXT $meta_keywords Meta keywords for this resource (null: do not edit) (blank: implicit)
 * @param  ?LONG_TEXT $meta_description Meta description for this resource (null: do not edit) (blank: implicit)
 * @param  array $regions The regions (empty: not region-limited)
 * @return AUTO_LINK The ID of the event
 */
function add_calendar_event($type, $recurrence, $recurrences, $seg_recurrences, $title, $content, $priority, $start_year, $start_month, $start_day, $start_monthly_spec_type, $start_hour, $start_minute, $end_year = null, $end_month = null, $end_day = null, $end_monthly_spec_type = 'day_of_month', $end_hour = null, $end_minute = null, $timezone = null, $do_timezone_conv = 1, $member_calendar = null, $validated = 1, $allow_rating = 1, $allow_comments = 1, $allow_trackbacks = 1, $notes = '', $submitter = null, $views = 0, $add_time = null, $edit_time = null, $id = null, $meta_keywords = '', $meta_description = '', $regions = array())
{
    if ($submitter === null) {
        $submitter = get_member();
    }
    if ($add_time === null) {
        $add_time = time();
    }

    if ($timezone === null) {
        $timezone = get_users_timezone();
    }

    require_code('comcode_check');

    check_comcode($content, null, false, null, true);

    require_code('global4');
    prevent_double_submit('ADD_CALENDAR_EVENT', null, $title);

    if (!addon_installed('unvalidated')) {
        $validated = 1;
    }
    $map = array(
        'e_submitter' => $submitter,
        'e_member_calendar' => $member_calendar,
        'e_views' => $views,
        'e_content' => 0,
        'e_add_date' => $add_time,
        'e_edit_date' => $edit_time,
        'e_recurrence' => $recurrence,
        'e_recurrences' => $recurrences,
        'e_seg_recurrences' => $seg_recurrences,
        'e_start_year' => $start_year,
        'e_start_month' => $start_month,
        'e_start_day' => $start_day,
        'e_start_monthly_spec_type' => $start_monthly_spec_type,
        'e_start_hour' => $start_hour,
        'e_start_minute' => $start_minute,
        'e_end_year' => $end_year,
        'e_end_month' => $end_month,
        'e_end_day' => $end_day,
        'e_end_monthly_spec_type' => $end_monthly_spec_type,
        'e_end_hour' => $end_hour,
        'e_end_minute' => $end_minute,
        'e_timezone' => $timezone,
        'e_do_timezone_conv' => $do_timezone_conv,
        'e_priority' => $priority,
        'e_type' => $type,
        'validated' => $validated,
        'allow_rating' => $allow_rating,
        'allow_comments' => $allow_comments,
        'allow_trackbacks' => $allow_trackbacks,
        'notes' => $notes
    );
    $map += insert_lang_comcode('e_title', $title, 2);
    if (multi_lang_content()) {
        $map['e_content'] = 0;
    } else {
        $map['e_content'] = '';
        $map['e_content__text_parsed'] = '';
        $map['e_content__source_user'] = get_member();
    }
    $map += insert_lang_comcode('e_title', $title, 2);
    if ($id !== null) {
        $map['id'] = $id;
    }
    $id = $GLOBALS['SITE_DB']->query_insert('calendar_events', $map, true);

    require_code('attachments2');
    $GLOBALS['SITE_DB']->query_update('calendar_events', insert_lang_comcode_attachments('e_content', 3, $content, 'calendar', strval($id)), array('id' => $id), '', 1);

    foreach ($regions as $region) {
        $GLOBALS['SITE_DB']->query_insert('content_regions', array('content_type' => 'event', 'content_id' => strval($id), 'region' => $region));
    }

    require_code('seo2');
    if (($meta_keywords == '') && ($meta_description == '')) {
        seo_meta_set_for_implicit('event', strval($id), array($title, $content), $content);
    } else {
        seo_meta_set_for_explicit('event', strval($id), $meta_keywords, $meta_description);
    }

    delete_cache_entry('side_calendar');

    if ($validated == 1) {
        if (addon_installed('content_privacy')) {
            require_code('content_privacy');
            $privacy_limits = privacy_limits_for('event', strval($id));
        } else {
            $privacy_limits = array();
        }

        require_lang('calendar');
        require_code('calendar');
        require_code('notifications');
        list($date_range) = get_calendar_event_first_date($timezone, $do_timezone_conv, $start_year, $start_month, $start_day, $start_monthly_spec_type, $start_hour, $start_minute, $end_year, $end_month, $end_day, $end_monthly_spec_type, $end_hour, $end_minute, $recurrence, $recurrences);
        $subject = do_lang('CALENDAR_EVENT_NOTIFICATION_MAIL_SUBJECT', get_site_name(), strip_comcode($title), $date_range);
        $self_url = build_url(array('page' => 'calendar', 'type' => 'view', 'id' => $id), get_module_zone('calendar'), array(), false, false, true);
        $mail = do_notification_lang('CALENDAR_EVENT_NOTIFICATION_MAIL', comcode_escape(get_site_name()), comcode_escape($title), array($self_url->evaluate(), comcode_escape($date_range)));
        dispatch_notification('calendar_event', strval($type), $subject, $mail, $privacy_limits);
    }

    if ($member_calendar !== null) {
        if ($submitter != $member_calendar) {
            require_lang('calendar');
            require_code('calendar');
            require_code('notifications');
            $username = $GLOBALS['FORUM_DRIVER']->get_username($submitter);
            list($date_range) = get_calendar_event_first_date($timezone, $do_timezone_conv, $start_year, $start_month, $start_day, $start_monthly_spec_type, $start_hour, $start_minute, $end_year, $end_month, $end_day, $end_monthly_spec_type, $end_hour, $end_minute, $recurrence, $recurrences);
            $subject = do_lang('MEMBER_CALENDAR_NOTIFICATION_NEW_EVENT_SUBJECT', get_site_name(), strip_comcode($title), array($date_range, $username));
            $self_url = build_url(array('page' => 'calendar', 'type' => 'view', 'id' => $id, 'member_id' => $member_calendar, 'private' => 1), get_module_zone('calendar'), array(), false, false, true);
            $mail = do_notification_lang('MEMBER_CALENDAR_NOTIFICATION_NEW_EVENT_BODY', comcode_escape(get_site_name()), comcode_escape($title), array($self_url->evaluate(), comcode_escape($date_range), comcode_escape($username)));
            dispatch_notification('member_calendar_changes', strval($member_calendar), $subject, $mail, array($member_calendar));
        }
    }

    log_it('ADD_CALENDAR_EVENT', strval($id), $title);

    if ((addon_installed('commandr')) && (!running_script('install'))) {
        require_code('resource_fs');
        generate_resource_fs_moniker('event', strval($id), null, null, true);
    }

    require_code('member_mentions');
    dispatch_member_mention_notifications('event', strval($id), $submitter);

    require_code('sitemap_xml');
    notify_sitemap_node_add('SEARCH:calendar:view:' . strval($id), $add_time, $edit_time, SITEMAP_IMPORTANCE_HIGH, 'weekly', has_category_access($GLOBALS['FORUM_DRIVER']->get_guest_id(), 'calendar', strval($type)));

    return $id;
}

/**
 * Edit a calendar event.
 *
 * @param  AUTO_LINK $id The ID of the event
 * @param  ?AUTO_LINK $type The event type (null: default)
 * @param  SHORT_TEXT $recurrence The recurrence code
 * @param  ?integer $recurrences The number of recurrences (null: none/infinite)
 * @param  BINARY $seg_recurrences Whether to segregate the comment-topics/rating/trackbacks per-recurrence
 * @param  SHORT_TEXT $title The title of the event
 * @param  LONG_TEXT $content The full text describing the event
 * @param  integer $priority The priority
 * @range  1 5
 * @param  integer $start_year The year the event starts at
 * @param  integer $start_month The month the event starts at
 * @param  integer $start_day The day the event starts at
 * @param  ID_TEXT $start_monthly_spec_type In-month specification type for start date
 * @set day_of_month day_of_month_backwards dow_of_month dow_of_month_backwards
 * @param  integer $start_hour The hour the event starts at
 * @param  integer $start_minute The minute the event starts at
 * @param  ?integer $end_year The year the event ends at (null: not a multi day event)
 * @param  ?integer $end_month The month the event ends at (null: not a multi day event)
 * @param  ?integer $end_day The day the event ends at (null: not a multi day event)
 * @param  ID_TEXT $end_monthly_spec_type In-month specification type for end date
 * @set day_of_month day_of_month_backwards dow_of_month dow_of_month_backwards
 * @param  ?integer $end_hour The hour the event ends at (null: not a multi day event)
 * @param  ?integer $end_minute The minute the event ends at (null: not a multi day event)
 * @param  ?ID_TEXT $timezone The timezone for the event (null: current user's timezone)
 * @param  BINARY $do_timezone_conv Whether the time should be presented in the viewer's own timezone
 * @param  ?MEMBER $member_calendar The member's calendar it will be on (null: not on a specific member's calendar)
 * @param  SHORT_TEXT $meta_keywords Meta keywords
 * @param  LONG_TEXT $meta_description Meta description
 * @param  ?BINARY $validated Whether the event has been validated (null: don't change)
 * @param  BINARY $allow_rating Whether the event may be rated
 * @param  SHORT_INTEGER $allow_comments Whether comments are allowed (0=no, 1=yes, 2=review style)
 * @param  BINARY $allow_trackbacks Whether the event may be trackbacked
 * @param  LONG_TEXT $notes Hidden notes pertaining to the event
 * @param  ?TIME $edit_time Edit time (null: either means current time, or if $null_is_literal, means reset to to null)
 * @param  ?TIME $add_time Add time (null: do not change)
 * @param  ?integer $views Number of views (null: do not change)
 * @param  ?MEMBER $submitter Submitter (null: do not change)
 * @param  array $regions The regions (empty: not region-limited)
 * @param  boolean $null_is_literal Determines whether some nulls passed mean 'use a default' or literally mean 'set to null'
 */
function edit_calendar_event($id, $type, $recurrence, $recurrences, $seg_recurrences, $title, $content, $priority, $start_year, $start_month, $start_day, $start_monthly_spec_type, $start_hour, $start_minute, $end_year, $end_month, $end_day, $end_monthly_spec_type, $end_hour, $end_minute, $timezone, $do_timezone_conv, $member_calendar, $meta_keywords, $meta_description, $validated, $allow_rating, $allow_comments, $allow_trackbacks, $notes, $edit_time = null, $add_time = null, $views = null, $submitter = null, $regions = array(), $null_is_literal = false)
{
    if ($edit_time === null) {
        $edit_time = $null_is_literal ? null : time();
    }

    $myrows = $GLOBALS['SITE_DB']->query_select('calendar_events', array('*'), array('id' => $id), '', 1);
    if (!array_key_exists(0, $myrows)) {
        warn_exit(do_lang_tempcode('MISSING_RESOURCE', 'event'));
    }
    $myrow = $myrows[0];

    require_code('urls2');
    suggest_new_idmoniker_for('calendar', 'view', strval($id), '', $title);

    require_code('seo2');
    seo_meta_set_for_explicit('event', strval($id), $meta_keywords, $meta_description);

    require_code('attachments2');
    require_code('attachments3');

    if (!addon_installed('unvalidated')) {
        $validated = 1;
    }

    require_code('submit');
    $just_validated = (!content_validated('event', strval($id))) && ($validated == 1);
    if ($just_validated) {
        send_content_validated_notification('event', strval($id));
    }

    $scheduling_map = array(
        'e_start_year' => $start_year,
        'e_start_month' => $start_month,
        'e_start_day' => $start_day,
        'e_start_monthly_spec_type' => $start_monthly_spec_type,
        'e_start_hour' => $start_hour,
        'e_start_minute' => $start_minute,
        'e_end_year' => $end_year,
        'e_end_month' => $end_month,
        'e_end_day' => $end_day,
        'e_end_monthly_spec_type' => $end_monthly_spec_type,
        'e_end_hour' => $end_hour,
        'e_end_minute' => $end_minute,
        'e_timezone' => $timezone,
    );
    $rescheduled = false;
    foreach ($scheduling_map as $key => $val) {
        if ($myrow[$key] != $val) {
            $rescheduled = true;
        }
    }
    $update_map = array(
        'e_recurrence' => $recurrence,
        'e_recurrences' => $recurrences,
        'e_seg_recurrences' => $seg_recurrences,
        'e_do_timezone_conv' => $do_timezone_conv,
        'e_priority' => $priority,
        'e_type' => $type,
        'allow_rating' => $allow_rating,
        'allow_comments' => $allow_comments,
        'allow_trackbacks' => $allow_trackbacks,
        'e_member_calendar' => $member_calendar,
        'notes' => $notes
    );
    $update_map += $scheduling_map;
    $update_map += lang_remap_comcode('e_title', $myrow['e_title'], $title);
    $update_map += update_lang_comcode_attachments('e_content', $myrow['e_content'], $content, 'calendar', strval($id), null, $myrow['e_submitter']);

    if ($validated !== null) {
        $update_map['validated'] = $validated;
    }
    $update_map['e_edit_date'] = $edit_time;
    if ($add_time !== null) {
        $update_map['e_add_date'] = $add_time;
    }
    if ($views !== null) {
        $update_map['e_views'] = $views;
    }
    if ($submitter !== null) {
        $update_map['e_submitter'] = $submitter;
    }

    $GLOBALS['SITE_DB']->query_update('calendar_events', $update_map, array('id' => $id), '', 1);

    $GLOBALS['SITE_DB']->query_delete('content_regions', array('content_type' => 'event', 'content_id' => strval($id)));
    foreach ($regions as $region) {
        $GLOBALS['SITE_DB']->query_insert('content_regions', array('content_type' => 'event', 'content_id' => strval($id), 'region' => $region));
    }

    $self_url = build_url(array('page' => 'calendar', 'type' => 'view', 'id' => $id), get_module_zone('calendar'), array(), false, false, true);

    if ($just_validated) {
        if (addon_installed('content_privacy')) {
            require_code('content_privacy');
            $privacy_limits = privacy_limits_for('event', strval($id));
        } else {
            $privacy_limits = array();
        }

        require_lang('calendar');
        require_code('calendar');
        require_code('notifications');
        list($date_range) = get_calendar_event_first_date($timezone, $do_timezone_conv, $start_year, $start_month, $start_day, $start_monthly_spec_type, $start_hour, $start_minute, $end_year, $end_month, $end_day, $end_monthly_spec_type, $end_hour, $end_minute, $recurrence, $recurrences);
        $subject = do_lang('CALENDAR_EVENT_NOTIFICATION_MAIL_SUBJECT', get_site_name(), strip_comcode($title), $date_range);
        $self_url = build_url(array('page' => 'calendar', 'type' => 'view', 'id' => $id), get_module_zone('calendar'), array(), false, false, true);
        $mail = do_notification_lang('CALENDAR_EVENT_NOTIFICATION_MAIL', comcode_escape(get_site_name()), comcode_escape($title), array($self_url->evaluate(), comcode_escape($date_range)));
        dispatch_notification('calendar_event', strval($type), $subject, $mail, $privacy_limits);
    }

    if ($member_calendar !== null) {
        if ($submitter !== null) {
            $myrow['e_submitter'] = $submitter;
        }
        if ($member_calendar != $myrow['e_submitter']) {
            require_lang('calendar');
            require_code('calendar');
            require_code('notifications');
            $username = $GLOBALS['FORUM_DRIVER']->get_username(get_member());
            list($date_range) = get_calendar_event_first_date($timezone, $do_timezone_conv, $start_year, $start_month, $start_day, $start_monthly_spec_type, $start_hour, $start_minute, $end_year, $end_month, $end_day, $end_monthly_spec_type, $end_hour, $end_minute, $recurrence, $recurrences);
            $l_subject = $rescheduled ? 'MEMBER_CALENDAR_NOTIFICATION_RESCHEDULED_EVENT_SUBJECT' : 'MEMBER_CALENDAR_NOTIFICATION_EDITED_EVENT_SUBJECT';
            $subject = do_lang($l_subject, get_site_name(), strip_comcode($title), array($date_range, $username));
            $self_url = build_url(array('page' => 'calendar', 'type' => 'view', 'id' => $id, 'member_id' => $member_calendar, 'private' => 1), get_module_zone('calendar'), array(), false, false, true);
            $l_body = $rescheduled ? 'MEMBER_CALENDAR_NOTIFICATION_RESCHEDULED_EVENT_BODY' : 'MEMBER_CALENDAR_NOTIFICATION_EDITED_EVENT_BODY';
            $mail = do_notification_lang($l_body, comcode_escape(get_site_name()), comcode_escape($title), array($self_url->evaluate(), comcode_escape($date_range), comcode_escape($username)));
            dispatch_notification('member_calendar_changes', strval($member_calendar), $subject, $mail, array((get_member() == $member_calendar) ? $myrow['e_submitter'] : $member_calendar));
        }
    }

    delete_cache_entry('side_calendar');

    require_code('feedback');
    update_spacer_post(
        $allow_comments != 0,
        'events',
        strval($id),
        $self_url,
        $title,
        process_overridden_comment_forum('calendar', strval($id), strval($type), strval($myrow['e_type']))
    );

    log_it('EDIT_CALENDAR_EVENT', strval($id), $title);

    if ((addon_installed('commandr')) && (!running_script('install'))) {
        require_code('resource_fs');
        generate_resource_fs_moniker('event', strval($id));
    }

    require_code('sitemap_xml');
    notify_sitemap_node_edit('SEARCH:calendar:view:' . strval($id), has_category_access($GLOBALS['FORUM_DRIVER']->get_guest_id(), 'calendar', strval($type)));
}

/**
 * Delete a calendar event.
 *
 * @param  AUTO_LINK $id The ID of the event
 */
function delete_calendar_event($id)
{
    $myrows = $GLOBALS['SITE_DB']->query_select('calendar_events', array('*'), array('id' => $id), '', 1);
    $myrow = $myrows[0];
    $e_title = get_translated_text($myrow['e_title']);

    $GLOBALS['SITE_DB']->query_delete('calendar_events', array('id' => $id), '', 1);

    $GLOBALS['SITE_DB']->query_delete('calendar_jobs', array('j_event_id' => $id));
    $GLOBALS['SITE_DB']->query_delete('calendar_reminders', array('e_id' => $id));

    require_code('seo2');
    seo_meta_erase_storage('event', strval($id));

    $GLOBALS['SITE_DB']->query_delete('rating', array('rating_for_type' => 'events', 'rating_for_id' => strval($id)));
    $GLOBALS['SITE_DB']->query_delete('trackbacks', array('trackback_for_type' => 'events', 'trackback_for_id' => strval($id)));
    $GLOBALS['SITE_DB']->query_delete('content_regions', array('content_type' => 'event', 'content_id' => strval($id)));
    require_code('notifications');
    delete_all_notifications_on('comment_posted', 'events_' . strval($id));

    delete_lang($myrow['e_title']);
    require_code('attachments2');
    require_code('attachments3');
    if ($myrow['e_content'] !== null) {
        delete_lang_comcode_attachments($myrow['e_content'], 'e_content', strval($id));
    }

    delete_cache_entry('side_calendar');

    $member_calendar = $myrow['e_member_calendar'];
    if ($member_calendar !== null) {
        if ($member_calendar != $myrow['e_submitter']) {
            $timezone = $myrow['e_timezone'];
            $do_timezone_conv = $myrow['e_do_timezone_conv'];
            $start_year = $myrow['e_start_year'];
            $start_month = $myrow['e_start_month'];
            $start_day = $myrow['e_start_day'];
            $start_monthly_spec_type = $myrow['e_start_monthly_spec_type'];
            $start_hour = $myrow['e_start_hour'];
            $start_minute = $myrow['e_start_minute'];
            $end_year = $myrow['e_end_year'];
            $end_month = $myrow['e_end_month'];
            $end_day = $myrow['e_end_day'];
            $end_monthly_spec_type = $myrow['e_end_monthly_spec_type'];
            $end_hour = $myrow['e_end_hour'];
            $end_minute = $myrow['e_end_minute'];
            $recurrence = $myrow['e_recurrence'];
            $recurrences = $myrow['e_recurrences'];

            require_lang('calendar');
            require_code('calendar');
            require_code('notifications');
            $username = $GLOBALS['FORUM_DRIVER']->get_username(get_member());
            list($date_range) = get_calendar_event_first_date($timezone, $do_timezone_conv, $start_year, $start_month, $start_day, $start_monthly_spec_type, $start_hour, $start_minute, $end_year, $end_month, $end_day, $end_monthly_spec_type, $end_hour, $end_minute, $recurrence, $recurrences);
            $subject = do_lang('MEMBER_CALENDAR_NOTIFICATION_DELETED_EVENT_SUBJECT', get_site_name(), strip_comcode($e_title), array($date_range, $username));
            $mail = do_notification_lang('MEMBER_CALENDAR_NOTIFICATION_DELETED_EVENT_BODY', comcode_escape(get_site_name()), comcode_escape($e_title), array(comcode_escape($date_range), comcode_escape($username)));
            dispatch_notification('member_calendar_changes', strval($member_calendar), $subject, $mail, array((get_member() == $member_calendar) ? $myrow['e_submitter'] : $member_calendar));
        }
    }

    if (addon_installed('catalogues')) {
        update_catalogue_content_ref('event', strval($id), '');
    }

    log_it('DELETE_CALENDAR_EVENT', strval($id), $e_title);

    if ((addon_installed('commandr')) && (!running_script('install'))) {
        require_code('resource_fs');
        expunge_resource_fs_moniker('event', strval($id));
    }

    require_code('sitemap_xml');
    notify_sitemap_node_delete('SEARCH:calendar:view:' . strval($id));
}

/**
 * Add a calendar event type.
 *
 * @param  SHORT_TEXT $title The title of the event type
 * @param  ID_TEXT $logo The theme image code
 * @param  URLPATH $external_feed URL to external feed to associate with this event type
 * @return AUTO_LINK The ID of the event type
 */
function add_event_type($title, $logo, $external_feed = '')
{
    require_code('global4');
    prevent_double_submit('ADD_EVENT_TYPE', null, $title);

    $map = array(
        't_logo' => $logo,
        't_external_feed' => $external_feed,
    );
    $map += insert_lang_comcode('t_title', $title, 2);
    $id = $GLOBALS['SITE_DB']->query_insert('calendar_types', $map, true);

    log_it('ADD_EVENT_TYPE', strval($id), $title);

    if ((addon_installed('commandr')) && (!running_script('install'))) {
        require_code('resource_fs');
        generate_resource_fs_moniker('calendar_type', strval($id), null, null, true);
    }

    require_code('member_mentions');
    dispatch_member_mention_notifications('calendar_type', strval($id));

    require_code('sitemap_xml');
    notify_sitemap_node_add('SEARCH:calendar:browse:int_' . strval($id) . '=1', null, null, SITEMAP_IMPORTANCE_MEDIUM, 'weekly', has_category_access($GLOBALS['FORUM_DRIVER']->get_guest_id(), 'calendar', strval($id)));

    return $id;
}

/**
 * Edit a calendar event type.
 *
 * @param  AUTO_LINK $id The ID of the event type
 * @param  SHORT_TEXT $title The title of the event type
 * @param  ID_TEXT $logo The theme image code
 * @param  URLPATH $external_feed URL to external feed to associate with this event type
 */
function edit_event_type($id, $title, $logo, $external_feed)
{
    $myrows = $GLOBALS['SITE_DB']->query_select('calendar_types', array('*'), array('id' => $id), '', 1);
    if (!array_key_exists(0, $myrows)) {
        warn_exit(do_lang_tempcode('MISSING_RESOURCE', 'calendar_type'));
    }
    $myrow = $myrows[0];

    require_code('urls2');
    suggest_new_idmoniker_for('calendar', 'browse', strval($id), '', $title);

    $old_theme_img_code = $myrow['t_logo'];
    require_code('themes2');
    tidy_theme_img_code($logo, $old_theme_img_code, 'calendar_types', 't_logo');

    $map = array(
        't_logo' => $logo,
        't_external_feed' => $external_feed,
    );
    $map += lang_remap_comcode('t_title', $myrow['t_title'], $title);
    $GLOBALS['SITE_DB']->query_update('calendar_types', $map, array('id' => $id), '', 1);

    if ((addon_installed('commandr')) && (!running_script('install'))) {
        require_code('resource_fs');
        generate_resource_fs_moniker('calendar_type', strval($id));
    }

    log_it('EDIT_EVENT_TYPE', strval($id), $title);

    require_code('sitemap_xml');
    notify_sitemap_node_edit('SEARCH:calendar:browse:int_' . strval($id) . '=1', has_category_access($GLOBALS['FORUM_DRIVER']->get_guest_id(), 'calendar', strval($id)));
}

/**
 * Delete a calendar event type.
 *
 * @param  AUTO_LINK $id The ID of the event type
 */
function delete_event_type($id)
{
    $myrows = $GLOBALS['SITE_DB']->query_select('calendar_types', array('t_title', 't_logo'), array('id' => $id), '', 1);
    if (!array_key_exists(0, $myrows)) {
        warn_exit(do_lang_tempcode('MISSING_RESOURCE', 'calendar_type'));
    }
    $myrow = $myrows[0];

    $lowest = $GLOBALS['SITE_DB']->query_value_if_there('SELECT MIN(id) FROM ' . get_table_prefix() . 'calendar_types WHERE id<>' . strval($id) . ' AND id<>' . strval(db_get_first_id()));
    if ($lowest === null) {
        warn_exit(do_lang_tempcode('NO_DELETE_LAST_CATEGORY', 'calendar_type'));
    }
    $GLOBALS['SITE_DB']->query_update('calendar_events', array('e_type' => $lowest), array('e_type' => $id));

    require_code('files2');
    delete_upload('themes/default/images_custom/calendar', 'calendar_types', 't_logo', 'id', $id);

    $old_theme_img_code = $myrow['t_logo'];
    require_code('themes2');
    tidy_theme_img_code(null, $old_theme_img_code, 'calendar_types', 't_logo');


    $GLOBALS['SITE_DB']->query_delete('calendar_types', array('id' => $id), '', 1);

    $GLOBALS['SITE_DB']->query_delete('calendar_interests', array('t_type' => $id));

    if (addon_installed('catalogues')) {
        update_catalogue_content_ref('event_type', strval($id), '');
    }

    delete_lang($myrow['t_title']);

    $GLOBALS['SITE_DB']->query_delete('group_category_access', array('module_the_name' => 'calendar', 'category_name' => strval($id)));

    log_it('DELETE_EVENT_TYPE', strval($id), get_translated_text($myrow['t_title']));

    if ((addon_installed('commandr')) && (!running_script('install'))) {
        require_code('resource_fs');
        expunge_resource_fs_moniker('calendar_type', strval($id));
    }

    require_code('sitemap_xml');
    notify_sitemap_node_delete('SEARCH:calendar:browse:int_' . strval($id) . '=1');
}
