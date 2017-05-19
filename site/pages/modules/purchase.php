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
 * @package    ecommerce
 */

/**
 * Module page class.
 */
class Module_purchase
{
    /**
     * Find details of the module.
     *
     * @return ?array Map of module info (null: module is disabled).
     */
    public function info()
    {
        $info = array();
        $info['author'] = 'Chris Graham';
        $info['organisation'] = 'ocProducts';
        $info['hacked_by'] = null;
        $info['hack_version'] = null;
        $info['version'] = 6;
        $info['update_require_upgrade'] = true;
        $info['locked'] = false;
        return $info;
    }

    /**
     * Uninstall the module.
     */
    public function uninstall()
    {
        $GLOBALS['SITE_DB']->drop_table_if_exists('transactions');
        $GLOBALS['SITE_DB']->drop_table_if_exists('trans_expecting');

        delete_privilege('access_ecommerce_in_test_mode');

        $cpf = array('currency', 'payment_cardholder_name', 'payment_type', 'payment_card_number', 'payment_card_start_date', 'payment_card_expiry_date', 'payment_card_issue_number');
        foreach ($cpf as $_cpf) {
            $GLOBALS['FORUM_DRIVER']->install_delete_custom_field($_cpf);
        }
    }

    /**
     * Install the module.
     *
     * @param  ?integer $upgrade_from What version we're upgrading from (null: new install)
     * @param  ?integer $upgrade_from_hack What hack version we're upgrading from (null: new-install/not-upgrading-from-a-hacked-version)
     */
    public function install($upgrade_from = null, $upgrade_from_hack = null)
    {
        if (is_null($upgrade_from)) {
            add_privilege('ECOMMERCE', 'access_ecommerce_in_test_mode', false);

            $GLOBALS['SITE_DB']->create_table('trans_expecting', array(
                'id' => '*ID_TEXT',
                'e_purchase_id' => 'ID_TEXT',
                'e_item_name' => 'SHORT_TEXT',
                'e_member_id' => 'MEMBER',
                'e_amount' => 'SHORT_TEXT',
                'e_currency' => 'ID_TEXT',
                'e_ip_address' => 'IP',
                'e_session_id' => 'ID_TEXT',
                'e_time' => 'TIME',
                'e_length' => '?INTEGER',
                'e_length_units' => 'ID_TEXT',
            ));

            require_code('currency');
            $cpf = array('currency' => array(3, 'list', '|' . implode('|', array_keys(get_currency_map()))));
            foreach ($cpf as $f => $l) {
                $GLOBALS['FORUM_DRIVER']->install_create_custom_field($f, $l[0], 0, 0, 1, 0, '', $l[1], 0, $l[2]);
            }
            $cpf = array('payment_cardholder_name' => array(100, 'short_text', ''), 'payment_type' => array(26, 'list', 'American Express|Delta|Diners Card|JCB|Master Card|Solo|Switch|Visa'), 'payment_card_number' => array(20, 'integer', ''), 'payment_card_start_date' => array(5, 'short_text', 'mm/yy'), 'payment_card_expiry_date' => array(5, 'short_text', 'mm/yy'), 'payment_card_issue_number' => array(2, 'short_text', ''), 'payment_card_cv2' => array(4, 'short_text', ''));
            foreach ($cpf as $f => $l) {
                $GLOBALS['FORUM_DRIVER']->install_create_custom_field($f, $l[0], 0, 0, 1, 0, '', $l[1], 1, $l[2]);
            }

            $GLOBALS['SITE_DB']->create_table('transactions', array(
                'id' => '*ID_TEXT',
                't_type_code' => 'ID_TEXT',
                't_purchase_id' => 'ID_TEXT',
                't_status' => 'SHORT_TEXT',
                't_reason' => 'SHORT_TEXT',
                't_amount' => 'SHORT_TEXT',
                't_currency' => 'ID_TEXT',
                't_parent_txn_id' => 'ID_TEXT',
                't_time' => '*TIME',
                't_pending_reason' => 'SHORT_TEXT',
                't_memo' => 'LONG_TEXT',
                't_via' => 'ID_TEXT'
            ));
        }

        if ((!is_null($upgrade_from)) && ($upgrade_from < 6)) {
            $GLOBALS['SITE_DB']->alter_table_field('transactions', 'purchase_id', 'ID_TEXT', 't_purchase_id');
            $GLOBALS['SITE_DB']->alter_table_field('transactions', 'status', 'SHORT_TEXT', 't_status');
            $GLOBALS['SITE_DB']->alter_table_field('transactions', 'reason', 'SHORT_TEXT', 't_reason');
            $GLOBALS['SITE_DB']->alter_table_field('transactions', 'amount', 'SHORT_TEXT', 't_amount');
            $GLOBALS['SITE_DB']->alter_table_field('transactions', 'linked', 'ID_TEXT', 't_parent_txn_id');
            $GLOBALS['SITE_DB']->alter_table_field('transactions', 'item', 'SHORT_TEXT', 't_type_code');
            $GLOBALS['SITE_DB']->alter_table_field('transactions', 'pending_reason', 'SHORT_TEXT', 't_pending_reason');

            $GLOBALS['FORUM_DB']->add_table_field('trans_expecting', 'e_currency', 'ID_TEXT', get_option('currency'));

            $GLOBALS['SITE_DB']->alter_table_field('trans_expecting', 'e_session_id', 'ID_TEXT');
        }
    }

