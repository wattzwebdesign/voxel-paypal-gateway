<?php
if ( ! defined('ABSPATH') ) {
	exit;
}

$webhook_url = home_url( '/?vx=1&action=paypal.webhooks' );
?>

<div class="ts-group">
	<div class="ts-group-head">
		<h3><?php _e( 'General', 'voxel-payment-gateways' ); ?></h3>
	</div>
	<div class="x-row">
		<?php \Voxel\Utils\Form_Models\Select_Model::render( [
			'v-model' => 'settings.mode',
			'label' => 'Mode',
			'classes' => 'x-col-6',
			'choices' => [
				'sandbox' => 'Sandbox (test mode)',
				'live' => 'Production',
			],
		] ) ?>

		<?php \Voxel\Utils\Form_Models\Text_Model::render( [
			'v-model' => 'settings.currency',
			'label' => 'Currency',
			'classes' => 'x-col-6',
			'placeholder' => 'USD',
			'infobox' => 'Primary currency code (USD, EUR, GBP, etc.)',
		] ) ?>
	</div>
</div>

<!-- Sandbox Credentials -->
<template v-if="settings.mode === 'sandbox'">
	<div class="ts-group">
		<div class="ts-group-head">
			<h3><?php _e( 'Sandbox Credentials', 'voxel-payment-gateways' ); ?></h3>
		</div>
		<div class="x-row">
			<?php \Voxel\Utils\Form_Models\Text_Model::render( [
				'v-model' => 'settings.sandbox.client_id',
				'label' => 'Sandbox Client ID',
				'classes' => 'x-col-12',
				'infobox' => 'Get from PayPal Developer Dashboard → My Apps & Credentials',
			] ) ?>

			<?php \Voxel\Utils\Form_Models\Password_Model::render( [
				'v-model' => 'settings.sandbox.client_secret',
				'label' => 'Sandbox Client Secret',
				'classes' => 'x-col-12',
				'infobox' => 'Keep this secure - never share publicly',
			] ) ?>
		</div>
	</div>

	<!-- Sandbox Webhook -->
	<div class="ts-group">
		<div class="ts-group-head">
			<h3><?php _e( 'Sandbox Webhook', 'voxel-payment-gateways' ); ?></h3>
		</div>
		<div class="x-row">
			<div class="ts-form-group x-col-12">
				<label><?php _e( 'Webhook URL', 'voxel-payment-gateways' ); ?></label>
				<input type="text" readonly value="<?= esc_attr( $webhook_url ) ?>" class="autofocus">
				<p class="ts-description">
					<?php _e( 'Add this URL in PayPal Developer Dashboard → Webhooks', 'voxel-payment-gateways' ); ?>
				</p>
			</div>

			<?php \Voxel\Utils\Form_Models\Text_Model::render( [
				'v-model' => 'settings.sandbox.webhook.id',
				'label' => 'Webhook ID',
				'classes' => 'x-col-6',
				'placeholder' => 'Not configured',
			] ) ?>

			<?php \Voxel\Utils\Form_Models\Text_Model::render( [
				'v-model' => 'settings.sandbox.webhook.secret',
				'label' => 'Webhook Secret (Optional)',
				'classes' => 'x-col-6',
				'placeholder' => 'Not configured',
			] ) ?>
		</div>
	</div>
</template>

