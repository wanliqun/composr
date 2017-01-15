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
class google_appengine_test_set extends cms_test_case
{
    public function testPregConstraint()
    {
        require_code('files');
        require_code('files2');
        $files = get_directory_contents(get_file_base(), '', true);
        foreach ($files as $file) {
            if ((substr($file, -4) == '.php') && (!should_ignore_file($file, IGNORE_BUNDLED_VOLATILE | IGNORE_NONBUNDLED_SCATTERED | IGNORE_CUSTOM_DIR_SUPPLIED_CONTENTS | IGNORE_CUSTOM_DIR_GROWN_CONTENTS))) {
                $contents = file_get_contents(get_file_base() . '/' . $file);

                if (preg_match('#preg_(replace|replace_callback|match|match_all|grep|split)\(\'(.)[^\']*(?<!\\\\)\\2[^\']*e#', $contents) != 0) {
                    $this->assertTrue(false, 'regexp /e not allowed (in ' . $file . ')');
                }

                if ((strpos($contents, '\'PHP_SELF\'') !== false) && (basename($file) != 'urls.php'/*Is a fallback in this file*/) && (basename($file) != 'static_cache.php') && (basename($file) != 'minikernel.php') && (basename($file) != 'global.php') && (basename($file) != 'global2.php') && (basename($file) != 'phpstub.php') && (basename($file) != 'cns_lost_password.php')) {
                    $this->assertTrue(false, 'PHP_SELF does not work stably across platforms (in ' . $file . ')');
                }

                /*
                Think Google AppEngine was since fixed, and we use this for symlink resolution
                if ((strpos($contents, '\'SCRIPT_FILENAME\'') !== false) && (basename($file) != 'minikernel.php') && (basename($file) != 'global.php') && (basename($file) != 'global2.php') && (basename($file) != 'phpstub.php')) {
                    $this->assertTrue(false, 'SCRIPT_FILENAME does not work stably across platforms (in ' . $file . ')');
                }
                */
            }
        }
    }
}