    /**
     * Find entry-points available within this module.
     *
     * @param  boolean $check_perms Whether to check permissions.
     * @param  ?MEMBER $member_id The member to check permissions as (null: current user).
     * @param  boolean $support_crosslinks Whether to allow cross links to other modules (identifiable via a full-page-link rather than a screen-name).
     * @param  boolean $be_deferential Whether to avoid any entry-point (or even return null to disable the page in the Sitemap) if we know another module, or page_group, is going to link to that entry-point. Note that "!" and "browse" entry points are automatically merged with container page nodes (likely called by page-groupings) as appropriate.
     * @return ?array A map of entry points (screen-name=>language-code/string or screen-name=>[language-code/string, icon-theme-image]) (null: disabled).
     */
    public function get_entry_points($check_perms = true, $member_id = null, $support_crosslinks = true, $be_deferential = false)
    {
        return array(
            'browse' => array('PURCHASING', 'menu/rich_content/ecommerce/purchase'),
        );
    }

    public $title;

    /**
     * Module pre-run function. Allows us to know metadata for <head> before we start streaming output.
     *
     * @return ?Tempcode Tempcode indicating some kind of exceptional output (null: none).
     */
    public function pre_run()
    {
        $type = get_param_string('type', 'browse');

        require_lang('ecommerce');

        $this->title = get_screen_title('PURCHASING_TITLE', true, array(do_lang_tempcode('PURCHASE_STAGE_' . $type)));
        breadcrumb_set_self(do_lang_tempcode('PURCHASE_STAGE_' . $type));

        if ($type == 'browse') {
            breadcrumb_set_self(do_lang_tempcode('PURCHASING'));
        } else {
            breadcrumb_set_parents(array(array('_SELF:_SELF:browse', do_lang_tempcode('PURCHASING'))));
        }

        return null;
    }

    /**
     * Execute the module.
     *
     * @return Tempcode The result of execution.
     */
    public function run()
    {
        @ignore_user_abort(true); // Must keep going till completion

        require_code('ecommerce');
        require_lang('config');
        require_css('ecommerce');

        // Kill switch
        if ((ecommerce_test_mode()) && (!$GLOBALS['IS_ACTUALLY_ADMIN']) && (!has_privilege(get_member(), 'access_ecommerce_in_test_mode'))) {
            warn_exit(do_lang_tempcode('PURCHASE_DISABLED'));
        }

        $type = get_param_string('type', 'browse');

        // Recognise join operations
        $new_username = post_param_string('username', null);
        if (!is_null($new_username)) {
            require_code('cns_join');
            list($messages) = cns_join_actual(true, false, false, true, false, false, false, true);
            if (is_guest()) {
                if (!$messages->is_empty()) {
                    return inform_screen($this->title, $messages);
                }
            }
        }

        // Normal processing
        if ($type == 'browse') {
            return $this->choose();
        }
        if ($type == 'message') {
            return $this->message();
        }
        if ($type == 'terms') {
            return $this->terms();
        }
        if ($type == 'details') {
            return $this->details();
        }
        if ($type == 'pay') {
            return $this->pay();
        }
        if ($type == 'finish') {
            return $this->finish();
        }
        return new Tempcode();
    }

