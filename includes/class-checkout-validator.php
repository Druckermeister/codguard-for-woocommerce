<?php
/**
 * CodGuard Checkout Validator - Alternative Approach
 * 
 * This uses WooCommerce checkout hooks instead of AJAX modal
 * Similar to UVB connector approach
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class CodGuard_Checkout_Validator {
    
    private static $rating_checked = false;
    private static $rating_result = null;
    
    public function __construct() {
        // Check rating before checkout processes
        add_action('woocommerce_after_checkout_validation', array($this, 'validate_cod_payment'), 10, 2);
        
        // Alternative: Check on checkout process
        add_action('woocommerce_checkout_process', array($this, 'check_cod_rating'));
    }
    
    /**
     * Validate COD payment on checkout
     */
    public function validate_cod_payment($data, $errors) {
        $this->check_cod_rating();
    }
    
    /**
     * Check COD rating during checkout process
     */
    public function check_cod_rating() {
        // Already checked in this request
        if (self::$rating_checked) {
            return;
        }
        
        // Check if plugin enabled
        if (!codguard_is_enabled()) {
            return;
        }
        
        // Get chosen payment method
        $payment_method = isset($_POST['payment_method']) ? sanitize_text_field($_POST['payment_method']) : '';
        
        // Check if it's a COD method
        $settings = codguard_get_settings();
        if (!in_array($payment_method, $settings['cod_methods'])) {
            return; // Not COD, allow
        }
        
        // Get billing email
        $email = isset($_POST['billing_email']) ? sanitize_email($_POST['billing_email']) : '';
        
        if (empty($email) || !is_email($email)) {
            return; // No valid email, allow
        }
        
        // Check rating
        $rating = $this->get_customer_rating($email);
        
        // Mark as checked
        self::$rating_checked = true;
        self::$rating_result = $rating;
        
        // If API failed, allow (fail-open)
        if ($rating === null) {
            return;
        }
        
        // Compare to tolerance
        $tolerance = (float) $settings['rating_tolerance'] / 100;
        
        if ($rating < $tolerance) {
            // Block COD
            wc_add_notice($settings['rejection_message'], 'error');
        }
    }
    
    /**
     * Get customer rating from API
     */
    private function get_customer_rating($email) {
        $shop_id = codguard_get_shop_id();
        
        if (empty($shop_id)) {
            return null;
        }
        
        $api_keys = codguard_get_api_keys();
        $url = 'https://api.codguard.com/api/customer-rating/' . urlencode($shop_id) . '/' . urlencode($email);
        
        // Log the API call
        if (function_exists('wc_get_logger')) {
            $logger = wc_get_logger();
            $logger->info('Calling CodGuard API: ' . $url, array('source' => 'codguard'));
        }
        
        // Build headers with API keys
        $headers = array(
            'Accept' => 'application/json',
            'x-api-key' => $api_keys['public']
        );
        
        $response = wp_remote_get($url, array(
            'timeout' => 10,
            'headers' => $headers
        ));
        
        // Handle errors
        if (is_wp_error($response)) {
            if (function_exists('wc_get_logger')) {
                $logger = wc_get_logger();
                $logger->error('API Error: ' . $response->get_error_message(), array('source' => 'codguard'));
            }
            return null; // Fail open
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        // Log response
        if (function_exists('wc_get_logger')) {
            $logger = wc_get_logger();
            $logger->info('API Response: Status=' . $status_code . ' Body=' . $body, array('source' => 'codguard'));
        }
        
        // 404 = new customer, allow
        if ($status_code === 404) {
            return 1.0;
        }
        
        // Other non-200 status, fail open
        if ($status_code !== 200) {
            return null;
        }
        
        // Parse JSON
        $data = json_decode($body, true);
        
        if (!isset($data['rating'])) {
            return null;
        }
        
        return (float) $data['rating'];
    }
}
