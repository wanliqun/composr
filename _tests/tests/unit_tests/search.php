<?php /*

 Composr
 Copyright (c) ocProducts, 2004-2018

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
class search_test_set extends cms_test_case
{
    public function setUp()
    {
        parent::setUp();

        $this->establish_admin_session();

        require_code('xml');
    }

    public function testOpenSearch()
    {
        $url = find_script('opensearch');
        $data = cms_http_request($url, array('cookies' => array(get_session_cookie() => get_session_id())));
        $parsed = new CMS_simple_xml_reader($data->data);
        $this->assertTrue(strpos($data->download_mime_type, 'text/xml') !== false);

        $url = find_script('opensearch') . '?type=suggest&request=abc';
        $data = cms_http_request($url, array('cookies' => array(get_session_cookie() => get_session_id())));
        $this->assertTrue(is_array(json_decode($data->data)));
        $this->assertTrue(strpos($data->download_mime_type, 'application/x-suggestions+json') !== false);
    }
}
