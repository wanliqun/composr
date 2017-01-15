<?php /*

 Composr
 Copyright (c) ocProducts, 2004-2016

 See text/EN/licence.txt for full licencing information.


 NOTE TO PROGRAMMERS:
   Do not edit this file. If you need to make changes, save your changed file to the appropriate *_custom folder
   **** If you ignore this advice, then your website upgrades (e.g. for bug fixes) will likely kill your changes ****

*/

/*
This eCommerce hook is more than a normal purchase hook.
It also provides:
 - Extra methods for shopping module
 - Extra methods for catalogues module
It is modular though, and purchase hook may provide these methods too.
*/

/**
 * @license     http://opensource.org/licenses/cpal_1.0 Common Public Attribution License
 * @copyright   ocProducts Ltd
 * @package     shopping
 */
class Hook_ecommerce_catalogue_items
{
    /**
     * Find whether a shipping address is needed.
     *
     * @return boolean Whether a shipping address is needed.
     */
    public function needs_shipping_address()
    {
        return true;
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
        if ($search === null) {
            $cnt = $GLOBALS['SITE_DB']->query_select_value('catalogue_entries t1 LEFT JOIN ' . get_table_prefix() . 'catalogues t2 ON t1.c_name=t2.c_name', 'COUNT(*)', array('c_ecommerce' => 1));
            if ($cnt > 50) {
                return array(); // Too many to list
            }
        }

        require_code('catalogues');

        $products = array();

        $where = array('c_ecommerce' => 1);
        if ($search !== null) {
            if (!$search_item_names) {
                if (!is_numeric($search)) {
                    return array();
                }
                $where['id'] = intval($search);
            } else {
                // Unfortunately no easy efficient solution due to inability to do easy querying on catalogue fields. However, practically speaking, this code path will not be called, as catalogue items are purchased as part of a card order rather than separately.
            }
        }

        if (php_function_allowed('set_time_limit')) {
            @set_time_limit(0);
        }

        $start = 0;
        do {
            $items = $GLOBALS['SITE_DB']->query_select('catalogue_entries t1 LEFT JOIN ' . get_table_prefix() . 'catalogues t2 ON t1.c_name=t2.c_name', array('t1.id', 't1.c_name'), $where, '', 500, $start);
            foreach ($items as $ecomm_item) {
                $field_rows = get_catalogue_entry_field_values($ecomm_item['c_name'], $ecomm_item['id'], null, null, true);

                $product_title = $field_rows[0]['effective_value_pure'];

                if (($search !== null) && ($search_item_names)) {
                    if ($product_title != $search) {
                        continue;
                    }
                }

                $item_price = '0.0';
                if (array_key_exists(2, $field_rows)) {
                    $item_price = $field_rows[2]['effective_value_pure'];
                }

                $tax = 0.0;
                if (array_key_exists(6, $field_rows)) {
                    $tax = floatval(preg_replace('#[^\d\.]#', '', $field_rows[6]['effective_value_pure']));
                }

                $product_weight = 0.0;
                if (array_key_exists(8, $field_rows)) {
                    $product_weight = floatval($field_rows[8]['effective_value_pure']);
                }

                $price = float_to_raw_string($this->calculate_product_price(floatval($item_price), $tax, $product_weight));

                /* For catalogue items we make the numeric product ID the raw ID for the eCommerce item. This is unique to catalogue items (necessarily so, to avoid conflicts), and we do it for convenience */
                $products[strval($ecomm_item['id'])] = array(
                    PRODUCT_CATALOGUE,
                    $price,
                    'handle_catalogue_items',
                    array('tax' => $tax),
                    $product_title,
                    get_option('currency'),
                );
            }
            $start += 500;
        } while (count($items) == 500);

        return $products;
    }

