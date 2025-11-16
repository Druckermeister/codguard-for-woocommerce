<!-- Phase 3: Order Sync Status Section -->
<!-- This should be added to the settings-page.php after the Rating Settings section -->

<div class="codguard-settings-section codguard-sync-status-section">
    <h2><?php esc_html_e('Order Sync Status', 'codguard'); ?></h2>
    <p class="description"><?php esc_html_e('Daily order synchronization with CodGuard API. Orders are uploaded at 02:00 local time.', 'codguard'); ?></p>

    <?php
    // Get sync status
    $is_scheduled = CodGuard_Order_Sync::is_scheduled();
    $next_sync = CodGuard_Order_Sync::get_next_sync_time();
    $last_sync = get_option('codguard_last_sync_time', false);
    $last_sync_status = get_option('codguard_last_sync_status', 'unknown');
    $last_sync_count = get_option('codguard_last_sync_count', 0);
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
            <div class="value <?php echo $is_scheduled ? 'success' : 'pending'; ?>">
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
                <?php if ($last_sync) : ?>
                    <span id="codguard-last-sync">
                        <?php echo esc_html(human_time_diff($last_sync, current_time('timestamp')) . ' ' . __('ago', 'codguard')); ?>
                    </span>
                    <br>
                    <span class="codguard-sync-badge <?php echo $last_sync_status === 'success' ? 'success' : 'error'; ?>">
                        <?php
                        if ($last_sync_status === 'success') {
                            /* translators: %d: number of orders synced */
                            printf(esc_html__('%d orders synced', 'codguard'), absint($last_sync_count));
                        } else {
                            esc_html_e('Failed', 'codguard');
                        }
                        ?>
                    </span>
                <?php else : ?>
                    <span class="codguard-sync-badge pending">
                        <?php esc_html_e('Never run', 'codguard'); ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Next Sync Info Box -->
    <?php if ($is_scheduled && $next_sync) : ?>
    <div class="codguard-next-sync-info">
        <span class="dashicons dashicons-clock"></span>
        <div class="info-text">
            <strong><?php esc_html_e('Automatic Sync Scheduled', 'codguard'); ?></strong>
            <p><?php
            /* translators: %s: next sync time */
            printf(esc_html__('Orders from yesterday will be automatically uploaded to CodGuard at %s.', 'codguard'), '<strong>' . esc_html($next_sync) . '</strong>'); ?></p>
        </div>
    </div>
    <?php endif; ?>

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

    <!-- Sync History (Optional) -->
    <?php
    $sync_history = get_option('codguard_sync_history', array());
    if (!empty($sync_history)) :
        ?>
    <div class="codguard-sync-history">
        <h3><?php esc_html_e('Recent Sync History', 'codguard'); ?></h3>
        <table>
            <thead>
                <tr>
                    <th><?php esc_html_e('Date/Time', 'codguard'); ?></th>
                    <th><?php esc_html_e('Status', 'codguard'); ?></th>
                    <th><?php esc_html_e('Orders', 'codguard'); ?></th>
                    <th><?php esc_html_e('Details', 'codguard'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (array_slice($sync_history, 0, 5) as $entry) : ?>
                <tr>
                    <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $entry['timestamp'])); ?></td>
                    <td>
                        <span class="status-<?php echo esc_attr($entry['status']); ?>">
                            <?php echo esc_html($entry['status'] === 'success' ? __('Success', 'codguard') : __('Failed', 'codguard')); ?>
                        </span>
                    </td>
                    <td><?php echo absint($entry['count']); ?></td>
                    <td><?php echo esc_html($entry['message']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Help Section -->
    <div class="codguard-info-box" style="margin-top: 20px;">
        <h3><?php esc_html_e('How Order Sync Works', 'codguard'); ?></h3>
        <ul style="margin-left: 20px;">
            <li><?php esc_html_e('Orders are automatically synced every day at 02:00 (site local time)', 'codguard'); ?></li>
            <li><?php esc_html_e('Only COD (Cash on Delivery) orders are synced', 'codguard'); ?></li>
            <li><?php esc_html_e('Order status is mapped to outcomes based on your configuration above', 'codguard'); ?></li>
            <li><?php
            /* translators: %s: successful order status name */
            printf(esc_html__('Successful orders (status: %s) are reported as outcome: 1', 'codguard'), '<code>' . esc_html($settings['good_status']) . '</code>'); ?></li>
            <li><?php
            /* translators: %s: refused order status name */
            printf(esc_html__('Refused orders (status: %s) are reported as outcome: -1', 'codguard'), '<code>' . esc_html($settings['refused_status']) . '</code>'); ?></li>
            <li><?php esc_html_e('You can trigger a manual sync anytime using the "Sync Now" button', 'codguard'); ?></li>
            <li><?php esc_html_e('View detailed logs in WooCommerce → Status → Logs → select "codguard"', 'codguard'); ?></li>
        </ul>
    </div>
</div>