    /**
     * Wrap-up so as to remove redundancy in templates.
     *
     * @param  Tempcode $content To wrap.
     * @param  Tempcode $title The title to use.
     * @param  ?mixed $url URL (null: no next URL).
     * @param  boolean $get Whether it is a GET form
     * @return Tempcode Wrapped.
     */
    public function _wrap($content, $title, $url, $get = false)
    {
        if (is_null($url)) {
            $url = '';
        }
        require_javascript('checking');
        return do_template('PURCHASE_WIZARD_SCREEN', array('_GUID' => 'a32c99acc28e8ad05fd9b5e2f2cda029', 'GET' => $get ? true : null, 'TITLE' => $title, 'CONTENT' => $content, 'URL' => $url));
    }

    /**
     * Choose product step.
     *
     * @return Tempcode The result of execution.
     */
    public function choose()
    {
        $url = build_url(array('page' => '_SELF', 'type' => 'message', 'id' => get_param_integer('id', -1)), '_SELF', null, true, true);

        require_code('form_templates');

        $list = new Tempcode();
        $filter = get_param_string('filter', '');
        $type_filter = get_param_integer('type_filter', null);
        $products = find_all_products();

        foreach ($products as $type_code => $details) {
            if ($filter != '') {
                if ((!is_string($type_code)) || (substr($type_code, 0, strlen($filter)) != $filter)) {
                    continue;
                }
            }

            if (!is_null($type_filter)) {
                if ($details[0] != $type_filter) {
                    continue;
                }
            }

            $wizard_supported = (($details[0] == PRODUCT_PURCHASE_WIZARD) || ($details[0] == PRODUCT_SUBSCRIPTION) || ($details[0] == PRODUCT_CATALOGUE));

            $is_available = false; // Anything without is_available is not meant to be purchased directly
            if (method_exists($details[count($details) - 1], 'is_available')) {
                $availability_status = $details[count($details) - 1]->is_available($type_code, get_member());
                $is_available = ($availability_status == ECOMMERCE_PRODUCT_AVAILABLE) || ($availability_status == ECOMMERCE_PRODUCT_NO_GUESTS);
            }

            if ($wizard_supported && $is_available) {
                require_code('currency');
                $currency = isset($details[5]) ? $details[5] : get_option('currency');
                $price = currency_convert(floatval($details[1]), $currency, null, true);

                $description = $details[4];
                if ($price != '' && strpos($details[4], (strpos($details[4], '.') === false) ? preg_replace('#\.00($|[^\d])#', '', $price) : $price) === false) {
                    $description .= (' (' . $price . ')');
                }
                $list->attach(form_input_list_entry($type_code, false, protect_from_escaping($description)));
            }
        }
        if ($list->is_empty()) {
            inform_exit(do_lang_tempcode('NO_CATEGORIES'));
        }
        $fields = form_input_huge_list(do_lang_tempcode('PRODUCT'), '', 'type_code', $list, null, true);

        return $this->_wrap(do_template('PURCHASE_WIZARD_STAGE_CHOOSE', array('_GUID' => '47c22d48313ff50e6323f05a78342eae', 'FIELDS' => $fields, 'TITLE' => $this->title)), $this->title, $url, true);
    }

