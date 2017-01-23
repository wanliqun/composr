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
 * Hook class.
 */
class Hook_ecommerce_email
{
    /**
     * Standard eCommerce product configuration function.
     *
     * @return ?array A tuple: list of [fields to shown, hidden fields], title for add form, add form (null: disabled)
     */
    public function config()
    {
        $rows = $GLOBALS['SITE_DB']->query('SELECT price,name FROM ' . get_table_prefix() . 'ecom_prods_prices WHERE name LIKE \'' . db_encode_like('forw\_%') . '\'');
        $out_forw = array();
        foreach ($rows as $i => $row) {
            $fields = new Tempcode();
            $hidden = new Tempcode();
            $domain = substr($row['name'], strlen('forw_'));
            $hidden->attach(form_input_hidden('dforw_' . strval($i), $domain));
            $fields->attach(form_input_line(do_lang_tempcode('MAIL_DOMAIN'), do_lang_tempcode('DESCRIPTION_MAIL_DOMAIN'), 'ndforw_' . strval($i), $domain, true));
            $fields->attach(form_input_integer(do_lang_tempcode('MAIL_COST'), do_lang_tempcode('DESCRIPTION_MAIL_COST', escape_html('forw'), escape_html($domain)), 'forw_' . strval($i), $row['price'], true));
            $fields->attach(do_template('FORM_SCREEN_FIELD_SPACER', array('_GUID' => '34f5212a96f58fa1b0575a99ca0509e7', 'TITLE' => do_lang_tempcode('ACTIONS'))));
            $fields->attach(form_input_tick(do_lang_tempcode('DELETE'), do_lang_tempcode('DESCRIPTION_DELETE'), 'delete_forw_' . strval($i), false));
            $out_forw[] = array($fields, $hidden, do_lang_tempcode('_EDIT_FORWARDING_DOMAIN', escape_html($domain)));
        }

        $rows = $GLOBALS['SITE_DB']->query('SELECT price,name FROM ' . get_table_prefix() . 'ecom_prods_prices WHERE name LIKE \'' . db_encode_like('pop3\_%') . '\'');
        $out_pop3 = array();
        foreach ($rows as $i => $row) {
            $fields = new Tempcode();
            $hidden = new Tempcode();
            $domain = substr($row['name'], strlen('pop3_'));
            $hidden->attach(form_input_hidden('dpop3_' . strval($i), $domain));
            $fields->attach(form_input_line(do_lang_tempcode('MAIL_DOMAIN'), do_lang_tempcode('DESCRIPTION_MAIL_DOMAIN'), 'ndpop3_' . strval($i), $domain, true));
            $fields->attach(form_input_integer(do_lang_tempcode('MAIL_COST'), do_lang_tempcode('DESCRIPTION_MAIL_COST', escape_html('pop3'), escape_html($domain)), 'pop3_' . strval($i), $row['price'], true));
            $fields->attach(do_template('FORM_SCREEN_FIELD_SPACER', array('_GUID' => '9e37f41f134eecae630bfbf32da7b9ec', 'TITLE' => do_lang_tempcode('ACTIONS'))));
            $fields->attach(form_input_tick(do_lang_tempcode('DELETE'), do_lang_tempcode('DESCRIPTION_DELETE'), 'delete_pop3_' . strval($i), false));
            $out_pop3[] = array($fields, $hidden, do_lang_tempcode('_EDIT_POP3_DOMAIN', escape_html($domain)));
        }

        return array(
            array($out_forw, do_lang_tempcode('ADD_NEW_FORWARDING_DOMAIN'), $this->_get_fields_forw(), do_lang_tempcode('FORWARDING_DESCRIPTION')),
            array($out_pop3, do_lang_tempcode('ADD_NEW_POP3_DOMAIN'), $this->_get_fields_pop3(), do_lang_tempcode('POP3_DESCRIPTION')),
        );
    }

