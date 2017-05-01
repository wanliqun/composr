<?php /*

 Composr
 Copyright (c) ocProducts, 2004-2016

 See text/EN/licence.txt for full licencing information.


 NOTE TO PROGRAMMERS:
   Do not edit this file. If you need to make changes, save your changed file to the appropriate *_custom folder
   **** If you ignore this advice, then your website upgrades (e.g. for bug fixes) will likely kill your changes ****

*/

/**
 * @license    http://opensource.org/licenses/cpal_1.0 Common Public Attribution License
 * @copyright  ocProducts Ltd
 * @package    core_rich_media
 */

/**
 * AJAX script for HTML<>Comcode conversion.
 *
 * @ignore
 */
function comcode_convert_script()
{
    require_code('input_filter_2');
    modsecurity_workaround_enable();

    prepare_for_known_ajax_response();

    attach_to_screen_header('<meta name="robots" content="noindex" />'); // XHTMLXHTML

    require_lang('comcode');

    $data = post_param_string('data', null, INPUT_FILTER_DEFAULT_POST & ~INPUT_FILTER_WYSIWYG_TO_COMCODE);
    if ($data === null) {
        // UI can be called up manually if desired, it's a useful little developer tool...

        $title = get_screen_title('_COMCODE');

        $fields = new Tempcode();
        $hidden = new Tempcode();

        require_code('form_templates');

        require_css('forms');

        $fields->attach(form_input_huge(do_lang_tempcode('TEXT'), '', 'data', '', true));

        $radio_list = new Tempcode();
        $radio_list->attach(form_input_radio_entry('from_html', '-1', false, 'No conversion'));
        $radio_list->attach(form_input_radio_entry('from_html', '0', true, 'Convert Comcode to HTML'));
        $radio_list->attach(form_input_radio_entry('from_html', '1', false, 'Convert HTML/semihtml to Comcode'));
        $fields->attach(form_input_radio('Conversion mode', '', 'from_html', $radio_list, false));

        $fields->attach(form_input_tick('Convert from WYSIWYG semihtml', '', 'is_semihtml', false));
        $fields->attach(form_input_tick('Convert to WYSIWYG semihtml rather than pure HTML', '', 'semihtml', false));
        $fields->attach(form_input_tick('Lax mode (fewer parse rules)', '', 'lax', false));
        $fields->attach(form_input_tick('Fix bad output HTML', '', 'fix_bad_html', false));

        $fields->attach(form_input_tick('Raw text output', '', 'raw_output', true));
        $fields->attach(form_input_tick('Reindent output', '', 'reindent', false));
        $fields->attach(form_input_tick('Do intensive conversion', '', 'force', false));

        require_javascript('core_rich_media');

        $out2 = globalise(do_template('FORM_SCREEN', array(
            '_GUID' => 'dd82970fa1196132e07049871c51aab7',
            'TITLE' => $title,
            'SUBMIT_NAME' => do_lang_tempcode('VIEW'),
            'SUBMIT_ICON' => 'buttons__proceed',
            'TEXT' => '',
            'HIDDEN' => $hidden,
            'URL' => find_script('comcode_convert', true),
            'FIELDS' => $fields,
            'JS_FUNCTION_CALLS' => ['comcodeToolsComcodeConvertScript'],
        )), null, '', true, true);

        $out2->evaluate_echo();

        return;
    }

    $from_html = either_param_integer('from_html', 0);

    if ($from_html == -1) {
        $out = trim($data); // "No conversion"

    } elseif ($from_html == 0) { // "Convert Comcode to HTML"
        if (either_param_integer('lax', 0) == 1) {
            push_lax_comcode(true);
        }

        $db = $GLOBALS['SITE_DB'];
        if (get_param_integer('forum_db', 0) == 1) {
            $db = $GLOBALS['FORUM_DB'];
        }

        if (either_param_integer('is_semihtml', 0) == 1) {
            require_code('comcode_from_html');
            $data = semihtml_to_comcode($data);
        }

        $tpl = comcode_to_tempcode($data, get_member(), false, null, $db, (either_param_integer('semihtml', 0) == 1) ? COMCODE_SEMIPARSE_MODE : COMCODE_NORMAL);
        $evaluated = $tpl->evaluate();
        $out = '';
        if ($evaluated != '') {
            if (get_param_integer('css', 0) == 1) {
                global $CSSS;
                unset($CSSS['global']);
                unset($CSSS['no_cache']);
                $out .= static_evaluate_tempcode(css_tempcode());
            }
            if (get_param_integer('javascript', 0) == 1) {
                global $JAVASCRIPTS;
                unset($JAVASCRIPTS['global']);
                unset($JAVASCRIPTS['staff']);
                $out .= static_evaluate_tempcode(javascript_tempcode());
            }
        }
        $out .= trim(trim($evaluated));

    } elseif ($from_html == 1) { // "Convert HTML/semihtml to Comcode"
        require_code('comcode_from_html');
        $out = trim(semihtml_to_comcode($data, post_param_integer('force', 0) == 1));
    }

    $box_title = get_param_string('box_title', '', INPUT_FILTER_GET_COMPLEX);

    if (($from_html != 1) && (either_param_integer('fix_bad_html', 0) == 1)) {
        require_code('xhtml');
        $new = xhtmlise_html($out, true);

        $stripped_new = preg_replace('#<!--.*-->#Us', '', preg_replace('#\s+#', '', $new));
        $stripped_old = preg_replace('#<!--.*-->#Us', '', preg_replace('#\s+#', '', $out));
        if (($box_title != '') && ($stripped_new != $stripped_old)) {
            /*
            require_code('files');
            cms_file_put_contents_safe(get_file_base() . '/a', preg_replace('#<!--.*-->#Us', '', preg_replace('#\s+#', chr(10), $new)));
            cms_file_put_contents_safe(get_file_base() . '/b', preg_replace('#<!--.*-->#Us', '', preg_replace('#\s+#', chr(10), $out)));
            */

            $out = $new . do_lang('BROKEN_XHTML_FIXED');
        } else {
            $out = $new;
        }
    }

    if (either_param_integer('reindent', 0) == 1) {
        $out = reindent_code($out, (either_param_integer('from_html', 0) != 1));
    }

    if (either_param_integer('raw_output', 0) == 0) {
        require_code('xml');

        safe_ini_set('ocproducts.xss_detect', '0');

        $box_title = get_param_string('box_title', '', INPUT_FILTER_GET_COMPLEX);
        if (($box_title != '') && ($out != '')) {
            $out = static_evaluate_tempcode(put_in_standard_box(make_string_tempcode($out), $box_title));
        }

        header('Content-Type: text/xml');
        echo '<?xml version="1.0" encoding="' . get_charset() . '"?' . '>';
        echo '<request><result>';
        echo xmlentities($out);
        echo '</result></request>';
    } else {
        safe_ini_set('ocproducts.xss_detect', '0');

        header('Content-type: text/plain; charset=' . get_charset());
        echo $out;
    }
}