    /**
     * Check whether the product codename is available for purchase by the member.
     *
     * @param  ID_TEXT $type_code The product codename.
     * @param  ?MEMBER $member The member we are checking against (null: current meber).
     * @param  integer $req_quantity The number required.
     * @return integer The availability code (a ECOMMERCE_PRODUCT_* constant).
     */
    public function is_available($type_code, $member = null, $req_quantity = 1)
    {
        require_code('catalogues');

        if (get_page_name() == 'purchase') {
            return ECOMMERCE_PRODUCT_DISABLED; // Don't list within purchasing module, we only want it as a part of a cart order
        }

        $res = $GLOBALS['SITE_DB']->query_select('catalogue_entries', array('*'), array('id' => intval($type_code)), '', 1);
        if (!array_key_exists(0, $res)) {
            return ECOMMERCE_PRODUCT_MISSING;
        }

        $entry_row = $res[0];

        $field_rows = get_catalogue_entry_field_values($entry_row['c_name'], $entry_row['id'], null, null, true);

        if (array_key_exists(5, $field_rows)) { // Check maintenance status
            if ((empty($field_rows[5]['effective_value_pure'])) || ($field_rows[5]['effective_value_pure'] == do_lang('YES'))) {
                return ECOMMERCE_PRODUCT_AVAILABLE;
            }
        }

        if (!array_key_exists(3, $field_rows)) {
            return ECOMMERCE_PRODUCT_INTERNAL_ERROR;
        }
        if (($field_rows[3]['effective_value_pure'] != '') && ($field_rows[3]['effective_value_pure'] != do_lang('NA'))) { // Check stock
            $available_stock = $this->get_available_quantity($type_code);

            return ($available_stock >= $req_quantity) ? ECOMMERCE_PRODUCT_AVAILABLE : ECOMMERCE_PRODUCT_OUT_OF_STOCK;
        }

        return ECOMMERCE_PRODUCT_AVAILABLE;
    }

    /**
     * Get currently available quantity of selected product.
     *
     * @param  ID_TEXT $type_code The product codename.
     * @param  boolean $consider_own_cart_contents Whether to consider the contents of your own cart.
     * @return ?integer Quantity (null: no limit).
     */
    public function get_available_quantity($type_code, $consider_own_cart_contents = true)
    {
        require_code('catalogues');

        $res = $GLOBALS['SITE_DB']->query_select('catalogue_entries', array('*'), array('id' => intval($type_code)));
        if (!array_key_exists(0, $res)) {
            return 0;
        }

        $entry_row = $res[0];

        $field_rows = get_catalogue_entry_field_values($entry_row['c_name'], $entry_row['id'], null, null, true);

        $stock_maintained = ($field_rows[5]['effective_value_pure'] == do_lang('YES'));
        if ($stock_maintained) {
            return null;
        }

        if (($field_rows[3]['effective_value_pure'] != '') && ($field_rows[3]['effective_value_pure'] != do_lang('NA'))) {
            $available_quantity = intval($field_rows[3]['effective_value_pure']);

            // Locked order check
            $query = 'SELECT sum(t2.p_quantity) FROM ' . get_table_prefix() . 'shopping_order t1 JOIN ' . get_table_prefix() . 'shopping_order_details t2 ON t1.id=t2.order_id WHERE add_date>' . strval(time() - 60 * 60 * intval(get_option('cart_hold_hours'))) . ' AND ' . db_string_equal_to('t1.order_status', 'ORDER_STATUS_awaiting_payment') . ' AND t2.p_id=' . strval(intval($type_code));
            if (is_guest()) {
                $query .= ' AND ' . db_string_not_equal_to('t1.session_id', get_session_id());
            } else {
                $query .= ' AND t1.c_member<>' . strval(get_member());
            }
            $locked_item_count = $GLOBALS['SITE_DB']->query_value_if_there($query);
            if ($locked_item_count === null) {
                $locked_item_count = 0;
            }

            // Items in own cart (not locked, but tied to this purchase)
            if ($consider_own_cart_contents) {
                $where = array(
                    'is_deleted' => 0,
                    'product_id' => intval($type_code),
                );
                if (is_guest()) {
                    $where['session_id'] = get_session_id();
                } else {
                    $where['ordered_by'] = get_member();
                }
                $cart_item_count = $GLOBALS['SITE_DB']->query_select_value('shopping_cart', 'SUM(quantity)', $where);
                if ($cart_item_count === null) {
                    $cart_item_count = 0;
                }
            } else {
                $cart_item_count = 0;
            }

            return ($available_quantity - $locked_item_count - $cart_item_count);
        }

        return null;
    }

