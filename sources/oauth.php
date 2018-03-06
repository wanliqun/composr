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
 * @package    core_configuration
 */

/*
Composr has a very simple oAuth2 implementation.
oAuth2 is simpler than oAuth1, because SSL is used for encryption, rather than a complex native implementation.

Our policy with oAuth is to use whatever oAuth is bundled with service APIs first, if there is one.
(Most web services provide PHP APIs and include an oAuth implementation within them)
If a service provides no oAuth implementation and isn't a simple oAuth2, we would probably use a further
third party library.
The requirements of all these third party APIs and implementations need codifying within the description
of whatever Composr addon uses them, as it will typically exceed Composr base requirements.

Regardless of how the oAuth works, it can be connected through via a Composr oAuth hook.
*/

/**
 * Standard code module initialisation function.
 *
 * @ignore
 */
function init__oauth()
{
    require_lang('oauth');
}

/**
 * Gets info about an oAuth service.
 *
 * @param  string $service_name The name of the service
 * @return array Service info
 */
function get_oauth_service_info($service_name)
{
    static $info_cache = array();
    if (isset($info_cache[$service_name])) {
        return $info_cache[$service_name];
    }

    require_code('hooks/systems/oauth/' . filter_naughty_harsh($service_name));
    $ob = object_factory('Hook_oauth_' . filter_naughty_harsh($service_name));
    $info_cache[$service_name] = $ob->info();

    return $info_cache[$service_name];
}

/**
 * Gets the oAuth refresh token for a particular service.
 *
 * @param  string $service_name The name of the service
 * @return ?string Refresh token (null: none)
 */
function get_oauth_refresh_token($service_name)
{
    $service_info = get_oauth_service_info($service_name);

    if (isset($service_info['refresh_token'])) {
        $refresh_token = $service_info['refresh_token'];
    } else {
        $refresh_token = get_value($service_info['saved_data']['refresh_token_key'], null, true);
    }
    return $refresh_token;
}

/**
 * Gets the oAuth access token for a particular service by doing a refresh.
 *
 * @param  string $service_name The name of the service
 * @return ?string Access token (null: none)
 */
function refresh_oauth2_token($service_name)
{
    $refresh_token = get_oauth_refresh_token($service_name);

    if ($refresh_token === null) {
        return null;
    }

    $service_info = get_oauth_service_info($service_name);

    $client_id = get_option($service_info['options']['client_id']);
    $client_secret = get_option($service_info['options']['client_secret']);

    $endpoint = $service_info['endpoint'];

    $post_params = array(
        'client_id' => get_option($service_info['options']['client_id']),
        'client_secret' => get_option($service_info['options']['client_secret']),
        'refresh_token' => $refresh_token,
        'grant_type' => 'refresh_token',
    );

    $result = http_get_contents($endpoint . '/token', array('post_params' => $post_params));
    $parsed_result = json_decode($result, true);

    if (!array_key_exists('access_token', $parsed_result)) {
        warn_exit(do_lang_tempcode('ERROR_OBTAINING_ACCESS_TOKEN'));
    }

    return $parsed_result['access_token'];
}
