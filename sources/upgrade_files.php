<?php /*

 Composr
 Copyright (c) ocProducts, 2004-2018

 See text/EN/licence.txt for full licensing information.


 NOTE TO PROGRAMMERS:
   Do not edit this file. If you need to make changes, save your changed file to the appropriate *_custom folder
   **** If you ignore this advice, then your website upgrades (e.g. for bug fixes) will likely kill your changes ****

*/

/**
 * @license    http://opensource.org/licenses/cpal_1.0 Common Public Attribution License
 * @copyright  ocProducts Ltd
 * @package    core_upgrader
 */

/**
 * Do upgrader screen: file upgrade UI.
 *
 * @ignore
 * @return string Output messages
 */
function upgrader_file_upgrade_screen()
{
    $out = '';

    require_code('version2');
    $personal_upgrader_url = 'http://compo.sr/uploads/website_specific/compo.sr/scripts/build_personal_upgrader.php?from=' . urlencode(get_version_dotted());
    $hooks = find_all_hooks('systems', 'addon_registry');
    foreach (array_keys($hooks) as $hook) {
        if (is_file(get_file_base() . '/sources/hooks/systems/addon_registry/' . $hook . '.php')) {
            $personal_upgrader_url .= '&addon_' . $hook . '=1';
        }
    }

    if (get_param_string('tar_url', '', INPUT_FILTER_URL_GENERAL) == '') {
        $out .= do_lang('UPGRADER_FILE_UPGRADE_INFO');
    }
    $out .= do_lang('UPGRADER_FILE_UPGRADE_INFO_MANUAL', escape_html($personal_upgrader_url));
    $out .= '<form title="' . do_lang('PROCEED') . '" enctype="multipart/form-data" action="upgrader.php?type=_file_upgrade" method="post">' . post_fields_relay();
    $out .= '<p><label for="url">' . do_lang('URL') . '</label> <input type="text" id="url" name="url" size="80" value="' . escape_html(base64_decode(get_param_string('tar_url', '', INPUT_FILTER_URL_GENERAL))) . '" /></p>';
    $out .= '<p><label for="dry_run"><input type="checkbox" id="dry_run" name="dry_run" value="1" /> ' . do_lang('UPGRADER_DRY_RUN') . '</label></p>';
    if ((get_local_hostname() == 'compo.sr') || ($GLOBALS['DEV_MODE'])) { // for ocProducts to use on own site, for testing
        $out .= '<p><label for="upload">' . do_lang('ALT_FIELD', do_lang('UPLOAD')) . '</label> <input type="file" id="upload" name="upload" /></p>';
        $out .= '<script ' . csp_nonce_html() . '>var url=document.getElementById(\'url\'); url.addEventListener(\'change\', function() { document.getElementById(\'upload\').disabled=url.value!=\'\'; });</script>';
    }
    $out .= '<p><input class="buttons--proceed button-screen" type="submit" value="' . do_lang('PROCEED') . '" /></p>';
    $out .= '</form>';

    return $out;
}

/**
 * Do upgrader screen: file upgrade actualiser.
 *
 * @ignore
 * @return string Output messages
 */
