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
class Hook_payment_gateway_worldpay
{
    // This is the Hosted Payment Pages API http://support.worldpay.com/support/kb/gg/hpp/Content/Home.htm
    // Requires:
    //  the "Payment Response URL" set in control panel should be set to "http://<WPDISPLAY ITEM=MC_callback>"
    //  the "Payment Response enabled?" and "Enable Recurring Payment Response" and "Enable the Shopper Response" should all be ticked (checked)
    //  the "Payment Response password" is the Composr "Callback password" option; it may be blank
    //  the "Installation ID" (a number given to you) is the Composr "Gateway username" option and also "Testing mode gateway username" option (it's all the same installation ID)
    //  the "MD5 secret for transactions" is the Composr "Gateway digest code" option; it may be blank
    //  the account must be set as 'live' in control panel once testing is done
    //  the "Shopper Redirect URL" should be left blank - arbitrary URLs are not supported, and Composr automatically injects a redirect response into Payment Response URL
    //  Logos, refund policies, and contact details [e-mail, phone, postal], may need coding into the templates (Worldpay have policies and checks). ECOM_LOGOS_WORLDPAY.tpl is included into the payment process automatically and does much of this
    //  FuturePay must be enabled for subscriptions to work (contact WorldPay about it)

    /**
     * Find a transaction fee from a transaction amount. Regular fees aren't taken into account.
     *
     * @param  float $amount A transaction amount.
     * @return float The fee.
     */
    public function get_transaction_fee($amount)
    {
        return 0.045 * $amount; // for credit card. Debit card is a flat 50p
    }

    /**
     * Get the gateway username.
     *
     * @return string The answer.
     */
    protected function _get_username()
    {
        return ecommerce_test_mode() ? get_option('payment_gateway_test_username') : get_option('payment_gateway_username');
    }

    /**
     * Get the remote form URL.
     *
     * @return URLPATH The remote form URL.
     */
    protected function _get_remote_form_url()
    {
        return 'https://' . (ecommerce_test_mode() ? 'select-test' : 'select') . '.worldpay.com/wcc/purchase';
    }

    /**
     * Get the card/gateway logos and other gateway-required details.
     *
     * @return Tempcode The stuff.
     */
    public function get_logos()
    {
        $inst_id = $this->_get_username();
        $address = str_replace("\n", '<br />', escape_html(get_option('pd_address')));
        $email = get_option('pd_email');
        $number = get_option('pd_number');
        return do_template('ECOM_LOGOS_WORLDPAY', array('_GUID' => '4b3254b330b3b1719d66d2b754c7a8c8', 'INST_ID' => $inst_id, 'PD_ADDRESS' => $address, 'PD_EMAIL' => $email, 'PD_NUMBER' => $number));
    }

    /**
     * Generate a transaction ID.
     *
     * @return string A transaction ID.
     */
    public function generate_trans_id()
    {
        require_code('crypt');
        return get_rand_password();
    }

