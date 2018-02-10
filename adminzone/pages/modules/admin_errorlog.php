<?php /*

 Composr
 Copyright (c) ocProducts, 2004-2018

 See text/EN/licence.txt for full licencing information.


 NOTE TO PROGRAMMERS:
   Do not edit this file. If you need to make changes, save your changed file to the appropriate *_custom folder
   **** If you ignore this advice, then your website upgrades (e.g. for bug fixes) will likely kill your changes ****

*/

/**
 * @license    http://opensource.org/licenses/cpal_1.0 Common Public Attribution License
 * @copyright  ocProducts Ltd
 * @package    errorlog
 */

/**
 * Module page class.
 */
class Module_admin_errorlog
{
    /**
     * Find details of the module.
     *
     * @return ?array Map of module info (null: module is disabled)
     */
    public function info()
    {
        $info = array();
        $info['author'] = 'Chris Graham';
        $info['organisation'] = 'ocProducts';
        $info['hacked_by'] = null;
        $info['hack_version'] = null;
        $info['version'] = 2;
        $info['locked'] = false;
        return $info;
    }

    /**
     * Find entry-points available within this module.
     *
     * @param  boolean $check_perms Whether to check permissions
     * @param  ?MEMBER $member_id The member to check permissions as (null: current user)
     * @param  boolean $support_crosslinks Whether to allow cross links to other modules (identifiable via a full-page-link rather than a screen-name)
     * @param  boolean $be_deferential Whether to avoid any entry-point (or even return null to disable the page in the Sitemap) if we know another module, or page_group, is going to link to that entry-point. Note that "!" and "browse" entry points are automatically merged with container page nodes (likely called by page-groupings) as appropriate.
     * @return ?array A map of entry points (screen-name=>language-code/string or screen-name=>[language-code/string, icon-theme-image]) (null: disabled)
     */
    public function get_entry_points($check_perms = true, $member_id = null, $support_crosslinks = true, $be_deferential = false)
    {
        return array(
            '!' => array('ERRORLOG', 'menu/adminzone/audit/errorlog'),
        );
    }

    public $title;

    /**
     * Module pre-run function. Allows us to know metadata for <head> before we start streaming output.
     *
     * @return ?Tempcode Tempcode indicating some kind of exceptional output (null: none)
     */
    public function pre_run()
    {
        $type = get_param_string('type', 'browse');

        require_lang('errorlog');

        if ($type == 'browse') {
            set_helper_panel_tutorial('tut_disaster');
            set_helper_panel_text(comcode_lang_string('DOC_ERRORLOG'));

            $this->title = get_screen_title('ERRORLOG');

            if (!php_function_allowed('ini_set')) {
                attach_message(do_lang_tempcode('ERROR_LOGGING_PROBABLY_BROKEN'), 'warn');
            }
        }

        if ($type == 'delete_log') {
            $this->title = get_screen_title('DELETE_LOG');
        }

        if ($type == 'clear_log') {
            $this->title = get_screen_title('CLEAR_LOG');
        }

        if ($type == 'download_log') {
            $this->title = get_screen_title('DOWNLOAD_LOG');
        }

        if ($type == 'init_log') {
            $this->title = get_screen_title('INIT_LOG');
        }

        return null;
    }

    /**
     * Execute the module.
     *
     * @return Tempcode The result of execution
     */
    public function run()
    {
        $type = get_param_string('type', 'browse');

        if ($type == 'browse') {
            return $this->show_logs();
        }

        if ($type == 'delete_log') {
            return $this->delete_log();
        }

        if ($type == 'clear_log' || $type == 'init_log') {
            return $this->clear_log();
        }

        if ($type == 'download_log') {
            return $this->download_log();
        }

        return new Tempcode(); // Should not get here
    }