function _upgrader_file_upgrade_screen()
{
    $out = '';

    // Dry run?
    $dry_run = (post_param_integer('dry_run', 0) == 1);
    if ($dry_run) {
        $out .= '<p>' . do_lang('UPGRADER_DOING_DRY_RUN') . '</p>';
    }

    // Turn off limits, this may be exhaustive
    if (php_function_allowed('set_time_limit')) {
        @set_time_limit(0);
    }
    disable_php_memory_limit();

    // Download file
    require_code('tar');
    $local_temp_path = false;
    if ((post_param_string('url', '', INPUT_FILTER_URL_GENERAL) == '') && ((get_local_hostname() == 'compo.sr') || ($GLOBALS['DEV_MODE']))) {
        $temp_path = $_FILES['upload']['tmp_name'];
    } else {
        if (post_param_string('url', '', INPUT_FILTER_URL_GENERAL) == '') {
            warn_exit(do_lang_tempcode('IMPROPERLY_FILLED_IN'));
        }

        $url = post_param_string('url', false, INPUT_FILTER_URL_GENERAL);
        if (substr($url, 0, strlen(get_base_url() . '/')) == get_base_url() . '/') {
            $local_temp_path = true;
            $temp_path = get_custom_file_base() . '/' . rawurldecode(substr($url, strlen(get_base_url() . '/')));
            if (!is_file($temp_path)) {
                warn_exit(do_lang_tempcode('MISSING_RESOURCE'));
            }
        } else {
            $temp_path = cms_tempnam();
            $myfile = fopen($temp_path, 'wb');
            http_get_contents($url, array('write_to_file' => $myfile));
            fclose($myfile);
        }
    }

    // We do support using a .zip (e.g. manual installer package), but we need to convert it
    if (substr(strtolower($temp_path), -4) == '.zip') {
        require_code('tar2');
        $temp_path_new = convert_zip_to_tar($temp_path);
        @unlink($temp_path);
        rename($temp_path_new, $temp_path);
        fix_permissions($temp_path);
    }

    // Open up TAR
    $upgrade_resource = tar_open($temp_path, 'rb');
    //tar_extract_to_folder($upgrade_resource, '', true);
    $directory = tar_get_directory($upgrade_resource); // Uses up to around 5MB of RAM

    // Hopefully $popup_simple_extract will be true (i.e. suEXEC mode), as it is safer
    $popup_simple_extract = (_ftp_info() === false);
    if ($popup_simple_extract) {
        $data = array('todo' => array());
    } else {
        $out .= '<p>' . do_lang('EXTRACTING_MESSAGE') . '</p>';
    }

    // Find addons
    $addon_contents = array();
    foreach ($directory as $upgrade_file2) {
        // See if we can find an addon registry file in our upgrade file
        if ((strpos($upgrade_file2['path'], '/addon_registry/') !== false) && (substr($upgrade_file2['path'], -4) == '.php')) {
            $file_data = tar_get_file($upgrade_resource, $upgrade_file2['path']);
            $addon_contents[basename($upgrade_file2['path'], '.php')] = $file_data['data'];
        }
    }
    $files_for_tar_updating = array();

    // Process files
    $i = 0;
    $cnt = count($directory);
    foreach ($directory as $offset => $upgrade_file) {
        $i++;
        $out .= '<!-- Looking at ' . escape_html($upgrade_file['path']) . ' (' . strval($i) . ' / ' . strval($cnt) . ') -->';

        // Skip over these, from manual installer package (which may be used for an upgrade)
        if ($upgrade_file['path'] == '_config.php') {
            continue;
        }
        if ($upgrade_file['path'] == 'install.php') {
            continue;
        }
        if ($upgrade_file['path'] == 'install.sql' || $upgrade_file['path'] == '_config.php.template') {
            continue;
        }

        if (!$popup_simple_extract) {
            // See if we can skip the file, if the on-disk version is identical?
            if ((file_exists(get_file_base() . '/' . $upgrade_file['path'])) && (filesize(get_file_base() . '/' . $upgrade_file['path']) == $upgrade_file['size'])) {
                $tar_data = tar_get_file($upgrade_resource, $upgrade_file['path']);
                if (file_get_contents(get_file_base() . '/' . $upgrade_file['path']) == $tar_data['data']) {
                    $out .= do_lang('UPGRADER_SKIPPING_MESSAGE', escape_html($upgrade_file['path'])) . '<br />';
                    continue;
                }
            }
        }

        // What kind of file did we find?
        if ((strpos($upgrade_file['path'], '/addon_registry/') !== false) && ((file_exists(get_file_base() . '/' . $upgrade_file['path'])) || (strpos($upgrade_file['path'], '/core_') !== false))) {
            // Addon registry file, for installed addon...

            if (substr($upgrade_file['path'], -1) != '/') {
                if ($popup_simple_extract) {
                    $data['todo'][] = array($upgrade_file['path'], $upgrade_file['mtime'], $offset + 512, $upgrade_file['size'], ($upgrade_file['mode'] & 0002) != 0);
                } else {
                    $file_data = tar_get_file($upgrade_resource, $upgrade_file['path']);
                    if (!$dry_run) {
                        afm_make_file($upgrade_file['path'], $file_data['data'], ($file_data['mode'] & 0002) != 0);
                    }
                    $out .= do_lang('UPGRADER_EXTRACTING_MESSAGE', escape_html($upgrade_file['path'])) . '<br />';
                }
            }
        } else {
            // Some other file...

            $found = null;
            if (substr($upgrade_file['path'], -1) != '/') {
                foreach ($addon_contents as $addon_name => $addon_data) {
                    // See if this is the addon for the file
                    $addon_file_path = $upgrade_file['path'];
                    if (strpos($addon_data, '\'' . addslashes($addon_file_path) . '\'') !== false) {
                        $found = $addon_name;
                        break;
                    }
                }
            }

            // Install if it's a file in an addon we have installed or for a core addon
            //  (if we couldn't find the addon for it we have to assume a corrupt upgrade TAR and must skip the file)
            if (($found !== null) && ((file_exists(get_file_base() . '/sources/hooks/systems/addon_registry/' . $found . '.php')) || (substr($found, 0, 5) == 'core_'))) {
                if (substr($upgrade_file['path'], -1) == '/') {
                    if (!$dry_run) {
                        afm_make_directory($upgrade_file['path'], false, true);
                    }
                } else {
                    if ($popup_simple_extract) {
                        $data['todo'][] = array($upgrade_file['path'], $upgrade_file['mtime'], $offset + 512, $upgrade_file['size'], ($upgrade_file['mode'] & 0002) != 0);
                    } else {
                        $file_data = tar_get_file($upgrade_resource, $upgrade_file['path']);
                        if (!$dry_run) {
                            if (!file_exists(get_file_base() . '/' . dirname($upgrade_file['path']))) {
                                afm_make_directory(dirname($upgrade_file['path']), false, true);
                            }
                            afm_make_file($upgrade_file['path'], $file_data['data'], ($file_data['mode'] & 0002) != 0);
                        }

                        $out .= do_lang('UPGRADER_EXTRACTING_MESSAGE', escape_html($upgrade_file['path'])) . '<br />';
                    }
                }
            }

            // Record to copy it into our archived addon so that addon is kept up-to-date
            if (substr($upgrade_file['path'], -1) != '/') {
                if (($found !== null) && (file_exists(get_file_base() . '/imports/addons/' . $found . '.tar'))) {
                    $files_for_tar_updating[$found][$upgrade_file['path']] = array($upgrade_file['mode'], $upgrade_file['mtime']);
                }
            }
        }
    }

    // Copy it into our archived addon so that addon is kept up-to-date
    foreach ($files_for_tar_updating as $found => $files) {
        $old_addon_file = tar_open(get_file_base() . '/imports/addons/' . $found . '.tar', 'rb');
        $directory2 = tar_get_directory($old_addon_file, true);
        if ($directory2 !== null) {
            // New version of TAR file
            $new_addon_file = tar_open(get_file_base() . '/imports/addons/' . $found . '.new.tar', 'wb');

            // Add files from old TAR file, except ones we are replacing
            foreach ($directory2 as $d) {
                if (array_key_exists($d['path'], $files)) {
                    continue;
                }

                $file_data = tar_get_file($old_addon_file, $d['path']);

                $file_data['data'] = preg_replace('#^version=.*#m', 'version=(version-synched)', $file_data['data']);

                tar_add_file($new_addon_file, $d['path'], $file_data['data'], $d['mode'], $d['mtime']);
            }
            tar_close($old_addon_file);

            foreach ($files as $file_to_update => $_file_to_update) {
                list($file_to_update_mode, $file_to_update_mtime) = $_file_to_update;

                $file_data = tar_get_file($upgrade_resource, $file_to_update);

                tar_add_file($new_addon_file, $file_to_update, $file_data['data'], $upgrade_file['mode'], $upgrade_file['mtime']);

                $out .= do_lang('UPGRADER_PACKING_MESSAGE', escape_html($file_to_update)) . '<br />';
            }

            tar_close($new_addon_file);

            if (!$dry_run) {
                unlink(get_file_base() . '/imports/addons/' . $found . '.tar');
                rename(get_file_base() . '/imports/addons/' . $found . '.new.tar', get_file_base() . '/imports/addons/' . $found . '.tar');
            } else {
                unlink(get_file_base() . '/imports/addons/' . $found . '.new.tar');
            }
            sync_file(get_file_base() . '/imports/addons/' . $found . '.tar');
        }
    }

    tar_close($upgrade_resource);

    // Do extraction within iframe, if possible
    if ($popup_simple_extract) {
        @unlink(get_custom_file_base() . '/data_custom/upgrader.cms.tmp');
        @unlink(get_custom_file_base() . '/data_custom/upgrader.tmp');
        if (!$local_temp_path) {
            $test = @copy($temp_path, get_custom_file_base() . '/data_custom/upgrader.cms.tmp');
            if ($test === false) {
                fatal_exit(do_lang_tempcode('UPGRADER_FTP_NEEDED'));
            }
            @unlink($temp_path);
            $temp_path = get_custom_file_base() . '/data_custom/upgrader.cms.tmp';
        }
        require_code('files');
        $tmp_data_path = get_custom_file_base() . '/data_custom/upgrader.tmp';
        cms_file_put_contents_safe($tmp_data_path, serialize($data));
        global $SITE_INFO;
        if (isset($GLOBALS['SITE_INFO']['admin_password'])) { // LEGACY
            $GLOBALS['SITE_INFO']['master_password'] = $GLOBALS['SITE_INFO']['admin_password'];
            unset($GLOBALS['SITE_INFO']['admin_password']);
        }
        if (!$dry_run) {
            $extract_url = get_base_url() . '/data/upgrader2.php?hashed_password=' . urlencode($SITE_INFO['master_password']) . '&tmp_path=' . urlencode($temp_path) . '&file_offset=0&tmp_data_path=' . urlencode($tmp_data_path) . '&done=' . urlencode(do_lang('DONE'));
            $out .= '<p>' . do_lang('UPGRADER_EXTRACTING_WINDOW', integer_format(count($data['todo']))) . '</p>';
            $out .= '<iframe frameBorder="0" style="width: 100%; height: 400px" src="' . escape_html($extract_url) . '"></iframe>';
        } else {
            $out .= '<p>' . do_lang('FILES') . ':</p>';
            $out .= '<ul>';
            foreach ($data['todo'] as $file) {
                $out .= '<li>' . escape_html($file[0]) . '</li>';
            }
            $out .= '</ul>';
        }
    } else {
        $out .= '<p>' . do_lang('SUCCESS') . '</p>';
        if (!$local_temp_path) {
            @unlink($temp_path);
        }
    }

    unset($_POST['news_id']);
    unset($_POST['from_version']);

    return $out;
}