    /**
     * Make a transaction (payment) button.
     *
     * @param  ID_TEXT $type_code The product codename.
     * @param  SHORT_TEXT $item_name The human-readable product title.
     * @param  ID_TEXT $purchase_id The purchase ID.
     * @param  float $amount A transaction amount.
     * @param  ID_TEXT $currency The currency to use.
     * @return Tempcode The button.
     */
    public function make_transaction_button($type_code, $item_name, $purchase_id, $amount, $currency)
    {
        $username = $this->_get_username();
        $form_url = $this->_get_remote_form_url();
        $email_address = $GLOBALS['FORUM_DRIVER']->get_member_email_address(get_member());
        $trans_id = $this->generate_trans_id();
        $digest_option = get_option('payment_gateway_digest');
        //$digest = md5((($digest_option == '') ? ($digest_option . ':') : '') . $trans_id . ':' . float_to_raw_string($amount) . ':' . $currency);  Deprecated
        $digest = md5((($digest_option == '') ? ($digest_option . ':') : '') . ';' . 'cartId:amount:currency;' . $trans_id . ';' . float_to_raw_string($amount) . ';' . $currency);

        // No 'custom' field for gateway to encode $purchase_id next to $item_name, so we need to pass through a single transaction ID
        $GLOBALS['SITE_DB']->query_insert('trans_expecting', array(
            'id' => $trans_id,
            'e_type_code' => $type_code,
            'e_purchase_id' => $purchase_id,
            'e_item_name' => $item_name,
            'e_member_id' => get_member(),
            'e_amount' => float_to_raw_string($amount),
            'e_currency' => $currency,
            'e_ip_address' => get_ip_address(),
            'e_session_id' => get_session_id(),
            'e_time' => time(),
            'e_length' => null,
            'e_length_units' => '',
        ));

        return do_template('ECOM_TRANSACTION_BUTTON_VIA_WORLDPAY', array(
            '_GUID' => '56c78a4e16c0e7f36fcfbe57d37bc3d3',
            'TYPE_CODE' => $type_code,
            'ITEM_NAME' => $item_name,
            'PURCHASE_ID' => $purchase_id,
            'TRANS_ID' => $trans_id,
            'DIGEST' => $digest,
            'TEST_MODE' => ecommerce_test_mode(),
            'AMOUNT' => float_to_raw_string($amount),
            'CURRENCY' => $currency,
            'USERNAME' => $username,
            'FORM_URL' => $form_url,
            'EMAIL_ADDRESS' => $email_address,
            'MEMBER_ADDRESS' => $this->_build_member_address(),
        ));
    }

    /**
     * Make a subscription (payment) button.
     *
     * @param  ID_TEXT $type_code The product codename.
     * @param  SHORT_TEXT $item_name The human-readable product title.
     * @param  ID_TEXT $purchase_id The purchase ID.
     * @param  float $amount A transaction amount.
     * @param  integer $length The subscription length in the units.
     * @param  ID_TEXT $length_units The length units.
     * @set    d w m y
     * @param  ID_TEXT $currency The currency to use.
     * @return Tempcode The button.
     */
    public function make_subscription_button($type_code, $item_name, $purchase_id, $amount, $length, $length_units, $currency)
    {
        // https://support.worldpay.com/support/kb/bg/recurringpayments/rpfp.html

        $username = $this->_get_username();
        $form_url = $this->_get_remote_form_url();
        $trans_id = $this->generate_trans_id();
        $length_units_2 = '1';
        $first_repeat = time();
        switch ($length_units) {
            case 'd':
                $length_units_2 = '1';
                $first_repeat = 60 * 60 * 24 * $length;
                break;
            case 'w':
                $length_units_2 = '2';
                $first_repeat = 60 * 60 * 24 * 7 * $length;
                break;
            case 'm':
                $length_units_2 = '3';
                $first_repeat = 60 * 60 * 24 * 31 * $length;
                break;
            case 'y':
                $length_units_2 = '4';
                $first_repeat = 60 * 60 * 24 * 365 * $length;
                break;
        }
        $digest_option = get_option('payment_gateway_digest');
        //$digest = md5((($digest_option == '') ? ($digest_option . ':') : '') . $trans_id . ':' . float_to_raw_string($amount) . ':' . $currency . $length_units_2 . strval($length));   Deprecated
        $digest = md5((($digest_option == '') ? ($digest_option . ':') : '') . ';' . 'cartId:amount:currency:intervalUnit:intervalMult;' . $trans_id . ';' . float_to_raw_string($amount) . ';' . $currency . $length_units_2 . strval($length));

        // No 'custom' field for gateway to encode $purchase_id next to $item_name, so we need to pass through a single transaction ID
        $GLOBALS['SITE_DB']->query_insert('trans_expecting', array(
            'id' => $trans_id,
            'e_type_code' => $type_code,
            'e_purchase_id' => $purchase_id,
            'e_item_name' => $item_name,
            'e_member_id' => get_member(),
            'e_amount' => float_to_raw_string($amount),
            'e_currency' => $currency,
            'e_ip_address' => get_ip_address(),
            'e_session_id' => get_session_id(),
            'e_time' => time(),
            'e_length' => null,
            'e_length_units' => '',
        ));

        return do_template('ECOM_SUBSCRIPTION_BUTTON_VIA_WORLDPAY', array(
            '_GUID' => '1f88716137762a467edbf5fbb980c6fe',
            'TYPE_CODE' => $type_code,
            'ITEM_NAME' => $item_name,
            'PURCHASE_ID' => $purchase_id,
            'TRANS_ID' => $trans_id,
            'DIGEST' => $digest,
            'TEST' => ecommerce_test_mode(),
            'LENGTH' => strval($length),
            'LENGTH_UNITS_2' => $length_units_2,
            'AMOUNT' => float_to_raw_string($amount),
            'FIRST_REPEAT' => date('Y-m-d', $first_repeat),
            'CURRENCY' => $currency,
            'USERNAME' => $username,
            'FORM_URL' => $form_url,
            'MEMBER_ADDRESS' => $this->_build_member_address(),
        ));
    }

