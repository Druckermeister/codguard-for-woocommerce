# CodGuard for WooCommerce - Version 2.1.2

## [2.1.2] - 2025-11-04

### Added
- **Complete Customer Rating Check System** - Silent verification at checkout
- Customer rating validation during checkout process (hooks into WooCommerce checkout)
- Automatic COD blocking for low-rated customers
- Seamless integration - no modal, no popups, works with standard checkout flow

### Features Now Complete
✅ **Admin Settings Page** - Configure API keys, tolerance, COD methods, order statuses
✅ **Customer Rating Check** - Silent API check when customer submits order with COD
✅ **Daily Order Sync** - Uploads yesterday's orders to CodGuard API at 2 AM

### How It Works
1. Customer fills billing email in normal checkout form
2. Customer selects COD payment method
3. When "Place Order" is clicked, rating is checked automatically
4. If rating < tolerance: Error message shown, order blocked
5. If rating OK: Order proceeds normally

### Technical Details
- Added `class-checkout-validator.php` for checkout validation
- Hooks into `woocommerce_checkout_process` and `woocommerce_after_checkout_validation`
- Integration with CodGuard Customer Rating API endpoint
- Fail-open approach: API errors allow order to proceed (customer-friendly)

---

## [2.1.1] - 2025-11-04

### Changed
- Now syncs orders based on `date_modified` instead of `date_created`
- This ensures orders that were updated/completed yesterday are synced
- Better captures status changes that occurred yesterday

✅ **Proper WP-Cron Scheduling Implemented**
- Automatic daily synchronization now properly scheduled at 02:00 local time
- Schedule persists across page reloads and plugin updates
- "Inactive" status issue resolved

✅ **All Orders Now Upload**
- Changed from COD-only to ALL payment methods
- ALL order statuses now upload (not just completed/cancelled)
- Every order gets `outcome = 1` 
- CodGuard server now handles all filtering logic

✅ **Test Module Removed**
- Removed faulty `test-activation.php` file completely
- Cleaner plugin structure

✅ **Improved Status Display**
- "Last Sync" now displays correctly after manual sync
- "Next Scheduled Sync" shows proper datetime
- Status indicators work properly

---

## Installation

1. **Deactivate** the old version (if installed)
2. **Delete** the old version
3. **Upload** version 2.0.8
4. **Activate** the plugin
5. Go to **WooCommerce > CodGuard**
6. Verify settings are preserved
7. Check "Order Sync Status" section - should show "Active" and next sync time

---

## How Order Sync Works Now

### What Gets Uploaded

**ALL orders from yesterday** including:
- ✅ Cash on Delivery orders
- ✅ Credit card orders  
- ✅ PayPal orders
- ✅ Bank transfer orders
- ✅ ANY payment method

**ALL order statuses:**
- ✅ Completed
- ✅ Processing
- ✅ On Hold
- ✅ Cancelled
- ✅ Refunded
- ✅ Failed
- ✅ Pending
- ✅ ANY status

### Outcome Value

**Outcome logic:**
- **Refused status** (as configured in admin) → `outcome = -1`
- **All other statuses** → `outcome = 1`

The refused status is the one you configured in **WooCommerce > CodGuard > Order Status Mapping > Refused Order Status** (default: "Cancelled").

### Why This Change?

Previously, the plugin was trying to filter orders based on:
- COD payment methods only
- Specific order statuses only

This was too restrictive. The new approach:
- Uploads everything (no payment method filtering)
- Uses simple refused/not-refused logic
- Lets CodGuard server process all order data
- Simpler plugin logic = fewer bugs

---

## Cron Schedule Details