    /**
     * Get fields for adding/editing one of these.
     *
     * @return Tempcode The fields
     */
    protected function _get_fields_forw()
    {
        $fields = new Tempcode();
        $fields->attach(form_input_line(do_lang_tempcode('MAIL_DOMAIN'), do_lang_tempcode('DESCRIPTION_MAIL_DOMAIN'), 'dforw', '', true));
        $fields->attach(form_input_integer(do_lang_tempcode('MAIL_COST'), do_lang_tempcode('_DESCRIPTION_MAIL_COST'), 'forw', null, true));
        return $fields;
    }

    /**
     * Get fields for adding/editing one of these.
     *
     * @return Tempcode The fields
     */
    protected function _get_fields_pop3()
    {
        $fields = new Tempcode();
        $fields->attach(form_input_line(do_lang_tempcode('MAIL_DOMAIN'), do_lang_tempcode('DESCRIPTION_MAIL_DOMAIN'), 'dpop3', '', true));
        $fields->attach(form_input_integer(do_lang_tempcode('MAIL_COST'), do_lang_tempcode('_DESCRIPTION_MAIL_COST'), 'pop3', null, true));
        return $fields;
    }

    /**
     * Standard eCommerce product configuration save function.
     */
    public function save_config()
    {
        $forw = post_param_integer('forw', null);
        if ($forw !== null) {
            $dforw = post_param_string('dforw');
            $GLOBALS['SITE_DB']->query_insert('ecom_prods_prices', array('name' => 'forw_' . $dforw, 'price' => $forw));
            log_it('ECOM_PRODUCTS_ADD_MAIL_FORWARDER', $dforw);
        }
        $this->_do_price_mail_forw();

        $pop3 = post_param_integer('pop3', null);
        if ($pop3 !== null) {
            $dpop3 = post_param_string('dpop3');
            $GLOBALS['SITE_DB']->query_insert('ecom_prods_prices', array('name' => 'pop3_' . $dpop3, 'price' => $pop3));
            log_it('ECOM_PRODUCTS_ADD_MAIL_POP3', $dpop3);
        }
        $this->_do_price_mail_pop3();
    }

    /**
     * Update an e-mail address from what was chosen in an interface; update or delete each price/cost/product
     */
    protected function _do_price_mail_forw()
    {
        $i = 0;
        while (array_key_exists('forw_' . strval($i), $_POST)) {
            $price = post_param_integer('forw_' . strval($i));
            $name = 'forw_' . post_param_string('dforw_' . strval($i));
            $name2 = 'forw_' . post_param_string('ndforw_' . strval($i));
            if (post_param_integer('delete_forw_' . strval($i), 0) == 1) {
                $GLOBALS['SITE_DB']->query_delete('ecom_prods_prices', array('name' => $name), '', 1);
            } else {
                $GLOBALS['SITE_DB']->query_update('ecom_prods_prices', array('price' => $price, 'name' => $name2), array('name' => $name), '', 1);
            }

            $i++;
        }
    }

    /**
     * Update an e-mail address from what was chosen in an interface; update or delete each price/cost/product
     */
    protected function _do_price_mail_pop3()
    {
        $i = 0;
        while (array_key_exists('pop3_' . strval($i), $_POST)) {
            $price = post_param_integer('pop3_' . strval($i));
            $name = 'pop3_' . post_param_string('dpop3_' . strval($i));
            $name2 = 'pop3_' . post_param_string('ndpop3_' . strval($i));
            if (post_param_integer('delete_pop3_' . strval($i), 0) == 1) {
                $GLOBALS['SITE_DB']->query_delete('ecom_prods_prices', array('name' => $name), '', 1);
            } else {
                $GLOBALS['SITE_DB']->query_update('ecom_prods_prices', array('price' => $price, 'name' => $name2), array('name' => $name), '', 1);
            }

            $i++;
        }
    }

