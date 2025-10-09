# Installation Guide - Voxel PayPal Gateway

## Quick Start (5 Minutes)

### Step 1: Install Plugin

```bash
# Option A: Upload via WordPress Admin
1. Download plugin as ZIP
2. Go to Plugins → Add New → Upload Plugin
3. Choose voxel-paypal-gateway.zip
4. Click "Install Now"
5. Click "Activate"

# Option B: Manual Installation
1. Upload voxel-paypal-gateway/ to /wp-content/plugins/
2. Activate in WordPress admin under Plugins
```

### Step 2: Get PayPal Credentials

**For Testing (Sandbox):**
1. Go to https://developer.paypal.com/dashboard/
2. Log in with your PayPal account
3. Click "Apps & Credentials"
4. Under "Sandbox" tab, click "Create App"
5. Name your app (e.g., "My Site Sandbox")
6. Copy **Client ID** and **Secret**

**For Production (Live):**
1. Go to https://www.paypal.com/businessprofile/settings/
2. Click "Account Settings" → "Website payments" → "API access"
3. Click "Update" → "Manage API credentials"
4. Under "REST API apps", click "Create App"
5. Copy **Client ID** and **Secret**

### Step 3: Configure Plugin

1. In WordPress admin, go to **Voxel → Orders → Payments**
2. Under "Payment Provider", select **PayPal**
3. Configure settings:

```
Mode: Sandbox (for testing) or Live (for production)
Currency: USD (or your preferred currency)

Sandbox/Live Credentials:
- Client ID: [paste your client ID]
- Client Secret: [paste your secret]

Payment Settings:
- Order Approval: Automatic (recommended)
- Brand Name: [your business name]
- Landing Page: No Preference
```

4. Click **Save Changes**

### Step 4: Configure Webhooks

1. In PayPal Dashboard, go to webhooks section:
   - **Sandbox**: https://developer.paypal.com/dashboard/applications/sandbox
   - **Live**: https://www.paypal.com/businessprofile/settings/

2. Click your app, then "Add Webhook"

3. Enter Webhook URL:
```
https://yoursite.com/?vx=1&action=paypal.webhooks
```

4. Select these events:
   - [x] Payment capture completed
   - [x] Payment capture denied
   - [x] Payment capture refunded
   - [x] Payment authorization created
   - [x] Payment authorization voided
   - [x] Checkout order approved

5. Save webhook

6. Copy the **Webhook ID** (looks like: `WH-XXXXX`)

7. Back in WordPress, under Payments settings:
   - Paste Webhook ID in "Webhook ID" field
   - Click **Save Changes**

### Step 5: Test Payment

1. Create a test product in Voxel
2. Add to cart and proceed to checkout
3. Click "Pay with PayPal"
4. Complete payment on PayPal (use sandbox test account if testing)
5. Verify order status shows "Completed"

**Done!** 🎉

---

## Detailed Configuration

### Payment Modes

#### Sandbox Mode (Testing)
Use this to test without real money:
- Get credentials from https://developer.paypal.com/dashboard/
- Use PayPal sandbox test accounts
- Transactions are simulated
- **Do not use in production!**

#### Live Mode (Production)
Use this for real payments:
- Get credentials from https://www.paypal.com/businessprofile/settings/
- Real money transactions
- Customers use real PayPal accounts
- **Test thoroughly in sandbox first!**

### Order Approval Modes

#### Automatic Capture (Recommended)
```
When to use: Most e-commerce sites
How it works:
1. Customer approves payment on PayPal
2. Payment is captured immediately
3. Order status: Completed
4. Funds available in your PayPal account

Benefits:
- Faster checkout
- Immediate payment
- No vendor action needed
```

#### Manual Capture
```
When to use: Service businesses, custom orders, pre-orders
How it works:
1. Customer approves payment on PayPal
2. Payment is authorized (held)
3. Order status: Pending Approval
4. Vendor reviews order
5. Vendor approves → payment captured
6. Or vendor declines → authorization voided

Benefits:
- Review orders before charging
- Prevent fraudulent orders
- Adjust pricing before capture
```

### Currency Configuration

PayPal supports 25+ currencies. Common ones:

```
USD - US Dollar
EUR - Euro
GBP - British Pound
CAD - Canadian Dollar
AUD - Australian Dollar
JPY - Japanese Yen
```

**Important:** All products must use the same currency you configure here.

### Webhook Configuration

Webhooks notify your site when PayPal events occur.

**Why webhooks are important:**
- Real-time order status updates
- Handle refunds automatically
- Process delayed captures
- Improve reliability

