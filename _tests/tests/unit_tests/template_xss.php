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
class template_xss_test_set extends cms_test_case
{
    public function setUp()
    {
        parent::setUp();

        require_code('themes2');
        require_code('files');
    }

    public function testHTMLCDataBreakout() // See http://css.dzone.com/articles/xss-still-tricky
    {
        $templates = array();

        $paths = array(
            get_file_base() . '/themes/default/templates',
            get_file_base() . '/themes/default/templates_custom',
        );
        $themes = find_all_themes();
        foreach (array_keys($themes) as $theme) {
            if ($theme == '_unnamed_') {
                continue;
            }

            $paths = array_merge($paths, array(
                get_file_base() . '/themes/' . $theme . '/templates',
                get_file_base() . '/themes/' . $theme . '/templates_custom',
            ));
        }

        foreach ($paths as $path) {
            $dh = @opendir($path);
            if ($dh !== false) {
                while (($file = readdir($dh)) !== false) {
                    if (strtolower(substr($file, -4)) == '.tpl') {
                        $c = file_get_contents($path . '/' . $file);
                        $c_orig = $c;

                        $c = $this->strip_down_template($c);

                        // Search
                        $matches = array();
                        $num_matches = preg_match_all('#\{(\w+)([*=;\#~^\'&.@+-]*)\}#U', $c, $matches);
                        $params_found = array();
                        for ($i = 0; $i < $num_matches; $i++) {
                            $match = $matches[0][$i];
                            $params_found[$match] = $matches;
                        }
                        foreach ($params_found as $match => $matches) {
                            $matches2 = array();
                            if (preg_match('#<script[^<>]*>(?:(?!</script>).)*(?<!\\\\)' . preg_quote($match, '#') . '(?:(?!</script>)).*</script>#Us', $c, $matches2) != 0) {
                                $this->assertTrue(false, 'Unsafe embedded parameter within JavaScript block, needing "/" escaper (' . $match . ') in ' . $file);

                                if (get_param_integer('save', 0) == 1) {
                                    $c_orig = str_replace($match, '{' . $matches[1][$i] . $matches[2][$i] . '/' . '}', $c_orig);
                                    cms_file_put_contents_safe($path . '/' . $file, $c_orig, FILE_WRITE_FIX_PERMISSIONS | FILE_WRITE_SYNC_FILE);
                                }
                            }
                        }
                    }
                }

                closedir($dh);
            }
        }
    }

    protected function strip_down_template($c)
    {
        // Strip parameters inside symbols, language strings and Tempcode portions
        do {
            $matches = array();
            $num_matches = preg_match('#\{[\$\!\+]#', $c, $matches, PREG_OFFSET_CAPTURE);
            if ($num_matches != 0) {
                $posa = $matches[0][1];
                $pos = $posa;
                $balance = 0;
                do {
                    if (!isset($c[$pos])) {
                        break;
                    }
                    $char = $c[$pos];
                    if ($char == '{') {
                        $balance++;
                    } elseif ($char == '}') {
                        $balance--;
                    }
                    $pos++;
                } while ($balance != 0);
                $c = str_replace(substr($c, $posa, $pos - $posa), '', $c);
            }
        } while ($num_matches > 0);
        return $c;
    }

    public function testHTMLAttributeBreakout()
    {
        $templates = array();

        $paths = array(
            get_file_base() . '/themes/default/templates',
            get_file_base() . '/themes/default/templates_custom',
        );
        $themes = find_all_themes();
        foreach (array_keys($themes) as $theme) {
            if ($theme == '_unnamed_') {
                continue;
            }

            $paths = array_merge($paths, array(
                get_file_base() . '/themes/' . $theme . '/templates',
                get_file_base() . '/themes/' . $theme . '/templates_custom',
            ));
        }

        foreach ($paths as $path) {
            $dh = @opendir($path);
            if ($dh !== false) {
                while (($file = readdir($dh)) !== false) {
                    if (strtolower(substr($file, -4)) == '.tpl') {
                        $c = file_get_contents($path . '/' . $file);
                        $c_orig = $c;

                        $c = $this->strip_down_template($c);

                        // Search
                        $matches = array();
                        $num_matches = preg_match_all('#\s\w+="[^"]*\{(\w+)[^\|\w\'=%"`\{\}\*]\}#Us', $c, $matches);
                        for ($i = 0; $i < $num_matches; $i++) {
                            $match = $matches[1][$i];
                            $this->assertTrue(false, 'Unsafe embedded parameter within HTML attribute, needing "*" escaper (' . $match . ') in ' . $file); // To stop HTML script tag breaking out of "escaped" quotes, due to working on higher level of the parser
                        }
                    }
                }
                closedir($dh);
            }
        }
    }
}