    /**
     * Get the overall categorisation for the products handled by this eCommerce hook.
     *
     * @return ?array A map of product categorisation details (null: disabled).
     */
    public function get_product_category()
    {
        require_lang('ecommerce');

        return array(
            'category_name' => do_lang('EMAIL_ACCOUNTS', integer_format(intval(get_option('initial_quota')))),
            'category_description' => do_lang_tempcode('EMAIL_TYPES_DESCRIPTION', escape_html(integer_format(intval(get_option('initial_quota'))))),
            'category_image_url' => find_theme_image('icons/48x48/contact_methods/email'),
        );
    }

    /**
     * Get the products handled by this eCommerce hook.
     *
     * IMPORTANT NOTE TO PROGRAMMERS: This function may depend only on the database, and not on get_member() or any GET/POST values.
     *  Such dependencies will break IPN, which works via a Guest and no dependable environment variables. It would also break manual transactions from the Admin Zone.
     *
     * @param  boolean $site_lang Whether to make sure the language for item_name is the site default language (crucial for when we read/go to third-party sales systems and use the item_name as a key).
     * @param  ?ID_TEXT $search Product being searched for (null: none).
     * @param  boolean $search_item_names Whether $search refers to the item name rather than the product codename.
     * @return array A map of product name to list of product details.
     */
    public function get_products($site_lang = false, $search = null, $search_item_names = false)
    {
        require_lang('ecommerce');

        $products = array();

        $initial_quota = intval(get_option('initial_quota'));
        $max_quota = intval(get_option('max_quota'));

        // It's slightly naughty for us to use get_member(), but it's only for something going into item_description so safe
        $current_amount = $initial_quota;
        $quota_increase_rows = $GLOBALS['SITE_DB']->query_select('ecom_sales', array('details2'), array('member_id' => get_member(), 'details' => do_lang('QUOTA', null, null, null, get_site_default_lang())));
        foreach ($quota_increase_rows as $quota_increase_row) {
            $current_amount += intval($quota_increase_row['details2']);
        }

        foreach (array(100, 1000, 2000, 5000, 10000) as $amount) {
            if ($max_quota < $amount) {
                continue;
            }

            $products['QUOTA_' . strval($amount)] = array(
                'item_name' => do_lang('PURCHASE_QUOTA', integer_format($amount), integer_format($current_amount), integer_format($current_amount + $amount), $site_lang ? get_site_default_lang() : user_lang()),
                'item_description' => do_lang_tempcode('PURCHASE_QUOTA_DESCRIPTION', escape_html(integer_format($amount)), escape_html(integer_format($current_amount)), escape_html(integer_format($current_amount + $amount))),
                'item_image_url' => find_theme_image('icons/48x48/menu/_generic_admin/add_to_category'),

                'type' => PRODUCT_PURCHASE,
                'type_special_details' => array(),

                'price' => null,
                'currency' => get_option('currency'),
                'price_points' => intval(get_option('quota')) * $amount,
                'discount_points__num_points' => null,
                'discount_points__price_reduction' => null,

                'needs_shipping_address' => false,
            );
        }

        foreach (array('pop3' => 'POP3', 'forw' => 'FORWARDING') as $protocol => $protocol_label) {
            $rows = $GLOBALS['SITE_DB']->query('SELECT * FROM ' . get_table_prefix() . 'ecom_prods_prices WHERE name LIKE \'' . db_encode_like($protocol . '\_%') . '\'');
            foreach ($rows as $row) {
                $domain = substr($row['name'], strlen($protocol . '_'));

                switch ($protocol) {
                    case 'forw':
                        $image_url = find_theme_image('icons/48x48/buttons/redirect');
                        break;

                    case 'pop3':
                        $image_url = find_theme_image('icons/48x48/menu/_generic_admin/add_one');
                        break;

                    default:
                        $image_url = '';
                }

                $products[$protocol_label . '_' . $domain] = array(
                    'item_name' => do_lang('TITLE_NEW' . $protocol_label, $domain, integer_format($initial_quota), null, $site_lang ? get_site_default_lang() : user_lang()),
                    'item_description' => do_lang_tempcode('NEW' . $protocol_label . '_DESCRIPTION', escape_html($domain), escape_html(integer_format($initial_quota))),
                    'item_image_url' => $image_url,

                    'type' => PRODUCT_PURCHASE,
                    'type_special_details' => array(),

                    'price' => null,
                    'currency' => get_option('currency'),
                    'price_points' => $row['price'],
                    'discount_points__num_points' => null,
                    'discount_points__price_reduction' => null,

                    'needs_shipping_address' => false,
                );
            }
        }

        return $products;
    }

