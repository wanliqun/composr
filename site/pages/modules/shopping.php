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
 * @package    shopping
 */

/**
 * Module page class.
 */
class Module_shopping
{
    /**
     * Find details of the module.
     *
     * @return ?array Map of module info (null: module is disabled).
     */
    public function info()
    {
        $info = array();
        $info['author'] = 'Manuprathap';
        $info['organisation'] = 'ocProducts';
        $info['hacked_by'] = null;
        $info['hack_version'] = null;
        $info['version'] = 8;
        $info['update_require_upgrade'] = true;
        $info['locked'] = false;
        return $info;
    }

    /**
     * Uninstall the module.
     */
    public function uninstall()
    {
        $GLOBALS['SITE_DB']->drop_table_if_exists('shopping_cart');
        $GLOBALS['SITE_DB']->drop_table_if_exists('shopping_order_details');
        $GLOBALS['SITE_DB']->drop_table_if_exists('shopping_order');
        $GLOBALS['SITE_DB']->drop_table_if_exists('shopping_logging');
        $GLOBALS['SITE_DB']->drop_table_if_exists('shopping_order_addresses');

        $GLOBALS['SITE_DB']->query_delete('group_category_access', array('module_the_name' => 'shopping'));
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
            $GLOBALS['SITE_DB']->create_table('shopping_cart', array(
                'id' => '*AUTO',
                'session_id' => 'ID_TEXT',
                'ordered_by' => 'MEMBER',
                'product_id' => 'AUTO_LINK',
                'product_name' => 'SHORT_TEXT',
                'product_code' => 'SHORT_TEXT',
                'quantity' => 'INTEGER',
                'price_pre_tax' => 'REAL',
                'price' => 'REAL',
                'product_description' => 'LONG_TEXT',
                'product_type' => 'SHORT_TEXT',
                'product_weight' => 'REAL',
                'is_deleted' => 'BINARY', // Indicates an item is no longer in the cart because the user deleted it
            ));
            $GLOBALS['SITE_DB']->create_index('shopping_cart', 'ordered_by', array('ordered_by'));
            $GLOBALS['SITE_DB']->create_index('shopping_cart', 'session_id', array('session_id'));
            $GLOBALS['SITE_DB']->create_index('shopping_cart', 'product_id', array('product_id'));

            // Cart contents turns into order + details...

            $GLOBALS['SITE_DB']->create_table('shopping_order', array(
                'id' => '*AUTO',
                'c_member' => 'INTEGER',
                'session_id' => 'ID_TEXT',
                'add_date' => 'TIME',
                'tot_price' => 'REAL',
                'order_status' => 'ID_TEXT', // ORDER_STATUS_[awaiting_payment|payment_received|onhold|dispatched|cancelled|returned]
                'notes' => 'LONG_TEXT',
                'transaction_id' => 'SHORT_TEXT',
                'purchase_through' => 'SHORT_TEXT',
                'tax_opted_out' => 'BINARY',
            ));
            $GLOBALS['SITE_DB']->create_index('shopping_order', 'finddispatchable', array('order_status'));
            $GLOBALS['SITE_DB']->create_index('shopping_order', 'soc_member', array('c_member'));
            $GLOBALS['SITE_DB']->create_index('shopping_order', 'sosession_id', array('session_id'));
            $GLOBALS['SITE_DB']->create_index('shopping_order', 'soadd_date', array('add_date'));

            $GLOBALS['SITE_DB']->create_table('shopping_order_details', array(
                'id' => '*AUTO',
                'order_id' => '?AUTO_LINK',
                'p_id' => '?AUTO_LINK',
                'p_name' => 'SHORT_TEXT',
                'p_code' => 'SHORT_TEXT',
                'p_type' => 'SHORT_TEXT',
                'p_quantity' => 'INTEGER',
                'p_price' => 'REAL',
                'included_tax' => 'REAL',
                'dispatch_status' => 'SHORT_TEXT'
            ));
            $GLOBALS['SITE_DB']->create_index('shopping_order_details', 'p_id', array('p_id'));
            $GLOBALS['SITE_DB']->create_index('shopping_order_details', 'order_id', array('order_id'));

            $GLOBALS['SITE_DB']->create_table('shopping_logging', array(
                'id' => '*AUTO',
                'e_member_id' => '*MEMBER',
                'session_id' => 'ID_TEXT',
                'ip' => 'IP',
                'last_action' => 'SHORT_TEXT',
                'date_and_time' => 'TIME'
            ));
            $GLOBALS['SITE_DB']->create_index('shopping_logging', 'calculate_bandwidth', array('date_and_time'));

            $GLOBALS['SITE_DB']->create_table('shopping_order_addresses', array(
                // These are filled after an order is made (maybe via what comes back from IPN, maybe from what is set for a local payment), and presented in the admin orders UI
                'id' => '*AUTO',
                'a_order_id' => '?AUTO_LINK',
                'a_firstname' => 'SHORT_TEXT', // NB: May be full-name, or include company name
                'a_lastname' => 'SHORT_TEXT',
                'a_street_address' => 'LONG_TEXT',
                'a_city' => 'SHORT_TEXT',
                'a_county' => 'SHORT_TEXT',
                'a_state' => 'SHORT_TEXT',
                'a_post_code' => 'SHORT_TEXT',
                'a_country' => 'SHORT_TEXT',
                'a_email' => 'SHORT_TEXT',
                'a_phone' => 'SHORT_TEXT',
            ));
            $GLOBALS['SITE_DB']->create_index('shopping_order_addresses', 'order_id', array('a_order_id'));
        }