<!-- Live Credentials -->
<template v-else>
	<div class="ts-group">
		<div class="ts-group-head">
			<h3><?php _e( 'Live Credentials', 'voxel-payment-gateways' ); ?></h3>
		</div>
		<div class="x-row">
			<?php \Voxel\Utils\Form_Models\Text_Model::render( [
				'v-model' => 'settings.live.client_id',
				'label' => 'Live Client ID',
				'classes' => 'x-col-12',
				'infobox' => 'Get from PayPal Business Dashboard → Account Settings → API Access',
			] ) ?>

			<?php \Voxel\Utils\Form_Models\Password_Model::render( [
				'v-model' => 'settings.live.client_secret',
				'label' => 'Live Client Secret',
				'classes' => 'x-col-12',
				'infobox' => 'Keep this secure - never share publicly',
			] ) ?>
		</div>
	</div>

	<!-- Live Webhook -->
	<div class="ts-group">
		<div class="ts-group-head">
			<h3><?php _e( 'Live Webhook', 'voxel-payment-gateways' ); ?></h3>
		</div>
		<div class="x-row">
			<div class="ts-form-group x-col-12">
				<label><?php _e( 'Webhook URL', 'voxel-payment-gateways' ); ?></label>
				<input type="text" readonly value="<?= esc_attr( $webhook_url ) ?>" class="autofocus">
				<p class="ts-description">
					<?php _e( 'Add this URL in PayPal Business Dashboard → Webhooks', 'voxel-payment-gateways' ); ?>
				</p>
			</div>

			<?php \Voxel\Utils\Form_Models\Text_Model::render( [
				'v-model' => 'settings.live.webhook.id',
				'label' => 'Webhook ID',
				'classes' => 'x-col-6',
				'placeholder' => 'Not configured',
			] ) ?>

			<?php \Voxel\Utils\Form_Models\Text_Model::render( [
				'v-model' => 'settings.live.webhook.secret',
				'label' => 'Webhook Secret (Optional)',
				'classes' => 'x-col-6',
				'placeholder' => 'Not configured',
			] ) ?>
		</div>
	</div>
</template>

<!-- Payment Settings -->
<div class="ts-group">
	<div class="ts-group-head">
		<h3><?php _e( 'Payment Settings', 'voxel-payment-gateways' ); ?></h3>
	</div>
	<div class="x-row">
		<?php \Voxel\Utils\Form_Models\Select_Model::render( [
			'v-model' => 'settings.payments.order_approval',
			'label' => 'Order Approval',
			'classes' => 'x-col-12',
			'choices' => [
				'automatic' => 'Automatic - Capture payment immediately',
				'manual' => 'Manual - Require vendor approval before capture',
			],
		] ) ?>

		<?php \Voxel\Utils\Form_Models\Text_Model::render( [
			'v-model' => 'settings.payments.brand_name',
			'label' => 'Brand Name',
			'classes' => 'x-col-6',
			'placeholder' => get_bloginfo( 'name' ),
			'infobox' => 'Name displayed on PayPal checkout (leave empty for site name)',
		] ) ?>

		<?php \Voxel\Utils\Form_Models\Select_Model::render( [
			'v-model' => 'settings.payments.landing_page',
			'label' => 'Landing Page',
			'classes' => 'x-col-6',
			'choices' => [
				'NO_PREFERENCE' => 'No Preference',
				'LOGIN' => 'PayPal Login',
				'BILLING' => 'Credit Card Form',
			],
			'infobox' => 'Default page shown on PayPal checkout',
		] ) ?>
	</div>
</div>

<!-- Marketplace Settings -->
<div class="ts-group">
	<div class="ts-group-head">
		<h3><?php _e( 'Marketplace Settings', 'voxel-payment-gateways' ); ?></h3>
	</div>
	<div class="x-row">
		<?php \Voxel\Utils\Form_Models\Select_Model::render( [
			'v-model' => 'settings.marketplace.enabled',
			'label' => 'Marketplace Mode',
			'classes' => 'x-col-6',
			'choices' => [
				'0' => 'Disabled',
				'1' => 'Enabled',
			],
			'description' => 'Enable to split payments between platform and vendors',
		] ) ?>

		<template v-if="settings.marketplace.enabled == '1' || settings.marketplace.enabled === true || settings.marketplace.enabled === 1">
			<?php \Voxel\Utils\Form_Models\Select_Model::render( [
				'v-model' => 'settings.marketplace.fee_type',
				'label' => 'Platform Fee Type',
				'classes' => 'x-col-6',
				'choices' => [
					'percentage' => 'Percentage of sale',
					'fixed' => 'Fixed amount per sale',
				],
			] ) ?>

			<?php \Voxel\Utils\Form_Models\Text_Model::render( [
				'v-model' => 'settings.marketplace.fee_value',
				'label' => 'Fee Value',
				'classes' => 'x-col-6',
				'placeholder' => '10',
				'description' => 'Percentage (e.g., 10 for 10%) or fixed amount',
			] ) ?>

			<?php \Voxel\Utils\Form_Models\Select_Model::render( [
				'v-model' => 'settings.marketplace.auto_payout',
				'label' => 'Automatic Payouts',
				'classes' => 'x-col-6',
				'choices' => [
					'0' => 'Manual - Admin initiates payouts',
					'1' => 'Automatic - Send on order completion',
				],
				'description' => 'Choose when to send payouts to vendors',
			] ) ?>

			<?php \Voxel\Utils\Form_Models\Text_Model::render( [
				'v-model' => 'settings.marketplace.payout_delay_days',
				'label' => 'Payout Delay (Days)',
				'classes' => 'x-col-6',
				'placeholder' => '0',
				'description' => 'Days to wait before sending payout (0 = immediate)',
			] ) ?>

			<?php \Voxel\Utils\Form_Models\Select_Model::render( [
				'v-model' => 'settings.marketplace.shipping_responsibility',
				'label' => 'Shipping Handled By',
				'classes' => 'x-col-12',
				'choices' => [
					'vendor' => 'Vendor handles shipping',
					'platform' => 'Platform handles shipping',
				],
			] ) ?>
		</template>
	</div>