    /**
     * Get the message for use in the purchasing module
     *
     * @param  ID_TEXT $type_code The product in question.
     * @return Tempcode The message.
     */
    public function get_message($type_code)
    {
        require_code('catalogues');
        require_lang('catalogues');

        $catalogue_name = $GLOBALS['SITE_DB']->query_select_value('catalogue_entries', 'c_name', array('id' => intval($type_code)));

        $catalogues = $GLOBALS['SITE_DB']->query_select('catalogues', array('*'), array('c_name' => $catalogue_name), '', 1);
        if (!array_key_exists(0, $catalogues)) {
            warn_exit(do_lang_tempcode('CATALOGUE_NOT_FOUND', $catalogue_name));
        }

        $catalogue = $catalogues[0];

        $entries = $GLOBALS['SITE_DB']->query_select('catalogue_entries', array('*'), array('id' => intval($type_code)), '', 1);

        if (!array_key_exists(0, $entries)) {
            return warn_screen(get_screen_title('CATALOGUES'), do_lang_tempcode('MISSING_RESOURCE', 'catalogue_entry'));
        }

        $entry = $entries[0];

        $map = get_catalogue_entry_map($entry, $catalogue, 'CATEGORY', $catalogue_name, null, null, null, false, true);

        return do_template('CATALOGUE_' . $catalogue_name . '_GRID_ENTRY_WRAP', $map, null, false, 'CATALOGUE_DEFAULT_GRID_ENTRY_WRAP');
    }

    /**
     * Get the product's details in a standard form.
     *
     * @param  ?AUTO_LINK $pid Product ID (null: read from environment, product_id).
     * @return array List of product details.
     */
    public function get_product_details($pid = null)
    {
        require_code('catalogues');

        if ($pid === null) {
            $pid = either_param_integer('product_id');
        }

        $qty = post_param_integer('quantity', 1);

        $catalogue_name = $GLOBALS['SITE_DB']->query_select_value_if_there('catalogue_entries', 'c_name', array('id' => $pid));
        if ($catalogue_name === null) {
            warn_exit(do_lang_tempcode('MISSING_RESOURCE', 'catalogue_entry'));
        }

        $field_rows = get_catalogue_entry_field_values($catalogue_name, $pid, null, null, true);

        foreach ($field_rows as $key => $value) {
            $field_rows[$key] = $value['effective_value_pure'];
        }
        for ($i = 0; $i <= 9; $i++) {
            if (!isset($field_rows[$i])) {
                $field_rows[$i] = '';
            }
        }

        $product = array(
            'product_id' => $pid,
            'product_name' => $field_rows[0],
            'product_code' => $field_rows[1],
            'price' => $field_rows[2],
            'tax' => floatval(preg_replace('#[^\d\.]#', '', $field_rows[6])),
            'description' => $field_rows[9],
            'quantity' => $qty,
            'product_type' => 'catalogue_items',
            'product_weight' => floatval($field_rows[8])
        );

        return $product;
    }

