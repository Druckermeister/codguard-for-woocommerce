<?php

/**
 * Settings Manager Class
 * Handles all CRUD operations for CodGuard settings
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class CodGuard_Settings_Manager
{
    /**
     * Option name in wp_options table
     */
    const OPTION_NAME = 'codguard_settings';

    /**
     * Get default settings
     *
     * @return array Default settings array
     */
    public static function get_default_settings()
    {
        return array(
            'shop_id' => '',
            'public_key' => '',
            'private_key' => '',
            'good_status' => 'completed',
            'refused_status' => 'cancelled',
            'cod_methods' => array(),
            'rating_tolerance' => 35,
            'rejection_message' => __('Unfortunately, we cannot offer Cash on Delivery for this order.', 'codguard'),
            'notification_email' => 'info@codguard.com',
            'enabled' => false
        );
    }

    /**
     * Get all settings
     *
     * @return array Settings array with defaults merged
     */
    public static function get_settings()
    {
        $defaults = self::get_default_settings();
        $settings = get_option(self::OPTION_NAME, $defaults);

        // Ensure all default keys exist
        return wp_parse_args($settings, $defaults);
    }

    /**
     * Update settings
     *
     * @param array $new_settings New settings to save
     * @return bool True on success, false on failure
     */
    public static function update_settings($new_settings)
    {
        $current = self::get_settings();
        $updated = array_merge($current, $new_settings);

        // Auto-enable if all required API credentials are provided
        if (
            !empty($updated['shop_id']) &&
            !empty($updated['public_key']) &&
            !empty($updated['private_key'])
        ) {
            $updated['enabled'] = true;
        } else {
            $updated['enabled'] = false;
        }

        return update_option(self::OPTION_NAME, $updated);
    }

    /**
     * Get shop ID
     *
     * @return string Shop ID
     */
    public static function get_shop_id()
    {
        $settings = self::get_settings();
        return $settings['shop_id'];
    }

    /**
     * Get API keys
     *
     * @return array Array with 'public' and 'private' keys
     */
    public static function get_api_keys()
    {
        $settings = self::get_settings();
        return array(
            'public' => $settings['public_key'],
            'private' => $settings['private_key']
        );
    }

    /**
     * Get rating tolerance
     *
     * @return int Rating tolerance (0-100)
     */
    public static function get_tolerance()
    {
        $settings = self::get_settings();
        return (int) $settings['rating_tolerance'];
    }

    /**
     * Get COD payment methods
     *
     * @return array Array of payment gateway IDs
     */
    public static function get_cod_methods()
    {
        $settings = self::get_settings();
        return is_array($settings['cod_methods']) ? $settings['cod_methods'] : array();
    }

    /**
     * Get order status mappings
     *
     * @return array Array with 'good' and 'refused' status slugs
     */
    public static function get_status_mappings()
    {
        $settings = self::get_settings();
        return array(
            'good' => $settings['good_status'],
            'refused' => $settings['refused_status']
        );
    }

    /**
     * Get rejection message
     *
     * @return string Rejection message
     */
    public static function get_rejection_message()
    {
        $settings = self::get_settings();
        return $settings['rejection_message'];
    }

    /**
     * Check if plugin is enabled
     *
     * @return bool True if enabled, false otherwise
     */
    public static function is_enabled()
    {
        $settings = self::get_settings();
        return (bool) $settings['enabled'];
    }

    /**
     * Validate settings before saving
     *
     * @param array $settings Settings to validate
     * @return array Array of error messages (empty if valid)
     */
    public static function validate_settings($settings)
    {
        $errors = array();

        // Shop ID validation
        if (empty($settings['shop_id'])) {
            $errors[] = __('Shop ID is required.', 'codguard');
        }

        // Public Key validation
        if (empty($settings['public_key'])) {
            $errors[] = __('Public Key is required.', 'codguard');
        } elseif (strlen($settings['public_key']) < 10) {
            $errors[] = __('Public Key must be at least 10 characters long.', 'codguard');
        }

        // Private Key validation
        if (empty($settings['private_key'])) {
            $errors[] = __('Private Key is required.', 'codguard');
        } elseif (strlen($settings['private_key']) < 10) {
            $errors[] = __('Private Key must be at least 10 characters long.', 'codguard');
        }

        // Rating tolerance validation
        if (
            !is_numeric($settings['rating_tolerance']) ||
            $settings['rating_tolerance'] < 0 ||
            $settings['rating_tolerance'] > 100
        ) {
            $errors[] = __('Rating Tolerance must be a number between 0 and 100.', 'codguard');
        }

        // Rejection message validation
        if (empty($settings['rejection_message'])) {
            $errors[] = __('Rejection Message is required.', 'codguard');
        } elseif (strlen($settings['rejection_message']) > 500) {
            $errors[] = __('Rejection Message must not exceed 500 characters.', 'codguard');
        }

        // Notification email validation
        if (!empty($settings['notification_email']) && !is_email($settings['notification_email'])) {
            $errors[] = __('Notification Email must be a valid email address.', 'codguard');
        }

        return $errors;
    }

    /**
     * Sanitize settings
     *
     * @param array $raw_settings Raw settings from form
     * @return array Sanitized settings
     */
    public static function sanitize_settings($raw_settings)
    {
        return array(
            'shop_id' => sanitize_text_field($raw_settings['shop_id']),
            'public_key' => sanitize_text_field($raw_settings['public_key']),
            'private_key' => sanitize_text_field($raw_settings['private_key']),
            'good_status' => sanitize_text_field($raw_settings['good_status']),
            'refused_status' => sanitize_text_field($raw_settings['refused_status']),
            'cod_methods' => isset($raw_settings['cod_methods']) && is_array($raw_settings['cod_methods'])
                ? array_map('sanitize_text_field', $raw_settings['cod_methods'])
                : array(),
            'rating_tolerance' => max(0, min(100, intval($raw_settings['rating_tolerance']))),
            'rejection_message' => sanitize_textarea_field($raw_settings['rejection_message']),
            'notification_email' => sanitize_email($raw_settings['notification_email'])
        );
    }
}
