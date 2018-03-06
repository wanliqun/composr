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
 * @package    core_abstract_interfaces
 */

/**
 * Put the contents of a screen inside an AJAX updatable area. This is typically used when a page is being used to traverse a result-set that spans multiple screens.
 *
 * @param  Tempcode $screen_content The screen content
 * @param  ?integer $refresh_time The time between refreshes (null: do not refresh)
 * @param  ?mixed $refresh_if_changed Data. A refresh will only happen if an AJAX-check indicates this data has changed (null: no check)
 * @return Tempcode The screen output, wrapped with some AJAX code
 */
function internalise_own_screen($screen_content, $refresh_time = null, $refresh_if_changed = null)
{
    if (get_bot_type() !== null) {
        return $screen_content;
    }
    if (get_param_integer('keep_frames', null) === 0) {
        return $screen_content;
    }

    $params = '';
    foreach ($_GET as $key => $param) {
        if (!is_string($param)) {
            continue;
        }
        if (($key == 'ajax') || ($key == 'zone') || ($key == 'utheme')) {
            continue;
        }
        if ((substr($key, 0, 5) == 'keep_') && (skippable_keep($key, $param))) {
            continue;
        }
        if (substr($key, -6) == '_start') {
            continue;
        }
        $params .= (($params == '') ? '?' : '&') . $key . '=' . urlencode($param);
    }
    $params .= (($params == '') ? '?' : '&') . 'ajax=1';
    if (get_param_string('utheme', '') != '') {
        $params .= '&utheme=' . urlencode(get_param_string('utheme', $GLOBALS['FORUM_DRIVER']->get_theme()));
    }
    $params .= '&zone=' . urlencode(get_zone_name());
    if (get_param_integer('refreshing', 0) == 0) {
        $params .= '&refreshing=1';
    }

    $url = find_script('iframe') . $params;

    if ($refresh_if_changed !== null) {
        require_javascript('sound');
        $change_detection_url = find_script('change_detection') . $params;
    } else {
        $refresh_if_changed = '';
        $change_detection_url = '';
    }

    return do_template('INTERNALISED_AJAX_SCREEN', array(
        '_GUID' => '06554eb227428fd5c648dee3c5b38185',
        'SCREEN_CONTENT' => $screen_content,
        'REFRESH_IF_CHANGED' => md5(serialize($refresh_if_changed)),
        'CHANGE_DETECTION_URL' => $change_detection_url,
        'URL' => $url,
        'REFRESH_TIME' => ($refresh_time === null) ? '' : strval($refresh_time),
    ));
}