    /**
     * Add an item to the cart.
     *
     * @param  array $product_det List of product details (as per get_product_details).
     * @return AUTO_LINK Order ID of newly added order.
     */
    public function add_to_cart($product_det)
    {
        if ($this->is_available($product_det['product_id'], get_member(), 1) != ECOMMERCE_PRODUCT_AVAILABLE) {
            require_lang('shopping');
            warn_exit(do_lang_tempcode('PRODUCT_UNAVAILABLE_WARNING', escape_html($product_det['product_name'])));
        }

        $where = array('product_code' => $product_det['product_code'], 'is_deleted' => 0);
        if (is_guest()) {
            $where['session_id'] = get_session_id();
        } else {
            $where['ordered_by'] = get_member();
        }
        $qty = $GLOBALS['SITE_DB']->query_select_value_if_there('shopping_cart', 'quantity', $where);

        if ($qty == 0) {
            $cart_map = array(
                'session_id' => get_session_id(),
                'ordered_by' => get_member(),
                'product_id' => $product_det['product_id'],
                'product_name' => $product_det['product_name'],
                'product_code' => $product_det['product_code'],
                'quantity' => $product_det['quantity'],
                'price' => round(floatval($product_det['price']), 2),
                'price_pre_tax' => round(floatval($product_det['tax']), 2),
                'product_description' => $product_det['description'],
                'product_type' => $product_det['product_type'],
                'product_weight' => $product_det['product_weight'],
                'is_deleted' => 0,
            );
            $id = $GLOBALS['SITE_DB']->query_insert('shopping_cart', $cart_map, true);
        } else {
            $where = array('product_name' => $product_det['product_name'], 'product_code' => $product_det['product_code']);
            if (is_guest()) {
                $where['session_id'] = get_session_id();
            } else {
                $where['ordered_by'] = get_member();
            }
            $id = $GLOBALS['SITE_DB']->query_update(
                'shopping_cart',
                array(
                    'quantity' => ($qty + $product_det['quantity']),
                    'price' => round(floatval($product_det['price']), 2),
                    'price_pre_tax' => round(floatval($product_det['tax']), 2),
                ),
                $where
            );
        }

        return $id;
    }

    /**
     * Produce a results table row for a particular shopping cart entry.
     *
     * @param  Tempcode $shopping_cart Tempcode object of shopping cart result table.
     * @param  array $product_det List of product details (as per get_product_details), of what to show.
     * @return Tempcode Tempcode object of shopping cart result row.
     */
    public function show_cart_entry(&$shopping_cart, $product_det)
    {
        $tpl_set = 'cart';

        require_code('images');

        require_lang('catalogues');

        $edit_qnty = do_template('ECOM_SHOPPING_ITEM_QUANTITY_FIELD',
            array(
                'PRODUCT_ID' => strval($product_det['product_id']),
                'QUANTITY' => strval($product_det['quantity'])
            )
        );

        $tax = $this->calculate_tax($product_det['price'], $product_det['price_pre_tax']);

        $shipping_cost = $this->calculate_shipping_cost($product_det['product_weight']);

        $del_item = do_template('ECOM_SHOPPING_ITEM_REMOVE_FIELD',
            array(
                'PRODUCT_ID' => strval($product_det['product_id']),
            )
        );

        $catalogue_name = $GLOBALS['SITE_DB']->query_select_value_if_there('catalogue_entries', 'c_name', array('id' => $product_det['product_id']));
        if ($catalogue_name === null) {
            $GLOBALS['SITE_DB']->query_delete('shopping_cart', array('product_id' => $product_det['product_id']));
            return new Tempcode();
        }

        $image = $this->_get_product_image($catalogue_name, $product_det['product_id']);

        if ($image == '') {
            $product_image = do_image_thumb('themes/default/images/no_image.png', do_lang('NO_IMAGE'), do_lang('NO_IMAGE'), false, 50, 50);
        } else {
            $product_image = do_image_thumb(url_is_local($image) ? (get_custom_base_url() . '/' . $image) : ($image), $product_det['product_name'], $product_det['product_name'], false, 50, 50);
        }

        $currency = ecommerce_get_currency_symbol();

        $price = round((round($product_det['price'] + $tax + $shipping_cost, 2)) * $product_det['quantity'], 2);

        $total_tax = $tax * $product_det['quantity'];
        $total_shipping = $shipping_cost * $product_det['quantity'];
        $order_price = $product_det["price"] * $product_det['quantity'];

        $product_url = build_url(array('page' => 'catalogues', 'type' => 'entry', 'id' => $product_det['product_id']), '_SELF');

        $product_link = hyperlink($product_url, $product_det['product_name'], false, true, do_lang('INDEX'));

        require_code('templates_results_table');
        $shopping_cart->attach(
            results_entry(
                array(
                    $product_image,
                    $product_link,
                    $currency . escape_html(float_format($product_det['price'], 2)),
                    $edit_qnty,
                    $currency . escape_html(float_format($order_price)),
                    $currency . escape_html(float_format($total_tax, 2)),
                    $currency . escape_html(float_format($total_shipping, 2)),
                    $currency . escape_html(float_format($price, 2)),
                    $del_item
                ), false, $tpl_set
            )
        );

        return $shopping_cart;
    }

