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
class js_standards_test_set extends cms_test_case
{
    public function testSSLIssues()
    {
        foreach (array('javascript', 'javascript_custom', 'templates', 'templates_custom') as $dir) {
            $path = get_file_base() . '/themes/default/' . $dir;
            $dh = opendir($path);
            while (($f = readdir($dh)) !== false) {
                if (strtolower(substr($f, -3)) == '.js') {
                    $c = file_get_contents($path . '/' . $f);

                    $matches = array();
                    $num_matches = preg_match_all('#(?<!\$util\.srl\([\'"])\{\$IMG[;*]+,(\w+)\}(.*)$#m', $c, $matches);
                    for ($i = 0; $i < $num_matches; $i++) {
                        $this->assertTrue(false, $f . '/' . $matches[1][$i] . ' not prepared for SSL');
                    }

                    $c2 = preg_replace('#\{\$PAGE_LINK[;*]?,[^,]*,[01],1#', '', $c); // This case is without keep_* params, so is okay. Strip out from data
                    $this->assertTrue(strpos($c2, '{$PAGE_LINK') === false, 'Should not encode page-links directly in JavaScript on ' . $f);

                    $this->checkForScriptOverrideIssue($f, $c);
                }

                if (strtolower(substr($f, -4)) == '.tpl') {
                    $c = file_get_contents($path . '/' . $f);

                    $this->checkForScriptOverrideIssue($f, $c);
                }
            }
            closedir($dh);
        }
    }

    protected function checkForScriptOverrideIssue($f, $c)
    {
        $c2 = str_replace('/index.php', '', $c);
        $this->assertTrue(preg_match('#/(data|adminzone|cms|site|forum)/\w+\.php#', $c2) == 0, $f . ' is directly referencing a script, bypassing override system');
    }
}