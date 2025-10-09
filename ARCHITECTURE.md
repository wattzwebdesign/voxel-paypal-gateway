# Voxel PayPal Gateway - Architecture Documentation

## Overview

This plugin integrates PayPal as a payment gateway for the Voxel WordPress theme. It follows Voxel's three-layer payment architecture exactly.

## Architecture Layers

### Layer 1: Payment Service (`PayPal_Payment_Service`)
**File:** `includes/class-paypal-payment-service.php`

Extends `\Voxel\Product_Types\Payment_Services\Base_Payment_Service`

**Responsibilities:**
- Register PayPal as a payment provider
- Define configuration schema (API keys, webhooks, settings)
- Provide settings UI template
- Specify which payment methods this service provides
- Define supported currencies

**Key Methods:**
- `get_key()`: Returns 'paypal'
- `get_label()`: Returns 'PayPal'
- `get_settings_schema()`: Defines all configuration options
- `get_payment_handler()`: Returns 'paypal_payment'
- `get_primary_currency()`: Returns configured currency

### Layer 2: Payment Method (`PayPal_Payment`)
**File:** `includes/payment-methods/class-paypal-payment.php`

Extends `\Voxel\Product_Types\Payment_Methods\Base_Payment_Method`

**Responsibilities:**
- Process actual payments
- Create PayPal orders
- Handle customer returns from PayPal
- Provide vendor/customer actions
- Sync with PayPal API

**Key Methods:**
- `process_payment()`: Creates PayPal order, returns redirect URL
- `handle_order_completed()`: Updates Voxel order after PayPal capture
- `get_vendor_actions()`: Approve/decline for manual capture
- `get_customer_actions()`: Cancel pending orders
- `sync()`: Manually sync with PayPal API

### Layer 3: Controllers

#### Main Controller (`PayPal_Controller`)
**File:** `includes/controllers/class-paypal-controller.php`

Extends `\Voxel\Controllers\Base_Controller`

**Responsibilities:**
- Register payment service via `voxel/product-types/payment-services` filter
- Register payment methods via `voxel/product-types/payment-methods` filter
- Initialize sub-controllers

#### Frontend Payments Controller
**File:** `includes/controllers/class-frontend-payments-controller.php`

**Responsibilities:**
- Handle return from PayPal checkout (`?action=paypal.checkout.success`)
- Handle checkout cancellation (`?action=paypal.checkout.cancel`)
- Capture or authorize payments
- Update order status
- Clear cart

#### Frontend Webhooks Controller
**File:** `includes/controllers/class-frontend-webhooks-controller.php`

**Responsibilities:**
- Handle PayPal webhook events (`?action=paypal.webhooks`)
- Verify webhook signatures
- Process webhook events:
  - `PAYMENT.CAPTURE.COMPLETED`
  - `PAYMENT.CAPTURE.DENIED`
  - `PAYMENT.CAPTURE.REFUNDED`
  - `PAYMENT.AUTHORIZATION.CREATED`
  - `PAYMENT.AUTHORIZATION.VOIDED`

### Supporting Classes

#### PayPal Client (`PayPal_Client`)
**File:** `includes/class-paypal-client.php`

**Responsibilities:**
- Manage PayPal API authentication
- Handle OAuth token caching
- Make API requests
- Provide helper methods for common operations

**Key Methods:**
- `get_access_token()`: Get/cache OAuth token
- `make_request()`: Generic API request handler
- `create_order()`: Create PayPal order
- `capture_order()`: Capture authorized payment
- `get_order()`: Fetch order details
- `verify_webhook_signature()`: Verify webhook authenticity

## Integration Points

### 1. Module Registration
```php
// In voxel-paypal-gateway.php
add_filter( 'voxel/modules', function( $modules ) {
    $modules[] = VOXEL_PAYPAL_FILE;
    return $modules;
}, 10, 1 );
```

### 2. Payment Service Registration
```php
// In PayPal_Controller
$this->filter( 'voxel/product-types/payment-services', '@register_payment_service' );

protected function register_payment_service( $payment_services ) {
    $payment_services['paypal'] = new PayPal_Payment_Service();
    return $payment_services;
}
```

### 3. Payment Method Registration
```php
// In PayPal_Controller
$this->filter( 'voxel/product-types/payment-methods', '@register_payment_methods' );

protected function register_payment_methods( $payment_methods ) {
    $payment_methods['paypal_payment'] = PayPal_Payment::class;
    return $payment_methods;
}
```

## Payment Flow

### Standard Flow (Automatic Capture)

```
1. Customer clicks "Checkout"
   ↓
2. Voxel creates order → status: pending_payment
   ↓
3. PayPal_Payment::process_payment() called
   ↓
4. Create PayPal order via API
   ↓
5. Redirect customer to PayPal
   ↓
6. Customer approves on PayPal
   ↓
7. Redirect back: ?action=paypal.checkout.success
   ↓
8. Frontend_Payments_Controller::handle_checkout_success()
   ↓
9. Capture payment via PayPal API
   ↓
10. Update Voxel order → status: completed
    ↓
11. Clear cart
    ↓
12. Redirect to order confirmation
```

