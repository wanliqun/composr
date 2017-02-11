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

/*
Orders are compound-products. They link together multiple eCommerce items into a single purchasable set with a fixed price.
*/

/**
 * eCommerce product hook.
 */
class Hook_ecommerce_cart_orders
{
    /**
     * Get the products handled by this eCommerce hook.
     *
     * IMPORTANT NOTE TO PROGRAMMERS: This function may depend only on the database, and not on get_member() or any GET/POST values.
     *  Such dependencies will break IPN, which works via a Guest and no dependable environment variables. It would also break manual transactions from the Admin Zone.
     *
     * @param  ?ID_TEXT $search Product being searched for (null: none).
     * @return array A map of product name to list of product details.
     */
    public function get_products($search = null)
    {
        $products = array();

        require_lang('shopping');

        if ($search !== null) {
            if (preg_match('#^CART_ORDER_#', $search) == 0) {
                return array();
            }
            $where = 'id=' . strval(intval(substr($search, strlen('CART_ORDER_'))));
        } else {
            $where = '(' . db_string_equal_to('order_status', 'ORDER_STATUS_awaiting_payment') . ' OR ' . db_string_equal_to('order_status', 'ORDER_STATUS_payment_received') . ')';

            if (get_page_name() == 'purchase') {
                $where .= ' AND member_id=' . strval(get_member()); // HACKHACK: A bit naughty, but we only do it if $search is null and on purchase page
            }
        }

        $orders = $GLOBALS['SITE_DB']->query('SELECT id,total_price,total_tax,total_shipping_cost FROM ' . get_table_prefix() . 'shopping_order WHERE ' . $where . ' ORDER BY add_date DESC', 50, null, false, true);

        foreach ($orders as $order) {
            $products['CART_ORDER_' . strval($order['id'])] = array(
                'item_name' => do_lang('CART_ORDER', strval($order['id'])),
                'item_description' => do_lang_tempcode('CART_ORDER_DESCRIPTION', escape_html(strval($order['id']))),
                'item_image_url' => find_theme_image('icons/48x48/menu/rich_content/ecommerce/shopping_cart'),

                'type' => PRODUCT_ORDERS,
                'type_special_details' => array(),

                'price' => $order['total_price'],
                'currency' => get_option('currency'),
                'price_points' => null,
                'discount_points__num_points' => null, // TODO (#3026) - We don't currently support point discounts for cart purchases
                'discount_points__price_reduction' => null,

                'tax' => $order['total_tax'],
                'shipping_cost' => $order['total_shipping_cost'],
                'needs_shipping_address' => true,
            );
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
        if (!has_actual_page_access($member_id, 'shopping')) {
            if (is_guest()) {
                return ECOMMERCE_PRODUCT_NO_GUESTS;
            }

            return ECOMMERCE_PRODUCT_PROHIBITED;
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
        $fields = mixed();
        ecommerce_attach_memo_field_if_needed($fields);

        return array(null, null, null);
    }

    /**
     * Get the filled in fields and do something with them.
     *
     * @param  ID_TEXT $type_code The product codename.
     * @return array A pair: The purchase ID, a confirmation box to show (null for no specific confirmation).
     */
    public function handle_needed_fields($type_code)
    {
        return array('', null);
    }

    /**
     * Handling of a product purchase change state.
     *
     * @param  ID_TEXT $type_code The product codename.
     * @param  ID_TEXT $purchase_id The purchase ID.
     * @param  array $details Details of the product, with added keys: TXN_ID, STATUS, ORDER_STATUS.
     * @return boolean Whether the product was automatically dispatched (if not then hopefully this function sent a staff notification).
     */
    public function actualiser($type_code, $purchase_id, $details)
    {
        require_code('shopping');
        require_lang('shopping');

        $order_id = intval(preg_replace('#^CART\_ORDER\_#', '', $type_code));

        if ($details['STATUS'] == 'Completed') {
            // Insert sale
            $member_id = $GLOBALS['SITE_DB']->query_select_value('shopping_order', 'member_id', array('id' => $order_id));
            $GLOBALS['SITE_DB']->query_insert('ecom_sales', array('date_and_time' => time(), 'member_id' => $member_id, 'details' => $details['item_name'], 'details2' => '', 'txn_id' => $details['TXN_ID']));

            $ordered_items = $GLOBALS['SITE_DB']->query_select('shopping_order_details', array('*'), array('p_order_id' => $order_id), '', 1);
            foreach ($ordered_items as $ordered_item) {
                list($sub_details, $sub_product_object) = find_product_details($ordered_item['p_type_code']);

                if ($sub_details === null) {
                    continue;
                }

                // Reduce stock
                if (method_exists($sub_product_object, 'reduce_stock')) {
                    $sub_product_object->reduce_stock($ordered_item['p_type_code'], $ordered_item['p_quantity']);
                }

                // Call actualiser
                $call_actualiser_from_cart = !isset($sub_details['type_special_details']['call_actualiser_from_cart']) || $sub_details['type_special_details']['call_actualiser_from_cart'];
                if ($call_actualiser_from_cart) {
                    $sub_product_object->actualiser($ordered_item['p_type_code'], $ordered_item['purchase_id'], $sub_details + $details/*Copy through transaction status etc, merge in this order gives precedence to $sub_details*/);
                }
            }
        }

        $old_status = $GLOBALS['SITE_DB']->query_select_value('shopping_order_details', 'p_dispatch_status', array('p_order_id' => $order_id));

        if ($old_status != $details['ORDER_STATUS']) {
            $GLOBALS['SITE_DB']->query_update('shopping_order_details', array('p_dispatch_status' => $details['ORDER_STATUS']), array('p_order_id' => $order_id));

            $GLOBALS['SITE_DB']->query_update('shopping_order', array('order_status' => $details['ORDER_STATUS'], 'txn_id' => $details['TXN_ID']), array('id' => $order_id));

            // Copy in memo from transaction, as customer notes
            $old_memo = $GLOBALS['SITE_DB']->query_select_value('shopping_order', 'notes', array('id' => $order_id));
            if ($old_memo == '') {
                $memo = $GLOBALS['SITE_DB']->query_select_value('ecom_transactions', 't_memo', array('id' => $details['TXN_ID']));
                if ($memo != '') {
                    $memo = do_lang('CUSTOMER_NOTES') . "\n" . $memo;
                    $GLOBALS['SITE_DB']->query_update('shopping_order', array('notes' => $memo), array('id' => $order_id), '', 1);
                }
            }

            if ($details['ORDER_STATUS'] == 'ORDER_STATUS_payment_received') {
                send_shopping_order_purchased_staff_mail($order_id);
            }
        }

        return false;
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
        $order_id = intval($purchase_id);
        return $GLOBALS['SITE_DB']->query_select_value_if_there('shopping_order', 'member_id', array('id' => $order_id));
    }

    /**
     * Function to return dispatch type of product.
     * (this hook represents a cart order, so find all of it's sub products's dispatch type and decide cart order product's dispatch type - automatic or manual)
     *
     * @param  SHORT_TEXT $order_id Item ID.
     * @return SHORT_TEXT Dispatch type.
     */
    public function get_product_dispatch_type($order_id)
    {
        $ordered_items = $GLOBALS['SITE_DB']->query_select('shopping_order_details', array('*'), array('p_order_id' => $order_id));
        foreach ($ordered_items as $ordered_item) {
            list(, $product_object) = find_product_details($ordered_item['p_type_code']);

            if ($product_object === null) {
                continue;
            }

            // If any of the product's dispatch type is manual, return type as 'manual'
            if ($product_object->get_product_dispatch_type() == 'manual') {
                return 'manual';
            }
        }

        // If none of product items have manual dispatch, return order dispatch as automatic.
        return 'automatic';
    }
}
