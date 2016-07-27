<?php /*

 Composr
 Copyright (c) ocProducts, 2004-2016

 You may not distribute a modified version of this file, unless it is solely as a Composr modification.
 See text/EN/licence.txt for full licencing information.

*/

/**
 * @license    http://opensource.org/licenses/cpal_1.0 Common Public Attribution License
 * @copyright  ocProducts Ltd
 * @package    composr_homesite
 */

/* Returns triple: PATH or null if critical error, null or error string if error */
function make_upgrade_get_path($from_version_dotted, $to_version_dotted)
{
    $err = null;

    require_code('version2');

    $from_version_pretty = get_version_pretty__from_dotted($from_version_dotted);
    $to_version_pretty = get_version_pretty__from_dotted($to_version_dotted);

    if (str_replace('.', '', $from_version_dotted) == '') {
        $err = 'Source version not entered correctly.';
        return array(null, $err);
    }

    if ($from_version_dotted == '..') {
        warn_exit(do_lang_tempcode('NO_PARAMETER_SENT', 'from version'));
    }
    if ($to_version_dotted == '..') {
        warn_exit(do_lang_tempcode('NO_PARAMETER_SENT', 'from version'));
    }

    if ($from_version_dotted == $to_version_dotted) {
        $err = 'Put in the version number you are upgrading <strong>from</strong>, not to. Then a specialised upgrade file will be generated for you.';
        return array(null, $err);
    }

    if (php_function_allowed('set_time_limit')) {
        @set_time_limit(0);
    }
    require_code('tar');
    require_code('m_zip');

    // Find out path/filenames for the upgrade file we're making
    $filename = $from_version_dotted . '-' . $to_version_dotted . '.cms';
    $tar_path = dirname(__FILE__) . '/tars/' . $filename;
    $wip_path = dirname(__FILE__) . '/tar_build/' . $filename;

    // Find out paths for the directories holding untarred full manual installers
    $old_base_path = dirname(__FILE__) . '/full/' . $from_version_dotted;
    $new_base_path = dirname(__FILE__) . '/full/' . $to_version_dotted;

    // Find corresponding download rows
    $old_download_row = ($from_version_dotted == '') ? null : find_download($from_version_pretty);
    if (is_null($old_download_row)) {
        $err = escape_html('Version ' . $from_version_pretty . ' is not recognised');
        return array(null, $err);
    }
    $new_download_row = find_download($to_version_pretty);
    if (is_null($new_download_row)) {
        return array(null, escape_html('Could not find version ' . $to_version_pretty . ' in the download database'));
    }
    $mtime = $new_download_row['add_date'];
    if (!is_null($new_download_row['edit_date'])) {
        $mtime = $new_download_row['edit_date'];
    }
    $mtime_disk = filemtime(get_file_base() . '/' . rawurldecode($new_download_row['url']));
    if ($mtime_disk > $mtime) {
        $mtime = $mtime_disk;
    }

    // Exists already
    if (file_exists($tar_path)) {
        if (filemtime($tar_path) > $mtime) {
            return array($tar_path, $err);
        } else { // Outdated
            unlink($tar_path);
        }
    }

    // Stop a race-condition
    if ((file_exists($old_base_path)) || (file_exists($new_base_path)) || (file_exists($wip_path))) {
        return array(null, 'An upgrade is currently being generated by another user. Please try again in a minute.');
    }

    // Unzip old
    if (!is_null($old_download_row)) {
        @mkdir($old_base_path, 0777);
        if (!url_is_local($old_download_row['url'])) {
            return array(null, escape_html('Non-local URL found (' . $old_download_row['url'] . '). Unexpected.'));
        }
        recursive_unzip(get_file_base() . '/' . rawurldecode($old_download_row['url']), $old_base_path);
    }

    // Unzip new
    @mkdir($new_base_path, 0777);
    if (!url_is_local($new_download_row['url'])) {
        return array(null, escape_html('Non-local URL found (' . $new_download_row['url'] . '). Unexpected.'));
    }
    recursive_unzip(get_file_base() . '/' . rawurldecode($new_download_row['url']), $new_base_path);

    // Make actual upgrader
    require_code('files2');
    @mkdir($wip_path, 0777);
    make_upgrader_do_dir($wip_path, $new_base_path, $old_base_path);
    @copy($old_base_path . '/data/files.dat', $wip_path . '/data/files_previous.dat');
    $log_file = fopen(dirname(__FILE__) . '/tarring.log', GOOGLE_APPENGINE ? 'wb' : 'wt');
    $tar_handle = tar_open($tar_path . '.new', 'wb');
    tar_add_folder($tar_handle, $log_file, $wip_path, null, '', null, null, false, true);
    tar_close($tar_handle);
    fclose($log_file);
    @rename($tar_path . '.new', $tar_path);

    // Clean up
    require_code('files');
    @deldir_contents($new_base_path);
    @deldir_contents($old_base_path);
    @deldir_contents($wip_path);
    @rmdir($new_base_path);
    @rmdir($old_base_path);
    @rmdir($wip_path);

    return array($tar_path, $err);
}

function find_download($version_pretty)
{
    global $DOWNLOAD_ROWS;
    load_download_rows();

    $download_row = null;
    foreach ($DOWNLOAD_ROWS as $_download_row) {
        // When debugging, check downloads are validated
        if (($_download_row['nice_title'] == 'Composr Version ' . $version_pretty . ' (manual)') || ($_download_row['nice_title'] == 'Composr Version ' . $version_pretty . ' (bleeding-edge, manual)')) {
            $download_row = $_download_row;
            break;
        }
    }

    if ((is_null($download_row)) && (substr_count($version_pretty, '.') < 2)) {
        return find_download($version_pretty . '.0');
    }

    return $download_row;
}

