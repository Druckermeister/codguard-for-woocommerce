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

class CodGuard_Checkout_Validator
{
    private static $rating_checked = false;

    public function __construct()
    {
        // Check rating before checkout processes
        add_action('woocommerce_after_checkout_validation', array($this, 'validate_cod_payment'), 10, 2);

        // Alternative: Check on checkout process
        add_action('woocommerce_checkout_process', array($this, 'check_cod_rating'));
    }

    /**
     * Validate COD payment on checkout
     */
    public function validate_cod_payment($data, $errors)
    {
        $this->check_cod_rating();
    }

    /**
     * Check COD rating during checkout process
     *
     * Note: Nonce verification is handled by WooCommerce before this hook fires.
     * WooCommerce verifies 'woocommerce-process-checkout-nonce' in WC_Checkout::process_checkout()
     * before calling woocommerce_checkout_process and woocommerce_after_checkout_validation hooks.
     */
    public function check_cod_rating()
    {
        // Already checked in this request
        if (self::$rating_checked) {
            codguard_log('Rating already checked in this request, skipping.', 'debug');
            return;
        }

        // Check if plugin enabled
        if (!codguard_is_enabled()) {
            codguard_log('CodGuard plugin is not enabled, skipping checkout validation.', 'debug');
            return;
        }

        // Get chosen payment method
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by WooCommerce
        $payment_method = isset($_POST['payment_method']) ? sanitize_text_field(wp_unslash($_POST['payment_method'])) : '';

        codguard_log(sprintf('Checkout validation triggered. Payment method: %s', $payment_method), 'debug');

        // Check if it's a COD method
        $settings = codguard_get_settings();
        codguard_log(sprintf('Configured COD methods: %s', implode(', ', $settings['cod_methods'])), 'debug');

        if (!in_array($payment_method, $settings['cod_methods'])) {
            codguard_log(sprintf('Payment method "%s" is not in configured COD methods list, skipping validation.', $payment_method), 'debug');
            return; // Not COD, allow
        }

        // Get billing email
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by WooCommerce
        $email = isset($_POST['billing_email']) ? sanitize_email(wp_unslash($_POST['billing_email'])) : '';

        if (empty($email) || !is_email($email)) {
            codguard_log('No valid billing email found, allowing checkout.', 'warning');
            return; // No valid email, allow
        }

        codguard_log(sprintf('Checking customer rating for email: %s', $email), 'info');

        // Check rating
        $rating = $this->get_customer_rating($email);

        // Mark as checked
        self::$rating_checked = true;

        // If API failed, allow (fail-open)
        if ($rating === null) {
            codguard_log('API request failed or returned null, allowing checkout (fail-open).', 'warning');
            return;
        }

        codguard_log(sprintf('Customer rating received: %.2f', $rating), 'info');

        // Compare to tolerance
        $tolerance = (float) $settings['rating_tolerance'] / 100;

        codguard_log(sprintf('Comparing rating %.2f against tolerance %.2f', $rating, $tolerance), 'debug');

        if ($rating < $tolerance) {
            // Block COD
            codguard_log(sprintf('Rating %.2f is below tolerance %.2f - BLOCKING COD payment', $rating, $tolerance), 'warning');

            // Increment block counter
            $this->increment_block_counter($email, $rating);

            // Send feedback to API
            $this->send_feedback($email, $rating, $tolerance, 'blocked');

            wc_add_notice($settings['rejection_message'], 'error');
        } else {
            codguard_log(sprintf('Rating %.2f meets tolerance %.2f - allowing COD payment', $rating, $tolerance), 'info');

            // Send feedback to API
            $this->send_feedback($email, $rating, $tolerance, 'allowed');
        }
    }