    /**
     * Check whether the product codename is available for purchase by the member.
     *
     * @param  ID_TEXT $type_code The product codename.
     * @param  MEMBER $member_id The member we are checking against.
     * @param  integer $req_quantity The number required.
     * @param  boolean $must_be_listed Whether the product must be available for public listing.
     * @return integer The availability code (a ECOMMERCE_PRODUCT_* constant).
     */
    public function is_available($type_code, $member_id, $req_quantity = 1, $must_be_listed = false)
    {
        if (is_guest($member_id)) {
            return ECOMMERCE_PRODUCT_NO_GUESTS;
        }

        switch (preg_replace('#\_.*$#', '', $type_code)) {
            case 'POP3':
                if (get_option('is_on_pop3_buy') == '1') {
                    return ECOMMERCE_PRODUCT_DISABLED;
                }

                if ($GLOBALS['SITE_DB']->query_value_if_there('SELECT COUNT(*) FROM ' . get_table_prefix() . 'ecom_prods_prices WHERE name LIKE \'' . db_encode_like('pop3\_%') . '\'') == 0) {
                    return ECOMMERCE_PRODUCT_MISSING;
                }

                $test = $GLOBALS['SITE_DB']->query_select_value_if_there('ecom_sales s JOIN ' . get_table_prefix() . 'ecom_transactions t ON t.id=s.transaction_id', 'details', array('member_id' => $member_id), ' AND t_type_code LIKE \'POP3%\'');
                if ($test !== null) {
                    return ECOMMERCE_PRODUCT_ALREADY_HAS;
                }

                break;

            case 'QUOTA':
                $test = $GLOBALS['SITE_DB']->query_select_value_if_there('ecom_sales s JOIN ' . get_table_prefix() . 'ecom_transactions t ON t.id=s.transaction_id', 'details', array('member_id' => $member_id), ' AND t_type_code LIKE \'POP3%\'');
                if ($test === null) {
                    return ECOMMERCE_PRODUCT_PROHIBITED;
                }

                break;

            case 'FORWARDING':
                if (get_option('is_on_forw_buy') == '1') {
                    return ECOMMERCE_PRODUCT_DISABLED;
                }

                if ($GLOBALS['SITE_DB']->query_value_if_there('SELECT COUNT(*) FROM ' . get_table_prefix() . 'ecom_prods_prices WHERE name LIKE \'' . db_encode_like('forw\_%') . '\'') == 0) {
                    return ECOMMERCE_PRODUCT_MISSING;
                }

                break;
        }

        return ECOMMERCE_PRODUCT_AVAILABLE;
    }

