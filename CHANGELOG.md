# Changelog - CodGuard for WooCommerce

All notable changes to this project will be documented in this file.

## [2.1.5] - 2025-11-06

### Fixed
- Fixed i18n issue with `_n_noop()` using concatenated strings instead of literals
- Replaced `_n_noop()` with manual array structure for dynamic custom status labels
- Resolves WordPress.WP.I18n.NonSingularStringLiteralSingular and NonSingularStringLiteralPlural warnings

### Technical Details
- Changed `label_count` from `_n_noop()` call to array structure in codguard.php:212-216
- Custom order statuses now use array with keys: 0 (singular), 1 (plural), 'domain'
- Maintains functionality while complying with WordPress i18n standards

---

## [2.1.4] - 2025-11-06

### Fixed
- Added "translators:" comments to all translation functions with placeholders
- Improved translation documentation for better internationalization support

### Technical Details
- Added translator comments above all sprintf(__()) and printf(__()) calls
- Clarifies placeholder meanings for translators (%s, %d)
- Affects files: class-order-sync.php, class-admin-settings.php, settings-page.php, settings-page-sync-section.php

---

## [2.1.3] - 2025-11-06

### Changed
- Updated text domain from 'codguard' to 'CodGuard-Woocommerce' across all files
- Improved compatibility with WordPress plugin standards
- Updated for WordPress plugin checker requirements

### Technical Details
- Modified text domain in main plugin file header
- Updated all translation function calls (__(), _e(), _n_noop(), printf()) to use new text domain
- Affects files: codguard.php, class-admin-settings.php, settings-page.php, settings-page-sync-section.php, class-order-sync.php, class-settings-manager.php

---

## [2.1.2] - 2025-11-04

### Added
- **Customer Rating Check System** - Complete implementation of checkout validation
- Silent rating verification during checkout process
- Automatic COD blocking for customers with low ratings
- Integration with CodGuard Customer Rating API (`/api/customer-rating/{shop_id}/{email}`)

### How It Works
- Customer fills billing email in standard checkout form
- When customer selects COD payment and clicks "Place Order", rating is checked
- If rating < configured tolerance: Error message shown, order blocked
- If rating OK or API error: Order proceeds (fail-open approach)
- Only checks rating when COD payment methods are selected

### Technical Details
- Added `class-checkout-validator.php` for checkout validation
- Hooks into `woocommerce_checkout_process` and `woocommerce_after_checkout_validation`
- Checks payment method against configured COD methods list
- API call only happens for COD payment methods
- Fail-open: API errors allow order to proceed (customer-friendly)
- No modals, no popups - seamless integration with WooCommerce

### Note
- This completes all three phases: Admin Panel, Customer Rating Check, and Daily Order Sync

---

## [2.0.9] - 2025-11-03

### Changed
- **BREAKING:** Now uploads ONLY orders matching configured statuses
- Orders with "Successful Order Status" → outcome = 1
- Orders with "Refused Order Status" → outcome = -1
- All other order statuses are now skipped (not uploaded)

### Improved
- Better logging showing which orders are skipped and why
- Status filtering based on admin panel configuration

### Migration Note
- If you were relying on all orders being uploaded, you may need to adjust your "Successful Order Status" and "Refused Order Status" settings
- Check WooCommerce → Status → Logs → codguard to see which orders are being synced

### Added
- Proper WP-Cron scheduling that persists across sessions
- `maybe_schedule_sync()` method to ensure cron is always scheduled when plugin is enabled
- Tracking of last sync time, status, and count in database options
- Static methods for getting sync status information
- Better logging for schedule creation and cron execution
- Status tracking options: `codguard_last_sync`, `codguard_last_sync_status`, `codguard_last_sync_count`