        if ((!is_null($upgrade_from)) && ($upgrade_from < 7)) {
            $GLOBALS['SITE_DB']->add_table_field('shopping_order_addresses', 'a_contact_phone', 'SHORT_TEXT');
            $GLOBALS['SITE_DB']->add_table_field('shopping_order_addresses', 'a_address_state', 'SHORT_TEXT');
            $GLOBALS['SITE_DB']->add_table_field('shopping_order_addresses', 'a_first_name', 'SHORT_TEXT');
            $GLOBALS['SITE_DB']->add_table_field('shopping_order_addresses', 'a_last_name', 'SHORT_TEXT');

            $GLOBALS['SITE_DB']->alter_table_field('shopping_order', 'session_id', 'ID_TEXT');
            $GLOBALS['SITE_DB']->alter_table_field('shopping_cart', 'session_id', 'ID_TEXT');
            $GLOBALS['SITE_DB']->alter_table_field('shopping_logging', 'session_id', 'ID_TEXT');

            $GLOBALS['SITE_DB']->change_primary_key('shopping_cart', array('id'));

            $GLOBALS['SITE_DB']->delete_index_if_exists('shopping_order', 'recent_shopped');
        }

        if (($upgrade_from !== null) && ($upgrade_from < 8)) {
            $GLOBALS['SITE_DB']->add_table_field('shopping_order_addresses', 'a_address_county', 'SHORT_TEXT');

            $GLOBALS['SITE_DB']->alter_table_field('shopping_order_addresses', 'first_name', 'SHORT_TEXT', 'a_firstname');
            $GLOBALS['SITE_DB']->alter_table_field('shopping_order_addresses', 'last_name', 'SHORT_TEXT', 'a_lastname');
            $GLOBALS['SITE_DB']->alter_table_field('shopping_order_addresses', 'address_street', 'LONG_TEXT', 'a_street_address');
            $GLOBALS['SITE_DB']->alter_table_field('shopping_order_addresses', 'address_city', 'SHORT_TEXT', 'a_city');
            $GLOBALS['SITE_DB']->alter_table_field('shopping_order_addresses', 'address_county', 'SHORT_TEXT', 'a_county');
            $GLOBALS['SITE_DB']->alter_table_field('shopping_order_addresses', 'address_state', 'SHORT_TEXT', 'a_state');
            $GLOBALS['SITE_DB']->alter_table_field('shopping_order_addresses', 'address_zip', 'SHORT_TEXT', 'a_post_code');
            $GLOBALS['SITE_DB']->alter_table_field('shopping_order_addresses', 'address_country', 'SHORT_TEXT', 'a_country');
            $GLOBALS['SITE_DB']->alter_table_field('shopping_order_addresses', 'receiver_email', 'SHORT_TEXT', 'a_email');
            $GLOBALS['SITE_DB']->alter_table_field('shopping_order_addresses', 'contact_phone', 'SHORT_TEXT', 'a_phone');
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
        if (get_forum_type() != 'cns') {
            return null;
        }

        $ret = array(
            'browse' => array('SHOPPING', 'menu/rich_content/ecommerce/shopping_cart'),
        );
        if (!$check_perms || !is_guest($member_id)) {
            $ret += array(
                'my_orders' => array('MY_ORDERS', 'menu/rich_content/ecommerce/orders'),
            );
        }
        return $ret;
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

        require_lang('shopping');
        require_lang('catalogues');

        $ecom_catalogue_count = $GLOBALS['SITE_DB']->query_select_value_if_there('catalogues', 'COUNT(*)', array('c_ecommerce' => 1));
        $ecom_catalogue = $GLOBALS['SITE_DB']->query_select_value_if_there('catalogues', 'c_name', array('c_ecommerce' => 1));
        $ecom_catalogue_id = $GLOBALS['SITE_DB']->query_select_value_if_there('catalogue_categories', 'MIN(id)', array('c_name' => $ecom_catalogue));

        if ($type == 'browse') {
            if ($ecom_catalogue_count == 1) {
                breadcrumb_set_parents(array(array('_SELF:catalogues:category:=' . $ecom_catalogue_id, do_lang_tempcode('DEFAULT_CATALOGUE_PRODUCTS_TITLE'))));
            } else {
                breadcrumb_set_parents(array(array('_SELF:catalogues:browse:ecommerce=1', do_lang_tempcode('CATALOGUES'))));
            }

            $this->title = get_screen_title('SHOPPING');
        }

        if ($type == 'add_item') {
            $this->title = get_screen_title('SHOPPING');
        }

        if ($type == 'update_cart') {
            $this->title = get_screen_title('SHOPPING');
        }

        if ($type == 'empty_cart') {
            $this->title = get_screen_title('SHOPPING');
        }

        if ($type == 'finish') {
            if ($ecom_catalogue_count == 1) {
                breadcrumb_set_parents(array(array('_SELF:catalogues:category:=' . $ecom_catalogue_id, do_lang_tempcode('DEFAULT_CATALOGUE_PRODUCTS_TITLE')), array('_SELF:_SELF:browse', do_lang_tempcode('SHOPPING'))));
            } else {
                breadcrumb_set_parents(array(array('_SELF:catalogues:browse:ecommerce=1', do_lang_tempcode('CATALOGUES')), array('_SELF:_SELF:browse', do_lang_tempcode('SHOPPING'))));
            }

            $this->title = get_screen_title('_PURCHASE_FINISHED');
        }

        if ($type == 'my_orders') {
            $this->title = get_screen_title('MY_ORDERS');
        }

        if ($type == 'order_details') {
            breadcrumb_set_parents(array(array('_SELF:orders:browse', do_lang_tempcode('MY_ORDERS'))));

            $id = get_param_integer('id');
            $this->title = get_screen_title('_MY_ORDER_DETAILS', true, array(escape_html($id)));
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

        require_code('shopping');
        require_code('feedback');
        require_lang('ecommerce');
        require_code('ecommerce');

        if (get_forum_type() != 'cns') {
            warn_exit(do_lang_tempcode('NO_CNS'));
        }

        // Kill switch
        if ((ecommerce_test_mode()) && (!$GLOBALS['IS_ACTUALLY_ADMIN']) && (!has_privilege(get_member(), 'access_ecommerce_in_test_mode'))) {
            warn_exit(do_lang_tempcode('PURCHASE_DISABLED'));
        }

        $GLOBALS['NO_QUERY_LIMIT'] = true;

        $type = get_param_string('type', 'browse');

        delete_incomplete_orders();

        if ($type == 'browse') {
            return $this->view_shopping_cart();
        }
        if ($type == 'add_item') {
            return $this->add_item_to_cart();
        }
        if ($type == 'update_cart') {
            return $this->update_cart();
        }
        if ($type == 'empty_cart') {
            return $this->empty_cart();
        }
        if ($type == 'finish') {
            return $this->finish();
        }
        if ($type == 'my_orders') {
            return $this->my_orders();
        }
        if ($type == 'order_details') {
            return $this->order_details();
        }

        return new Tempcode();
    }

    /**
     * The UI to show shopping cart
     *
     * @return Tempcode The UI
     */
    public function view_shopping_cart()
    {
        $products_ids = array();

        require_code('templates_results_table');
        require_code('form_templates');
        require_css('shopping');
        require_css('ecommerce');
        require_javascript('shopping');

        log_cart_actions('View cart');

        $where = array('is_deleted' => 0);
        if (is_guest()) {
            $where['session_id'] = get_session_id();
        } else {
            $where['ordered_by'] = get_member();
        }
        $shopping_cart_rows = $GLOBALS['SITE_DB']->query_select('shopping_cart', array('*'), $where);

        $max_rows = count($shopping_cart_rows);

        $grand_total = 0.0;
        $shipping_cost = 0.0;

        if ($max_rows > 0) {
            $shopping_cart = new Tempcode();

            $fields_title = results_field_title(
                array(
                    '',
                    do_lang_tempcode('PRODUCT'),
                    do_lang_tempcode('UNIT_PRICE'),
                    do_lang_tempcode('QUANTITY'),
                    do_lang_tempcode('ORDER_PRICE_AMT'),
                    do_lang_tempcode('TAX'),
                    do_lang_tempcode('SHIPPING_PRICE'),
                    do_lang_tempcode('TOTAL_PRICE'),
                    do_lang_tempcode('REMOVE')
                ), null
            );

            foreach ($shopping_cart_rows as $i => $value) {
                $products_ids[] = $value['product_id'];

                $_hook = $value['product_type'];

                $value['sl_no'] = $i + 1;

                require_code('hooks/systems/ecommerce/' . filter_naughty_harsh($_hook));

                $product_object = object_factory('Hook_ecommerce_' . filter_naughty_harsh($_hook));

                if (method_exists($product_object, 'show_cart_entry')) {
                    $product_object->show_cart_entry($shopping_cart, $value);
                }

                // Tax
                $tax = 0;
                if (method_exists($product_object, 'calculate_tax')) {
                    $tax = $product_object->calculate_tax($value['price'], $value['price_pre_tax']);
                }

                // Shipping
                if (method_exists($product_object, 'calculate_shipping_cost')) {
                    $shipping_cost = $product_object->calculate_shipping_cost($value['product_weight']);
                } else {
                    $shipping_cost = 0;
                }

                $grand_total += round($value['price'] + $tax + $shipping_cost, 2) * $value['quantity'];
            }

            $results_table = results_table(do_lang_tempcode('SHOPPING'), 0, 'cart_start', $max_rows, 'cart_max', $max_rows, $fields_title, $shopping_cart, null, null, null, 'sort', null, null, 'cart');

            $update_cart_url = build_url(array('page' => '_SELF', 'type' => 'update_cart'), '_SELF');
            $empty_cart_url = build_url(array('page' => '_SELF', 'type' => 'empty_cart'), '_SELF');

            list($payment_form, $finish_url) = render_cart_payment_form();
        } else {
            $update_cart_url = new Tempcode();
            $empty_cart_url = new Tempcode();

            $results_table = do_lang_tempcode('CART_EMPTY');

            $payment_form = new Tempcode();
            $finish_url = '';
        }

        $ecom_catalogue_count = $GLOBALS['SITE_DB']->query_select_value_if_there('catalogues', 'COUNT(*)', array('c_ecommerce' => 1));
        $ecom_catalogue = $GLOBALS['SITE_DB']->query_select_value_if_there('catalogues', 'c_name', array('c_ecommerce' => 1));
        $ecom_catalogue_id = $GLOBALS['SITE_DB']->query_select_value_if_there('catalogue_categories', 'MIN(id)', array('c_name' => $ecom_catalogue));
        if ($ecom_catalogue_count == 1) {
            $continue_shopping_url = build_url(array('page' => 'catalogues', 'type' => 'category', 'id' => $ecom_catalogue_id), get_module_zone('catalogues'));
        } else {
            $continue_shopping_url = build_url(array('page' => 'catalogues', 'type' => 'browse', 'ecommerce' => 1), get_module_zone('catalogues'));
        }

        $products_ids_val = is_array($products_ids) ? implode(',', $products_ids) : ''; // Product ID string for hidden field in Shopping cart

        $allow_opt_out_tax = get_option('allow_opting_out_of_tax');
        $allow_opt_out_tax_value = get_order_tax_opt_out_status();

        $tpl = do_template('ECOM_SHOPPING_CART_SCREEN', array(
            '_GUID' => 'badff09daf52ee1c84b472c44be1bfae',
            'TITLE' => $this->title,
            'RESULTS_TABLE' => $results_table,
            'UPDATE_CART_URL' => $update_cart_url,
            'CONTINUE_SHOPPING_URL' => $continue_shopping_url,
            'MESSAGE' => '',
            'PRODUCT_IDS' => $products_ids_val,
            'EMPTY_CART_URL' => $empty_cart_url,
            'ALLOW_OPTOUT_TAX' => $allow_opt_out_tax,
            'ALLOW_OPTOUT_TAX_VALUE' => strval($allow_opt_out_tax_value),
            'SHIPPING_COST' => float_format($shipping_cost),
            'GRAND_TOTAL' => float_format($grand_total),
            'CURRENCY' => ecommerce_get_currency_symbol(),
            'PAYMENT_FORM' => $payment_form,
            'FINISH_URL' => $finish_url,
        ));

        require_code('templates_internalise_screen');
        return internalise_own_screen($tpl);
    }

    /**
     * Add an item to the cart.
     *
     * @return Tempcode The UI
     */
    public function add_item_to_cart()
    {
        if (is_guest()) {
            require_code('users_inactive_occasionals');
            set_session_id(get_session_id(), true); // Persist guest sessions longer
        }

        $product_details = get_product_details();

        add_to_cart($product_details);

        log_cart_actions('Added to cart');

        $cart_view = build_url(array('page' => '_SELF', 'type' => 'browse'), '_SELF');

        return redirect_screen($this->title, $cart_view, do_lang_tempcode('ADDED_TO_CART'));
    }

    /**
     * Update the cart.
     *
     * @return Tempcode The UI
     */
    public function update_cart()
    {
        $pids = explode(',', post_param_string('product_ids'));

        $product_to_remove = array();

        $product_details = array();

        if (count($pids) > 0) {
            foreach ($pids as $pid) {
                $qty = post_param_integer('quantity_' . $pid);

                $product_object = find_product($pid);

                $remove = post_param_integer('remove_' . $pid, 0);

                if ($remove == 0) {
                    if (method_exists($product_object, 'get_available_quantity')) {
                        $available_qty = $product_object->get_available_quantity($pid, false);

                        if ((!is_null($available_qty)) && ($available_qty <= $qty)) {
                            $qty = $available_qty;

                            attach_message(do_lang_tempcode('PRODUCT_QUANTITY_CHANGED', strval($pid)), 'warn');
                        }
                    }
                }

                $product_details[] = array('product_id' => $pid, 'quantity' => $qty);

                if ($remove == 1) {
                    $product_to_remove[] = $pid;
                }
            }
        }

        update_cart($product_details);

        log_cart_actions('Updated cart');

        if (count($product_to_remove) > 0) {
            remove_from_cart($product_to_remove);
        }

        $cart_view = build_url(array('page' => '_SELF', 'type' => 'browse'), '_SELF');

        return redirect_screen($this->title, $cart_view, do_lang_tempcode('CART_UPDATED'));
    }

    /**
     * Empty the shopping cart.
     *
     * @return Tempcode The UI
     */
    public function empty_cart()
    {
        log_cart_actions('Cart emptied');

        empty_cart(true);

        $cart_view = build_url(array('page' => '_SELF', 'type' => 'browse'), '_SELF');

        return redirect_screen($this->title, $cart_view, do_lang_tempcode('CART_EMPTIED'));
    }

    /**
     * Finish step.
     *
     * @return Tempcode The result of execution.
     */
    public function finish()
    {
        $payment_gateway = get_option('payment_gateway');
        require_code('hooks/systems/payment_gateway/' . filter_naughty_harsh($payment_gateway));
        $payment_gateway_object = object_factory('Hook_payment_gateway_' . $payment_gateway);

        $message = get_param_string('message', null);
        if ($message === null) {
            if (method_exists($payment_gateway_object, 'get_callback_url_message')) {
                $message = $payment_gateway_object->get_callback_url_message();
            }
        }

        require_code('shopping');

        if (get_param_integer('cancel', 0) == 1) {
            delete_pending_orders_for_current_user(); // Don't lock the stock unless they go back to the cart again

            if ($message !== null) {
                return $this->wrap(do_template('PURCHASE_WIZARD_STAGE_FINISH', array('_GUID' => '6eafce1925e5069ceb438ec24754b47d', 'TITLE' => $this->title, 'MESSAGE' => $message)), $this->title, null);
            }

            return inform_screen(get_screen_title('PURCHASING'), do_lang_tempcode('PRODUCT_PURCHASE_CANCEL'), true);
        }

        if (perform_local_payment()) {
            list($success, $message, $message_raw) = do_local_transaction($payment_gateway, $payment_gateway_object);
            if (!$success) {
                attach_message($message, 'warn');
                return $this->view_shopping_cart();
            }
        }

        // We know success at this point...

        empty_cart();

        log_cart_actions('Completed payment');

        if ((!perform_local_payment()) && (has_interesting_post_fields())) { // Alternative to IPN, *if* posted fields sent here
            handle_ipn_transaction_script(); // This is just in case the IPN doesn't arrive somehow, we still know success because the gateway sent us here on success
        }

        $redirect = get_param_string('redirect', null); // TODO: Correct flag in v11

        if ($redirect === null) {
            $product_object = find_product('cart_orders');
            if (method_exists($product_object, 'get_finish_url')) {
                $redirect = $product_object->get_finish_url('cart_orders', $message);
            }
        }

        if ($redirect !== null) {
            return redirect_screen($this->title, $redirect, $message);
        }

        return $this->wrap(do_template('PURCHASE_WIZARD_STAGE_FINISH', array('_GUID' => '3857e761ab75f314f4960805bc76b936', 'TITLE' => $this->title, 'MESSAGE' => $message)), $this->title, null);
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
    public function wrap($content, $title, $url, $get = false)
    {
        if ($url === null) {
            $url = '';
        }
        require_javascript('checking');

        return do_template('PURCHASE_WIZARD_SCREEN', array('_GUID' => '02fd80e2b4d4fc2348736a72e504a208', 'GET' => $get ? true : null, 'TITLE' => $title, 'CONTENT' => $content, 'URL' => $url));
    }

    /**
     * Show all my orders
     *
     * @return Tempcode The interface.
     */
    public function my_orders()
    {
        $member_id = get_member();

        if (has_privilege(get_member(), 'assume_any_member')) {
            $member_id = get_param_integer('id', $member_id);
        }

        $orders = array();

        $rows = $GLOBALS['SITE_DB']->query_select('shopping_order', array('*'), array('c_member' => $member_id), 'ORDER BY add_date');

        foreach ($rows as $row) {
            if ($row['purchase_through'] == 'cart') {
                $order_details_url = build_url(array('page' => '_SELF', 'type' => 'order_details', 'id' => $row['id']), '_SELF');

                $order_title = do_lang('CART_ORDER', $row['id']);
            } else {
                $res = $GLOBALS['SITE_DB']->query_select('shopping_order_details', array('p_id', 'p_name'), array('order_id' => $row['id']));

                if (!array_key_exists(0, $res)) {
                    continue; // DB corruption
                }
                $product_det = $res[0];

                $order_title = $product_det['p_name'];

                $order_details_url = build_url(array('page' => 'catalogues', 'type' => 'entry', 'id' => $product_det['p_id']), get_module_zone('catalogues'));
            }

            $orders[] = array('ORDER_TITLE' => $order_title, 'ID' => strval($row['id']), 'AMOUNT' => strval($row['tot_price']), 'TIME' => get_timezoned_date($row['add_date'], true, false, true, true), 'STATE' => do_lang_tempcode($row['order_status']), 'NOTE' => '', 'ORDER_DET_URL' => $order_details_url, 'DELIVERABLE' => '');
        }

        if (count($orders) == 0) {
            inform_exit(do_lang_tempcode('NO_ENTRIES'));
        }

        return do_template('ECOM_ORDERS_SCREEN', array('_GUID' => '79eb5f17cf4bc2dc4f0cccf438261c73', 'TITLE' => $this->title, 'CURRENCY' => get_option('currency'), 'ORDERS' => $orders));
    }

    /**
     * Show an order details
     *
     * @return Tempcode The interface.
     */
    public function order_details()
    {
        $id = get_param_integer('id');

        $products = array();

        $rows = $GLOBALS['SITE_DB']->query_select('shopping_order_details', array('*'), array('order_id' => $id), 'ORDER BY p_name');
        foreach ($rows as $row) {
            $product_det_url = build_url(array('page' => 'catalogues', 'type' => 'entry', 'id' => $row['p_id']), get_module_zone('catalogues'));

            $products[] = array('PRODUCT_NAME' => $row['p_name'], 'ID' => strval($row['p_id']), 'AMOUNT' => strval($row['p_price']), 'QUANTITY' => strval($row['p_quantity']), 'DISPATCH_STATUS' => do_lang_tempcode($row['dispatch_status']), 'PRODUCT_DET_URL' => $product_det_url, 'DELIVERABLE' => '');
        }

        if (count($products) == 0) {
            inform_exit(do_lang_tempcode('NO_ENTRIES'));
        }

        return do_template('ECOM_ORDERS_DETAILS_SCREEN', array('_GUID' => '8122a53dc0ccf27648af460759a2b6f6', 'TITLE' => $this->title, 'CURRENCY' => get_option('currency'), 'PRODUCTS' => $products));
    }
}