    /**
     * Get fields that need to be filled in in the purchasing module.
     *
     * @param  ID_TEXT $type_code The product codename.
     * @return ?array A triple: The fields (null: none), The text (null: none), The JavaScript (null: none).
     */
    public function get_needed_fields($type_code)
    {
        require_lang('ecommerce');

        $fields = new Tempcode();

        $member_id = get_member();

        switch (preg_replace('#\_.*$#', '', $type_code)) {
            case 'POP3':
                $domain = preg_replace('#^.*\_#', '', $type_code);
                $fields->attach(form_input_line(do_lang_tempcode('ADDRESS_DESIRED_STUB'), do_lang_tempcode('DESCRIPTION_ADDRESS_DESIRED_STUB', escape_html($domain)), 'email_prefix', $GLOBALS['FORUM_DRIVER']->get_username(get_member()), true));
                $fields->attach(form_input_password(do_lang_tempcode('PASSWORD'), '', 'pass1', true));
                $fields->attach(form_input_password(do_lang_tempcode('CONFIRM_PASSWORD'), '', 'pass2', true));

                $javascript = "
                    var form=document.getElementById('pass1').form;
                    form.old_submit=form.onsubmit;
                    form.onsubmit=function() {
                        if ((form.elements['pass1'].value!=form.elements['pass2'].value))
                        {
                            window.fauxmodal_alert('" . php_addslashes(do_lang('PASSWORD_MISMATCH')) . "');
                            return false;
                        }
                        if (typeof form.old_submit!='undefined' && form.old_submit) return form.old_submit();
                        return true;
                    };
                ";

                break;

            case 'QUOTA':
                $fields = null;
                $javascript = null;

                break;

            case 'FORWARDING':
                $domain = preg_replace('#^.*\_#', '', $type_code);
                $fields->attach(form_input_line(do_lang_tempcode('ADDRESS_DESIRED_STUB'), do_lang_tempcode('DESCRIPTION_ADDRESS_DESIRED_STUB', escape_html($domain)), 'email_prefix', $GLOBALS['FORUM_DRIVER']->get_username(get_member()), true));
                $fields->attach(form_input_line(do_lang_tempcode('ADDRESS_CURRENT'), '', 'email', $GLOBALS['FORUM_DRIVER']->get_member_email_address($member_id), true));

                $javascript = null;

                break;
        }

        return array($fields, null, $javascript);
    }

    /**
     * Get the filled in fields and do something with them.
     *
     * @param  ID_TEXT $type_code The product codename.
     * @return array A pair: The purchase ID, a confirmation box to show (null no specific confirmation).
     */
    public function handle_needed_fields($type_code)
    {
        require_lang('ecommerce');

        $member_id = get_member();

        switch (preg_replace('#\_.*$#', '', $type_code)) {
            case 'POP3':
                $suffix = preg_replace('#^POP3\_#', '', $type_code);

                $prefix = post_param_string('email_prefix');
                $pass1 = post_param_string('pass1');
                $pass2 = post_param_string('pass2');

                if ($pass1 != $pass2) {
                    warn_exit(do_lang_tempcode('PASSWORD_MISMATCH'));
                }

                // Does the prefix contain valid characters?
                require_code('type_sanitisation');
                if (!is_email_address($prefix . '@' . $suffix)) {
                    warn_exit(do_lang_tempcode('INVALID_EMAIL_PREFIX'));
                }

                $this->_ecom_product_handle_error_taken($prefix, $suffix);

                $e_details = json_encode(array($member_id, $prefix, $pass1));

                break;

            case 'QUOTA':
                return array(strval(get_member()), null);

            case 'FORWARDING':
                $suffix = preg_replace('#^FORWARDING\_#', '', $type_code);

                $email = post_param_string('email');
                $prefix = post_param_string('email_prefix');

                // Does the prefix contain valid characters?
                require_code('type_sanitisation');
                if (!is_email_address($prefix . '@' . $suffix)) {
                    warn_exit(do_lang_tempcode('INVALID_EMAIL_PREFIX'));
                }

                // Is the email for things to be forwarded to valid?
                if (!is_email_address($email)) {
                    warn_exit(do_lang_tempcode('INVALID_EMAIL_ADDRESS'));
                }

                $this->_ecom_product_handle_error_taken($prefix, $suffix);

                $e_details = json_encode(array($member_id, $email, $prefix));

                break;
        }

        $purchase_id = strval($GLOBALS['SITE_DB']->query_insert('ecom_sales_expecting', array('e_details' => $e_details, 'e_time' => time()), true));
        return array($purchase_id, null);
    }

    /**
     * Check to see if the specified e-mail address has already been purchased. If so, spawn an error message.
     *
     * @param  ID_TEXT $prefix The prefix (mailbox name)
     * @param  ID_TEXT $suffix The suffix (domain name)
     */
    protected function _ecom_product_handle_error_taken($prefix, $suffix)
    {
        // Has this email address been taken?
        $taken = $GLOBALS['SITE_DB']->query_select_value_if_there('ecom_sales s JOIN ' . get_table_prefix() . 'ecom_transactions t ON t.id=s.transaction_id', 'details', array('details' => $prefix, 'details2' => '@' . $suffix), ' AND (t_type_code LIKE \'POP3%\' OR t_type_code LIKE \'FORW%\')');
        if ($taken !== null) {
            warn_exit(do_lang_tempcode('EMAIL_TAKEN'));
        }
    }