### Changed
- **BREAKING:** Now uploads ALL orders regardless of payment method (not just COD)
- **BREAKING:** Simplified outcome logic: Refused status = -1, all others = 1
- Removed payment method filtering - was too restrictive
- Removed "good status" outcome mapping - no longer needed
- Updated success email notification to mention new outcome logic
- Improved AJAX response messages to indicate refused=-1, others=1
- Enhanced logging to show payment method, status, and outcome for each order

### Fixed
- WP-Cron not scheduling properly on plugin activation
- "Schedule Status: Inactive" showing incorrectly
- "Next Scheduled Sync" not displaying
- "Last Sync" not updating after manual sync
- Cron schedule not persisting between page loads
- Schedule status display synchronization issues

### Removed
- `test-activation.php` - Faulty test module completely removed
- COD payment method filtering logic
- Status-based outcome mapping (good_status → 1, refused_status → -1)
- Filtering of orders with outcome=0

### Technical Details
- Added `update_option('codguard_last_sync')` after successful sync
- Added `update_option('codguard_last_sync_status', 'success|failed')`  
- Added `update_option('codguard_last_sync_count', $count)`
- Added `maybe_schedule_sync()` called on init hook
- Modified `prepare_order_data()` to remove payment method check
- Modified `prepare_order_data()` to set outcome based on refused status
- Updated `sync_orders()` to track status properly

---

## [2.0.0] - 2025-11-02

### Added
- Complete order synchronization functionality (Phase 3)
- WP-Cron daily scheduling at 02:00 local time
- Manual sync button in admin interface
- Order filtering by COD payment methods
- Status-based outcome mapping (good=1, refused=-1, other=0)
- Admin email notifications on sync success/failure
- AJAX manual sync handler
- Order sync status section in settings page
- Support for custom order statuses
- WooCommerce logger integration

### Changed
- Updated main plugin file to initialize order sync
- Enhanced admin settings page with sync section
- Improved error handling for API failures

### Fixed
- Timezone handling for cron scheduling
- Order date range calculation

---

## [1.0.0] - 2025-11-01

### Added
- Initial plugin release (Phase 1)
- WordPress admin settings page under WooCommerce menu
- API configuration interface (Shop ID, Public Key, Private Key)
- Order status mapping for CodGuard outcomes
- Payment method configuration with multi-select
- Rating tolerance slider (0-100%)
- Custom rejection message editor
- Automatic plugin enable/disable based on API credentials
- Form validation with error messages
- Security features (nonces, sanitization, escaping)
- Helper functions for settings access
- WooCommerce dependency check
- Translation-ready with textdomain 'codguard'

### Security
- Nonce verification for all form submissions
- User capability checks (manage_woocommerce)
- Input sanitization for all fields
- Output escaping for all displayed data
- Private key masking in admin interface
- SQL injection prevention via WordPress Options API
- XSS prevention with proper escaping

---

## Upgrade Notes

### Upgrading to 2.0.8 from 2.0.0
**Important Changes:**
1. All orders now upload (not just COD) - this is intentional
2. All orders get outcome=1 - CodGuard server handles filtering
3. No action needed - settings are preserved
4. Cron will reschedule automatically on reactivation

**What to Check After Upgrade:**
- Settings page → Order Sync Status should show "Active"
- Next scheduled sync time should be displayed
- Click "Sync Now" to test immediately
- Check WooCommerce logs for any errors

### Upgrading to 2.0.0 from 1.0.0
**New Features:**
- Daily order sync now available
- Configure sync in settings page
- Check logs at WooCommerce > Status > Logs

---

## Known Issues

### Version 2.0.8
- None currently known

### Version 2.0.0
- ❌ Cron scheduling not persistent (FIXED in 2.0.8)
- ❌ Status display issues (FIXED in 2.0.8)
- ⚠️ Only syncs COD orders (CHANGED in 2.0.8 - now syncs all)

---

## Support

**Documentation:** https://codguard.com/docs  
**Support Email:** support@codguard.com  
**GitHub Issues:** (if public repository)

---

**Maintained by:** CodGuard Development Team
**Last Updated:** November 6, 2025