**Setting up webhooks:**

1. Get your webhook URL:
```
https://yoursite.com/?vx=1&action=paypal.webhooks
```

2. In PayPal, create webhook with these events:
```
PAYMENT.CAPTURE.COMPLETED       → Payment succeeded
PAYMENT.CAPTURE.DENIED          → Payment failed
PAYMENT.CAPTURE.DECLINED        → Payment declined
PAYMENT.CAPTURE.REFUNDED        → Payment refunded
PAYMENT.AUTHORIZATION.CREATED   → Payment authorized (manual)
PAYMENT.AUTHORIZATION.VOIDED    → Authorization cancelled
CHECKOUT.ORDER.APPROVED         → Customer approved order
```

3. Test webhook delivery:
   - In PayPal, find your webhook
   - Click "Send test"
   - Check delivery status

**Troubleshooting webhooks:**
- Ensure webhook URL is publicly accessible
- Check SSL certificate is valid
- Verify webhook secret is configured
- Check WordPress debug log for errors

---

## Common Issues

### "Voxel theme required" error
**Solution:** This plugin only works with the Voxel theme. Install and activate Voxel first.

### "Unable to authenticate with PayPal"
**Causes:**
- Wrong Client ID or Secret
- Sandbox credentials in Live mode (or vice versa)
- Network/firewall blocking PayPal API

**Solutions:**
1. Double-check credentials match the mode
2. Regenerate credentials in PayPal dashboard
3. Check server can reach api-m.paypal.com

### "Payment failed" error
**Causes:**
- Invalid currency configuration
- Missing required fields
- PayPal account issues

**Solutions:**
1. Check debug log: `/wp-content/debug.log`
2. Enable WP_DEBUG in wp-config.php
3. Verify PayPal account is in good standing

### Webhook not firing
**Causes:**
- Webhook URL not accessible
- SSL certificate issues
- Incorrect webhook secret

**Solutions:**
1. Test webhook URL in browser
2. Check SSL with https://www.ssllabs.com/ssltest/
3. Verify webhook ID in settings
4. Check PayPal webhook delivery log

### Order stuck in "Pending Payment"
**Causes:**
- Customer didn't complete PayPal checkout
- Webhook not configured
- Network error during capture

**Solutions:**
1. Configure webhooks (see above)
2. Manually click "Sync with PayPal" in order details
3. Check PayPal dashboard for payment status

---

## Security Checklist

Before going live:

- [ ] Switch to Live mode
- [ ] Use Live credentials (not Sandbox)
- [ ] Configure Live webhook
- [ ] Enable HTTPS on your site
- [ ] Test with small transaction
- [ ] Verify webhook delivery
- [ ] Check refund process works
- [ ] Review PayPal account settings
- [ ] Set up 2FA on PayPal account
- [ ] Configure email notifications in PayPal

---

## Support Resources

### Documentation
- Plugin README: `/voxel-paypal-gateway/README.md`
- Architecture: `/voxel-paypal-gateway/ARCHITECTURE.md`
- PayPal Docs: https://developer.paypal.com/docs/

### Debugging

Enable WordPress debugging:
```php
// In wp-config.php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
```

Check logs:
```
/wp-content/debug.log
```

### Getting Help

1. Check debug log for specific error messages
2. Review PayPal API documentation
3. Test in Sandbox mode to isolate issues
4. Contact plugin support with:
   - WordPress version
   - PHP version
   - Voxel theme version
   - Error messages from debug log
   - Steps to reproduce issue

---

## Upgrading

### From Sandbox to Live

1. In WordPress admin, go to Voxel → Orders → Payments
2. Change Mode from "Sandbox" to "Live"
3. Enter Live credentials (Client ID and Secret)
4. Update webhook:
   - Create new webhook in Live PayPal account
   - Use same webhook URL
   - Enter Webhook ID in plugin settings
5. Save changes
6. Test with small transaction
7. Verify webhook delivery

### Plugin Updates

```bash
# Backup first!
1. Backup your site
2. Deactivate plugin
3. Delete old plugin files
4. Upload new plugin version
5. Activate plugin
6. Test checkout process
```

Settings are preserved during updates.

---

## Next Steps

After installation:

1. **Test thoroughly** in Sandbox mode
2. **Create test orders** with various products
3. **Test refunds** to ensure they work
4. **Configure email notifications** in Voxel
5. **Set up order management** workflows
6. **Train staff** on vendor/admin features
7. **Go live** when ready!

Need help? Check the README.md and ARCHITECTURE.md files for detailed information.