</div>

<!-- Marketplace Setup Instructions -->
<template v-if="settings.marketplace.enabled == '1' || settings.marketplace.enabled === true || settings.marketplace.enabled === 1">
	<div class="ts-group">
		<div class="ts-group-head">
			<h3><?php _e( 'Marketplace Setup Guide', 'voxel-payment-gateways' ); ?></h3>
		</div>
		<div class="x-row">
			<div class="ts-form-group x-col-12">
				<p><?php _e( 'Complete these steps to enable vendor payouts:', 'voxel-payment-gateways' ); ?></p>

				<p style="margin-top: 20px;"><strong>1. Enable PayPal Payouts API Access</strong></p>
				<ul style="list-style: disc; padding-left: 20px; margin: 5px 0 15px 0;">
					<li>Visit: <a href="https://www.paypal.com/businesswallet/payouts" target="_blank">https://www.paypal.com/businesswallet/payouts</a></li>
					<li>Request access to Payouts API (Business account required)</li>
					<li>Verify your business details and link a bank account</li>
					<li>For sandbox testing: Add test funds to your sandbox business account</li>
				</ul>

				<p><strong>2. Add PayPal Connect Widget to Vendor Dashboard</strong></p>
				<ul style="list-style: disc; padding-left: 20px; margin: 5px 0 15px 0;">
					<li>Go to: <strong>Elementor</strong> → Edit your vendor dashboard template</li>
					<li>Search for: <strong>"PayPal Connect"</strong> widget</li>
					<li>Drag the widget to your vendor dashboard page</li>
					<li>Vendors will use this widget to enter their PayPal email address</li>
					<li>Alternative: Vendors can also set their email via WordPress user profile</li>
				</ul>

				<p><strong>3. Shortcode (Alternative to Widget)</strong></p>
				<ul style="list-style: disc; padding-left: 20px; margin: 5px 0 15px 0;">
					<li>Use shortcode: <code>[paypal_vendor_email]</code></li>
					<li>Add to any page where vendors can manage their settings</li>
				</ul>

				<p style="margin-top: 20px;"><strong>How Marketplace Payouts Work</strong></p>
				<ol style="padding-left: 20px; margin: 5px 0 15px 0;">
					<li>Customer purchases from vendor's listing</li>
					<li>PayPal captures full payment to platform account</li>
					<li>Platform fee is calculated (<?php echo \Voxel\get('payments.paypal.marketplace.fee_value', '10'); ?><?php echo \Voxel\get('payments.paypal.marketplace.fee_type', 'percentage') === 'percentage' ? '%' : ' (fixed)'; ?>)</li>
					<li>Vendor earnings = Order total - Platform fee</li>
					<li>System automatically creates PayPal Payout to vendor's email</li>
					<li>Vendor receives payment directly to their PayPal account</li>
				</ol>

				<p><strong>Important Requirements</strong></p>
				<ul style="list-style: disc; padding-left: 20px; margin: 5px 0 15px 0;">
					<li>Vendors must set their PayPal email using the widget or user profile</li>
					<li>If no PayPal email is set, their WordPress account email will be used</li>
					<li>Minimum payout amount: $1.00 USD (or equivalent)</li>
					<li>PayPal account must have sufficient funds for payouts</li>
					<li>Product must be a marketplace listing (not "Paid Listing" type)</li>
					<li>Vendor must be different from customer (can't buy own products)</li>
				</ul>

				<p><strong>Testing Checklist</strong></p>
				<ol style="padding-left: 20px; margin: 5px 0 15px 0;">
					<li>Marketplace mode enabled (above)</li>
					<li>Platform fee configured</li>
					<li>Automatic payouts enabled (or manual if preferred)</li>
					<li>PayPal Connect widget added to vendor dashboard</li>
					<li>Vendor has set their PayPal email via widget</li>
					<li>Webhooks configured (see below)</li>
					<li>Make test purchase as different user</li>
					<li>Check logs: <code>wp-content/debug.log</code></li>
					<li>Verify payout in PayPal dashboard: Activity → Payouts</li>
				</ol>

				<p style="margin-top: 20px;">
					<strong><?php _e( 'Need Help?', 'voxel-payment-gateways' ); ?></strong><br>
					<?php _e( 'Check the documentation files in the plugin folder:', 'voxel-payment-gateways' ); ?>
					<code>MARKETPLACE-PAYOUTS-COMPLETE.md</code>
				</p>
			</div>
		</div>
	</div>
</template>

<!-- Required Webhook Events -->
<div class="ts-group">
	<div class="ts-group-head">
		<h3><?php _e( 'Required Webhook Events', 'voxel-payment-gateways' ); ?></h3>
	</div>
	<div class="x-row">
		<div class="ts-form-group x-col-12">
			<p><?php _e( 'Configure these events in your PayPal webhook:', 'voxel-payment-gateways' ); ?></p>

			<p style="margin-top: 15px;"><strong><?php _e( 'One-Time Payments:', 'voxel-payment-gateways' ); ?></strong></p>
			<ul style="list-style: disc; padding-left: 20px; margin-top: 5px;">
				<li><code>PAYMENT.CAPTURE.COMPLETED</code></li>
				<li><code>PAYMENT.CAPTURE.DENIED</code></li>
				<li><code>PAYMENT.CAPTURE.DECLINED</code></li>
				<li><code>PAYMENT.CAPTURE.REFUNDED</code></li>
				<li><code>PAYMENT.AUTHORIZATION.CREATED</code></li>
				<li><code>PAYMENT.AUTHORIZATION.VOIDED</code></li>
				<li><code>CHECKOUT.ORDER.APPROVED</code></li>
			</ul>

			<p style="margin-top: 15px;"><strong><?php _e( 'Subscriptions:', 'voxel-payment-gateways' ); ?></strong></p>
			<ul style="list-style: disc; padding-left: 20px; margin-top: 5px;">
				<li><code>BILLING.SUBSCRIPTION.ACTIVATED</code></li>
				<li><code>BILLING.SUBSCRIPTION.CANCELLED</code></li>
				<li><code>BILLING.SUBSCRIPTION.EXPIRED</code></li>
				<li><code>BILLING.SUBSCRIPTION.SUSPENDED</code></li>
				<li><code>BILLING.SUBSCRIPTION.UPDATED</code></li>
				<li><code>BILLING.SUBSCRIPTION.PAYMENT.FAILED</code></li>
			</ul>

			<p style="margin-top: 15px;"><strong><?php _e( 'Marketplace Payouts (if enabled):', 'voxel-payment-gateways' ); ?></strong></p>
			<ul style="list-style: disc; padding-left: 20px; margin-top: 5px;">
				<li><code>PAYMENT.PAYOUTSBATCH.SUCCESS</code></li>
				<li><code>PAYMENT.PAYOUTSBATCH.DENIED</code></li>
				<li><code>PAYMENT.PAYOUTS-ITEM.SUCCEEDED</code></li>
				<li><code>PAYMENT.PAYOUTS-ITEM.FAILED</code></li>
				<li><code>PAYMENT.PAYOUTS-ITEM.BLOCKED</code></li>
				<li><code>PAYMENT.PAYOUTS-ITEM.REFUNDED</code></li>
				<li><code>PAYMENT.PAYOUTS-ITEM.RETURNED</code></li>
				<li><code>PAYMENT.PAYOUTS-ITEM.CANCELED</code></li>
				<li><code>PAYMENT.PAYOUTS-ITEM.UNCLAIMED</code></li>
			</ul>
		</div>
	</div>
</div>
