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
 * @package    core
 */

/**
 * Take a URL and process it to make a hard include. We'll get the HTML and we'll also load up some global stuff for 'do_header' to use.
 *
 * @param  URLPATH $url The URL that we're operating on.
 * @param  URLPATH $operation_base_url We open up linked URLs under this recursively.
 * @return string The cleaned up contents at the URL, set up for the recursive integrator usage.
 */
function reprocess_url($url, $operation_base_url)
{
    if (url_is_local($url)) {
        return '';
    }
    $trail_end = strrpos($url, '/');
    if ($trail_end !== false) {
        $url_base = substr($url, 0, $trail_end);
    }

    $val = mixed();

    // Cookie relaying from client through to server
    $url_bits = @parse_url($url) or warn_exit(do_lang_tempcode('HTTP_DOWNLOAD_NO_SERVER', $url), false, true);
    $url_bits_2 = parse_url(get_base_url());
    $cookies_relayed = null;
    if (!array_key_exists('host', $url_bits)) {
        $url_bits['host'] = 'localhost';
    }
    if (!array_key_exists('host', $url_bits_2)) {
        $url_bits_2['host'] = 'localhost';
    }
    if ($url_bits['host'] == $url_bits_2['host']) {
        $cookies_relayed = array();
        foreach ($_COOKIE as $key => $val) {
            if (is_array($val)) {
                $cookies_relayed[$key] = array();
                foreach ($val as $_val) {
                    $cookies_relayed[$key][] = $_val;
                }
            } else {
                $cookies_relayed[$key] = $val;
            }
        }
    }

    // Download the document
    $ua = cms_srv('HTTP_USER_AGENT');
    if ($ua == '') {
        $ua = 'Composr-integrator';
    }
    $accept = cms_srv('HTTP_ACCEPT');
    if ($accept == '') {
        $accept = null;
    }
    $accept_charset = cms_srv('HTTP_ACCEPT_CHARSET');
    if ($accept_charset == '') {
        $accept_charset = null;
    }
    $accept_language = cms_srv('HTTP_ACCEPT_LANGUAGE');
    if ($accept_language == '') {
        $accept_language = null;
    }
    $post_relayed = null;
    if (count($_POST) != 0) {
        $post_relayed = array();
        foreach ($_POST as $key => $val) {
            if (is_array($val)) {
                $post_relayed[$key] = array();
                foreach ($val as $_val) {
                    $post_relayed[$key] = $val;
                }
            } else {
                $post_relayed[$key] = $val;
            }
        }
    }
    require_code('character_sets');
    $http_result = cms_http_request($url, array('ua' => $ua, 'post_params' => $post_relayed, 'cookies' => $cookies_relayed, 'accept' => $accept, 'accept_charset' => $accept_charset, 'accept_language' => $accept_language));
    $document = convert_to_internal_encoding($http_result->data, $http_result->charset);

    if (($http_result->download_mime_type != 'text/html') && ($http_result->download_mime_type != 'application/xhtml+xml')) {
        header('Location: ' . str_replace("\r", '', str_replace("\n", '', $url)));
        return '';
    }

    // Were we asked to set any cookies?
    if ($url_bits['host'] == $url_bits_2['host']) {
        foreach ($http_result->new_cookies as $cookie_key => $cookie_parts) {
            setcookie(
                $cookie_key,
                $cookie_parts['value'],
                array_key_exists('expires', $cookie_parts) ? strtotime($cookie_parts['expires']) : 0,
                array_key_exists('path', $cookie_parts) ? $cookie_parts['path'] : '',
                array_key_exists('domain', $cookie_parts) ? $cookie_parts['domain'] : ''
            );
        }
    }

    // Sort out title
    $matches = array();
    if (preg_match('#<\s*title[^>]*>(.*)<\s*/\s*title\s*>#is', $document, $matches) != 0) {
        $title = str_replace('&bull;', '-', str_replace('&ndash;', '-', str_replace('&mdash;', '-', @html_entity_decode($matches[1], ENT_QUOTES, get_charset()))));
        set_short_title(trim($title));
        get_screen_title(trim($title), false);
    }

    // Better base?
    $matches = array();
    if (preg_match('#<\s*base\s+href\s*=\s*["\']?(.*)["\']?\s*/?\s*>#is', $document, $matches) != 0) {
        $url_base = trim(@html_entity_decode($matches[1], ENT_QUOTES, get_charset()));
    }

    // Sort out body
    if (preg_match('#<\s*body[^>]*>(.*)<\s*/\s*body\s*>#is', $document, $matches) != 0) {
        $body = '<div>' . $matches[1] . '</div>';
    } else {
        $body = '<div>' . $document . '</div>';
    }

    // Link filtering, so as to make non-external/non-new-window hyperlinks link through the Composr module
    $_self_url = build_url(array('page' => '_SELF'), '_SELF', null, false, true);
    $self_url = $_self_url->evaluate();
    $expressions = array(
        '(src)="([^"]*)"', '(src)=\'([^\'])*\'',
        '(href)="([^"]*)"', '(href)=\'([^\'])*\'',
        '(data)="([^"]*)"', '(data)=\'([^\']*)\'',
        '(action)="([^"]*)"', '(action)=\'([^\']*)\'',
    );
    foreach ($expressions as $expression) {
        $all_matches = array();
        $count = preg_match_all('#(<[^>]*)' . $expression . '([^>]*>)#i', $body, $all_matches, PREG_OFFSET_CAPTURE);
        for ($i = $count - 1; $i >= 0 ; $i--) {
            $m_to_replace = $all_matches[0][$i][0];
            $offset = $all_matches[0][$i][1];
            $offset_end = $offset + strlen($m_to_replace);

            $m_type = trim(@html_entity_decode($all_matches[2][$i][0], ENT_QUOTES, get_charset()));
            $m_url = trim(@html_entity_decode($all_matches[3][$i][0], ENT_QUOTES, get_charset()));
            if (url_is_local($m_url)) {
                $m_url = qualify_url($m_url, $url_base);
            }
            $is_non_local = substr($m_url, 0, strlen($operation_base_url)) != $operation_base_url;
            if (($m_type == 'src') || ($m_type == 'data') || ($is_non_local)) {
                $new_url = $m_url;
            } else {
                $new_url = $self_url . '&url=' . rawurlencode($m_url);
            }

            if ((strtolower($m_type) == 'action') && (strpos($new_url, '?') !== false)) {
                if (preg_match('#<form.*\smethod="get".*>#i', $m_to_replace) != 0) {
                    $extra_get_fields = array();
                    parse_str(preg_replace('#^.*\?#', '', $new_url), $extra_get_fields);

                    $_extra_get_fields = '';
                    foreach ($extra_get_fields as $get_key => $get_val) {
                        $_extra_get_fields .= '<input type="hidden" name="' . escape_html($get_key) . '" value="' . escape_html($get_val) . '" />';
                    }
                    $body = substr($body, 0, $offset_end) . $_extra_get_fields . substr($body, $offset_end);
                    $new_url = preg_replace('#\?.*$#', '', $new_url);
                }
            }

            $m_replace_with = $all_matches[1][$i][0] . $m_type . '="' . escape_html($new_url) . '"' . $all_matches[4][$i][0];
            $body = substr($body, 0, $offset) . $m_replace_with . substr($body, $offset_end);
        }
    }

    // Moving of CSS sheet imports, etc, into Composr's head section
    if (preg_match('#<head[^<>]*>(.*)</head>#is', $document, $matches) != 0) {
        $head = $matches[1];

        // meta
        global $SEO_KEYWORDS, $SEO_DESCRIPTION;
        $count = preg_match_all('#\<\s*meta[^\>]*name=["\']([^"\']*)["\'][^\>]*content="([^"]*)"[^\>]*/?\s*>#i', $head, $all_matches);
        if ($count == 0) {
            $count = preg_match_all('#\<\s*meta\s+[^\>]*name=["\']([^"\']*)["\']\s+[^\>]*content=\'([^\']*)\'[^\>]*/?\s*>#i', $head, $all_matches);
        }
        if ($count != 0) {
            for ($i = 0; $i < count($all_matches[0]); $i++) {
                $m_name = trim(@html_entity_decode($all_matches[1][$i], ENT_QUOTES, get_charset()));
                $m_content = trim(@html_entity_decode($all_matches[2][$i], ENT_QUOTES, get_charset()));
                if ($m_name == 'description') {
                    $SEO_DESCRIPTION = $m_content;
                } elseif ($m_name == 'keywords') {
                    $SEO_KEYWORDS = explode(',', $m_content);
                }
            }
        }

        // Stuff to copy
        $head_patterns = array('#<\s*script.*<\s*/\s*script\s*>#isU', '#<\s*link[^<>]*>#isU', '#<\s*style.*<\s*/\s*style\s*>#isU');
        foreach ($head_patterns as $pattern) {
            $num_matches = preg_match_all($pattern, $head, $matches);
            for ($i = 0; $i < $num_matches; $i++) {
                $x = $matches[0][$i];
                $match_x = array();
                if (preg_match('#\s(src|href)=["\']([^"\']+)["\']#i', $x, $match_x) != 0) {
                    if (url_is_local($match_x[1])) {
                        $url_new = qualify_url($match_x[2], $url_base);
                        $x = str_replace($match_x[0], str_replace($match_x[2], $url_new, $match_x[0]), $x);
                    }
                }
                attach_to_screen_header($x);
            }
        }
    }

    return $body;
}
