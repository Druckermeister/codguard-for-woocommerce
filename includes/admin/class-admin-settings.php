<?php
/**
 * Admin Settings Class
 * Handles the admin interface for CodGuard settings
 * Updated for Phase 3 with manual sync support
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class CodGuard_Admin_Settings {

    /**
     * Initialize admin settings
     */
    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'add_admin_menu'), 99);
        add_action('admin_post_codguard_save_settings', array(__CLASS__, 'save_settings'));
        add_action('admin_notices', array(__CLASS__, 'admin_notices'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_scripts'));
        add_action('wp_ajax_codguard_create_order_status', array(__CLASS__, 'ajax_create_order_status'));
    }

    /**
     * Add admin menu under WooCommerce
     */
    public static function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __('CodGuard Settings', 'codguard'),
            __('CodGuard', 'codguard'),
            'manage_woocommerce',
            'codguard-settings',
            array(__CLASS__, 'render_settings_page')
        );
    }

    /**
     * Enqueue admin scripts and styles
     *
     * @param string $hook Current admin page hook
     */
    public static function enqueue_scripts($hook) {
        // Only load on our settings page
        if ('woocommerce_page_codguard-settings' !== $hook) {
            return;
        }

        // Enqueue CSS
        wp_enqueue_style(
            'codguard-admin',
            CODGUARD_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            CODGUARD_VERSION
        );

        // Enqueue JavaScript
        wp_enqueue_script(
            'codguard-admin',
            CODGUARD_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            CODGUARD_VERSION,
            true
        );

        // Localize script with data (Phase 1 + Phase 3)
        wp_localize_script('codguard-admin', 'codguardAdminData', array(
            'nonce' => wp_create_nonce('codguard_admin'),
            'i18n' => array(
                // Phase 1: Custom status creation
                'emptyStatusName' => esc_html__('Please enter a status name.', 'codguard'),
                'statusTooLong' => esc_html__('Status name must not exceed 50 characters.', 'codguard'),
                'creating' => esc_html__('Creating...', 'codguard'),
                'createStatus' => esc_html__('Create Status', 'codguard'),
                'genericError' => esc_html__('An error occurred. Please try again.', 'codguard'),
                'reloadPage' => esc_html__('Status created successfully! Reload the page to see it in the dropdowns?', 'codguard'),

                // Phase 3: Manual sync
                'confirmSync' => esc_html__('Are you sure you want to sync yesterday\'s orders now?', 'codguard'),
                'syncing' => esc_html__('Syncing...', 'codguard'),
                'syncSuccess' => esc_html__('Orders synced successfully!', 'codguard'),
                'syncFailed' => esc_html__('Sync failed. Please check the logs for details.', 'codguard'),
                'syncError' => esc_html__('An error occurred during sync. Please try again.', 'codguard'),
                'justNow' => esc_html__('Just now', 'codguard')
            )
        ));
    }

    /**
     * Render settings page
     */
    public static function render_settings_page() {
        // Check user capabilities
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'codguard'));
        }

        // Get current settings
        $settings = CodGuard_Settings_Manager::get_settings();

        // Include the view template
        include CODGUARD_PLUGIN_DIR . 'includes/admin/views/settings-page.php';
    }

    /**
     * Save settings
     */
    public static function save_settings() {
        // Verify nonce
        if (!isset($_POST['codguard_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['codguard_nonce'])), 'codguard_settings_save')) {
            wp_die(esc_html__('Security check failed.', 'codguard'));
        }

        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have sufficient permissions to perform this action.', 'codguard'));
        }

        $current_settings = CodGuard_Settings_Manager::get_settings();

        // Sanitize settings
        $settings = CodGuard_Settings_Manager::sanitize_settings($_POST, $current_settings);

        // Validate settings
        $errors = CodGuard_Settings_Manager::validate_settings($settings);

        if (!empty($errors)) {
            // Store errors in transient
            set_transient('codguard_settings_errors', $errors, 45);
            wp_redirect(admin_url('admin.php?page=codguard-settings&error=1'));
            exit;
        }

        // Save settings
        $saved = CodGuard_Settings_Manager::update_settings($settings);

        if ($saved) {
            // Success message
            set_transient('codguard_settings_saved', true, 45);
            
            // Log the save action
            codguard_log('Settings saved successfully.');
            
            // Phase 3: Reschedule sync if plugin is now enabled
            if (codguard_is_enabled() && class_exists('CodGuard_Order_Sync')) {
                $order_sync = new CodGuard_Order_Sync();
                $order_sync->schedule_sync();
            }
            
            wp_redirect(admin_url('admin.php?page=codguard-settings&saved=1'));
        } else {
            // Error saving
            set_transient('codguard_settings_errors', array(__('Failed to save settings. Please try again.', 'codguard')), 45);
            wp_redirect(admin_url('admin.php?page=codguard-settings&error=1'));
        }
        exit;
    }

    /**
     * Display admin notices
     */
    public static function admin_notices() {
        // Only show on our settings page
        if (!function_exists('get_current_screen')) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen || $screen->id !== 'woocommerce_page_codguard-settings') {
            return;
        }

        // Success notice
        if (get_transient('codguard_settings_saved')) {
            delete_transient('codguard_settings_saved');
            ?>
            <div class="notice notice-success is-dismissible">
                <p><?php esc_html_e('CodGuard settings saved successfully!', 'codguard'); ?></p>
            </div>
            <?php
        }

        // Error notices
        if ($errors = get_transient('codguard_settings_errors')) {
            delete_transient('codguard_settings_errors');
            ?>
            <div class="notice notice-error is-dismissible">
                <?php foreach ($errors as $error) : ?>
                    <p><?php echo esc_html($error); ?></p>
                <?php endforeach; ?>
            </div>
            <?php
        }

        // Plugin status notice
        if (!CodGuard_Settings_Manager::is_enabled()) {
            ?>
            <div class="notice notice-warning">
                <p><?php esc_html_e('CodGuard is currently disabled. Please enter your API credentials to enable the plugin.', 'codguard'); ?></p>
            </div>
            <?php
        }
    }

    /**
     * AJAX handler to create custom order status
     */
    public static function ajax_create_order_status() {
        // Verify nonce
        if (!check_ajax_referer('codguard_admin', 'nonce', false)) {
            wp_send_json_error(array(
                'message' => __('Security check failed.', 'codguard')
            ));
        }

        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array(
                'message' => __('You do not have sufficient permissions.', 'codguard')
            ));
        }

        // Get and validate status name
        $status_name = isset($_POST['status_name']) ? sanitize_text_field(wp_unslash($_POST['status_name'])) : '';

        if (empty($status_name)) {
            wp_send_json_error(array(
                'message' => __('Status name is required.', 'codguard')
            ));
        }

        if (strlen($status_name) > 50) {
            wp_send_json_error(array(
                'message' => __('Status name must not exceed 50 characters.', 'codguard')
            ));
        }

        // Create slug from name
        $status_slug = 'wc-' . sanitize_title($status_name);
        
        // Remove wc- prefix for storage (WooCommerce adds it)
        $status_slug_clean = str_replace('wc-', '', $status_slug);

        // Check if status already exists
        $existing_statuses = wc_get_order_statuses();
        if (isset($existing_statuses[$status_slug])) {
            wp_send_json_error(array(
                'message' => __('A status with this name already exists.', 'codguard')
            ));
        }

        // Register the custom status
        try {
            // Store in options for persistence
            $custom_statuses = get_option('codguard_custom_statuses', array());
            $custom_statuses[$status_slug_clean] = $status_name;
            update_option('codguard_custom_statuses', $custom_statuses);

            // Log the creation
            codguard_log(sprintf('Custom order status created: %s (%s)', $status_name, $status_slug_clean));

            wp_send_json_success(array(
                /* translators: %s: name of the order status */
                'message' => sprintf(__('Order status "%s" created successfully!', 'codguard'), $status_name),
                'slug' => $status_slug_clean,
                'label' => $status_name
            ));
        } catch (Exception $e) {
            codguard_log('Error creating custom status: ' . $e->getMessage(), 'error');
            wp_send_json_error(array(
                'message' => __('Failed to create status. Please try again.', 'codguard')
            ));
        }
    }
}
