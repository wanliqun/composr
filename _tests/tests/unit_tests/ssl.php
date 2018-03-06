<?php /*

 Composr
 Copyright (c) ocProducts, 2004-2018

 See text/EN/licence.txt for full licensing information.

*/

/**
 * @license    http://opensource.org/licenses/cpal_1.0 Common Public Attribution License
 * @copyright  ocProducts Ltd
 * @package    testing_platform
 */

/**
 * Composr test case class (unit testing).
 */
class ssl_test_set extends cms_test_case
{
    public function setUp()
    {
        parent::setUp();

        set_value('disable_ssl_for__' . $_SERVER['HTTP_HOST'], '1');
    }

    public function testHTTPSStatus()
    {
        if (strpos(get_base_url(), 'https://') === 0) {
            $this->assertTrue(false, 'Test can only run on HTTP site');
            return;
        }

        global $HTTPS_PAGES_CACHE;
        $HTTPS_PAGES_CACHE = null;

        set_value('disable_ssl_for__' . $_SERVER['HTTP_HOST'], '1');

        if (get_forum_type() == 'cns') {
            $page_link = 'forum:forumview';
            $page = 'forumview';
        } else {
            $page_link = ':recommend';
            $page = 'recommend';
        }

        // HTTPS (SSL) version
        $GLOBALS['SITE_DB']->query_insert('https_pages', array('https_page_name' => $page_link), false, true/*in case previous test didn't finish*/);
        $HTTPS_PAGES_CACHE = null;
        erase_persistent_cache();
        $url = build_url(array('page' => $page), get_module_zone($page));
        $c = http_get_contents($url->evaluate());
        $this->assertTrue(strpos($c, 'src="http://') === false, 'HTTPS version failed (HTTP embed [e.g. image] found) on ' . $url->evaluate());

        // HTTP version
        $GLOBALS['SITE_DB']->query_delete('https_pages', array('https_page_name' => $page_link));
        $HTTPS_PAGES_CACHE = null;
        erase_persistent_cache();
        $url = build_url(array('page' => $page), get_module_zone($page));
        $c = http_get_contents($url->evaluate());
        $this->assertTrue(strpos($c, 'src="https://') === false, 'HTTP version failed (HTTPS embed [e.g. image] found) on ' . $url->evaluate());
    }

    public function tearDown()
    {
        set_value('disable_ssl_for__' . $_SERVER['HTTP_HOST'], '0');

        parent::tearDown();
    }
}