/**
 * Turn a triple of emoticon parameters into some actual tempcode.
 *
 * @param  string $text Code to reindent
 * @param  boolean $is_comcode Whether the code is Comcode
 * @return string Reindented code
 */
function reindent_code($text, $is_comcode)
{
    $text = unixify_line_format($text);

    if ($is_comcode) {
        $text = str_replace('[semihtml]', '', $text);
        $text = str_replace('[/semihtml]', '', $text);
        $text = str_replace('[html]', '', $text);
        $text = str_replace('[/html]', '', $text);
    }

    $html_tags_to_indent = array(
        'div',
        'p',
        'table',
        'thead',
        'tbody',
        'tr',
        'th',
        'td',
        'ul',
        'ol',
        'li',
        'dt',
        'dl',
        'dd',
        'object',
        'embed',
        'nav',
        'main',
        'section',
        'article',
        'blockquote',
        'form',
        'header',
        'footer',
        'video',
    );
    $comcode_tags_to_indent = array(
        'surround',
        'box',
        'center',
        'left',
        'right',
        'if_in_group',
        'section',
        'big_tab',
        'tab',
        'carousel',
        'codebox',
        'code',
        'hide',
        'quote',
    );
    $regexp = '#';
    foreach ($html_tags_to_indent as $tag) {
        if ($regexp != '#') {
            $regexp .= '|';
        }
        $regexp .= '(</?' . $tag . '[^<>]*>)';
    }
    foreach ($comcode_tags_to_indent as $tag) {
        $regexp .= '|';
        $regexp .= '(\[/?' . $tag . '[^\[\]]*\])';
    }
    $regexp .= '|';
    $regexp .= '(\{\+START,[^\n\+]+\})';
    $regexp .= '|';
    $regexp .= '(\{\+END\})';
    $regexp .= '#';
    $_text = preg_split($regexp, $text, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);

    $text = '';
    $indent = 1;
    foreach ($_text as $i => $part) {
        if (trim($part) == '') {
            continue;
        }

        $is_delimiter = (preg_match($regexp, $part) != 0);

        if ($is_delimiter) {
            if (($part == '{+END}' || $part[1] == '/')) {
                $indent--;
                if ($indent == -1) {
                    $indent = 0;
                }

                $_indent = str_repeat("\t", $indent);
                $text .= $_indent . $part . "\n";
            } else {
                $_indent = str_repeat("\t", $indent);
                $text .= $_indent . $part . "\n";

                $indent++;
            }
        } else {
            $_indent = str_repeat("\t", $indent);
            $text .= $_indent . str_replace("\n", "\n" . $_indent, trim($part)) . "\n";
        }
    }

    if ($is_comcode) {
        $text = "[semihtml]\n{$text}[/semihtml]\n";
    }

    return $text;
}