    /**
     * Handling of a product purchase change state.
     *
     * @param  ID_TEXT $type_code The product codename.
     * @param  ID_TEXT $purchase_id The purchase ID.
     * @param  array $details Details of the product, with added keys: TXN_ID, PAYMENT_STATUS, ORDER_STATUS.
     */
    public function actualiser($type_code, $purchase_id, $details)
    {
        if ($details['PAYMENT_STATUS'] != 'Completed') {
            return;
        }

        require_lang('ecommerce');

        switch (preg_replace('#\_.*$#', '', $type_code)) {
            case 'POP3':
                $suffix = preg_replace('#^POP3\_#', '', $type_code);

                $e_details = $GLOBALS['SITE_DB']->query_select_value('ecom_sales_expecting', 'e_details', array('id' => intval($purchase_id)));
                list($member_id, $prefix, $password) = json_decode($e_details);

                $this->_ecom_product_handle_error_taken($prefix, $suffix);

                $sale_id = $GLOBALS['SITE_DB']->query_insert('ecom_sales', array('date_and_time' => time(), 'member_id' => $member_id, 'details' => $prefix, 'details2' => '@' . $suffix, 'transaction_id' => $details['TXN_ID']), true);

                $mail_server = get_option('mail_server');
                $pop3_url = get_option('pop_url');
                $initial_quota = intval(get_option('initial_quota'));
                $login = $prefix . '@' . $suffix;
                $email = $GLOBALS['FORUM_DRIVER']->get_member_email_address($member_id);
                $encoded_reason = do_lang('TITLE_NEWPOP3');
                require_code('notifications');
                $message_raw = do_notification_template('ECOM_PRODUCT_POP3_MAIL', array(
                    '_GUID' => '19022c49d0bdde39735245850d04fca7',
                    'EMAIL' => $email,
                    'ENCODED_REASON' => $encoded_reason,
                    'LOGIN' => $login,
                    'QUOTA' => integer_format($initial_quota),
                    'MAIL_SERVER' => $mail_server,
                    'PASSWORD' => $password,
                    'PREFIX' => $prefix,
                    'SUFFIX' => $suffix,
                    'POP3_URL' => $pop3_url,
                ), null, false, null, '.txt', 'text');
                dispatch_notification('ecom_product_request_pop3', 'pop3_' . strval($sale_id), do_lang('MAIL_REQUEST_POP3', null, null, null, get_site_default_lang()), $message_raw->evaluate(get_site_default_lang()), null, null, 3, true, false, null, null, '', '', '', '', null, true);

                $result = do_lang_tempcode('ORDER_POP3_DONE', escape_html($prefix . '@' . $suffix));
                global $ECOMMERCE_SPECIAL_SUCCESS_MESSAGE;
                $ECOMMERCE_SPECIAL_SUCCESS_MESSAGE = $result;

                break;

            case 'QUOTA':
                $member_id = intval($purchase_id);

                $pop3_details = $GLOBALS['SITE_DB']->query_select('ecom_sales s JOIN ' . get_table_prefix() . 'ecom_transactions t ON t.id=s.transaction_id', array('details', 'details2'), array('member_id' => $member_id), ' AND t_type_code LIKE \'POP3%\'', 1);
                if (!array_key_exists(0, $pop3_details)) {
                    warn_exit(do_lang_tempcode('INTERNAL_ERROR'));
                }

                $prefix = $pop3_details[0]['details'];
                $suffix = $pop3_details[0]['details2'];

                $quota = intval(preg_replace('#^QUOTA\_#', '', $type_code));

                $sale_id = $GLOBALS['SITE_DB']->query_insert('ecom_sales', array('date_and_time' => time(), 'member_id' => $member_id, 'details' => do_lang('QUOTA', null, null, null, get_site_default_lang()), 'details2' => strval($quota), 'transaction_id' => $details['TXN_ID']), true);

                $quota_url = get_option('quota_url');
                $encoded_reason = do_lang('TITLE_QUOTA');
                require_code('notifications');
                $message_raw = do_notification_template('ECOM_PRODUCT_QUOTA_MAIL', array(
                    '_GUID' => '5a4e0bb5e53e6ccf8e57581c377557f4',
                    'ENCODED_REASON' => $encoded_reason,
                    'QUOTA' => integer_format($quota),
                    'EMAIL' => $prefix . $suffix,
                    'QUOTA_URL' => $quota_url,
                ), null, false, null, '.txt', 'text');
                dispatch_notification('ecom_request_quota', 'quota_' . uniqid('', true), do_lang('MAIL_REQUEST_QUOTA', null, null, null, get_site_default_lang()), $message_raw->evaluate(get_site_default_lang()), null, null, 3, true, false, null, null, '', '', '', '', null, true);

                $result = do_lang_tempcode('ORDER_QUOTA_DONE');
                global $ECOMMERCE_SPECIAL_SUCCESS_MESSAGE;
                $ECOMMERCE_SPECIAL_SUCCESS_MESSAGE = $result;

                break;

            case 'FORWARDING':
                $suffix = preg_replace('#^FORWARDING\_#', '', $type_code);

                $e_details = $GLOBALS['SITE_DB']->query_select_value('ecom_sales_expecting', 'e_details', array('id' => intval($purchase_id)));
                list($member_id, $email, $prefix) = json_decode($e_details);

                $this->_ecom_product_handle_error_taken($prefix, $suffix);

                $sale_id = $GLOBALS['SITE_DB']->query_insert('ecom_sales', array('date_and_time' => time(), 'member_id' => $member_id, 'details' => $prefix, 'details2' => '@' . $suffix, 'transaction_id' => $details['TXN_ID']), true);

                $forw_url = get_option('forw_url');
                require_code('notifications');
                $encoded_reason = do_lang('TITLE_NEWFORWARDING');
                $message_raw = do_notification_template('ECOM_PRODUCT_FORWARDER_MAIL', array(
                    '_GUID' => 'a09dba8b440baa5cd48d462ebfafd15f',
                    'ENCODED_REASON' => $encoded_reason,
                    'EMAIL' => $email,
                    'PREFIX' => $prefix,
                    'SUFFIX' => $suffix,
                    'FORW_URL' => $forw_url,
                ), null, false, null, '.txt', 'text');
                dispatch_notification('ecom_product_request_forwarding', 'forw_' . strval($sale_id), do_lang('MAIL_REQUEST_FORWARDING', null, null, null, get_site_default_lang()), $message_raw->evaluate(get_site_default_lang()), null, null, 3, true, false, null, null, '', '', '', '', null, true);

                $result = do_lang_tempcode('ORDER_FORWARDER_DONE', $email, escape_html($prefix . '@' . $suffix));
                global $ECOMMERCE_SPECIAL_SUCCESS_MESSAGE;
                $ECOMMERCE_SPECIAL_SUCCESS_MESSAGE = $result;

                break;
        }
    }

    /**
     * Get the member who made the purchase.
     *
     * @param  ID_TEXT $type_code The product codename.
     * @param  ID_TEXT $purchase_id The purchase ID.
     * @return ?MEMBER The member ID (null: none).
     */
    public function member_for($type_code, $purchase_id)
    {
        switch (preg_replace('#\_.*$#', '', $type_code)) {
            case 'POP3':
            case 'FORWARDING':
                $e_details = $GLOBALS['SITE_DB']->query_select_value('ecom_sales_expecting', 'e_details', array('id' => intval($purchase_id)));
                list($member_id) = json_decode($e_details);
                return $member_id;

            case 'QUOTA':
                return intval($purchase_id);
        }

        return null;
    }
}