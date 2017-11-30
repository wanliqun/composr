<?php /*

 Composr
 Copyright (c) ocProducts, 2004-2016

 See text/EN/licence.txt for full licencing information.

*/

/**
 * @license    http://opensource.org/licenses/cpal_1.0 Common Public Attribution License
 * @copyright  ocProducts Ltd
 * @package    testing_platform
 */

/**
 * Composr test case class (unit testing).
 */
class geocoding_test_set extends cms_test_case
{
    public function testGeocodeInternal()
    {
        $test = $GLOBALS['SITE_DB']->query_select_value_if_there('ip_country', 'id');
        $has_geolocation_data = ($test !== null);
        if (!$has_geolocation_data) {
            require_code('tasks');
            require_lang('stats');
            call_user_func_array__long_task(do_lang('INSTALL_GEOLOCATION_DATA'), get_screen_title('INSTALL_GEOLOCATION_DATA'), 'install_geolocation_data', null, false, true);
        }
        require_code('locations');

        $this->assertTrue(geolocate_ip('217.160.72.6') == 'DE');
    }

    public function testGeocodeExternal()
    {
        require_code('locations_geocoding');

        $address = reverse_geocode(52.516667, 13.388889);
        $this->assertTrue($address[2] == 'Berlin');
        $this->assertTrue($address[6] == 'DE');

        $address = reverse_geocode(64.133333, -21.933333);
        $this->assertTrue($address[2] == 'Reykjavík');
        $this->assertTrue($address[6] == 'IS');
    }
}
