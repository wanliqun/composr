<?php /*

 Composr
 Copyright (c) ocProducts, 2004-2015

 See text/EN/licence.txt for full licencing information.


 NOTE TO PROGRAMMERS:
   Do not edit this file. If you need to make changes, save your changed file to the appropriate *_custom folder
   **** If you ignore this advice, then your website upgrades (e.g. for bug fixes) will likely kill your changes ****

*/

/*EXTRA FUNCTIONS: xml_.**/

/**
 * @license    http://opensource.org/licenses/cpal_1.0 Common Public Attribution License
 * @copyright  ocProducts Ltd
 * @package    breadcrumbs
 */

/**
 * Load all breadcrumb substitutions and return them.
 *
 * @param  array $segments The default breadcrumb segments
 * @return array The adjusted breadcrumb segments
 */
function load_breadcrumb_substitutions($segments)
{
    // Works by going through in left-to-right order, doing multiple sweeps until no more substitutions can be made.
    // Only one substitution per rule is allowed.

    static $substitutions = null;
    if ($substitutions === null) {
        $substitutions = persistent_cache_get('BREADCRUMBS_CACHE');
    }
    if ($substitutions === null) {
        $data = @file_get_contents(get_custom_file_base() . '/data_custom/xml_config/breadcrumbs.xml');
        if ($data === false) {
            $data = @file_get_contents(get_file_base() . '/data/xml_config/breadcrumbs.xml');
        }
        if ($data === false) {
            $data = '';
        }

        if (trim($data) == '') {
            persistent_cache_set('BREADCRUMBS_CACHE', array());

            return $segments;
        }

        $loader = new Breadcrumb_substitution_loader();
        $loader->go($data);
        $substitutions = $loader->substitutions;

        persistent_cache_set('BREADCRUMBS_CACHE', $substitutions);
    }

    $segments_new = array();
    $done_one = false;

    foreach ($segments as $i => $segment) {
        if (is_object($segment[1])) {
            $segment[1] = $segment[1]->evaluate();
        }

        if (!$done_one && $segment[0] != '') {
            list($zone, $attributes, $hash) = page_link_decode($segment[0]);

            foreach ($substitutions as $j => $details) {
                if ($details !== null) {
                    if (($details[0][0][0] == 'site') && ($zone == '') || ($details[0][0][0] == '') && ($zone == 'site')) {
                        // Special handling, we don't want single public zone option (collapse_user_zones) to be too "smart" and apply a rule intended for when that option is off
                        continue;
                    }

                    if (isset($attributes['page']) && match_key_match($details[0], false, $attributes, $zone, $attributes['page'])) {
                        if ($details[1] === null || $details[1] == $segment[1]) {
                            if (!$done_one) {
                                $segments_new = $details[2]; // New stem found
                                $done_one = true;
                            }

                            $substitutions[$j] = null; // Stop loops when recursing
                        }
                    }
                }
            }
        }

        $segments_new[] = $segment;
    }

    if ($done_one) {
        return load_breadcrumb_substitutions($segments_new); // Try a new sweep
    }

    return $segments_new;
}

/**
 * Breadcrumb composition class.
 *
 * @package    breadcrumbs
 */
class Breadcrumb_substitution_loader
{
    // Used during parsing
    private $tag_stack, $attribute_stack, $text_so_far;
    private $substitution_current_match_key, $substitution_current_label, $substitution_current_links;
    public $substitutions; // output

    /**
     * Run the loader, to load up field-restrictions from the XML file.
     *
     * @param  string $data The breadcrumb XML data
     */
    public function go($data)
    {
        $this->tag_stack = array();
        $this->attribute_stack = array();

        $this->substitution_current_match_key = null;
        $this->substitution_current_label = null;
        $this->substitution_current_links = array();

        $this->substitutions = array();

        // Create and setup our parser
        if (function_exists('libxml_disable_entity_loader')) {
            libxml_disable_entity_loader();
        }
        $xml_parser = @xml_parser_create();
        if ($xml_parser === false) {
            return; // PHP5 default build on windows comes with this function disabled, so we need to be able to escape on error
        }
        xml_set_object($xml_parser, $this);
        @xml_parser_set_option($xml_parser, XML_OPTION_TARGET_ENCODING, get_charset());
        @xml_parser_set_option($xml_parser, XML_OPTION_CASE_FOLDING, 0);
        xml_set_element_handler($xml_parser, 'startElement', 'endElement');
        xml_set_character_data_handler($xml_parser, 'startText');

        // Run the parser
        if (@xml_parse($xml_parser, $data, true) == 0) {
            attach_message('breadcrumbs.xml: ' . xml_error_string(xml_get_error_code($xml_parser)), 'warn');
            return;
        }
        @xml_parser_free($xml_parser);
    }

    /**
     * Standard PHP XML parser function.
     *
     * @param  object $parser The parser object (same as 'this')
     * @param  string $tag The name of the element found
     * @param  array $_attributes Array of attributes of the element
     */
    public function startElement($parser, $tag, $_attributes)
    {
        array_push($this->tag_stack, $tag);
        $tag_attributes = array();
        foreach ($_attributes as $key => $val) {
            $tag_attributes[$key] = $val;
        }
        array_push($this->attribute_stack, $tag_attributes);

        switch ($tag) {
            case 'substitution':
                $_substitution_current_match_key = isset($tag_attributes['match_key']) ? $tag_attributes['match_key'] : '_WILD:_WILD';
                //$this->substitution_current_match_key = page_link_decode($_substitution_current_match_key); match_key_match doesn't actually want it like this
                $this->substitution_current_match_key = array(explode(':', $_substitution_current_match_key));
                $this->substitution_current_label = isset($tag_attributes['label']) ? $tag_attributes['label'] : null;
                $this->substitution_current_links = array();
                break;

            case 'link':
                break;
        }
        $this->text_so_far = '';
    }

    /**
     * Standard PHP XML parser function.
     *
     * @param  object $parser The parser object (same as 'this')
     * @param  string $data The text
     */
    public function startText($parser, $data)
    {
        $this->text_so_far .= $data;
    }

    /**
     * Standard PHP XML parser function.
     *
     * @param  object $parser The parser object (same as 'this')
     */
    public function endElement($parser)
    {
        $tag = array_pop($this->tag_stack);
        $tag_attributes = array_pop($this->attribute_stack);

        switch ($tag) {
            case 'substitution':
                $this->substitutions[] = array(
                    $this->substitution_current_match_key,
                    $this->substitution_current_label,
                    $this->substitution_current_links
                );
                break;

            case 'link':
                $page_link = trim(str_replace('\n', "\n", $this->text_so_far));
                $this->substitution_current_links[] = array(
                    $page_link,
                    isset($tag_attributes['label']) ? static_evaluate_tempcode(comcode_to_tempcode($tag_attributes['label'])) : new Tempcode()
                );
                break;
        }
    }
}