    /**
     * Get customer rating from API
     */
    private function get_customer_rating($email)
    {
        $shop_id = codguard_get_shop_id();

        if (empty($shop_id)) {
            return null;
        }

        $api_keys = codguard_get_api_keys();

        // Validate API keys exist
        if (empty($api_keys['public']) || empty($api_keys['private'])) {
            codguard_log('API keys are empty or not configured properly', 'error');
            return null;
        }

        $url = 'https://api.codguard.com/api/customer-rating/' . urlencode($shop_id) . '/' . urlencode($email);

        // Build headers with API keys
        // Note: Customer rating endpoint only requires public key (x-api-key header)
        // This is different from order sync endpoint which requires both keys
        $headers = array(
            'Accept'     => 'application/json',
            'x-api-key'  => $api_keys['public'],
        );

        // Log the API call with headers (mask keys for security)
        if (function_exists('wc_get_logger')) {
            $logger = wc_get_logger();
            $logger->info('Calling CodGuard API: ' . $url, array('source' => 'codguard'));
            $logger->debug(sprintf(
                'Request Headers - x-api-key (Public Key): %s... (%d chars)',
                substr($api_keys['public'], 0, 10),
                strlen($api_keys['public'])
            ), array('source' => 'codguard'));
        }

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
        $response_headers = wp_remote_retrieve_headers($response);

        // Log response with headers
        if (function_exists('wc_get_logger')) {
            $logger = wc_get_logger();
            $logger->info('API Response: Status=' . $status_code . ' Body=' . $body, array('source' => 'codguard'));
            if ($status_code !== 200) {
                $logger->debug('Response Headers: ' . print_r($response_headers, true), array('source' => 'codguard'));
            }
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

    /**
     * Increment block counter
     *
     * @param string $email Customer email
     * @param float $rating Customer rating
     */
    private function increment_block_counter($email, $rating)
    {
        $block_events = get_option('codguard_block_events', array());

        // Add new block event
        $block_events[] = array(
            'timestamp' => current_time('timestamp'),
            'email'     => $email,
            'rating'    => $rating,
        );

        // Keep only last 90 days of data (optional cleanup)
        $ninety_days_ago = current_time('timestamp') - (90 * DAY_IN_SECONDS);
        $block_events = array_filter($block_events, function ($event) use ($ninety_days_ago) {
            return $event['timestamp'] > $ninety_days_ago;
        });

        update_option('codguard_block_events', $block_events);

        codguard_log(sprintf('Block event recorded for %s (rating: %.2f). Total events: %d', $email, $rating, count($block_events)), 'debug');
    }

    /**
     * Send feedback to API
     *
     * @param string $email Customer email
     * @param float $rating Customer rating
     * @param float $threshold Rating threshold
     * @param string $action Action taken (blocked|allowed)
     */
    private function send_feedback($email, $rating, $threshold, $action)
    {
        $shop_id = codguard_get_shop_id();

        if (empty($shop_id)) {
            codguard_log('Cannot send feedback: Shop ID is empty', 'warning');
            return;
        }

        $api_keys = codguard_get_api_keys();

        if (empty($api_keys['public'])) {
            codguard_log('Cannot send feedback: API key is empty', 'warning');
            return;
        }

        $url = 'https://api.codguard.com/api/feedback';

        $body = array(
            'eshop_id'   => (int) $shop_id,
            'email'      => $email,
            'reputation' => (float) $rating,
            'threshold'  => (float) $threshold,
            'action'     => $action,
        );

        $headers = array(
            'Content-Type' => 'application/json',
            'X-API-KEY'    => $api_keys['public'],
        );

        codguard_log(sprintf('Sending feedback to API: %s (action: %s)', $url, $action), 'debug');

        $response = wp_remote_post($url, array(
            'timeout' => 5,
            'headers' => $headers,
            'body'    => wp_json_encode($body),
        ));

        // Handle errors
        if (is_wp_error($response)) {
            codguard_log(sprintf('Feedback API Error: %s', $response->get_error_message()), 'warning');
            return;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($status_code === 200) {
            codguard_log(sprintf('Feedback sent successfully: %s', $response_body), 'debug');
        } else {
            codguard_log(sprintf('Feedback API returned status %d: %s', $status_code, $response_body), 'warning');
        }
    }
}
