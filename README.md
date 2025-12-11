# Voxel Payment Gateways

Payment gateway integrations for the Voxel WordPress theme.

## Features

### PayPal Gateway
- ✅ **PayPal Checkout Integration** - Accept payments via PayPal's secure checkout
- ✅ **Automatic & Manual Capture** - Choose between immediate payment capture or vendor approval
- ✅ **Webhook Support** - Real-time payment status updates
- ✅ **Sandbox & Live Mode** - Test in sandbox before going live
- ✅ **Multi-Currency Support** - Support for 25+ currencies
- ✅ **Order Sync** - Sync order status with PayPal

### Offline Payment Gateway
- ✅ **Cash on Delivery** - Accept payment upon delivery
- ✅ **Bank Transfer** - Accept direct bank transfers
- ✅ **Pay at Pickup** - In-store payment option
- ✅ **Custom Instructions** - Configurable payment instructions for customers
- ✅ **Vendor Control** - Vendors can mark orders as paid or cancel them

### General
- ✅ **Seamless Voxel Integration** - Works with Voxel's order system, cart, and product types
- ✅ **Vendor Actions** - Approve/decline orders from vendor dashboard
- ✅ **Customer Actions** - Cancel pending orders

## Requirements

- WordPress 6.0+
- PHP 8.1+
- **Voxel Theme** (active)
- PayPal Business Account

## Installation

1. **Download** the plugin files
2. **Upload** to `/wp-content/plugins/voxel-payment-gateways/`
3. **Activate** the plugin through WordPress admin
4. Navigate to **Voxel → Orders → Payments**
5. Select **PayPal** as your payment provider
6. Configure your PayPal credentials

## Configuration

### 1. Get PayPal API Credentials