    /**
     * Message about product step.
     *
     * @return Tempcode The result of execution.
     */
    public function message()
    {
        require_code('form_templates');

        $type_code = get_param_string('type_code');

        $text = new Tempcode();
        $object = find_product($type_code);
        if (is_null($object)) {
            warn_exit(do_lang_tempcode('MISSING_RESOURCE'));
        }

        $test = $this->_check_availability($type_code);
        if (!is_null($test)) {
            return $test;
        }

        // Work out what next step is
        $terms = method_exists($object, 'get_terms') ? $object->get_terms($type_code) : '';
        $fields = method_exists($object, 'get_needed_fields') ? $object->get_needed_fields($type_code) : null;
        if ((!is_null($fields)) && ($fields->is_empty())) {
            $fields = null;
        }
        $url = build_url(array('page' => '_SELF', 'type' => ($terms == '') ? (is_null($fields) ? 'pay' : 'details') : 'terms', 'type_code' => $type_code, 'id' => get_param_integer('id', -1)), '_SELF', null, true);

        if (method_exists($object, 'product_info')) {
            $text->attach($object->product_info(get_param_integer('type_code'), $this->title));
        } else {
            if (!method_exists($object, 'get_message')) {
                // Ah, not even a message to show - jump ahead
                return redirect_screen($this->title, $url, '');
            }
            $text->attach($object->get_message($type_code));
        }

        return $this->_wrap(do_template('PURCHASE_WIZARD_STAGE_MESSAGE', array('_GUID' => '8667b6b544c4cea645a52bb4d087f816', 'TITLE' => '', 'TEXT' => $text)), $this->title, $url);
    }

    /**
     * Terms and conditions step.
     *
     * @return Tempcode The result of execution.
     */
    public function terms()
    {
        require_lang('installer');

        require_code('form_templates');

        $type_code = get_param_string('type_code');

        $object = find_product($type_code);

        $test = $this->_check_availability($type_code);
        if (!is_null($test)) {
            return $test;
        }

        // Work out what next step is
        $terms = $object->get_terms($type_code);
        $fields = $object->get_needed_fields($type_code);
        if ((!is_null($fields)) && ($fields->is_empty())) {
            $fields = null;
        }
        $url = build_url(array('page' => '_SELF', 'type' => is_null($fields) ? 'pay' : 'details', 'type_code' => $type_code, 'id' => get_param_integer('id', -1), 'accepted' => 1), '_SELF', null, true, true);

        return $this->_wrap(do_template('PURCHASE_WIZARD_STAGE_TERMS', array('_GUID' => '55c7bc550bb327535db1aebdac9d85f2', 'TITLE' => $this->title, 'URL' => $url, 'TERMS' => $terms)), $this->title, null);
    }

    /**
     * Details about purchase step.
     *
     * @return Tempcode The result of execution.
     */
    public function details()
    {
        require_code('form_templates');

        if (get_param_integer('accepted', 0) == 1) {
            attach_message(do_lang_tempcode('LICENCE_WAS_ACCEPTED'), 'inform');
        }

        $type_code = get_param_string('type_code');

        $object = find_product($type_code);

        $test = $this->_check_availability($type_code);
        if (!is_null($test)) {
            return $test;
        }

        // Work out what next step is
        $fields = $object->get_needed_fields($type_code, get_param_integer('id', -1));
        $url = build_url(array('page' => '_SELF', 'type' => 'pay', 'type_code' => $type_code), '_SELF', null, true);

        return $this->_wrap(do_template('PURCHASE_WIZARD_STAGE_DETAILS', array('_GUID' => '7fcbb0be5e90e52163bfec01f22f4ea0', 'TEXT' => is_array($fields) ? $fields[1] : '', 'FIELDS' => is_array($fields) ? $fields[0] : $fields)), $this->title, $url);
    }

