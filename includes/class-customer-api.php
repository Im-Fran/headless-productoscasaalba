<?php
/**
 * Customer API Class
 *
 * Handles REST API endpoints for customer profile and addresses
 */

if (!defined('ABSPATH')) {
    exit;
}

class Casa_Alba_Customer_API {

    /**
     * Register REST API routes
     */
    public function register_routes() {
        // Get customer profile
        register_rest_route('casa-alba/v1', '/customer/profile', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_profile'),
            'permission_callback' => array($this, 'check_authentication')
        ));

        // Update customer profile
        register_rest_route('casa-alba/v1', '/customer/profile', array(
            'methods' => 'PUT',
            'callback' => array($this, 'update_profile'),
            'permission_callback' => array($this, 'check_authentication')
        ));

        // Get customer addresses
        register_rest_route('casa-alba/v1', '/customer/addresses', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_addresses'),
            'permission_callback' => array($this, 'check_authentication')
        ));

        // Update customer addresses
        register_rest_route('casa-alba/v1', '/customer/addresses', array(
            'methods' => 'PUT',
            'callback' => array($this, 'update_addresses'),
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
     * Get customer profile
     */
    public function get_profile($request) {
        $user_id = get_current_user_id();
        $user = get_userdata($user_id);

        if (!$user) {
            return new WP_Error(
                'user_not_found',
                __('Usuario no encontrado', 'casa-alba-headless'),
                array('status' => 404)
            );
        }

        $customer = new WC_Customer($user_id);

        $profile = array(
            'id' => $user_id,
            'email' => $user->user_email,
            'username' => $user->user_login,
            'first_name' => $customer->get_first_name(),
            'last_name' => $customer->get_last_name(),
            'display_name' => $user->display_name,
            'phone' => $customer->get_billing_phone(),
            'date_created' => $user->user_registered,
            'orders_count' => wc_get_customer_order_count($user_id),
            'total_spent' => wc_get_customer_total_spent($user_id),
        );

        return rest_ensure_response($profile);
    }

    /**
     * Update customer profile
     */
    public function update_profile($request) {
        $user_id = get_current_user_id();
        $customer = new WC_Customer($user_id);

        $params = $request->get_json_params();

        // Update user data
        if (isset($params['first_name'])) {
            $customer->set_first_name(sanitize_text_field($params['first_name']));
        }

        if (isset($params['last_name'])) {
            $customer->set_last_name(sanitize_text_field($params['last_name']));
        }

        if (isset($params['display_name'])) {
            wp_update_user(array(
                'ID' => $user_id,
                'display_name' => sanitize_text_field($params['display_name'])
            ));
        }

        if (isset($params['phone'])) {
            $customer->set_billing_phone(sanitize_text_field($params['phone']));
        }

        // Handle email update separately (requires validation)
        if (isset($params['email'])) {
            $new_email = sanitize_email($params['email']);

            if (!is_email($new_email)) {
                return new WP_Error(
                    'invalid_email',
                    __('El formato del email no es válido', 'casa-alba-headless'),
                    array('status' => 400)
                );
            }

            // Check if email is already in use by another user
            if (email_exists($new_email) && email_exists($new_email) !== $user_id) {
                return new WP_Error(
                    'email_exists',
                    __('Este email ya está en uso', 'casa-alba-headless'),
                    array('status' => 409)
                );
            }

            wp_update_user(array(
                'ID' => $user_id,
                'user_email' => $new_email
            ));

            $customer->set_email($new_email);
        }

        // Handle password update
        if (isset($params['current_password']) && isset($params['new_password'])) {
            $user = get_userdata($user_id);

            if (!wp_check_password($params['current_password'], $user->user_pass, $user_id)) {
                return new WP_Error(
                    'invalid_password',
                    __('La contraseña actual no es correcta', 'casa-alba-headless'),
                    array('status' => 400)
                );
            }

            if (strlen($params['new_password']) < 8) {
                return new WP_Error(
                    'weak_password',
                    __('La nueva contraseña debe tener al menos 8 caracteres', 'casa-alba-headless'),
                    array('status' => 400)
                );
            }

            wp_set_password($params['new_password'], $user_id);
        }

        // Save customer data
        $customer->save();

        return rest_ensure_response(array(
            'message' => __('Perfil actualizado correctamente', 'casa-alba-headless'),
            'profile' => $this->get_profile($request)->data
        ));
    }

    /**
     * Get customer addresses
     */
    public function get_addresses($request) {
        $user_id = get_current_user_id();
        $customer = new WC_Customer($user_id);

        $addresses = array(
            'billing' => array(
                'first_name' => $customer->get_billing_first_name(),
                'last_name' => $customer->get_billing_last_name(),
                'company' => $customer->get_billing_company(),
                'address_1' => $customer->get_billing_address_1(),
                'address_2' => $customer->get_billing_address_2(),
                'city' => $customer->get_billing_city(),
                'state' => $customer->get_billing_state(),
                'postcode' => $customer->get_billing_postcode(),
                'country' => $customer->get_billing_country(),
                'phone' => $customer->get_billing_phone(),
                'email' => $customer->get_billing_email(),
            ),
            'shipping' => array(
                'first_name' => $customer->get_shipping_first_name(),
                'last_name' => $customer->get_shipping_last_name(),
                'company' => $customer->get_shipping_company(),
                'address_1' => $customer->get_shipping_address_1(),
                'address_2' => $customer->get_shipping_address_2(),
                'city' => $customer->get_shipping_city(),
                'state' => $customer->get_shipping_state(),
                'postcode' => $customer->get_shipping_postcode(),
                'country' => $customer->get_shipping_country(),
            )
        );

        return rest_ensure_response($addresses);
    }

    /**
     * Update customer addresses
     */
    public function update_addresses($request) {
        $user_id = get_current_user_id();
        $customer = new WC_Customer($user_id);

        $params = $request->get_json_params();

        // Update billing address
        if (isset($params['billing']) && is_array($params['billing'])) {
            $billing = $params['billing'];

            if (isset($billing['first_name'])) {
                $customer->set_billing_first_name(sanitize_text_field($billing['first_name']));
            }
            if (isset($billing['last_name'])) {
                $customer->set_billing_last_name(sanitize_text_field($billing['last_name']));
            }
            if (isset($billing['company'])) {
                $customer->set_billing_company(sanitize_text_field($billing['company']));
            }
            if (isset($billing['address_1'])) {
                $customer->set_billing_address_1(sanitize_text_field($billing['address_1']));
            }
            if (isset($billing['address_2'])) {
                $customer->set_billing_address_2(sanitize_text_field($billing['address_2']));
            }
            if (isset($billing['city'])) {
                $customer->set_billing_city(sanitize_text_field($billing['city']));
            }
            if (isset($billing['state'])) {
                $customer->set_billing_state(sanitize_text_field($billing['state']));
            }
            if (isset($billing['postcode'])) {
                $customer->set_billing_postcode(sanitize_text_field($billing['postcode']));
            }
            if (isset($billing['country'])) {
                $customer->set_billing_country(sanitize_text_field($billing['country']));
            }
            if (isset($billing['phone'])) {
                $customer->set_billing_phone(sanitize_text_field($billing['phone']));
            }
            if (isset($billing['email'])) {
                $email = sanitize_email($billing['email']);
                if (is_email($email)) {
                    $customer->set_billing_email($email);
                }
            }
        }

        // Update shipping address
        if (isset($params['shipping']) && is_array($params['shipping'])) {
            $shipping = $params['shipping'];

            if (isset($shipping['first_name'])) {
                $customer->set_shipping_first_name(sanitize_text_field($shipping['first_name']));
            }
            if (isset($shipping['last_name'])) {
                $customer->set_shipping_last_name(sanitize_text_field($shipping['last_name']));
            }
            if (isset($shipping['company'])) {
                $customer->set_shipping_company(sanitize_text_field($shipping['company']));
            }
            if (isset($shipping['address_1'])) {
                $customer->set_shipping_address_1(sanitize_text_field($shipping['address_1']));
            }
            if (isset($shipping['address_2'])) {
                $customer->set_shipping_address_2(sanitize_text_field($shipping['address_2']));
            }
            if (isset($shipping['city'])) {
                $customer->set_shipping_city(sanitize_text_field($shipping['city']));
            }
            if (isset($shipping['state'])) {
                $customer->set_shipping_state(sanitize_text_field($shipping['state']));
            }
            if (isset($shipping['postcode'])) {
                $customer->set_shipping_postcode(sanitize_text_field($shipping['postcode']));
            }
            if (isset($shipping['country'])) {
                $customer->set_shipping_country(sanitize_text_field($shipping['country']));
            }
        }

        // Save customer data
        $customer->save();

        return rest_ensure_response(array(
            'message' => __('Direcciones actualizadas correctamente', 'casa-alba-headless'),
            'addresses' => $this->get_addresses($request)->data
        ));
    }
}