    /**
     * Get a member address/etc for use in payment buttons.
     *
     * @return array A map of member address details (form field name => address value).
     */
    protected function _build_member_address()
    {
        $member_address = array();
        if (!is_guest()) {
            $member_address['name'] = trim(get_cms_cpf('firstname') . ' ' . get_cms_cpf('lastname'));
            $address_lines = explode("\n", get_cms_cpf('street_address'));
            $member_address['address1'] = $address_lines[0];
            $member_address['address2'] = $address_lines[1];
            unset($address_lines[1]);
            unset($address_lines[0]);
            $member_address['address3'] = implode(', ', $address_lines);
            $member_address['town'] = get_cms_cpf('city');
            $member_address['region'] = get_cms_cpf('state');
            $member_address['postcode'] = get_cms_cpf('post_code');
            $member_address['country'] = get_cms_cpf('country');
            $member_address['tel'] = get_cms_cpf('mobile_phone_number');
            $member_address['email'] = $GLOBALS['FORUM_DRIVER']->get_member_email_address(get_member());
        }
        return $member_address;
    }

    /**
     * Make a subscription cancellation button.
     *
     * @param  ID_TEXT $purchase_id The purchase ID.
     * @return Tempcode The button.
     */
    public function make_cancel_button($purchase_id)
    {
        return do_template('ECOM_SUBSCRIPTION_CANCEL_BUTTON_VIA_WORLDPAY', array('_GUID' => '187fba57424e7850b9e21fc147de48eb', 'PURCHASE_ID' => $purchase_id));
    }

    /**
     * Handle IPN's. The function may produce output, which would be returned to the Payment Gateway. The function may do transaction verification.
     *
     * @param  boolean $silent_fail Return null on failure rather than showing any error message. Used when not sure a valid & finalised transaction is in the POST environment, but you want to try just in case (e.g. on a redirect back from the gateway).
     * @return ?array A long tuple of collected data. Emulates some of the key variables of the PayPal IPN response (null: no transaction; will only return null when $silent_fail is set).
     */
    public function handle_ipn_transaction($silent_fail)
    {
        // http://support.worldpay.com/support/kb/bg/paymentresponse/pr0000.html

        $code = post_param_string('transStatus');
        if ($code == 'C') {
            if ($silent_fail) {
                return null;
            }
            exit(); // Cancellation signal, won't process
        }

        $txn_id = post_param_string('transId');
        $cart_id = post_param_string('cartId');
        if (post_param_string('futurePayType', '') == 'regular') {
            $is_subscription = true;
        } else {
            $is_subscription = false;
        }

        $trans_expecting_rows = $GLOBALS['SITE_DB']->query_select('trans_expecting', array('*'), array('id' => $cart_id), '', 1);
        if (!array_key_exists(0, $trans_expecting_rows)) {
            if ($silent_fail) {
                return null;
            }
            warn_exit(do_lang_tempcode('MISSING_RESOURCE'));
        }
        $trans_expecting_row = $trans_expecting_rows[0];

        $item_name = $is_subscription ? '' : $trans_expecting_row['e_item_name'];
        $purchase_id = $trans_expecting_row['e_purchase_id'];

        $success = ($code == 'Y');
        $message = post_param_string('rawAuthMessage');

        $payment_status = $success ? 'Completed' : 'Failed';
        $reason_code = '';
        $pending_reason = '';
        $memo = '';
        $mc_gross = post_param_string('authAmount');
        $mc_currency = post_param_string('authCurrency');
        $parent_txn_id = '';
        $period = '';

        // SECURITY: Check password
        if (post_param_string('callbackPW') != get_option('payment_gateway_callback_password')) {
            if ($silent_fail) {
                return null;
            }
            fatal_ipn_exit(do_lang('IPN_UNVERIFIED') . ' - ' . flatten_slashed_array($_POST, true));
        }

        if (addon_installed('shopping')) {
            if ($trans_expecting_row['e_type_code'] == 'cart_orders') {
                $this->store_shipping_address(intval($purchase_id));
            }
        }

        return array($purchase_id, $item_name, $payment_status, $reason_code, $pending_reason, $memo, $mc_gross, $mc_currency, $txn_id, $parent_txn_id, $period, $trans_expecting_row['e_member_id']);
    }