    /**
     * Payment step.
     *
     * @return Tempcode The result of execution.
     */
    public function pay()
    {
        $type_code = get_param_string('type_code');
        $object = find_product($type_code);

        $via = get_param_string('via', get_option('payment_gateway'));
        require_code('hooks/systems/ecommerce_via/' . filter_naughty_harsh($via));
        $purchase_object = object_factory('Hook_' . $via);

        $test = $this->_check_availability($type_code);
        if (!is_null($test)) {
            return $test;
        }

        $temp = $object->get_products(true, $type_code);
        $price = $temp[$type_code][1];
        $item_name = $temp[$type_code][4];
        $currency = isset($temp[$type_code][5]) ? $temp[$type_code][5] : get_option('currency');

        if (method_exists($object, 'set_needed_fields')) {
            $purchase_id = $object->set_needed_fields($type_code);
        } else {
            $purchase_id = strval(get_member());
        }

        if ($temp[$type_code][0] == PRODUCT_SUBSCRIPTION) {
            $_purchase_id = $GLOBALS['SITE_DB']->query_select_value_if_there('subscriptions', 'id', array(
                's_type_code' => $type_code,
                's_member_id' => get_member(),
                's_state' => 'new'
            ));
            if (is_null($_purchase_id)) {
                $purchase_id = strval($GLOBALS['SITE_DB']->query_insert('subscriptions', array(
                    's_type_code' => $type_code,
                    's_member_id' => get_member(),
                    's_state' => 'new',
                    's_amount' => $temp[$type_code][1],
                    's_purchase_id' => $purchase_id,
                    's_time' => time(),
                    's_auto_fund_source' => '',
                    's_auto_fund_key' => '',
                    's_via' => $via,
                    's_length' => $temp[$type_code][3]['length'],
                    's_length_units' => $temp[$type_code][3]['length_units'],
                ), true));
            } else {
                $purchase_id = strval($_purchase_id);
            }

            $length = array_key_exists('length', $temp[$type_code][3]) ? $temp[$type_code][3]['length'] : 1;
            $length_units = array_key_exists('length_units', $temp[$type_code][3]) ? $temp[$type_code][3]['length_units'] : 'm';
        } else {
            $length = null;
            $length_units = '';

            // Add cataloue item order to shopping_orders
            if (method_exists($object, 'add_purchase_order')) {
                $purchase_id = strval($object->add_purchase_order($type_code, $temp[$type_code]));
            }
        }

        if ($price == '0') {
            $payment_status = 'Completed';
            $reason_code = '';
            $pending_reason = '';
            $txn_id = 'manual-' . substr(uniqid('', true), 0, 10);
            $parent_txn_id = '';
            $memo = 'Free';
            $mc_gross = '';
            handle_confirmed_transaction($purchase_id, $item_name, $payment_status, $reason_code, $pending_reason, $memo, $mc_gross, $currency, $txn_id, $parent_txn_id, '', 'manual');
            return inform_screen($this->title, do_lang_tempcode('FREE_PURCHASE'));
        }

        if (!array_key_exists(4, $temp[$type_code])) {
            $item_name = do_lang('CUSTOM_PRODUCT_' . $type_code, null, null, null, get_site_default_lang());
        }

        $text = mixed();
        if (get_param_integer('include_message', 0) == 1) {
            $text = new Tempcode();
            if (method_exists($object, 'product_info')) {
                $text->attach($object->product_info(get_param_integer('product'), $this->title));
            } elseif (method_exists($object, 'get_message')) {
                $text->attach($object->get_message($type_code));
            }
        }

        if (!perform_local_payment()) { // Pass through to the gateway's HTTP server
            if ($temp[$type_code][0] == PRODUCT_SUBSCRIPTION) {
                $transaction_button = make_subscription_button($type_code, $item_name, $purchase_id, floatval($price), $length, $length_units, $currency, $via);
            } else {
                $transaction_button = make_transaction_button($type_code, $item_name, $purchase_id, floatval($price), $currency, $via);
            }
            $tpl = ($temp[$type_code][0] == PRODUCT_SUBSCRIPTION) ? 'PURCHASE_WIZARD_STAGE_SUBSCRIBE' : 'PURCHASE_WIZARD_STAGE_PAY';
            $logos = method_exists($purchase_object, 'get_logos') ? $purchase_object->get_logos() : new Tempcode();
            $result = do_template($tpl, array(
                'LOGOS' => $logos,
                'TRANSACTION_BUTTON' => $transaction_button,
                'CURRENCY' => $currency,
                'ITEM_NAME' => $item_name,
                'TITLE' => $this->title,
                'LENGTH' => is_null($length) ? '' : strval($length),
                'LENGTH_UNITS' => $length_units,
                'PURCHASE_ID' => $purchase_id,
                'PRICE' => float_to_raw_string(floatval($price)),
                'TEXT' => $text,
            ));
        } else { // Handle the transaction internally
            if ((!tacit_https()) && (!ecommerce_test_mode())) {
                warn_exit(do_lang_tempcode('NO_SSL_SETUP'));
            }

            list($fields, $hidden) = get_transaction_form_fields(null, $purchase_id, $item_name, float_to_raw_string($price), $currency, ($temp[$type_code][0] == PRODUCT_SUBSCRIPTION) ? intval($length) : null, ($temp[$type_code][0] == PRODUCT_SUBSCRIPTION) ? $length_units : '', $via);

            $finish_url = build_url(array('page' => '_SELF', 'type' => 'finish'), '_SELF');

            $result = do_template('PURCHASE_WIZARD_STAGE_TRANSACT', array('_GUID' => '15cbba9733f6ff8610968418d8ab527e', 'FIELDS' => $fields, 'HIDDEN' => $hidden));
            return $this->_wrap($result, $this->title, $finish_url);
        }
        return $this->_wrap($result, $this->title, null);
    }