    /**
     * Calculate tax of catalogue item.
     *
     * @param  float $gross_cost Gross cost of product.
     * @param  float $tax_percentage Tax in percentage.
     * @return float Calculated tax for the product.
     */
    public function calculate_tax($gross_cost, $tax_percentage)
    {
        if (addon_installed('shopping')) {
            require_code('shopping');
            if (get_order_tax_opt_out_status() == 1) {
                return 0.0;
            }
        }

        $tax = ($gross_cost * $tax_percentage) / 100.0;
        return $tax;
    }

    /**
     * Calculate shipping cost of product.
     *
     * @param  float $item_weight Weight of product.
     * @return float Calculated shipping cost for the product.
     */
    public function calculate_shipping_cost($item_weight)
    {
        $option = get_option('shipping_cost_factor');
        $base = 0.0;
        $matches = array();
        if (preg_match('#\(([\d\.]+)\)#', $option, $matches) != 0) {
            $base = floatval($matches[1]);
            $option = str_replace($matches[0], '', $option);
        }
        if ($option == '') {
            $option = '0';
        }
        $factor = floatval($option) / 100.0;
        $shipping_cost = $base + $item_weight * $factor;

        return $shipping_cost;
    }

    /**
     * Calculate product price.
     *
     * @param  float $item_price Weight of product.
     * @param  float $tax Tax in percentage.
     * @param  integer $item_weight Weight of item.
     * @return float Calculated shipping cost for the product.
     */
    public function calculate_product_price($item_price, $tax, $item_weight)
    {
        $item_price = round($item_price, 2);

        $shipping_cost = $this->calculate_shipping_cost($item_weight);

        if (get_option('allow_opting_out_of_tax') == '1' && post_param_integer('tax_opted_out', 0) == 1) {
            $tax = 0.0;
        } else {
            $tax = $this->calculate_tax($item_price, $tax);
        }

        $product_price = round(($item_price + $tax + $shipping_cost), 2);

        return $product_price;
    }

    /**
     * Find product image for a specific catalogue item.
     *
     * @param  ID_TEXT $catalogue_name Catalogue name.
     * @param  AUTO_LINK $entry_id Catalogue entry ID.
     * @return ?SHORT_TEXT Image name (null: no image).
     */
    protected function _get_product_image($catalogue_name, $entry_id)
    {
        require_code('catalogues');

        $field_rows = get_catalogue_entry_field_values($catalogue_name, $entry_id, null, null, true);

        $image = null;

        if (array_key_exists(7, $field_rows)) {
            return is_object($field_rows[7]['effective_value']) ? $field_rows[7]['effective_value']->evaluate() : $field_rows[7]['effective_value'];
        } else {
            return null;
        }
    }