    /**
     * Show a payment response after IPN runs (for hooks that handle redirects in this way).
     *
     * @param  ID_TEXT $type_code The product codename.
     * @param  ID_TEXT $purchase_id Purchase ID.
     * @return string The response.
     */
    public function show_payment_response($type_code, $purchase_id)
    {
        $txn_id = post_param_string('transId');
        $message = do_lang('TRANSACTION_ID_WRITTEN', $txn_id);
        $url = build_url(array('page' => 'purchase', 'type' => 'finish', 'type_code' => $type_code, 'message' => $message, 'from' => 'worldpay'), get_module_zone('purchase'));
        return '<meta http-equiv="refresh" content="0;url=' . escape_html($url->evaluate()) . '" />';
    }

    /**
     * Store shipping address for orders.
     *
     * @param  AUTO_LINK $order_id Order ID
     * @return ?mixed Address ID (null: No address record found).
     */
    protected function store_shipping_address($order_id)
    {
        if ($GLOBALS['SITE_DB']->query_select_value_if_there('shopping_order_addresses', 'id', array('a_order_id' => $order_id)) === null) {
            $_name = explode(' ', post_param_string('delvName', ''));
            $name = array();
            if (count($_name) > 0) {
                $name[1] = $_name[count($_name) - 1];
                unset($_name[count($_name) - 1]);
            }
            $name[0] = implode(' ', $_name);

            $shipping_address = array(
                'a_order_id' => $order_id,
                'a_firstname' => $name[0],
                'a_lastname' => $name[1],
                'a_street_address' => trim(post_param_string('delvAddress1', '') . ' ' . post_param_string('delvAddress2', '') . ' ' . post_param_string('delvAddress3', '')),
                'a_city' => post_param_string('city', ''),
                'a_county' => '',
                'a_state' => '',
                'a_post_code' => post_param_string('delvPostcode', ''),
                'a_country' => post_param_string('delvCountryString', ''),
                'a_email' => post_param_string('email', ''),
                'a_phone' => post_param_string('tel', ''),
            );
            return $GLOBALS['SITE_DB']->query_insert('shopping_order_addresses', $shipping_address, true);
        }

        return null;
    }

    /**
     * Find whether the hook auto-cancels (if it does, auto cancel the given subscription).
     *
     * @param  AUTO_LINK $subscription_id ID of the subscription to cancel.
     * @return ?boolean True: yes. False: no. (null: cancels via a user-URL-directioning)
     */
    public function auto_cancel($subscription_id)
    {
        // They created a username and password initially. They need to login using this at https://futurepay.worldpay.com/fp/jsp/common/login_shopper.jsp

        return false;
    }
}