<?php /*

 Composr
 Copyright (c) ocProducts, 2004-2015

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
 * Endpoint API entry script.
 */
function endpoint_script()
{
    header('Cache-Control: no-cache, must-revalidate'); // HTTP/1.1
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past

    safe_ini_set('ocproducts.xss_detect', '0');

    $hook_type = mixed();
    $hook = mixed();
    $type = mixed();
    $id = mixed();

    $hook_type = false;
    $hook = false;
    //$type = false; Is optional, so let it pass null as a default instead of false (=error)
    //$id = false; Is optional, so let it pass null as a default instead of false (=error)

    $response_type = 'json';

    require_code('failure');
    set_throw_errors(true);

    try
    {
        // Restful
        if (!empty($_SERVER['PATH_INFO'])) {
            // What response type is desired
            if (!empty($_SERVER['HTTP_ACCEPT'])) {
                if (strpos($_SERVER['HTTP_ACCEPT'], 'json') !== false) {
                    $response_type = 'json';
                }
                // ... Currently we actually only support JSON anyway! No need for unnecessary complexity.
            }

            // Path-info is translated to $hook_type/$hook/$id
            $path_info = $_SERVER['PATH_INFO'];
            $matches = array();
            if (preg_match('#^(/\w+)(/\w+)?(/.+)?#', $path_info, $matches) != 0) {
                $hook_type = ltrim($matches[1], '/');
                $hook = isset($matches[2]) ? ltrim($matches[2], '/') : false;
                $id = isset($matches[3]) ? ltrim($matches[3], '/') : null;
            }

            // POST data may need switching about
            if (count($_POST) == 0) {
                global $HTTP_RAW_POST_DATA;
                if (isset($HTTP_RAW_POST_DATA)) {
                    $ver = PHP_VERSION;
                    if (intval($ver[0]) >= 5) {
                        $_POST['data'] = @file_get_contents('php://input');
                    } else {
                        $_POST['data'] = isset($GLOBALS['HTTP_RAW_POST_DATA']) ? $GLOBALS['HTTP_RAW_POST_DATA'] : '';
                    }
                }
            }

            // Convert from REST's use of standard HTTP verbs to Composr's standard $type names (also corresponds with CRUD)
            switch (strtoupper($_SERVER['REQUEST_METHOD'])) {
                case 'POST': // REST POST = CRUD create = Composr add
                    $type = 'add';
                    break;

                case 'PUT': // REST PUT = CRUD update = Composr edit
                    $type = 'edit';
                    break;

                case 'DELETE': // REST DELETE = CRUD delete = Composr delete
                    $type = 'delete';
                    break;

                case 'GET': // REST GET = N/A = Composr view
                default:
                    $type = 'view';
                    break;
            }
        }

        // GET params take priority
        $hook_type = get_param_string('hook_type', $hook_type);
        $hook = get_param_string('hook', $hook);
        $type = get_param_string('type', $type);
        $id = get_param_string('id', $id);
        $response_type = get_param_string('response_type', $response_type);

        // Call appropriate hook to handle
        require_code('hooks/endpoints/' . filter_naughty($hook_type) . '/' . filter_naughty($hook));
        $ob = object_factory('Hook_endpoint_' . filter_naughty($hook_type) . '_' . filter_naughty($hook));
        $result = $ob->run($type, $id);

        // Process into output structure
        $return_data = array(
            'success' => isset($result['success']) ? $result['success'] : true,
            'error_details' => isset($result['error_details']) ? $result['error_details'] : null,
            'response_data' => array_diff_key($result, array('success' => true, 'error_details' => true)),
        );
    }
    catch (Exception $e) {
        $return_data = array(
            'success' => false,
            'error_details' => strip_html($e->getMessage()),
            'response_data' => array(),
        );
    }

    // Output
    switch ($response_type) {
        case 'json':
            require_code('json');
            header('Content-type: application/json');
            echo json_encode($return_data);
            break;

        default:
            fatal_exit(do_lang_tempcode('JSON_ONLY'));
            break;
    }
}
