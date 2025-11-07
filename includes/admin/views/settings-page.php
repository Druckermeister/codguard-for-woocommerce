<?php
/**
 * Admin Settings Page Template
 * Includes Phase 1 (all original sections) + Phase 3 (sync status)
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get order statuses and payment gateways
$order_statuses = codguard_get_order_statuses();
$payment_gateways = codguard_get_payment_gateways();
$is_enabled = CodGuard_Settings_Manager::is_enabled();
?>

<div class="wrap codguard-settings-wrap">
    <h1><?php esc_html_e('CodGuard Settings', 'codguard'); ?></h1>

    <div class="codguard-settings-header">
        <p><?php esc_html_e('Configure your CodGuard integration to manage cash-on-delivery payments based on customer ratings.', 'codguard'); ?></p>
        
        <?php if ($is_enabled) : ?>
            <div class="codguard-status codguard-status-enabled">
                <span class="dashicons dashicons-yes-alt"></span>
                <?php esc_html_e('Plugin Enabled', 'codguard'); ?>
            </div>
        <?php else : ?>
            <div class="codguard-status codguard-status-disabled">
                <span class="dashicons dashicons-warning"></span>
                <?php esc_html_e('Plugin Disabled', 'codguard'); ?>
            </div>
        <?php endif; ?>
    </div>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="codguard_save_settings">
        <?php wp_nonce_field('codguard_settings_save', 'codguard_nonce'); ?>

        <!-- Section 1: API Configuration -->
        <div class="codguard-settings-section">
            <h2><?php esc_html_e('API Configuration', 'codguard'); ?></h2>
            <p class="description"><?php esc_html_e('Enter your CodGuard API credentials. You can find these in your CodGuard dashboard.', 'codguard'); ?></p>

            <table class="form-table" role="presentation">
                <tbody>
                    <!-- Shop ID -->
                    <tr>
                        <th scope="row">
                            <label for="shop_id">
                                <?php esc_html_e('Shop ID', 'codguard'); ?>
                                <span class="required">*</span>
                            </label>
                        </th>
                        <td>
                            <input 
                                type="text" 
                                name="shop_id" 
                                id="shop_id" 
                                value="<?php echo esc_attr($settings['shop_id']); ?>" 
                                class="regular-text" 
                                required
                            >
                            <p class="description">
                                <?php esc_html_e('Your unique shop identifier from CodGuard.', 'codguard'); ?>
                            </p>
                        </td>
                    </tr>

                    <!-- Public Key -->
                    <tr>
                        <th scope="row">
                            <label for="public_key">
                                <?php esc_html_e('Public Key', 'codguard'); ?>
                                <span class="required">*</span>
                            </label>
                        </th>
                        <td>
                            <input 
                                type="text" 
                                name="public_key" 
                                id="public_key" 
                                value="<?php echo esc_attr($settings['public_key']); ?>" 
                                class="regular-text" 
                                required
                                minlength="10"
                            >
                            <p class="description">
                                <?php esc_html_e('Your API public key (minimum 10 characters).', 'codguard'); ?>
                            </p>
                        </td>
                    </tr>

                    <!-- Private Key -->
                    <tr>
                        <th scope="row">
                            <label for="private_key">
                                <?php esc_html_e('Private Key', 'codguard'); ?>
                                <span class="required">*</span>
                            </label>
                        </th>
                        <td>
                            <?php if (!empty($settings['private_key'])) : ?>
                                <input 
                                    type="password" 
                                    name="private_key" 
                                    id="private_key" 
                                    class="regular-text" 
                                    placeholder="<?php esc_attr_e('••••••••••••••••', 'codguard'); ?>"
                                    autocomplete="new-password"
                                    minlength="10"
                                >
                                <p class="description">
                                    <?php esc_html_e('Private key is set. Leave blank to keep current value, or enter a new key to update.', 'codguard'); ?>
                                </p>
                            <?php else : ?>
                                <input 
                                    type="password" 
                                    name="private_key" 
                                    id="private_key" 
                                    value="" 
                                    class="regular-text" 
                                    autocomplete="new-password"
                                    required
                                    minlength="10"
                                >
                                <p class="description">
                                    <?php esc_html_e('Your API private key (minimum 10 characters). Keep this secure!', 'codguard'); ?>
                                </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Section 2: Order Status Mapping -->
        <div class="codguard-settings-section">
            <h2><?php esc_html_e('Order Status Mapping', 'codguard'); ?></h2>
            <p class="description"><?php esc_html_e('Map WooCommerce order statuses to CodGuard outcomes for order reporting.', 'codguard'); ?></p>

            <table class="form-table" role="presentation">
                <tbody>
                    <!-- Successful Order Status -->
                    <tr>
                        <th scope="row">
                            <label for="good_status">
                                <?php esc_html_e('Successful Order Status', 'codguard'); ?>
                            </label>
                        </th>
                        <td>
                            <select name="good_status" id="good_status" class="regular-text">
                                <?php foreach ($order_statuses as $status_slug => $status_name) : ?>
                                    <option value="<?php echo esc_attr(str_replace('wc-', '', $status_slug)); ?>" <?php selected($settings['good_status'], str_replace('wc-', '', $status_slug)); ?>>
                                        <?php echo esc_html($status_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">
                                <?php esc_html_e('Orders with this status will be marked as successful (outcome: 1) when reported to CodGuard.', 'codguard'); ?>
                            </p>
                        </td>
                    </tr>

                    <!-- Refused Order Status -->
                    <tr>
                        <th scope="row">
                            <label for="refused_status">
                                <?php esc_html_e('Refused Order Status', 'codguard'); ?>
                            </label>
                        </th>
                        <td>
                            <select name="refused_status" id="refused_status" class="regular-text">
                                <?php foreach ($order_statuses as $status_slug => $status_name) : ?>
                                    <option value="<?php echo esc_attr(str_replace('wc-', '', $status_slug)); ?>" <?php selected($settings['refused_status'], str_replace('wc-', '', $status_slug)); ?>>
                                        <?php echo esc_html($status_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">
                                <?php esc_html_e('Orders with this status will be marked as refused (outcome: -1) when reported to CodGuard.', 'codguard'); ?>
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Section 3: Payment Method Configuration -->
        <div class="codguard-settings-section">
            <h2><?php esc_html_e('Payment Method Configuration', 'codguard'); ?></h2>
            <p class="description"><?php esc_html_e('Select which payment methods should trigger customer rating checks.', 'codguard'); ?></p>

            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label>
                                <?php esc_html_e('Cash on Delivery Methods', 'codguard'); ?>
                            </label>
                        </th>
                        <td>
                            <?php if (!empty($payment_gateways)) : ?>
                                <fieldset>
                                    <?php foreach ($payment_gateways as $gateway_id => $gateway_title) : ?>
                                        <label style="display: block; margin-bottom: 8px;">
                                            <input 
                                                type="checkbox" 
                                                name="cod_methods[]" 
                                                value="<?php echo esc_attr($gateway_id); ?>"
                                                <?php checked(in_array($gateway_id, $settings['cod_methods'])); ?>
                                            >
                                            <?php echo esc_html($gateway_title); ?>
                                        </label>
                                    <?php endforeach; ?>
                                </fieldset>
                                <p class="description">
                                    <?php esc_html_e('Select all payment methods that should trigger customer rating checks. Typically, this includes cash on delivery methods.', 'codguard'); ?>
                                </p>
                            <?php else : ?>
                                <p class="description">
                                    <?php esc_html_e('No payment gateways are currently available. Please configure your WooCommerce payment methods first.', 'codguard'); ?>
                                </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Section 4: Rating Settings -->
        <div class="codguard-settings-section">
            <h2><?php esc_html_e('Rating Settings', 'codguard'); ?></h2>
            <p class="description"><?php esc_html_e('Configure how customer ratings affect payment method availability.', 'codguard'); ?></p>

            <table class="form-table" role="presentation">
                <tbody>
                    <!-- Rating Tolerance -->
                    <tr>
                        <th scope="row">
                            <label for="rating_tolerance">
                                <?php esc_html_e('Rating Tolerance', 'codguard'); ?>
                            </label>
                        </th>
                        <td>
                            <input 
                                type="number" 
                                name="rating_tolerance" 
                                id="rating_tolerance" 
                                value="<?php echo esc_attr($settings['rating_tolerance']); ?>" 
                                min="0" 
                                max="100" 
                                step="1"
                                class="small-text"
                            > %
                            <p class="description">
                                <?php esc_html_e('Customers with a rating below this threshold will not be able to use COD payment methods. Recommended: 30-40%.', 'codguard'); ?>
                            </p>
                        </td>
                    </tr>

                    <!-- Rejection Message -->
                    <tr>
                        <th scope="row">
                            <label for="rejection_message">
                                <?php esc_html_e('Rejection Message', 'codguard'); ?>
                            </label>
                        </th>
                        <td>
                            <textarea 
                                name="rejection_message" 
                                id="rejection_message" 
                                rows="3" 
                                class="large-text"
                                maxlength="500"
                                required
                            ><?php echo esc_textarea($settings['rejection_message']); ?></textarea>
                            <p class="description">
                                <?php esc_html_e('This message will be displayed to customers whose rating is below the tolerance threshold. Maximum 500 characters.', 'codguard'); ?>
                            </p>
                        </td>
                    </tr>

                    <!-- Notification Email -->
                    <tr>
                        <th scope="row">
                            <label for="notification_email">
                                <?php esc_html_e('Notification Email', 'codguard'); ?>
                            </label>
                        </th>
                        <td>
                            <input 
                                type="email" 
                                name="notification_email" 
                                id="notification_email" 
                                value="<?php echo esc_attr($settings['notification_email']); ?>" 
                                class="regular-text"
                            >
                            <p class="description">
                                <?php esc_html_e('Email address for API error notifications and alerts. Default: info@codguard.com', 'codguard'); ?>
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Save Button -->
        <p class="submit">
            <?php submit_button(esc_html__('Save Settings', 'codguard'), 'primary', 'submit', false); ?>
        </p>
    </form>

    <!-- PHASE 3: Order Sync Status Section -->
    <?php if (class_exists('CodGuard_Order_Sync')) : ?>
    <div class="codguard-settings-section codguard-sync-status-section">
        <h2><?php esc_html_e('Order Sync Status', 'codguard'); ?></h2>
        <p class="description"><?php esc_html_e('Daily order synchronization with CodGuard API. Orders are uploaded at 02:00 local time.', 'codguard'); ?></p>

        <?php
        // Get sync status
        $is_scheduled = CodGuard_Order_Sync::is_scheduled();
        $next_sync = CodGuard_Order_Sync::get_next_sync_time();
        $last_sync_raw = get_option('codguard_last_sync', false);
        $last_sync_timestamp = $last_sync_raw ? strtotime($last_sync_raw) : false;
        $last_sync_display = CodGuard_Order_Sync::get_last_sync_time();
        $last_sync_status = CodGuard_Order_Sync::get_last_sync_status();
        $last_sync_count = CodGuard_Order_Sync::get_last_sync_count();
        $last_sync_error = get_option('codguard_last_sync_error');
        ?>

        <!-- Sync Status Grid -->
        <div class="codguard-sync-status-grid">
            <!-- Schedule Status -->
            <div class="codguard-sync-status-item">
                <h4><?php esc_html_e('Schedule Status', 'codguard'); ?></h4>
                <div class="value">
                    <?php if ($is_scheduled && $is_enabled) : ?>
                        <span class="codguard-sync-badge success">
                            <span class="dashicons dashicons-yes-alt"></span>
                            <?php esc_html_e('Active', 'codguard'); ?>
                        </span>
                    <?php else : ?>
                        <span class="codguard-sync-badge error">
                            <span class="dashicons dashicons-warning"></span>
                            <?php esc_html_e('Inactive', 'codguard'); ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Next Sync Time -->
            <div class="codguard-sync-status-item">
                <h4><?php esc_html_e('Next Scheduled Sync', 'codguard'); ?></h4>
                <div class="value <?php echo esc_attr($is_scheduled ? 'success' : 'pending'); ?>">
                    <?php
                    if ($next_sync) {
                        echo esc_html($next_sync);
                    } else {
                        esc_html_e('Not scheduled', 'codguard');
                    }
                    ?>
                </div>
            </div>

            <!-- Last Sync Status -->
            <div class="codguard-sync-status-item">
                <h4><?php esc_html_e('Last Sync', 'codguard'); ?></h4>
                <div class="value">
                    <?php if ($last_sync_timestamp) : ?>
                        <span id="codguard-last-sync">
                            <?php echo esc_html(human_time_diff($last_sync_timestamp, current_time('timestamp')) . ' ' . __('ago', 'codguard')); ?>
                        </span>
                        <br>
                        <?php if ($last_sync_display) : ?>
                            <small><?php echo esc_html($last_sync_display); ?></small>
                            <br>
                        <?php endif; ?>
                        <span class="codguard-sync-badge <?php echo esc_attr($last_sync_status === 'success' ? 'success' : 'error'); ?>">
                            <?php
                            if ($last_sync_status === 'success') {
                                /* translators: %d: number of orders synced */
                                printf(esc_html__('%d orders synced', 'codguard'), absint($last_sync_count));
                            } else {
                                esc_html_e('Failed', 'codguard');
                            }
                            ?>
                        </span>
                        <?php if ('failed' === $last_sync_status && !empty($last_sync_error)) : ?>
                            <p class="codguard-sync-error">
                                <?php printf(esc_html__('Last error: %s', 'codguard'), esc_html($last_sync_error)); ?>
                            </p>
                        <?php endif; ?>
                    <?php else : ?>
                        <span class="codguard-sync-badge pending">
                            <?php esc_html_e('Never run', 'codguard'); ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Manual Sync Button -->
        <div style="margin-top: 20px;">
            <button type="button" id="codguard-manual-sync" class="button button-secondary">
                <span class="dashicons dashicons-update"></span>
                <span class="button-text"><?php esc_html_e('Sync Now', 'codguard'); ?></span>
            </button>
            <p class="description" style="margin-top: 10px;">
                <?php esc_html_e('Manually trigger order synchronization for yesterday\'s orders. This will upload all COD orders from the previous day to CodGuard.', 'codguard'); ?>
            </p>
        </div>

        <!-- Sync Message Container -->
        <div id="codguard-sync-message" style="display: none;"></div>
    </div>
    <?php endif; ?>

</div>
