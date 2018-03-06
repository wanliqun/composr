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

/**
 * Hook class.
 */
class Hook_oauth_google_analytics
{
    /**
     * Standard information about an oAuth profile.
     *
     * @return array Map of oAuth details
     */
    public function info()
    {
        return array(
            'label' => 'Google Analytics',
            'available' => (get_option('google_analytics') != ''),
            'protocol' => 'oauth2',
            'options' => array(
                'client_id' => 'google_apis_client_id',
                'client_secret' => 'google_apis_client_secret',
                'api_key' => 'google_apis_api_key',
            ),
            'saved_data' => array(
                'refresh_token_key' => 'google_analytics_refresh_token',
            ),
            'refresh_token' => null,
            'endpoint' => 'https://accounts.google.com/o/oauth2',
            'scope' => 'https://www.googleapis.com/auth/analytics.readonly',
        );
    }
}
