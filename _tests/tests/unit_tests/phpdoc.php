<?php /*

 Composr
 Copyright (c) ocProducts, 2004-2017

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
class phpdoc_test_set extends cms_test_case
{
    public function testForCopyAndPastedDescriptions()
    {
        $phpdoc_to_functions = array();

        $exceptions = array(
            'Output a login page.',
            'Standard code module initialisation function.',
            'Provides a hook for file synchronisation between mirrored servers.',
            'Check the given master password is valid.',
            'Evaluate a particular Tempcode directive.',
            'Evaluate a particular Tempcode symbol.',
            'Check the given master password is valid.',
            'Return parse info for parse type.',
            'Create file with unique file name, but works around compatibility issues between servers.',
            'Standard Tapatalk endpoint implementation.',
            'Standard Tapatalk endpoint test.',
            'Make sure we are doing necessary join to be able to access the given field.',
            'This is a less-revealing alternative to fatal_exit, that is used for user-errors/common-corruption-scenarios.',
            'Get suitable placeholder text.',
            'Get a preview(s) of a (group of) template(s), as a full standalone piece of HTML in Tempcode format.',
            'Run a section of health checks.',
            'Standard Commandr-fs',
            'Implementation-specific e-mail dispatcher, passed with pre-prepared/tidied e-mail component details for us to use.',
            'Standard import function.',
            'Further filter results from _all_members_who_have_enabled.',
            '@license',
            '@package',
            'Standard PHP XML parser function.',
            'Find the cache signature for the block.',
            'Standard crud_module cat getter.',
            'Actualiser to undo a certain type of punitive action.',
            'Substitution callback for \'fix_links\'.',
            'Get the filename for a resource ID. Note that filenames are unique across all folders in a filesystem.',
            'Ensure that the specified file/folder is writeable for the FTP user',
            'Helper function for usort to sort a list by string length.',
            'Read a virtual property for a member file.',
            'Do the inner call using a particular downloader method.',
            'XHTML-aware helper function',
            'Get a well formed URL equivalent to the current URL.',
            'Syndicate human-intended descriptions of activities performed to the internal wall, and external listeners.',
            'Execute the module.',
            'Find caching details for the block.',
            'Database driver class.',
            'Forum driver class.',
            'Find details of a position in the Sitemap.',
            'Get the permission page that nodes matching $page_link in this hook are tied to.',
            'Find if a page-link will be covered by this node.',
        );
        $exceptions_regexp = '#' . implode('|', array_map('preg_quote', $exceptions)) . '#';

        require_code('files2');
        $files = get_directory_contents(get_file_base(), '', IGNORE_FLOATING | IGNORE_SHIPPED_VOLATILE | IGNORE_UNSHIPPED_VOLATILE, true, true, array('php'));

        foreach ($files as $file) {
            if (preg_match('#^(tracker|_tests|sources_custom/photobucket|sources_custom/ILess|sources_custom/swift_mailer|sources_custom/spout|sources_custom/sabredav|sources_custom/aws|mobiquo/lib)/#', $file) != 0) {
                continue;
            }
            if (preg_match('#^(sources_custom/facebook/facebook.php|sources/mail_dkim\.php|sources/firephp\.php|sources_custom/twitter\.php|sources_custom/browser_detect\.php)$#', $file) != 0) {
                continue;
            }

            $c = file_get_contents(get_file_base() . '/' . $file);

            $c = preg_replace('#\n\/\*\*.*\n \*\/\n\n#Us', '', $c);

            $matches = array();
            $num_matches = preg_match_all('#\/\*\*\n\s+\* (.*)\n\s+\*\/\n(\s*)((public|protected|private) )?function &?(\w+)\(#Us', $c, $matches);
            for ($i = 0; $i < $num_matches; $i++) {
                $phpdoc = $matches[1][$i];
                $function_name = $matches[5][$i];

                $phpdoc = preg_replace('#\* @.*$#s', '', $phpdoc);

                if (preg_match($exceptions_regexp, $phpdoc) != 0) {
                    continue;
                }

                if (!isset($phpdoc_to_functions[$phpdoc])) {
                    $phpdoc_to_functions[$phpdoc] = array();
                }
                $phpdoc_to_functions[$phpdoc][] = $function_name;
            }
        }

        foreach ($phpdoc_to_functions as $phpdoc => $functions) {
            $rationalised_functions = array();
            foreach ($functions as $function_name) {
                $rationalised_function_name = ltrim($function_name, '_');
                $rationalised_functions[$rationalised_function_name] = true;
            }

            $this->assertTrue(count($rationalised_functions) == 1, 'Multiple use of phpdoc comment... ' . $phpdoc . ' (' . serialize($rationalised_functions) . ')');
        }
    }
}
