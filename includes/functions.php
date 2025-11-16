<?php

/**
 * Helper Functions
 * Global helper functions for the CodGuard plugin
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get all settings
 *
 * @return array Settings array
 */
function codguard_get_settings()
{
    return CodGuard_Settings_Manager::get_settings();
}

/**
 * Update settings
 *
 * @param array $new_settings New settings to save
 * @return bool True on success, false on failure
 */
function codguard_update_settings($new_settings)
{
    return CodGuard_Settings_Manager::update_settings($new_settings);
}

/**
 * Get shop ID
 *
 * @return string Shop ID
 */
function codguard_get_shop_id()
{
    return CodGuard_Settings_Manager::get_shop_id();
}

/**
 * Get API keys
 *
 * @return array Array with 'public' and 'private' keys
 */
function codguard_get_api_keys()
{
    return CodGuard_Settings_Manager::get_api_keys();
}

/**
 * Get rating tolerance
 *
 * @return int Rating tolerance (0-100)
 */
function codguard_get_tolerance()
{
    return CodGuard_Settings_Manager::get_tolerance();
}

/**
 * Get COD payment methods
 *
 * @return array Array of payment gateway IDs
 */
function codguard_get_cod_methods()
{
    return CodGuard_Settings_Manager::get_cod_methods();
}

/**
 * Get order status mappings
 *
 * @return array Array with 'good' and 'refused' status slugs
 */
function codguard_get_status_mappings()
{
    return CodGuard_Settings_Manager::get_status_mappings();
}

/**
 * Get rejection message
 *
 * @return string Rejection message
 */
function codguard_get_rejection_message()
{
    return CodGuard_Settings_Manager::get_rejection_message();
}

/**
 * Check if plugin is enabled
 *
 * @return bool True if enabled, false otherwise
 */
function codguard_is_enabled()
{
    return CodGuard_Settings_Manager::is_enabled();
}

/**
 * Log message to WooCommerce logger
 *
 * @param string $message Message to log
 * @param string $level Log level (info, warning, error, debug)
 */
function codguard_log($message, $level = 'info')
{
    if (function_exists('wc_get_logger')) {
        $logger = wc_get_logger();
        $logger->log($level, $message, array('source' => 'codguard'));
    }
}

/**
 * Get available WooCommerce order statuses
 *
 * @return array Array of order statuses
 */
function codguard_get_order_statuses()
{
    if (function_exists('wc_get_order_statuses')) {
        return wc_get_order_statuses();
    }
    return array();
}

/**
 * Get available payment gateways
 *
 * @return array Array of payment gateways
 */
function codguard_get_payment_gateways()
{
    $gateways = array();

    if (function_exists('WC')) {
        $payment_gateways = WC()->payment_gateways();
        $available_gateways = $payment_gateways->get_available_payment_gateways();

        foreach ($available_gateways as $gateway_id => $gateway) {
            $gateways[$gateway_id] = $gateway->get_title();
        }
    }

    return $gateways;
}

/**
 * Check if a payment method is a COD method
 *
 * @param string $payment_method Payment method ID
 * @return bool True if COD method, false otherwise
 */
function codguard_is_cod_method($payment_method)
{
    $cod_methods = codguard_get_cod_methods();
    return in_array($payment_method, $cod_methods);
}
