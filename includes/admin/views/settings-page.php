<?php

/**
 * Admin Settings Page Template
 * CodGuard settings configuration
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
                                    value="<?php echo esc_attr($settings['private_key']); ?>" 
                                    class="regular-text" 
                                    placeholder="<?php esc_attr_e('••••••••••••••••', 'codguard'); ?>"
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
                                <?php esc_html_e('Orders with this status will be allowed for future COD payments.', 'codguard'); ?>
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
                                <?php esc_html_e('Orders with this status will be blocked for future COD payments.', 'codguard'); ?>
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Create Custom Status -->
            <div style="margin-top: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
                <h3 style="margin-top: 0;"><?php esc_html_e('Create Custom Order Status', 'codguard'); ?></h3>
                <p class="description">
                    <?php esc_html_e('Need a custom order status? Create one here and it will be immediately available in the dropdowns above.', 'codguard'); ?>
                </p>
                <div style="margin-top: 15px;">
                    <input
                        type="text"
                        id="custom_status_name"
                        placeholder="<?php esc_attr_e('Enter status name (e.g., Pending Payment)', 'codguard'); ?>"
                        class="regular-text"
                        maxlength="50"
                    >
                    <button type="button" id="codguard-create-status" class="button button-secondary">
                        <?php esc_html_e('Create Status', 'codguard'); ?>
                    </button>
                </div>
                <div id="codguard-status-message" style="display: none; margin-top: 10px;"></div>
            </div>
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
                </tbody>
            </table>
        </div>

        <!-- Save Button -->
        <p class="submit">
            <?php submit_button(esc_html__('Save Settings', 'codguard'), 'primary', 'submit', false); ?>
        </p>
    </form>

    <!-- COD Block Statistics Section -->
    <div class="codguard-settings-section" style="margin-top: 30px;">
        <h2><?php esc_html_e('COD Block Statistics', 'codguard'); ?></h2>
        <p class="description"><?php esc_html_e('View how many times cash-on-delivery payment was blocked due to low customer ratings.', 'codguard'); ?></p>

        <?php
        // Get block events
        $block_events = get_option('codguard_block_events', array());
        $current_time = current_time('timestamp');

        // Calculate stats for different periods
        $stats = array(
            'today' => 0,
            'week' => 0,
            'month' => 0,
            'all' => count($block_events),
        );

        $today_start = strtotime('today', $current_time);
        $week_start = strtotime('-7 days', $current_time);
        $month_start = strtotime('-30 days', $current_time);

        foreach ($block_events as $event) {
            if ($event['timestamp'] >= $today_start) {
                $stats['today']++;
            }
            if ($event['timestamp'] >= $week_start) {
                $stats['week']++;
            }
            if ($event['timestamp'] >= $month_start) {
                $stats['month']++;
            }
        }
        ?>

        <!-- Stats Display -->
        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-top: 20px;">
            <div style="background: #fff; border: 1px solid #ddd; border-radius: 4px; padding: 20px; text-align: center;">
                <div style="font-size: 32px; font-weight: bold; color: #2271b1;"><?php echo esc_html($stats['today']); ?></div>
                <div style="margin-top: 8px; color: #666;"><?php esc_html_e('Today', 'codguard'); ?></div>
            </div>

            <div style="background: #fff; border: 1px solid #ddd; border-radius: 4px; padding: 20px; text-align: center;">
                <div style="font-size: 32px; font-weight: bold; color: #2271b1;"><?php echo esc_html($stats['week']); ?></div>
                <div style="margin-top: 8px; color: #666;"><?php esc_html_e('Last 7 Days', 'codguard'); ?></div>
            </div>

            <div style="background: #fff; border: 1px solid #ddd; border-radius: 4px; padding: 20px; text-align: center;">
                <div style="font-size: 32px; font-weight: bold; color: #2271b1;"><?php echo esc_html($stats['month']); ?></div>
                <div style="margin-top: 8px; color: #666;"><?php esc_html_e('Last 30 Days', 'codguard'); ?></div>
            </div>

            <div style="background: #fff; border: 1px solid #ddd; border-radius: 4px; padding: 20px; text-align: center;">
                <div style="font-size: 32px; font-weight: bold; color: #2271b1;"><?php echo esc_html($stats['all']); ?></div>
                <div style="margin-top: 8px; color: #666;"><?php esc_html_e('All Time', 'codguard'); ?></div>
            </div>
        </div>

        <?php if (!empty($block_events)) : ?>
        <!-- Recent Blocks Table -->
        <div style="margin-top: 30px;">
            <h3><?php esc_html_e('Recent Blocks', 'codguard'); ?></h3>
            <table class="wp-list-table widefat fixed striped" style="margin-top: 10px;">
                <thead>
                    <tr>
                        <th style="width: 25%;"><?php esc_html_e('Date & Time', 'codguard'); ?></th>
                        <th style="width: 50%;"><?php esc_html_e('Customer Email', 'codguard'); ?></th>
                        <th style="width: 25%;"><?php esc_html_e('Rating', 'codguard'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Show last 10 events
                    $recent_events = array_slice(array_reverse($block_events), 0, 10);
                    foreach ($recent_events as $event) :
                        ?>
                    <tr>
                        <td><?php echo esc_html(date_i18n('Y-m-d H:i:s', $event['timestamp'])); ?></td>
                        <td><?php echo esc_html($event['email']); ?></td>
                        <td><?php echo esc_html(number_format($event['rating'] * 100, 1) . '%'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if (count($block_events) > 10) : ?>
            <p class="description" style="margin-top: 10px;">
                <?php
                /* translators: %d: number of total block events */
                printf(esc_html__('Showing 10 most recent blocks out of %d total.', 'codguard'), count($block_events));
                ?>
            </p>
            <?php endif; ?>
        </div>
        <?php else : ?>
        <p style="margin-top: 20px; padding: 20px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
            <?php esc_html_e('No COD blocks recorded yet. Statistics will appear here when customers are blocked from using cash-on-delivery payment.', 'codguard'); ?>
        </p>
        <?php endif; ?>
    </div>

</div>
