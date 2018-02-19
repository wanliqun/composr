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

// E.g. http://localhost/composr/_tests/?id=unit_tests%2Fweb_resources&close_if_passed=1&debug=1&keep_minify=0&only=checking.js

/**
 * Composr test case class (unit testing).
 */
class web_resources_test_set extends cms_test_case
{
    public function setUp()
    {
        parent::setUp();

        if (php_function_allowed('set_time_limit')) {
            @set_time_limit(1000);
        }

        $_GET['keep_minify'] = '0';

        disable_php_memory_limit();

        require_code('webstandards');
        require_code('webstandards2');
        require_lang('webstandards');
        require_code('themes2');

        global $WEBSTANDARDS_JAVASCRIPT, $WEBSTANDARDS_CSS, $WEBSTANDARDS_WCAG, $WEBSTANDARDS_COMPAT, $WEBSTANDARDS_EXT_FILES, $WEBSTANDARDS_MANUAL;
        $WEBSTANDARDS_JAVASCRIPT = true;
        $WEBSTANDARDS_CSS = true;
        $WEBSTANDARDS_WCAG = true;
        $WEBSTANDARDS_COMPAT = false;
        $WEBSTANDARDS_EXT_FILES = true;
        $WEBSTANDARDS_MANUAL = false;
    }

    public function testJavaScript()
    {
        require_code('webstandards_js_lint');

        $themes = find_all_themes();
        foreach (array_keys($themes) as $theme) {
            if ($theme == '_unnamed_') {
                continue;
            }

            foreach (array('javascript', 'javascript_custom') as $dir) {
                $this->javaScriptTestForTheme($theme, $dir);
            }
        }
    }

    protected function javaScriptTestForTheme($theme, $dir)
    {
        $exceptions = array(
            // Won't parse
            'jwplayer.js',
            'jquery_ui.js',
            'modernizr.js',
            'plupload.js',
            'base64.js',

            // Third-party code not confirming to Composr standards
            'widget_color.js',
            'widget_date.js',
            'jquery.js',
            'sortable_tables.js',
            'unslider.js',
            'tag_cloud.js',
            'mediaelement-and-player.js',
            'sound.js',
            'base64.js',

            // Partial code that will give errors
            'ATTACHMENT_UI_DEFAULTS.js',
        );

        $only = get_param_string('only', null);
        if (($only !== null) && (in_array($only, $exceptions))) {
            unset($exceptions[array_search($only, $exceptions)]);
        }

        $dh = @opendir(get_file_base() . '/themes/' . $theme . '/' . $dir);
        if ($dh !== false) {
            while (($file = readdir($dh)) !== false) {
                if (substr($file, -3) == '.js') {
                    if (in_array($file, $exceptions)) {
                        continue;
                    }

                    if ($only !== null) {
                        if ($file != $only) {
                            continue;
                        }
                    }

                    if (!is_file(get_file_base() . '/themes/' . $theme . '/' . $dir . '/' . $file)) {
                        continue;
                    }

                    $path = javascript_enforce(basename($file, '.js'), $theme);
                    if ($path == '') {
                        continue; // Empty file, so skipped
                    }

                    $c = file_get_contents($path);
                    $errors = check_js($c);
                    if ($errors !== null) {
                        foreach ($errors['errors'] as $i => $e) {
                            $e['line'] += 3;
                            $errors['errors'][$i] = $e;
                        }
                    }
                    if (($errors !== null) && ($errors['errors'] == array())) {
                        $errors = null; // Normalise
                    }
                    $this->assertTrue(($errors === null), 'Bad JS in ' . $file);
                    if ($errors !== null) {
                        if (get_param_integer('debug', 0) == 1) {
                            unset($errors['tag_ranges']);
                            unset($errors['value_ranges']);
                            unset($errors['level_ranges']);
                            echo '<pre>';
                            var_dump($errors['errors']);
                            echo '</pre>';
                        }
                    }
                }
            }
            closedir($dh);
        }
    }

    public function testCSS()
    {
        $themes = find_all_themes();
        foreach (array_keys($themes) as $theme) {
            if ($theme == '_unnamed_') {
                continue;
            }

            foreach (array('css', 'css_custom') as $dir) {
                $this->cssTestForTheme($theme, $dir);
            }
        }
    }

    protected function cssTestForTheme($theme, $dir)
    {
        $exceptions = array(
            // Third-party code not confirming to Composr standards
            'widget_color.css',
            'widget_date.css',
            'widget_select2.css',
            'unslider.css',
            'skitter.css',
            'mediaelementplayer.css',
            'jquery_ui.css',
            'confluence.css',
        );

        $only = get_param_string('only', null);
        if (($only !== null) && (in_array($only, $exceptions))) {
            unset($exceptions[array_search($only, $exceptions)]);
        }

        $dh = @opendir(get_file_base() . '/themes/' . $theme . '/' . $dir);
        if ($dh !== false) {
            while (($file = readdir($dh)) !== false) {
                if ((substr($file, -4) == '.css') && ($file != 'svg.css'/*SVG-CSS*/) && ($file != 'no_cache.css')) {
                    if (in_array($file, $exceptions)) {
                        continue;
                    }

                    $path = css_enforce(basename($file, '.css'), $theme);
                    if ($path == '') {
                        continue; // Nothing in file after minimisation
                    }

                    if ($only !== null) {
                        if ($file != $only) {
                            continue;
                        }
                    }

                    $c = file_get_contents($path);
                    $errors = check_css($c);
                    if (($errors !== null) && ($errors['errors'] == array())) {
                        $errors = null; // Normalise
                    }
                    $this->assertTrue(($errors === null), 'Bad CSS in ' . $file . (($only === null) ? (' (run with &only=' . $file . '&debug=1 to see errors)') : ''));
                    if ($errors !== null) {
                        if (get_param_integer('debug', 0) == 1) {
                            echo '<pre>';
                            var_dump($errors['errors']);
                            echo '</pre>';
                        }
                    }
                }
            }
            closedir($dh);
        }
    }
}