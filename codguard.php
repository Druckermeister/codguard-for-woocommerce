<?php
/**
 * Plugin Name: CodGuard for WooCommerce
 * Plugin URI: https://codguard.com
 * Description: Integrates with the CodGuard API to manage cash-on-delivery payment options based on customer ratings and synchronize order data.
 * Version: 2.1.5
 * Author: CodGuard
 * Author URI: https://codguard.com
 * Text Domain: CodGuard-Woocommerce
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * WC requires at least: 4.0
 * WC tested up to: 8.0
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('CODGUARD_VERSION', '2.1.5');
define('CODGUARD_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CODGUARD_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CODGUARD_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Initialize automatic updates from GitHub
 */
require CODGUARD_PLUGIN_DIR . 'vendor/autoload.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$codguard_update_checker = PucFactory::buildUpdateChecker(
    'https://github.com/Druckermeister/codguard-woocommerce/',
    __FILE__,
    'codguard-woocommerce'
);

// Enable GitHub releases
$codguard_update_checker->getVcsApi()->enableReleaseAssets();

// Set branch (optional, defaults to main)
$codguard_update_checker->setBranch('main');

/**
 * Check if WooCommerce is active
 */
function codguard_is_woocommerce_active() {
    // Check for single site
    if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins', array())))) {
        return true;
    }
    
    // Check for multisite
    if (is_multisite()) {
        $plugins = get_site_option('active_sitewide_plugins');
        if (isset($plugins['woocommerce/woocommerce.php'])) {
            return true;
        }
    }
    
    // Alternative check: see if WooCommerce class exists
    return class_exists('WooCommerce');
}

/**
 * Display admin notice if WooCommerce is not active
 */
function codguard_woocommerce_missing_notice() {
    ?>
    <div class="notice notice-error">
        <p><?php _e('CodGuard for WooCommerce requires WooCommerce to be installed and active.', 'CodGuard-Woocommerce'); ?></p>
    </div>
    <?php
}

/**
 * Initialize the plugin
 */
function codguard_init() {
    // Check if WooCommerce is active
    if (!codguard_is_woocommerce_active()) {
        add_action('admin_notices', 'codguard_woocommerce_missing_notice');
        return;
    }

    // Load plugin files
    require_once CODGUARD_PLUGIN_DIR . 'includes/functions.php';
    require_once CODGUARD_PLUGIN_DIR . 'includes/class-settings-manager.php';
    require_once CODGUARD_PLUGIN_DIR . 'includes/admin/class-admin-settings.php';
    require_once CODGUARD_PLUGIN_DIR . 'includes/class-order-sync.php';

    // Initialize admin settings
    if (is_admin()) {
        CodGuard_Admin_Settings::init();
    }

    // Load customer rating check validator
    require_once CODGUARD_PLUGIN_DIR . 'includes/class-checkout-validator.php';

    // Initialize order sync if plugin is enabled
    if (function_exists('codguard_is_enabled') && codguard_is_enabled()) {
        $order_sync = new CodGuard_Order_Sync();

        // Schedule sync if not already scheduled
        add_action('init', array($order_sync, 'maybe_schedule_sync'));
    }

    // Register custom order statuses
    add_action('init', 'codguard_register_custom_order_statuses');
    add_filter('wc_order_statuses', 'codguard_add_custom_order_statuses');
}
add_action('plugins_loaded', 'codguard_init');

/**
 * Plugin activation hook
 */
function codguard_activate() {
    // Check if WooCommerce is active
    if (!codguard_is_woocommerce_active()) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(__('CodGuard for WooCommerce requires WooCommerce to be installed and active.', 'CodGuard-Woocommerce'));
    }

    // Load required files
    require_once CODGUARD_PLUGIN_DIR . 'includes/class-settings-manager.php';

    // Initialize default settings if not exists
    if (!get_option('codguard_settings')) {
        $defaults = CodGuard_Settings_Manager::get_default_settings();
        update_option('codguard_settings', $defaults);
    }
    
    // Load order sync class
    require_once CODGUARD_PLUGIN_DIR . 'includes/class-order-sync.php';
    
    // Schedule cron if plugin is enabled
    if (function_exists('codguard_is_enabled') && codguard_is_enabled()) {
        $order_sync = new CodGuard_Order_Sync();
        $order_sync->schedule_sync();
    }
}
register_activation_hook(__FILE__, 'codguard_activate');

/**
 * Plugin deactivation hook
 */
function codguard_deactivate() {
    // Clear scheduled cron
    wp_clear_scheduled_hook('codguard_daily_order_sync');
    
    // Clear any transients
    delete_transient('codguard_settings_saved');
    delete_transient('codguard_settings_errors');
    
    // Log deactivation
    if (function_exists('codguard_log')) {
        codguard_log('Plugin deactivated, cron schedule cleared', 'info');
    }
}
register_deactivation_hook(__FILE__, 'codguard_deactivate');

/**
 * Load plugin text domain for translations
 */
function codguard_load_textdomain() {
    load_plugin_textdomain('CodGuard-Woocommerce', false, dirname(CODGUARD_PLUGIN_BASENAME) . '/languages');
}
add_action('init', 'codguard_load_textdomain');

/**
 * Declare HPOS (High-Performance Order Storage) compatibility
 */
add_action('before_woocommerce_init', function() {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

/**
 * Initialize customer rating check validator
 */
function codguard_init_rating_check() {
    // Initialize checkout validator (validates during checkout process)
    if (class_exists('CodGuard_Checkout_Validator')) {
        new CodGuard_Checkout_Validator();
    }
}
add_action('init', 'codguard_init_rating_check', 5);

/**
 * Register custom order statuses with WordPress
 */
function codguard_register_custom_order_statuses() {
    $custom_statuses = get_option('codguard_custom_statuses', array());

    if (empty($custom_statuses)) {
        return;
    }

    foreach ($custom_statuses as $slug => $label) {
        register_post_status('wc-' . $slug, array(
            'label' => $label,
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => array(
                0 => $label . ' <span class="count">(%s)</span>',
                1 => $label . ' <span class="count">(%s)</span>',
                'domain' => 'CodGuard-Woocommerce'
            )
        ));
    }
}

/**
 * Add custom order statuses to WooCommerce
 *
 * @param array $order_statuses Existing order statuses
 * @return array Modified order statuses
 */
function codguard_add_custom_order_statuses($order_statuses) {
    $custom_statuses = get_option('codguard_custom_statuses', array());

    if (empty($custom_statuses)) {
        return $order_statuses;
    }

    foreach ($custom_statuses as $slug => $label) {
        $order_statuses['wc-' . $slug] = $label;
    }

    return $order_statuses;
}

/**
 * Add action links to plugin page
 */
function codguard_plugin_action_links($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=codguard-settings') . '">' . __('Settings', 'CodGuard-Woocommerce') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . CODGUARD_PLUGIN_BASENAME, 'codguard_plugin_action_links');
