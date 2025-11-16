<?php

/**
 * Order Sync Class
 * Handles real-time bundled order synchronization with CodGuard API
 *
 * @package CodGuard
 * @since 2.0.0
 * @version 2.2.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class CodGuard_Order_Sync
{
    /**
     * Queue transient key
     */
    const QUEUE_KEY = 'codguard_order_queue';

    /**
     * Bundled send hook name
     */
    const SEND_HOOK = 'codguard_send_bundled_orders';

    /**
     * Bundling delay in seconds (1 hour)
     */
    const BUNDLE_DELAY = 3600;

    /**
     * Initialize order sync functionality
     */
    public function __construct()
    {
        // Hook into WooCommerce order status changes
        add_action('woocommerce_order_status_changed', array($this, 'on_order_status_changed'), 10, 4);

        // Register bundled send hook
        add_action(self::SEND_HOOK, array($this, 'send_bundled_orders'));
    }

    /**
     * Handle order status change
     * Adds order to queue and schedules bundled send
     *
     * @param int $order_id Order ID
     * @param string $old_status Old status
     * @param string $new_status New status
     * @param WC_Order $order Order object
     */
    public function on_order_status_changed($order_id, $old_status, $new_status, $order)
    {
        // Check if plugin is enabled
        if (!codguard_is_enabled()) {
            return;
        }

        // Get configured statuses
        $status_mappings = codguard_get_status_mappings();
        $successful_status = $status_mappings['good'];
        $refused_status = $status_mappings['refused'];

        // Only queue orders that match configured statuses
        if ($new_status !== $successful_status && $new_status !== $refused_status) {
            return;
        }

        codguard_log(sprintf(
            'Order #%d status changed to %s - adding to queue',
            $order_id,
            $new_status
        ), 'info');

        // Add to queue
        $this->add_to_queue($order);

        // Schedule bundled send if not already scheduled
        if (!wp_next_scheduled(self::SEND_HOOK)) {
            wp_schedule_single_event(time() + self::BUNDLE_DELAY, self::SEND_HOOK);
            codguard_log(sprintf(
                'Bundled send scheduled for %s (1 hour delay)',
                date('Y-m-d H:i:s', time() + self::BUNDLE_DELAY)
            ), 'info');
        }
    }

    /**
     * Add order to queue
     *
     * @param WC_Order $order Order object
     */
    private function add_to_queue($order)
    {
        // Get current queue
        $queue = get_transient(self::QUEUE_KEY);
        if ($queue === false) {
            $queue = array();
        }

        // Prepare order data
        $order_data = $this->prepare_single_order($order);

        if ($order_data === null) {
            return;
        }

        // Add to queue (use order ID as key to prevent duplicates)
        $queue[$order->get_id()] = $order_data;

        // Save queue (24 hour expiry as safety net)
        set_transient(self::QUEUE_KEY, $queue, DAY_IN_SECONDS);

        codguard_log(sprintf(
            'Order #%d added to queue (%d orders total)',
            $order->get_id(),
            count($queue)
        ), 'debug');
    }

    /**
     * Send bundled orders from queue
     */
    public function send_bundled_orders()
    {
        // Get queue
        $queue = get_transient(self::QUEUE_KEY);

        if (empty($queue)) {
            codguard_log('Bundled send triggered but queue is empty', 'info');
            return;
        }

        codguard_log(sprintf('Sending %d bundled orders to API', count($queue)), 'info');

        // Convert to indexed array for API
        $order_data = array_values($queue);

        // Send to API
        $result = $this->send_to_api($order_data);

        if (is_wp_error($result)) {
            codguard_log(sprintf('Bundled send failed: %s', $result->get_error_message()), 'error');
            // Don't clear queue on failure - will retry on next bundle
            return;
        }

        // Clear queue on success
        delete_transient(self::QUEUE_KEY);
        codguard_log(sprintf('Successfully sent %d bundled orders', count($order_data)), 'info');
    }

    /**
     * Prepare single order data for API
     *
     * @param WC_Order $order Order object
     * @return array|null Formatted order data or null if invalid
     */
    private function prepare_single_order($order)
    {
        $shop_id = codguard_get_shop_id();
        $status_mappings = codguard_get_status_mappings();

        // Get configured statuses
        $successful_status = $status_mappings['good'];
        $refused_status = $status_mappings['refused'];
        $order_status = $order->get_status();

        // Get billing info
        $billing_email = $order->get_billing_email();
        $billing_phone = $order->get_billing_phone();
        $billing_country = $order->get_billing_country();
        $billing_postcode = $order->get_billing_postcode();
        $billing_address = $this->format_address($order);

        // Skip if no email (required field)
        if (empty($billing_email)) {
            codguard_log(sprintf('Skipping order #%d: No email address', $order->get_id()), 'warning');
            return null;
        }

        // Determine outcome based on status
        // Refused status = "-1", successful status = "1"
        $outcome = ($order_status === $refused_status) ? '-1' : '1';

        return array(
            'eshop_id'     => (int) $shop_id,
            'email'        => $billing_email,
            'code'         => $order->get_order_number(),
            'status'       => $order_status,
            'outcome'      => $outcome,
            'phone'        => $billing_phone ?: '',
            'country_code' => $billing_country ?: '',
            'postal_code'  => $billing_postcode ?: '',
            'address'      => $billing_address,
        );
    }

    /**
     * Format billing address
     *
     * @param WC_Order $order Order object
     * @return string Formatted address
     */
    private function format_address($order)
    {
        $address_parts = array_filter(array(
            $order->get_billing_address_1(),
            $order->get_billing_address_2(),
            $order->get_billing_city(),
            $order->get_billing_state(),
        ));

        return implode(', ', $address_parts);
    }

    /**
     * Send order data to CodGuard API
     *
     * @param array $order_data Prepared order data
     * @return array|WP_Error API response or error
     */
    private function send_to_api($order_data)
    {
        if (empty($order_data)) {
            return new WP_Error('no_orders', 'No orders to sync');
        }

        $keys = codguard_get_api_keys();
        $url = 'https://api.codguard.com/api/orders/import';

        $body = array(
            'orders' => $order_data
        );

        codguard_log(sprintf('Sending %d orders to API', count($order_data)), 'debug');

        $response = wp_remote_post($url, array(
            'timeout' => 30,
            'headers' => array(
                'Content-Type'       => 'application/json',
                'X-API-PUBLIC-KEY'   => $keys['public'],
                'X-API-PRIVATE-KEY'  => $keys['private'],
            ),
            'body' => wp_json_encode($body),
        ));

        // Check for WP errors
        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        codguard_log(sprintf('API Response: Status %d, Body: %s', $status_code, $response_body), 'debug');

        // Check status code
        if ($status_code !== 200 && $status_code !== 201) {
            return new WP_Error(
                'api_error',
                sprintf('API returned status code %d: %s', $status_code, $response_body)
            );
        }

        // Parse response
        $data = json_decode($response_body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_error', 'Invalid JSON response from API');
        }

        return $data;
    }
}