### When Orders Sync
- **Daily at 02:00** (your WordPress site's timezone)
- Uploads orders from the **previous day** (00:00 to 23:59)

### How to Verify It's Scheduled

1. Go to **WooCommerce > CodGuard**
2. Scroll to **Order Sync Status** section
3. Check these fields:
   - **Schedule Status:** Should say "Active" (green)
   - **Next Scheduled Sync:** Should show a date/time
   - **Last Sync:** Shows when last sync ran

### Manual Sync

You can click **"Sync Now"** to:
- Test the API connection
- Upload yesterday's orders immediately
- See how many orders were found

---

## What Changed from 2.0.0 to 2.0.8

| Feature | Version 2.0.0 | Version 2.0.8 |
|---------|--------------|---------------|
| Cron Scheduling | ❌ Not working | ✅ Working properly |
| Upload Filter | Only COD orders | ALL orders |
| Outcome Logic | Based on status mapping | Always 1 |
| Test File | ✅ Included | ❌ Removed |
| Status Display | ❌ Broken | ✅ Fixed |
| Schedule Persistence | ❌ Not persistent | ✅ Persistent |

---

## Troubleshooting

### "Schedule Status: Inactive"

**Cause:** Plugin is not enabled or cron not scheduled

**Fix:**
1. Check that Shop ID, Public Key, and Private Key are entered
2. Save settings (this auto-enables the plugin)
3. Refresh the page
4. If still inactive, deactivate and reactivate the plugin

### "Next Scheduled Sync: Not scheduled"

**Cause:** Cron event not registered

**Fix:**
1. Deactivate the plugin
2. Reactivate the plugin (this triggers scheduling)
3. Refresh the settings page
4. Should now show next sync time

### "Last Sync: Never run"

**Normal:** This is normal if you just installed the plugin

**To test it:**
1. Click "Sync Now" button
2. Wait for response
3. Refresh page
4. "Last Sync" should now show a timestamp

### Manual Sync Returns "0 orders"

**Cause:** No orders created yesterday

**Fix:** This is normal. The sync only looks for orders from **yesterday** (not today, not older)

To test with orders:
1. Create test orders with yesterday's date (requires direct DB edit or wait until tomorrow)
2. Or modify the date range temporarily for testing

---

## Technical Details

### Cron Hook
- **Hook name:** `codguard_daily_order_sync`
- **Recurrence:** `codguard_daily` (24 hours)
- **Function:** `CodGuard_Order_Sync::sync_orders()`

### WP-Cron Check
To verify cron is scheduled, run this in MySQL:
```sql
SELECT * FROM wp_options WHERE option_name = 'cron';
```
Look for `codguard_daily_order_sync` in the serialized array.

### Logs
All sync activity is logged to WooCommerce logs:
- Go to **WooCommerce > Status > Logs**
- Select **codguard-{date}.log**
- View sync attempts, results, and errors

---

## API Request Format

Orders are sent to: `https://api.codguard.com/api/orders/import`

**Headers:**
```
Content-Type: application/json
X-API-PUBLIC-KEY: {your_public_key}
X-API-PRIVATE-KEY: {your_private_key}
```

**Body:**
```json
{
  "orders": [
    {
      "eshop_id": 123,
      "email": "customer@example.com",
      "code": "ORDER-001",
      "status": "completed",
      "outcome": 1,
      "phone": "+36701234567",
      "country_code": "HU",
      "postal_code": "1111",
      "address": "Budapest, Example St. 1"
    }
  ]
}
```

**Note:** `outcome` is always `1` for all orders in v2.0.8

---

## Email Notifications

When sync runs (success or failure), an email is sent to:
- The email specified in **Settings > Notification Email**
- OR the WordPress admin email if not specified

**Success email includes:**
- Number of orders synced
- Date and time
- Link to logs

**Failure email includes:**
- Error message
- Number of orders attempted
- Links to settings and logs

---

## Files Changed in 2.0.8

**Modified:**
- `codguard.php` - Updated to v2.0.8, added proper cron initialization
- `includes/class-order-sync.php` - Complete rewrite:
  - Uploads ALL orders
  - All orders get outcome=1
  - Fixed cron scheduling
  - Added status tracking

**Removed:**
- `test-activation.php` - Faulty test module completely removed

**Unchanged:**
- Admin settings pages
- Settings manager
- Helper functions
- CSS/JS assets

---

## Upgrade Path

### From 2.0.0 → 2.0.8
1. Deactivate old version
2. Delete old files
3. Upload 2.0.8
4. Activate
5. Settings preserved automatically
6. Cron scheduled automatically

### From 1.x → 2.0.8
1. Export settings (write them down)
2. Deactivate and delete old version
3. Install 2.0.8
4. Re-enter settings
5. Save settings

---

## Support

**Documentation:** https://codguard.com/docs  
**Support Email:** info@codguard.com  
**Plugin Version:** 2.1.1  
**Release Date:** November 3, 2025

---

## Version History

### 2.0.8 (2025-11-03)
- ✅ Fixed: WP-Cron scheduling now works properly
- ✅ Changed: Upload ALL orders (not just COD)
- ✅ Changed: All orders get outcome=1
- ✅ Removed: test-activation.php file
- ✅ Fixed: Status display issues
- ✅ Added: Better cron persistence

### 2.0.0 (2025-11-02)
- Initial release with order sync
- COD filtering (removed in 2.0.8)
- Status mapping (simplified in 2.0.8)

### 1.0.0 (2025-11-01)
- Admin settings panel
- Phase 1 functionality only