    /**
     * Finish step.
     *
     * @return Tempcode The result of execution.
     */
    public function finish()
    {
        $via = get_option('payment_gateway');
        require_code('hooks/systems/ecommerce_via/' . filter_naughty_harsh($via));
        $object = object_factory('Hook_' . $via);

        $message = mixed();
        if (method_exists($object, 'get_callback_url_message')) {
            $message = $object->get_callback_url_message();
        }

        if (get_param_integer('cancel', 0) == 0) {
            if (perform_local_payment()) { // We need to try and run the transaction
                $trans_id = post_param_string('trans_id');
                $transaction_rows = $GLOBALS['SITE_DB']->query_select('trans_expecting', array('*'), array('id' => $trans_id), '', 1);
                if (!array_key_exists(0, $transaction_rows)) {
                    warn_exit(do_lang_tempcode('MISSING_RESOURCE'));
                }
                $transaction_row = $transaction_rows[0];
                $amount = $transaction_row['e_amount'];
                $length = $transaction_row['e_length'];
                $length_units = $transaction_row['e_length_units'];
                $currency = $transaction_row['e_currency'];

                $name = post_param_string('name');
                $card_number = post_param_string('card_number');
                $expiry_date = str_replace('/', '', post_param_string('expiry_date'));
                $issue_number = post_param_integer('issue_number', null);
                $start_date = str_replace('/', '', post_param_string('start_date'));
                $card_type = post_param_string('card_type');
                $cv2 = post_param_string('cv2');

                list($success, , $message, $message_raw) = $object->do_transaction($trans_id, $name, $card_number, $amount, $currency, $expiry_date, $issue_number, $start_date, $card_type, $cv2, $length, $length_units);

                $item_name = $transaction_row['e_item_name'];

                if (addon_installed('shopping')) {
                    if (preg_match('#' . str_replace('xxx', '.*', preg_quote(do_lang('shopping:CART_ORDER', 'xxx'), '#')) . '#', $item_name) != 0) {
                        $this->store_shipping_address(intval($transaction_row['e_purchase_id']));
                    }
                }

                if (($success) || (!is_null($length))) {
                    $status = ((!is_null($length)) && (!$success)) ? 'SCancelled' : 'Completed';
                    handle_confirmed_transaction($transaction_row['e_purchase_id'], $transaction_row['e_item_name'], $status, $message_raw, '', '', $amount, $currency, $trans_id, '', is_null($length) ? '' : strtolower(strval($length) . ' ' . $length_units), $via);
                }

                if ($success) {
                    $member_id = $transaction_row['e_member_id'];
                    require_code('notifications');
                    dispatch_notification('payment_received', null, do_lang('PAYMENT_RECEIVED_SUBJECT', $trans_id), do_notification_lang('PAYMENT_RECEIVED_BODY', float_format(floatval($amount)), $currency, get_site_name()), array($member_id), A_FROM_SYSTEM_PRIVILEGED);
                }
            }

            $type_code = get_param_string('type_code', '');
            if ($type_code != '') {
                if ((!perform_local_payment()) && (has_interesting_post_fields())) { // Alternative to IPN, *if* posted fields sent here
                    handle_transaction_script();
                }

                $product_object = find_product($type_code);

                if (method_exists($product_object, 'get_finish_url')) {
                    return redirect_screen($this->title, $product_object->get_finish_url($type_code, $message, get_param_integer('purchase_id', null)), $message);
                }
            }

            return $this->_wrap(do_template('PURCHASE_WIZARD_STAGE_FINISH', array('_GUID' => '43f706793719ea893c280604efffacfe', 'TITLE' => $this->title, 'MESSAGE' => $message)), $this->title, null);
        }

        if (!is_null($message)) {
            return $this->_wrap(do_template('PURCHASE_WIZARD_STAGE_FINISH', array('_GUID' => '859c31e8f0f02a2a46951be698dd22cf', 'TITLE' => $this->title, 'MESSAGE' => $message)), $this->title, null);
        }

        return inform_screen(get_screen_title('PURCHASING'), do_lang_tempcode('PRODUCT_PURCHASE_CANCEL'), true);
    }