function load_download_rows()
{
    global $DOWNLOAD_ROWS;
    if (!isset($DOWNLOAD_ROWS)) {
        if (get_param_integer('test_mode', 0) == 1) { // Test data
            $DOWNLOAD_ROWS = array(
                array('id' => 20, 'nice_title' => 'Composr Version 3.0', 'add_date' => time() - 60 * 60 * 8, 'edit_date' => null, 'url' => 'uploads/downloads/test.zip', 'nice_description' => '[Test message] This is 3. Yo peeps. 3.1 is the biz.'),
                array('id' => 30, 'nice_title' => 'Composr Version 3.1', 'add_date' => time() - 60 * 60 * 5, 'edit_date' => null, 'url' => 'uploads/downloads/test.zip', 'nice_description' => '[Test message] This is 3.1.1. 3.1.1 is out dudes.'),
                array('id' => 35, 'nice_title' => 'Composr Version 3.1.1', 'add_date' => time() - 60 * 60 * 5, 'edit_date' => null, 'url' => 'uploads/downloads/test.zip', 'nice_description' => '[Test message] This is 3.1.1. 3.2 is out dudes.'),
                array('id' => 40, 'nice_title' => 'Composr Version 3.2 beta1', 'add_date' => time() - 60 * 60 * 4, 'edit_date' => null, 'url' => 'uploads/downloads/test.zip', 'nice_description' => '[Test message] This is 3.2 beta1. 3.2 beta2 is out.'),
                array('id' => 50, 'nice_title' => 'Composr Version 3.2', 'add_date' => time() - 60 * 60 * 3, 'edit_date' => null, 'url' => 'uploads/downloads/test.zip', 'nice_description' => '[Test message] This is 3.2. 4 is out.'),
                array('id' => 60, 'nice_title' => 'Composr Version 4.0', 'add_date' => time() - 60 * 60 * 1, 'edit_date' => null, 'url' => 'uploads/downloads/test.zip', 'nice_description' => '[Test message] This is the 4 and you can find bug reports somewhere.'),
            );
        } else {
            $DOWNLOAD_ROWS = $GLOBALS['SITE_DB']->query_select('download_downloads', array('*'), array('validated' => 1), 'ORDER BY add_date');
            foreach ($DOWNLOAD_ROWS as $i => $row) {
                $DOWNLOAD_ROWS[$i]['nice_title'] = get_translated_text($row['name']);
                $DOWNLOAD_ROWS[$i]['nice_description'] = get_translated_text($row['description']);
            }
        }
    }
}

function recursive_unzip($zip_path, $unzip_path)
{
    $zip_handle = zip_open($zip_path);
    while (($entry = (zip_read($zip_handle))) !== false) {
        $entry_name = zip_entry_name($entry);
        if (substr($entry_name, -1) != '/') {
            $_entry = zip_entry_open($zip_handle, $entry);
            if ($_entry !== false) {
                @mkdir(dirname($unzip_path . '/' . $entry_name), 0777, true);
                $out_file = fopen($unzip_path . '/' . $entry_name, 'wb');
                while (true) {
                    $it = zip_entry_read($entry, 1024);
                    if (($it === false) || ($it == '')) {
                        break;
                    }
                    fwrite($out_file, $it);
                }
                zip_entry_close($entry);
                fclose($out_file);
            }
        }
    }
    zip_close($zip_handle);
}

function make_upgrader_do_dir($build_path, $new_base_path, $old_base_path, $dir = '', $pretend_dir = '')
{
    require_code('files');

    $dh = opendir($new_base_path . '/' . $dir);
    while (($file = readdir($dh)) !== false) {
        $is_dir = is_dir($new_base_path . '/' . $dir . $file);

        if (should_ignore_file($pretend_dir . $file, IGNORE_NONBUNDLED_SCATTERED | IGNORE_CUSTOM_DIR_SUPPLIED_CONTENTS | IGNORE_CUSTOM_DIR_GROWN_CONTENTS | IGNORE_CUSTOM_ZONES | IGNORE_CUSTOM_THEMES | IGNORE_NON_EN_SCATTERED_LANGS | IGNORE_BUNDLED_VOLATILE | IGNORE_BUNDLED_UNSHIPPED_VOLATILE, 0)) {
            continue;
        }

        if ($is_dir) {
            @mkdir($build_path . '/' . $pretend_dir . $file, 0777);
            make_upgrader_do_dir($build_path, $new_base_path, $old_base_path, $dir . $file . '/', $pretend_dir . $file . '/');

            // If it's empty still, delete it
            @rmdir($build_path . '/' . $pretend_dir . $file);
        } else {
            $contents = file_get_contents($new_base_path . '/' . $dir . $file);
            if ((strpos($dir, '/addon_registry') !== false) || (!file_exists($old_base_path . '/' . $pretend_dir . '/' . $file)) || (unixify_line_format($contents) != unixify_line_format(file_get_contents($old_base_path . '/' . $pretend_dir . '/' . $file)))) {
                copy($new_base_path . '/' . $dir . $file, $build_path . '/' . $pretend_dir . $file);
                touch($build_path . '/' . $pretend_dir . $file, filemtime($new_base_path . '/' . $dir . $file));
            }
        }
    }
}