    /**
     * Show the main UI.
     *
     * @return Tempcode The result of execution
     */
    public function show_logs()
    {
        require_css('errorlog');

        // Read in errors
        if (!GOOGLE_APPENGINE) {
            if (is_readable(get_custom_file_base() . '/data_custom/errorlog.php')) {
                if (filesize(get_custom_file_base() . '/data_custom/errorlog.php') > 1024 * 1024) {
                    $myfile = fopen(get_custom_file_base() . '/data_custom/errorlog.php', 'rb');
                    flock($myfile, LOCK_SH);
                    fseek($myfile, -1024 * 500, SEEK_END);
                    $lines = explode("\n", fread($myfile, 1024 * 500));
                    flock($myfile, LOCK_UN);
                    fclose($myfile);
                    unset($lines[0]);
                    $lines[] = '...';
                } else {
                    $lines = file(get_custom_file_base() . '/data_custom/errorlog.php');
                }
            } else {
                $lines = array();
            }
            $stuff = array();
            foreach ($lines as $line) {
                $_line = trim($line);

                if (($_line != '') && (strpos($_line, '<?php') === false)) {
                    $matches = array();
                    if (preg_match('#^\[(.+?) (.+?)\] (.{1,20}):  ?(.*)#', $_line, $matches) != 0) {
                        $stuff[] = $matches;
                    }
                }
            }
        } else {
            $stuff = array();

            require_once('google/appengine/api/log/LogService.php');

            $_log_service = 'google\appengine\api\log\LogService';
            $log_service = new $_log_service;
            $options = array();
            $options['include_app_logs'] = true;
            $options['minimum_log_level'] = eval('return $log_service::LEVEL_WARNING;'); // = PHP notice
            $options['batch_size'] = 300;

            $logs = $log_service->fetch($options);
            foreach ($logs as $log) {
                $app_logs = $log->getAppLogs();
                foreach ($app_logs as $app_log) {
                    $message = $app_log->getMessage();

                    $level = $app_log->getLevel();
                    $_level = '';
                    if ($level == eval('return $log_service::LEVEL_WARNING;')) {
                        $_level = 'notice';
                    } elseif ($level == eval('return $log_service::LEVEL_ERROR;')) {
                        $_level = 'warning';
                    } elseif ($level == eval('return $log_service::LEVEL_CRITICAL;')) {
                        $_level = 'error';
                    } else {
                        continue;
                    }

                    $time = intval($app_log->getTimeUsec() / 1000000.0);

                    $stuff[] = array('', date('D-M-Y', $time), date('H:i:s', $time), $_level, $message);
                }
            }
        }

        // Put errors into table
        $start = get_param_integer('start', 0);
        $max = get_param_integer('max', 50);
        $sortables = array('date_and_time' => do_lang_tempcode('DATE_TIME'));
        $test = explode(' ', get_param_string('sort', 'date_and_time DESC', INPUT_FILTER_GET_COMPLEX), 2);
        if (count($test) == 1) {
            $test[1] = 'DESC';
        }
        list($sortable, $sort_order) = $test;
        if (((strtoupper($sort_order) != 'ASC') && (strtoupper($sort_order) != 'DESC')) || (!array_key_exists($sortable, $sortables))) {
            log_hack_attack_and_exit('ORDERBY_HACK');
        }
        if ($sort_order == 'DESC') {
            $stuff = array_reverse($stuff);
        }
        require_code('templates_results_table');
        $fields_title = results_field_title(array(do_lang_tempcode('DATE_TIME'), do_lang_tempcode('TYPE'), do_lang_tempcode('MESSAGE')), $sortables, 'sort', $sortable . ' ' . $sort_order);
        $fields = new Tempcode();
        for ($i = $start; $i < $start + $max; $i++) {
            if (!array_key_exists($i, $stuff)) {
                break;
            }

            $message = str_replace(get_file_base(), '', $stuff[$i][4]);

            $fields->attach(results_entry(array(
                $stuff[$i][1] . ' ' . $stuff[$i][2],
                $stuff[$i][3],
                $message,
            ), true));
        }
        $errors = results_table(do_lang_tempcode('ERRORLOG'), $start, 'start', $max, 'max', $i, $fields_title, $fields, $sortables, $sortable, $sort_order, 'sort', new Tempcode());

        // Read in end of any other log files we find
        require_all_lang();
        $logs = array();
        $dh = opendir(get_custom_file_base() . '/data_custom');
        while (($filename = readdir($dh)) !== false) {
            if (substr($filename, -4) == '.log') {
                $myfile = @fopen(get_custom_file_base() . '/data_custom/' . $filename, 'rb');
                if ($myfile !== false) {
                    // Get last 40000 bytes of log
                    flock($myfile, LOCK_SH);
                    fseek($myfile, -40000, SEEK_END);
                    $data = '';
                    while (!feof($myfile)) {
                        $data .= fread($myfile, 8192);
                    }
                    flock($myfile, LOCK_UN);
                    fclose($myfile);

                    // Split into lines
                    $lines = explode("\n", $data);

                    // Mark if we have truncated the start
                    if (count($lines) != 0) {
                        if (strlen($data) == 40000) {
                            $lines[0] = '...';
                        }
                    }

                    // Any special support for reformatting particular logs
                    foreach ($lines as $i => $line) {
                        // Special support for permission log
                        $matches = array();
                        if (preg_match('#^\s+has_privilege: (\w+)#', $line, $matches) != 0) {
                            $looked_up = do_lang('PRIVILEGE_' . $matches[1], null, null, null, null, false);
                            if ($looked_up !== null) {
                                $line = str_replace($matches[1], $looked_up, $line);
                                $lines[$i] = $line;
                            }
                        }
                    }
                }

                // Put lines back together
                $log = implode("\n", $lines);
                $download_url = new Tempcode();
                $clear_url = new Tempcode();
                $add_url = new Tempcode();
                if ($log != '') {
                    $download_url = build_url(array('page' => '_SELF', 'type' => 'download_log', 'id' => basename($filename, '.log')), '_SELF');
                }
                if ($log != '') {
                    $clear_url = build_url(array('page' => '_SELF', 'type' => 'clear_log', 'id' => basename($filename, '.log')), '_SELF');
                }
                $delete_url = build_url(array('page' => '_SELF', 'type' => 'delete_log', 'id' => basename($filename, '.log')), '_SELF');
                $logs[$filename] = array(
                    'LOG' => $log,
                    'DOWNLOAD_URL' => $download_url,
                    'CLEAR_URL' => $clear_url,
                    'DELETE_URL' => $delete_url,
                    'ADD_URL' => $add_url,
                );
            }
        }

        // Other logs that may be create-able...
        $logs_available = array( // FUDGE Ideally we'd use hooks, but it is so trivial (and non-bundled addons can document how to create their log, no problem)
            'cron.log' => null,
            'health_check.log' => 'health_check',
            'tasks.log' => null,
            'permission_checks.log' => null,
            'queries.log' => null,
            'big_query_screens.log' => null,
            'resource_fs.log' => 'commandr',
        );
        foreach ($logs_available as $filename => $addon_needed) {
            if ((!isset($logs[$filename])) && (($addon_needed === null) || (addon_installed($addon_needed)))) {
                $add_url = build_url(array('page' => '_SELF', 'type' => 'init_log', 'id' => basename($filename, '.log')), '_SELF');

                $logs[$filename] = array(
                    'LOG' => null,
                    'DOWNLOAD_URL' => new Tempcode(),
                    'CLEAR_URL' => new Tempcode(),
                    'DELETE_URL' => new Tempcode(),
                    'ADD_URL' => $add_url,
                );
            }
        }

        ksort($logs);

        // Put it all together...

        $clear_url = build_url(array('page' => '_SELF', 'type' => 'clear_log', 'id' => 'errorlog'), '_SELF');

        $tpl = do_template('ERRORLOG_SCREEN', array(
            '_GUID' => '9186c7beb6b722a52f39e2cbe16aded6',
            'TITLE' => $this->title,
            'ERRORS' => $errors,
            'LOGS' => $logs,
            'CLEAR_URL' => $clear_url,
        ));

        require_code('templates_internalise_screen');
        return internalise_own_screen($tpl);
    }