    /**
     * Update stock levels after a purchase.
     *
     * @param  AUTO_LINK $entry_id Catalogue entry ID.
     * @param  integer $quantity Quantity to deduct.
     */
    public function reduce_stock($entry_id, $quantity)
    {
        require_code('catalogues');

        $res = $GLOBALS['SITE_DB']->query_select('catalogue_entries', array('c_name', 'cc_id'), array('id' => $entry_id), '', 1);
        if (!array_key_exists(0, $res)) {
            return;
        }
        $row = $res[0];

        $catalogue_name = $row['c_name'];

        $field_rows = get_catalogue_entry_field_values($catalogue_name, $entry_id, null, null, true);

        $available_quantity = 0;
        if (array_key_exists(3, $field_rows)) { // Stock level
            if (($field_rows[3]['effective_value_pure'] == '') || ($field_rows[3]['effective_value_pure'] == do_lang('NA'))) {
                return;
            }

            $stock_field_id = $field_rows[3]['id'];
            $available_quantity = intval($field_rows[3]['effective_value_pure']);
        }

        $stock_maintained = false;
        if (array_key_exists(5, $field_rows)) { // Stock maintained
            if ((empty($field_rows[5]['effective_value_pure'])) || ($field_rows[5]['effective_value_pure'] === do_lang('NA'))) {
                return;
            }

            $stock_maintained = ($field_rows[5]['effective_value_pure'] == do_lang('YES'));
        }

        $stock_level_warn_threshold = 0;
        if (array_key_exists(4, $field_rows)) { // Stock level warn threshold
            if ((empty($field_rows[4]['effective_value_pure'])) || ($field_rows[4]['effective_value_pure'] === do_lang('NA'))) {
                return;
            }

            $stock_level_warn_threshold = intval($field_rows[4]['effective_value_pure']);
        }

        $product_name = get_translated_text($row['cc_id']);

        if ($available_quantity < $quantity && !$stock_maintained) {
            require_code('site');
            attach_message(do_lang_tempcode('LOW_STOCK_DISPATCH_FAILED', $product_name));
        }

        $stock_after_dispatch = $available_quantity - $quantity;

        if ($stock_after_dispatch < $stock_level_warn_threshold) {
            stock_maintain_warn_mail($product_name, $entry_id);
        }

        if (array_key_exists(3, $field_rows)) { // Stock level
            $GLOBALS['SITE_DB']->query_update('catalogue_efv_integer', array('cv_value' => intval($stock_after_dispatch)), array('cf_id' => $stock_field_id, 'ce_id' => $entry_id));
        }
    }

    /**
     * Function to return dispatch type of product.
     *
     * @return  ID_TEXT    Dispatch type (manual/automatic).
     */
    public function get_product_dispatch_type()
    {
        return 'manual';
    }

    /**
     * Get custom fields for eCommerce product.
     *
     * @param  AUTO_LINK $id Product entry ID.
     * @param  array $map Map where product details are placed.
     */
    public function get_custom_product_map_fields($id, &$map)
    {
        require_code('feedback');
        require_code('ecommerce');
        require_code('images');

        require_lang('shopping');

        $shopping_cart_url = build_url(array('page' => 'shopping', 'type' => 'browse'), '_SELF');

        $product_title = null;

        if (array_key_exists('FIELD_0', $map)) {
            $product_title = $map['FIELD_0_PLAIN'];
            if (is_object($product_title)) {
                $product_title = strip_html($product_title->evaluate());
            }
        }

        $terms = method_exists($this, 'get_terms') ? $this->get_terms(strval($id)) : '';

        $fields = method_exists($this, 'get_needed_fields') ? $this->get_needed_fields(strval($id)) : null;

        $available_quantity = $this->get_available_quantity(strval($id));
        $out_of_stock = ($available_quantity !== null) && ($available_quantity <= 0);

        $cart_url = build_url(array('page' => 'shopping', 'type' => 'add_item', 'hook' => 'catalogue_items'), '_SELF');

        $purchase_mod_url = build_url(array('page' => 'purchase', 'type' => ($terms == '') ? (($fields === null) ? 'pay' : 'details') : 'terms', 'type_code' => strval($id), 'id' => $id), '_SELF');

        $map['CART_BUTTONS'] = do_template('ECOM_SHOPPING_CART_BUTTONS', array(
            '_GUID' => 'd4491c6e221b1f06375a6427da062bac',
            'OUT_OF_STOCK' => $out_of_stock,
            'ACTION_URL' => $cart_url,
            'PRODUCT_ID' => strval($id),
            'ALLOW_OPTOUT_TAX' => get_option('allow_opting_out_of_tax'),
            'PURCHASE_ACTION_URL' => $purchase_mod_url,
            'CART_URL' => $shopping_cart_url,
        ));
    }
}

/**
 * Update stock count after transaction.
 *
 * @param  AUTO_LINK $entry_id Product ID.
 * @param  array $details Details of product.
 */
function handle_catalogue_items($entry_id, $details)
{
    $product_object = object_factory('Hook_ecommerce_catalogue_items');
    $product_object->reduce_stock($entry_id, 1);
}