    /**
     * Check to see if a product is available to the current user.
     *
     * @param  ID_TEXT $type_code The product code.
     * @return ?Tempcode Error screen (null: no error).
     */
    public function _check_availability($type_code)
    {
        $object = find_product($type_code);
        if (!method_exists($object, 'is_available')) {
            warn_exit(do_lang_tempcode('INTERNAL_ERROR'));
        }

        $availability_status = $object->is_available($type_code, get_member());

        switch ($availability_status) {
            case ECOMMERCE_PRODUCT_ALREADY_HAS:
                return warn_screen(get_screen_title('PURCHASING'), do_lang_tempcode('ECOMMERCE_PRODUCT_ALREADY_HAS'), true, true);

            case ECOMMERCE_PRODUCT_DISABLED:
                return warn_screen(get_screen_title('PURCHASING'), do_lang_tempcode('ECOMMERCE_PRODUCT_DISABLED'), true, true);

            case ECOMMERCE_PRODUCT_PROHIBITED:
                return warn_screen(get_screen_title('PURCHASING'), do_lang_tempcode('ECOMMERCE_PRODUCT_PROHIBITED'), true, true);

            case ECOMMERCE_PRODUCT_OUT_OF_STOCK:
                return warn_screen(get_screen_title('PURCHASING'), do_lang_tempcode('ECOMMERCE_PRODUCT_OUT_OF_STOCK'));

            case ECOMMERCE_PRODUCT_MISSING:
                return warn_screen(get_screen_title('PURCHASING'), do_lang_tempcode('ECOMMERCE_PRODUCT_MISSING'));

            case ECOMMERCE_PRODUCT_INTERNAL_ERROR:
                return warn_screen(get_screen_title('PURCHASING'), do_lang_tempcode('INTERNAL_ERROR'));

            case ECOMMERCE_PRODUCT_NO_GUESTS:
                if ((is_guest()) && (get_forum_type() != 'cns')) {
                    access_denied('NOT_AS_GUEST');
                }

                require_code('cns_join');

                $url = get_self_url();

                list($javascript, $form) = cns_join_form($url, true, false, false, false);

                $hidden = build_keep_post_fields();

                $join_screen = do_template('PURCHASE_WIZARD_STAGE_GUEST', array(
                    '_GUID' => 'accf475a1457f73d7280b14d774acc6e',
                    'TEXT' => do_lang_tempcode('PURCHASE_NOT_LOGGED_IN', escape_html(get_site_name())),
                    'JAVASCRIPT' => $javascript,
                    'FORM' => $form,
                    'HIDDEN' => $hidden,
                ));

                return $this->_wrap($join_screen, get_screen_title('PURCHASING'), null);
        }

        return null;
    }
}
