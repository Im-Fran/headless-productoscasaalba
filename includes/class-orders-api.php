<?php
/**
 * Orders API Class
 *
 * Handles REST API endpoints for customer orders
 */

if (!defined('ABSPATH')) {
    exit;
}

class Casa_Alba_Orders_API {

    /**
     * Register REST API routes
     */
    public function register_routes() {
        // Get all customer orders
        register_rest_route('casa-alba/v1', '/customer/orders', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_orders'),
            'permission_callback' => array($this, 'check_authentication')
        ));

        // Get single order detail (authenticated)
        register_rest_route('casa-alba/v1', '/customer/orders/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_order'),
            'permission_callback' => array($this, 'check_authentication')
        ));

        // Get order detail by order key (public - for order received page)
        register_rest_route('casa-alba/v1', '/orders/(?P<id>\d+)/public', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_order_by_key'),
            'permission_callback' => '__return_true',
            'args' => array(
                'key' => array(
                    'required' => true,
                    'type' => 'string',
                    'description' => 'Order key for verification'
                )
            )
        ));

        // Cancel order
        register_rest_route('casa-alba/v1', '/customer/orders/(?P<id>\d+)/cancel', array(
            'methods' => 'POST',
            'callback' => array($this, 'cancel_order'),
            'permission_callback' => array($this, 'check_authentication')
        ));

        // Reorder
        register_rest_route('casa-alba/v1', '/customer/orders/(?P<id>\d+)/reorder', array(
            'methods' => 'POST',
            'callback' => array($this, 'reorder'),
            'permission_callback' => array($this, 'check_authentication')
        ));
    }

    /**
     * Check if user is authenticated
     */
    public function check_authentication($request) {
        $user_id = get_current_user_id();

        if (!$user_id) {
            return new WP_Error(
                'unauthorized',
                __('No autorizado. Por favor inicie sesión.', 'casa-alba-headless'),
                array('status' => 401)
            );
        }

        return true;
    }

    /**
     * Get all customer orders
     */
    public function get_orders($request) {
        $user_id = get_current_user_id();

        // Get pagination parameters
        $page = $request->get_param('page') ?: 1;
        $per_page = $request->get_param('per_page') ?: 10;
        $status = $request->get_param('status') ?: 'any';

        // Get orders
        $args = array(
            'customer_id' => $user_id,
            'limit' => $per_page,
            'page' => $page,
            'orderby' => 'date',
            'order' => 'DESC',
        );

        if ($status !== 'any') {
            $args['status'] = $status;
        }

        $orders = wc_get_orders($args);

        // Get total count for pagination
        $total_args = array(
            'customer_id' => $user_id,
            'return' => 'ids',
        );

        if ($status !== 'any') {
            $total_args['status'] = $status;
        }

        $total_orders = count(wc_get_orders($total_args));

        $formatted_orders = array();

        foreach ($orders as $order) {
            $formatted_orders[] = array(
                'id' => $order->get_id(),
                'order_number' => $order->get_order_number(),
                'date_created' => $order->get_date_created()->date('Y-m-d H:i:s'),
                'date_created_gmt' => $order->get_date_created()->date('c'),
                'status' => $order->get_status(),
                'status_label' => wc_get_order_status_name($order->get_status()),
                'total' => $order->get_total(),
                'total_formatted' => wc_price($order->get_total()),
                'currency' => $order->get_currency(),
                'payment_method' => $order->get_payment_method(),
                'payment_method_title' => $order->get_payment_method_title(),
                'items_count' => $order->get_item_count(),
            );
        }

        $response = rest_ensure_response(array(
            'orders' => $formatted_orders,
            'pagination' => array(
                'page' => $page,
                'per_page' => $per_page,
                'total' => $total_orders,
                'total_pages' => ceil($total_orders / $per_page),
            )
        ));

        return $response;
    }

    /**
     * Get single order detail
     */
    public function get_order($request) {
        $user_id = get_current_user_id();
        $order_id = $request->get_param('id');

        $order = wc_get_order($order_id);

        // Verify order exists and belongs to current user
        if (!$order || $order->get_customer_id() !== $user_id) {
            return new WP_Error(
                'order_not_found',
                __('Pedido no encontrado', 'casa-alba-headless'),
                array('status' => 404)
            );
        }

        // Get order items
        $items = array();
        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();

            $items[] = array(
                'id' => $item_id,
                'product_id' => $item->get_product_id(),
                'variation_id' => $item->get_variation_id(),
                'name' => $item->get_name(),
                'quantity' => $item->get_quantity(),
                'subtotal' => $item->get_subtotal(),
                'subtotal_formatted' => wc_price($item->get_subtotal()),
                'total' => $item->get_total(),
                'total_formatted' => wc_price($item->get_total()),
                'sku' => $product ? $product->get_sku() : '',
                'image' => $product && $product->get_image_id() ? wp_get_attachment_url($product->get_image_id()) : '',
            );
        }

        // Get shipping lines
        $shipping = array();
        foreach ($order->get_shipping_methods() as $shipping_id => $shipping_item) {
            $shipping[] = array(
                'id' => $shipping_id,
                'method_title' => $shipping_item->get_method_title(),
                'method_id' => $shipping_item->get_method_id(),
                'total' => $shipping_item->get_total(),
                'total_formatted' => wc_price($shipping_item->get_total()),
            );
        }

        // Get frontend URL
        $frontend_url = defined('CASA_ALBA_FRONTEND_URL')
            ? CASA_ALBA_FRONTEND_URL
            : get_option('casa_alba_frontend_url', 'https://productoscasaalba.cl');

        // Build action links
        $links = array();
        $order_status = $order->get_status();

        // Payment link (if pending payment)
        if (in_array($order_status, array('pending', 'on-hold')) && $order->needs_payment()) {
            $links['pay'] = array(
                'url' => $order->get_checkout_payment_url(),
                'label' => __('Pagar pedido', 'casa-alba-headless'),
            );
        }

        // Cancel link (if pending payment)
        if (in_array($order_status, array('pending', 'on-hold'))) {
            $cancel_url = trailingslashit($frontend_url) . 'pedido-cancelado';
            $cancel_url = add_query_arg(array('order_id' => $order_id), $cancel_url);

            $links['cancel'] = array(
                'url' => $cancel_url,
                'label' => __('Cancelar pedido', 'casa-alba-headless'),
            );
        }

        // Reorder link (if completed or cancelled)
        if (in_array($order_status, array('completed', 'cancelled', 'failed'))) {
            $links['reorder'] = array(
                'url' => trailingslashit($frontend_url) . 'carrito?reorder=' . $order_id,
                'label' => __('Volver a pedir', 'casa-alba-headless'),
            );
        }

        // Build order detail response
        $order_data = array(
            'id' => $order->get_id(),
            'order_number' => $order->get_order_number(),
            'order_key' => $order->get_order_key(),
            'date_created' => $order->get_date_created()->date('Y-m-d H:i:s'),
            'date_created_gmt' => $order->get_date_created()->date('c'),
            'date_modified' => $order->get_date_modified() ? $order->get_date_modified()->date('Y-m-d H:i:s') : null,
            'status' => $order->get_status(),
            'status_label' => wc_get_order_status_name($order->get_status()),
            'currency' => $order->get_currency(),
            'total' => $order->get_total(),
            'total_formatted' => wc_price($order->get_total()),
            'subtotal' => $order->get_subtotal(),
            'subtotal_formatted' => wc_price($order->get_subtotal()),
            'total_tax' => $order->get_total_tax(),
            'total_tax_formatted' => wc_price($order->get_total_tax()),
            'shipping_total' => $order->get_shipping_total(),
            'shipping_total_formatted' => wc_price($order->get_shipping_total()),
            'discount_total' => $order->get_discount_total(),
            'discount_total_formatted' => wc_price($order->get_discount_total()),
            'payment_method' => $order->get_payment_method(),
            'payment_method_title' => $order->get_payment_method_title(),
            'customer_note' => $order->get_customer_note(),
            'billing' => array(
                'first_name' => $order->get_billing_first_name(),
                'last_name' => $order->get_billing_last_name(),
                'company' => $order->get_billing_company(),
                'address_1' => $order->get_billing_address_1(),
                'address_2' => $order->get_billing_address_2(),
                'city' => $order->get_billing_city(),
                'state' => $order->get_billing_state(),
                'postcode' => $order->get_billing_postcode(),
                'country' => $order->get_billing_country(),
                'email' => $order->get_billing_email(),
                'phone' => $order->get_billing_phone(),
            ),
            'shipping' => array(
                'first_name' => $order->get_shipping_first_name(),
                'last_name' => $order->get_shipping_last_name(),
                'company' => $order->get_shipping_company(),
                'address_1' => $order->get_shipping_address_1(),
                'address_2' => $order->get_shipping_address_2(),
                'city' => $order->get_shipping_city(),
                'state' => $order->get_shipping_state(),
                'postcode' => $order->get_shipping_postcode(),
                'country' => $order->get_shipping_country(),
            ),
            'line_items' => $items,
            'shipping_lines' => $shipping,
            'links' => $links,
        );

        return rest_ensure_response($order_data);
    }

    /**
     * Get order detail by order key (public endpoint for order received page)
     */
    public function get_order_by_key($request) {
        $order_id = $request->get_param('id');
        $order_key = $request->get_param('key');

        if (!$order_key) {
            return new WP_Error(
                'missing_order_key',
                __('Se requiere la clave del pedido', 'casa-alba-headless'),
                array('status' => 400)
            );
        }

        $order = wc_get_order($order_id);

        // Verify order exists and order key matches
        if (!$order || $order->get_order_key() !== $order_key) {
            return new WP_Error(
                'invalid_order_key',
                __('Pedido no encontrado o clave inválida', 'casa-alba-headless'),
                array('status' => 404)
            );
        }

        // Get order items
        $items = array();
        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();

            $items[] = array(
                'id' => $item_id,
                'product_id' => $item->get_product_id(),
                'variation_id' => $item->get_variation_id(),
                'name' => $item->get_name(),
                'quantity' => $item->get_quantity(),
                'subtotal' => $item->get_subtotal(),
                'subtotal_formatted' => wc_price($item->get_subtotal()),
                'total' => $item->get_total(),
                'total_formatted' => wc_price($item->get_total()),
                'sku' => $product ? $product->get_sku() : '',
                'image' => $product && $product->get_image_id() ? wp_get_attachment_url($product->get_image_id()) : '',
            );
        }

        // Get shipping lines
        $shipping = array();
        foreach ($order->get_shipping_methods() as $shipping_id => $shipping_item) {
            $shipping[] = array(
                'id' => $shipping_id,
                'method_title' => $shipping_item->get_method_title(),
                'method_id' => $shipping_item->get_method_id(),
                'total' => $shipping_item->get_total(),
                'total_formatted' => wc_price($shipping_item->get_total()),
            );
        }

        // Get frontend URL
        $frontend_url = defined('CASA_ALBA_FRONTEND_URL')
            ? CASA_ALBA_FRONTEND_URL
            : get_option('casa_alba_frontend_url', 'https://productoscasaalba.cl');

        // Build action links (limited for public view)
        $links = array();
        $order_status = $order->get_status();

        // Payment link (if pending payment)
        if (in_array($order_status, array('pending', 'on-hold')) && $order->needs_payment()) {
            $links['pay'] = array(
                'url' => $order->get_checkout_payment_url(),
                'label' => __('Pagar pedido', 'casa-alba-headless'),
            );
        }

        // Build order detail response (same format as authenticated endpoint)
        $order_data = array(
            'id' => $order->get_id(),
            'order_number' => $order->get_order_number(),
            'order_key' => $order->get_order_key(),
            'date_created' => $order->get_date_created()->date('Y-m-d H:i:s'),
            'date_created_gmt' => $order->get_date_created()->date('c'),
            'date_modified' => $order->get_date_modified() ? $order->get_date_modified()->date('Y-m-d H:i:s') : null,
            'status' => $order->get_status(),
            'status_label' => wc_get_order_status_name($order->get_status()),
            'currency' => $order->get_currency(),
            'total' => $order->get_total(),
            'total_formatted' => wc_price($order->get_total()),
            'subtotal' => $order->get_subtotal(),
            'subtotal_formatted' => wc_price($order->get_subtotal()),
            'total_tax' => $order->get_total_tax(),
            'total_tax_formatted' => wc_price($order->get_total_tax()),
            'shipping_total' => $order->get_shipping_total(),
            'shipping_total_formatted' => wc_price($order->get_shipping_total()),
            'discount_total' => $order->get_discount_total(),
            'discount_total_formatted' => wc_price($order->get_discount_total()),
            'payment_method' => $order->get_payment_method(),
            'payment_method_title' => $order->get_payment_method_title(),
            'customer_note' => $order->get_customer_note(),
            'billing' => array(
                'first_name' => $order->get_billing_first_name(),
                'last_name' => $order->get_billing_last_name(),
                'company' => $order->get_billing_company(),
                'address_1' => $order->get_billing_address_1(),
                'address_2' => $order->get_billing_address_2(),
                'city' => $order->get_billing_city(),
                'state' => $order->get_billing_state(),
                'postcode' => $order->get_billing_postcode(),
                'country' => $order->get_billing_country(),
                'email' => $order->get_billing_email(),
                'phone' => $order->get_billing_phone(),
            ),
            'shipping' => array(
                'first_name' => $order->get_shipping_first_name(),
                'last_name' => $order->get_shipping_last_name(),
                'company' => $order->get_shipping_company(),
                'address_1' => $order->get_shipping_address_1(),
                'address_2' => $order->get_shipping_address_2(),
                'city' => $order->get_shipping_city(),
                'state' => $order->get_shipping_state(),
                'postcode' => $order->get_shipping_postcode(),
                'country' => $order->get_shipping_country(),
            ),
            'line_items' => $items,
            'shipping_lines' => $shipping,
            'links' => $links,
        );

        return rest_ensure_response($order_data);
    }

    /**
     * Cancel order
     */
    public function cancel_order($request) {
        $user_id = get_current_user_id();
        $order_id = $request->get_param('id');

        $order = wc_get_order($order_id);

        // Verify order exists and belongs to current user
        if (!$order || $order->get_customer_id() !== $user_id) {
            return new WP_Error(
                'order_not_found',
                __('Pedido no encontrado', 'casa-alba-headless'),
                array('status' => 404)
            );
        }

        // Check if order can be cancelled
        if (!in_array($order->get_status(), array('pending', 'on-hold'))) {
            return new WP_Error(
                'cannot_cancel',
                __('Este pedido no puede ser cancelado', 'casa-alba-headless'),
                array('status' => 400)
            );
        }

        // Cancel the order
        $order->update_status('cancelled', __('Cancelado por el cliente desde el frontend', 'casa-alba-headless'));

        return rest_ensure_response(array(
            'message' => __('Pedido cancelado correctamente', 'casa-alba-headless'),
            'order_id' => $order_id,
            'status' => 'cancelled',
        ));
    }

    /**
     * Reorder - add all items from a previous order to cart
     */
    public function reorder($request) {
        $user_id = get_current_user_id();
        $order_id = $request->get_param('id');

        $order = wc_get_order($order_id);

        // Verify order exists and belongs to current user
        if (!$order || $order->get_customer_id() !== $user_id) {
            return new WP_Error(
                'order_not_found',
                __('Pedido no encontrado', 'casa-alba-headless'),
                array('status' => 404)
            );
        }

        // Check if order can be reordered
        if (!in_array($order->get_status(), array('completed', 'cancelled', 'failed'))) {
            return new WP_Error(
                'cannot_reorder',
                __('Este pedido no puede ser reordenado en este momento', 'casa-alba-headless'),
                array('status' => 400)
            );
        }

        // Clear current cart
        WC()->cart->empty_cart();

        $items_added = 0;
        $items_failed = array();

        // Add all items to cart
        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $variation_id = $item->get_variation_id();
            $quantity = $item->get_quantity();

            // Get product
            $product = wc_get_product($variation_id ? $variation_id : $product_id);

            // Check if product is available
            if (!$product || !$product->is_in_stock() || !$product->is_purchasable()) {
                $items_failed[] = array(
                    'name' => $item->get_name(),
                    'reason' => __('Producto no disponible', 'casa-alba-headless'),
                );
                continue;
            }

            // Add to cart
            try {
                WC()->cart->add_to_cart(
                    $product_id,
                    $quantity,
                    $variation_id,
                    $item->get_meta_data()
                );
                $items_added++;
            } catch (Exception $e) {
                $items_failed[] = array(
                    'name' => $item->get_name(),
                    'reason' => $e->getMessage(),
                );
            }
        }

        $response = array(
            'message' => sprintf(
                __('%d productos agregados al carrito', 'casa-alba-headless'),
                $items_added
            ),
            'items_added' => $items_added,
            'cart_item_count' => WC()->cart->get_cart_contents_count(),
        );

        if (!empty($items_failed)) {
            $response['items_failed'] = $items_failed;
            $response['warning'] = __('Algunos productos no pudieron ser agregados al carrito', 'casa-alba-headless');
        }

        return rest_ensure_response($response);
    }
}