    /**
     * Delete log actualiser.
     *
     * @return Tempcode The result of execution
     */
    public function delete_log()
    {
        $log_file = filter_naughty(get_param_string('id'));
        if ($log_file == 'errorlog') {
            $log_file .= '.php';
        } else {
            $log_file .= '.log';
        }

        unlink(get_custom_file_base() . '/data_custom/' . $log_file);

        $url = build_url(array('page' => '_SELF'), '_SELF');
        return redirect_screen($this->title, $url, do_lang_tempcode('SUCCESS'));
    }

    /**
     * Clear/init log actualiser.
     *
     * @return Tempcode The result of execution
     */
    public function clear_log()
    {
        $log_file = filter_naughty(get_param_string('id'));
        if ($log_file == 'errorlog') {
            $log_file .= '.php';
        } else {
            $log_file .= '.log';
        }

        require_code('files');
        cms_file_put_contents_safe(get_custom_file_base() . '/data_custom/' . $log_file, '');

        $url = build_url(array('page' => '_SELF'), '_SELF');
        return redirect_screen($this->title, $url, do_lang_tempcode('SUCCESS'));
    }

    /**
     * Download log actualiser.
     *
     * @return Tempcode The result of execution
     */
    public function download_log()
    {
        $log_file = filter_naughty(get_param_string('id'));
        if ($log_file == 'errorlog') {
            $log_file .= '.php';
        } else {
            $log_file .= '.log';
        }

        safe_ini_set('ocproducts.xss_detect', '0');

        header('Content-Type: text/plain');

        echo file_get_contents(get_custom_file_base() . '/data_custom/' . $log_file);

        $GLOBALS['SCREEN_TEMPLATE_CALLED'] = '';
        exit();

        return new Tempcode();
    }
}