**For Sandbox (Testing):**
1. Go to [PayPal Developer Dashboard](https://developer.paypal.com/dashboard/)
2. Create a Sandbox app
3. Copy your **Client ID** and **Client Secret**

**For Live (Production):**
1. Go to [PayPal Business Dashboard](https://www.paypal.com/businessprofile/settings/)
2. Navigate to Account Settings → API Access
3. Create REST API credentials
4. Copy your **Client ID** and **Client Secret**

### 2. Configure Webhooks

1. In PayPal Dashboard, create a new webhook
2. Use this webhook URL: `https://yoursite.com/?vx=1&action=paypal.webhooks`
3. Select these events:
   - `PAYMENT.CAPTURE.COMPLETED`
   - `PAYMENT.CAPTURE.DENIED`
   - `PAYMENT.CAPTURE.DECLINED`
   - `PAYMENT.CAPTURE.REFUNDED`
   - `PAYMENT.AUTHORIZATION.CREATED`
   - `PAYMENT.AUTHORIZATION.VOIDED`
   - `CHECKOUT.ORDER.APPROVED`
4. Save the **Webhook ID**
5. Enter it in the plugin settings

### 3. Plugin Settings

Navigate to **Voxel → Orders → Payments** and configure:

- **Mode**: Sandbox (testing) or Live (production)
- **Currency**: Your primary currency (USD, EUR, GBP, etc.)
- **Order Approval**:
  - *Automatic* - Capture payments immediately
  - *Manual* - Require vendor approval before capture
- **Brand Name**: Name shown on PayPal checkout (optional)
- **Landing Page**: PayPal login or credit card form preference

## Usage

### For Site Admins

1. Activate the plugin
2. Configure PayPal credentials in Voxel settings
3. Set your preferred currency and capture method
4. Configure webhooks for real-time updates

### For Vendors

**Manual Capture Mode:**
- View pending orders in your vendor dashboard
- **Approve** to capture payment
- **Decline** to void the authorization

### For Customers

- Select products and add to cart
- Proceed to checkout
- Click "Pay with PayPal"
- Complete payment on PayPal's secure checkout
- Return to your site to view order confirmation

## Order Flow

### Automatic Capture (Recommended)
1. Customer clicks "Pay with PayPal"
2. Redirected to PayPal checkout
3. Customer approves payment
4. Payment is **captured immediately**
5. Customer returns to site
6. Order status: **Completed**

### Manual Capture
1. Customer clicks "Pay with PayPal"
2. Redirected to PayPal checkout
3. Customer approves payment
4. Payment is **authorized** (not captured)
5. Vendor reviews order
6. Vendor clicks **Approve** to capture or **Decline** to void
7. Order status: **Completed** or **Canceled**

## Hooks & Filters

### Actions

```php
// Payment captured successfully
do_action( 'voxel/paypal/payment-captured', $order, $event );

// Payment failed
do_action( 'voxel/paypal/payment-failed', $order, $event );

// Payment refunded
do_action( 'voxel/paypal/payment-refunded', $order, $event );

// Authorization created (manual capture)
do_action( 'voxel/paypal/authorization-created', $order, $event );

// Authorization voided
do_action( 'voxel/paypal/authorization-voided', $order, $event );
```

### Filters

```php
// Modify PayPal order data before sending
apply_filters( 'voxel/paypal/order-data', $order_data, $voxel_order );
```

## Troubleshooting

### Payment Not Processing
- Verify API credentials are correct
- Check mode (sandbox vs live) matches your credentials
- Ensure webhook URL is accessible
- Check WordPress debug log for errors

### Webhook Not Working
- Verify webhook secret is configured
- Ensure webhook URL is publicly accessible
- Check PayPal webhook delivery history
- Verify all required events are enabled

### Order Status Not Updating
- Check webhook configuration
- Manually click "Sync with PayPal" in order details
- Verify PayPal order ID is stored in order details

## Security

- All API communications use HTTPS
- API credentials are stored securely in WordPress options
- Webhook signatures are verified
- Access tokens are cached with proper expiration
- All inputs are sanitized and validated

## Support

For issues or questions:
1. Check the [documentation](https://your-site.com/docs)
2. Review [PayPal API documentation](https://developer.paypal.com/docs/api/overview/)
3. Contact support at support@your-site.com

## Changelog

### 2.0.0 - 2025-12-11
- Added Customer Wallet feature
  - Users can add funds to wallet via site's configured payment gateway
  - Pay for orders using wallet balance (pre-checkout toggle)
  - Transaction history with deposits, purchases, refunds, adjustments
  - Admin can enable/disable wallet for entire site
  - Admin user profile section for viewing/adjusting user balances
  - Wallet Elementor widget with comprehensive styling controls
  - Dynamic tag support: @user(wallet.balance), @user(wallet.balance_formatted)
  - Customizable labels for all widget text
- Added Stripe enhancement: Skip checkout for zero amount orders
  - Toggle in Stripe Payments settings to bypass checkout when order total is $0
  - Orders complete immediately without redirecting to Stripe
- Renamed plugin from "Voxel PayPal Gateway" to "Voxel Payment Gateways"
- Added Offline Payment gateway (Cash on Delivery, Bank Transfer, Pay at Pickup)
- Offline payments support pending payment and pending approval order statuses
- Vendor can mark offline orders as paid or cancel them
- Customer can cancel pending offline orders
- Configurable payment instructions for offline orders
- Updated package script to output versioned zip files to dist folder
- Added Square Payment Gateway integration
  - Support for memberships, paid listings, and products
  - Square Checkout Links (redirect-based checkout)
  - Payment methods: Cards, Apple Pay, Google Pay, Cash App Pay, Afterpay
  - Subscription support for recurring payments
  - Webhook handling for payment events
  - Sandbox and live mode support
  - Note: Marketplace/vendor payouts not supported (Square limitation)
- Added Mercado Pago Payment Gateway integration
  - Checkout Pro integration (redirect-based checkout)
  - One-time payments for products and paid listings
  - Subscription support (preapprovals) for recurring payments
  - Marketplace support with vendor OAuth connections
  - Split payments with configurable platform fees (percentage or fixed)
  - Vendor Connect widget for Elementor
  - Webhook handling for payments, subscriptions, and marketplace events
  - Sandbox and live mode support
  - Supported currencies: ARS, BRL, CLP, COP, MXN, PEN, UYU
- Added Paystack Payment Gateway integration
  - Paystack Checkout integration (popup and redirect checkout)
  - One-time payments for products and paid listings
  - Subscription support with Paystack Plans for recurring payments
  - Marketplace support with vendor subaccounts
  - Split payments with configurable platform fees (percentage, fixed, or combined)
  - Vendor Connect widget for Elementor
  - Webhook handling for charge, subscription, and transfer events
  - Test and live mode support
  - Supported currencies: NGN, GHS, ZAR, KES, USD

### 1.0.0 - 2025-01-XX
- Initial release
- PayPal Checkout integration
- Automatic and manual capture modes
- Webhook support
- Multi-currency support
- Full Voxel theme integration

## Credits

Developed by Your Company
Built for the Voxel WordPress Theme

## License

GPL v2 or later - https://www.gnu.org/licenses/gpl-2.0.html