### Manual Capture Flow

```
1-7. [Same as automatic]
   ↓
8. Frontend_Payments_Controller::handle_checkout_success()
   ↓
9. Authorize (not capture) payment
   ↓
10. Update Voxel order → status: pending_approval
    ↓
11. Vendor views order in dashboard
    ↓
12. Vendor clicks "Approve"
    ↓
13. PayPal_Payment::get_vendor_actions()['vendor.approve']
    ↓
14. Capture authorization via API
    ↓
15. Update order → status: completed
```

### Webhook Flow

```
1. PayPal event occurs (capture, refund, etc.)
   ↓
2. PayPal sends webhook → ?action=paypal.webhooks
   ↓
3. Frontend_Webhooks_Controller::handle_webhooks()
   ↓
4. Verify webhook signature
   ↓
5. Parse event type
   ↓
6. Find matching Voxel order
   ↓
7. Update order status
   ↓
8. Fire action hooks
   ↓
9. Return success to PayPal
```

## Data Storage

All PayPal-specific data is stored in Voxel order details:

```php
// PayPal order ID
$order->set_details( 'paypal.order_id', $paypal_order_id );

// Order status
$order->set_details( 'paypal.status', 'COMPLETED' );

// Capture method
$order->set_details( 'paypal.capture_method', 'automatic' );

// Capture ID (for refunds)
$order->set_details( 'paypal.capture_id', $capture_id );

// Authorization ID (manual capture)
$order->set_details( 'paypal.authorization_id', $auth_id );

// Full order object
$order->set_details( 'paypal.order', $paypal_order );

// Last sync timestamp
$order->set_details( 'paypal.last_synced_at', $timestamp );
```

## Settings Schema

Settings are stored in Voxel's settings system under `payments.paypal.*`:

```php
\Voxel\get( 'payments.paypal.mode' ); // 'sandbox' or 'live'
\Voxel\get( 'payments.paypal.currency' ); // 'USD', 'EUR', etc.
\Voxel\get( 'payments.paypal.sandbox.client_id' );
\Voxel\get( 'payments.paypal.sandbox.client_secret' );
\Voxel\get( 'payments.paypal.live.client_id' );
\Voxel\get( 'payments.paypal.live.client_secret' );
\Voxel\get( 'payments.paypal.payments.order_approval' ); // 'automatic' or 'manual'
```

## Error Handling

### Payment Processing Errors
```php
try {
    // Create PayPal order
} catch ( \Exception $e ) {
    return [
        'success' => false,
        'message' => 'PayPal payment failed',
        'debug' => [
            'type' => 'paypal_error',
            'message' => $e->getMessage(),
        ],
    ];
}
```

### Webhook Errors
```php
try {
    // Process webhook
} catch ( \Exception $e ) {
    error_log( 'PayPal Webhook Error: ' . $e->getMessage() );
    wp_send_json( [ 'success' => false ], 400 );
}
```

## Security Measures

1. **API Authentication**: OAuth 2.0 with token caching
2. **Webhook Verification**: Signature validation
3. **Input Sanitization**: All inputs sanitized/validated
4. **HTTPS Enforcement**: All API calls use HTTPS
5. **Capability Checks**: Admin actions require proper permissions
6. **Nonce Verification**: WordPress nonces for admin actions

## Extensibility

### Action Hooks
```php
// After payment captured
do_action( 'voxel/paypal/payment-captured', $order, $event );

// After payment failed
do_action( 'voxel/paypal/payment-failed', $order, $event );

// After refund
do_action( 'voxel/paypal/payment-refunded', $order, $event );
```

### Filter Hooks
```php
// Modify PayPal order data
$order_data = apply_filters( 'voxel/paypal/order-data', $order_data, $voxel_order );
```

## Testing

### Sandbox Mode
1. Set mode to 'sandbox'
2. Use sandbox credentials from PayPal Developer Dashboard
3. Use test PayPal accounts for checkout
4. Use sandbox webhook for events

### Production Checklist
- [ ] Switch to 'live' mode
- [ ] Update to live credentials
- [ ] Configure live webhook
- [ ] Test with small transaction
- [ ] Verify webhook delivery
- [ ] Test refund process
- [ ] Test manual capture (if enabled)

## Performance Considerations

1. **Token Caching**: OAuth tokens cached for ~59 minutes
2. **Transients**: Used for temporary data storage
3. **Minimal DB Queries**: Order data stored in single meta field
4. **Async Webhooks**: Status updates via webhooks (no polling)

## Compatibility

- WordPress: 6.0+
- PHP: 8.1+
- Voxel Theme: Latest version
- PayPal API: v2 (REST)

## Future Enhancements

Potential features for future versions:

1. **Subscription Support**: Recurring payments via PayPal subscriptions
2. **Marketplace/Split Payments**: PayPal Commerce Platform integration
3. **Advanced Refunds**: Partial refunds, refund reasons
4. **PayPal Credit**: Offer PayPal Credit as payment option
5. **Alternative Payment Methods**: Venmo, local payment methods
6. **Enhanced Analytics**: Payment analytics dashboard
