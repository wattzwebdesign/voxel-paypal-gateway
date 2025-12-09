<?php
if ( ! defined('ABSPATH') ) {
	exit;
}

$webhook_url = home_url( '/?vx=1&action=square.webhooks' );
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
			'infobox' => 'Primary currency code (USD, CAD, GBP, EUR, AUD, JPY)',
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
				'v-model' => 'settings.sandbox.application_id',
				'label' => 'Sandbox Application ID',
				'classes' => 'x-col-12',
				'infobox' => 'Get from Square Developer Dashboard → Applications',
			] ) ?>

			<?php \Voxel\Utils\Form_Models\Password_Model::render( [
				'v-model' => 'settings.sandbox.access_token',
				'label' => 'Sandbox Access Token',
				'classes' => 'x-col-12',
				'infobox' => 'Keep this secure - never share publicly',
			] ) ?>

			<?php \Voxel\Utils\Form_Models\Text_Model::render( [
				'v-model' => 'settings.sandbox.location_id',
				'label' => 'Sandbox Location ID',
				'classes' => 'x-col-12',
				'infobox' => 'Get from Square Developer Dashboard → Locations',
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
					<?php _e( 'Add this URL in Square Developer Dashboard → Webhooks', 'voxel-payment-gateways' ); ?>
				</p>
			</div>

			<?php \Voxel\Utils\Form_Models\Text_Model::render( [
				'v-model' => 'settings.sandbox.webhook_signature_key',
				'label' => 'Webhook Signature Key',
				'classes' => 'x-col-12',
				'placeholder' => 'Not configured',
				'infobox' => 'Used to verify webhook requests from Square',
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
				'v-model' => 'settings.live.application_id',
				'label' => 'Live Application ID',
				'classes' => 'x-col-12',
				'infobox' => 'Get from Square Developer Dashboard → Applications',
			] ) ?>

			<?php \Voxel\Utils\Form_Models\Password_Model::render( [
				'v-model' => 'settings.live.access_token',
				'label' => 'Live Access Token',
				'classes' => 'x-col-12',
				'infobox' => 'Keep this secure - never share publicly',
			] ) ?>

			<?php \Voxel\Utils\Form_Models\Text_Model::render( [
				'v-model' => 'settings.live.location_id',
				'label' => 'Live Location ID',
				'classes' => 'x-col-12',
				'infobox' => 'Get from Square Developer Dashboard → Locations',
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
					<?php _e( 'Add this URL in Square Developer Dashboard → Webhooks', 'voxel-payment-gateways' ); ?>
				</p>
			</div>

			<?php \Voxel\Utils\Form_Models\Text_Model::render( [
				'v-model' => 'settings.live.webhook_signature_key',
				'label' => 'Webhook Signature Key',
				'classes' => 'x-col-12',
				'placeholder' => 'Not configured',
				'infobox' => 'Used to verify webhook requests from Square',
			] ) ?>
		</div>
	</div>
</template>

<!-- Payment Methods Info -->
<div class="ts-group">
	<div class="ts-group-head">
		<h3><?php _e( 'Payment Methods', 'voxel-payment-gateways' ); ?></h3>
	</div>
	<div class="x-row">
		<div class="ts-form-group x-col-12">
			<p style="margin: 0 0 15px 0;"><?php _e( 'Square Checkout supports the following payment methods:', 'voxel-payment-gateways' ); ?></p>
			<ul style="margin: 0; padding-left: 20px; line-height: 1.8;">
				<li><strong><?php _e( 'Credit & Debit Cards', 'voxel-payment-gateways' ); ?></strong></li>
				<li><strong><?php _e( 'Apple Pay', 'voxel-payment-gateways' ); ?></strong> - <?php _e( 'Shows on supported devices/browsers', 'voxel-payment-gateways' ); ?></li>
				<li><strong><?php _e( 'Google Pay', 'voxel-payment-gateways' ); ?></strong> - <?php _e( 'Shows on supported devices/browsers', 'voxel-payment-gateways' ); ?></li>
				<li><strong><?php _e( 'Cash App Pay', 'voxel-payment-gateways' ); ?></strong> - <?php _e( 'US only', 'voxel-payment-gateways' ); ?></li>
				<li><strong><?php _e( 'Afterpay/Clearpay', 'voxel-payment-gateways' ); ?></strong> - <?php _e( 'Buy now, pay later', 'voxel-payment-gateways' ); ?></li>
			</ul>
			<p style="margin: 15px 0 0 0; padding: 10px; background: rgba(255,255,255,0.05); border-radius: 5px;">
				<strong><?php _e( 'Note:', 'voxel-payment-gateways' ); ?></strong>
				<?php _e( 'Payment method availability is controlled in your', 'voxel-payment-gateways' ); ?>
				<a href="https://squareup.com/dashboard/payments/payment-links/settings" target="_blank" style="color: #4da6ff;"><?php _e( 'Square Dashboard', 'voxel-payment-gateways' ); ?></a>
				<?php _e( 'under Payments > Payment Links > Settings.', 'voxel-payment-gateways' ); ?>
			</p>
		</div>
	</div>
</div>

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
			'classes' => 'x-col-12',
			'placeholder' => get_bloginfo( 'name' ),
			'infobox' => 'Name displayed on Square checkout (leave empty for site name)',
		] ) ?>
	</div>
</div>

<!-- Marketplace Notice -->
<div class="ts-group">
	<div class="ts-group-head">
		<h3><?php _e( 'Marketplace', 'voxel-payment-gateways' ); ?></h3>
	</div>
	<div class="x-row">
		<div class="ts-form-group x-col-12">
			<style>
				.square-marketplace-notice { background: #fff3cd !important; border: 1px solid #ffc107 !important; padding: 15px !important; border-radius: 5px !important; }
				.square-marketplace-notice * { color: #664d03 !important; }
				.square-marketplace-notice strong { color: #856404 !important; }
			</style>
			<div class="square-marketplace-notice">
				<strong style="display: block; margin-bottom: 8px;">
					<span style="margin-right: 5px;">&#9888;</span>
					<?php _e( 'Marketplace Not Supported', 'voxel-payment-gateways' ); ?>
				</strong>
				<p style="margin: 0; line-height: 1.6;">
					<?php _e( 'Square does not support marketplace payouts like PayPal. For marketplace functionality with automatic vendor payouts, please use PayPal as your payment provider.', 'voxel-payment-gateways' ); ?>
				</p>
				<p style="margin: 10px 0 0 0; line-height: 1.6;">
					<?php _e( 'Square is ideal for:', 'voxel-payment-gateways' ); ?>
				</p>
				<ul style="margin: 5px 0 0 0; padding-left: 0; line-height: 1.8; list-style: none;">
					<li>&#8226; <?php _e( 'Direct sales (no vendor payouts needed)', 'voxel-payment-gateways' ); ?></li>
					<li>&#8226; <?php _e( 'Membership subscriptions', 'voxel-payment-gateways' ); ?></li>
					<li>&#8226; <?php _e( 'Paid listings and products', 'voxel-payment-gateways' ); ?></li>
				</ul>
			</div>
		</div>
	</div>
</div>

<!-- Required Webhook Events -->
<div class="ts-group">
	<div class="ts-group-head">
		<h3><?php _e( 'Required Webhook Events', 'voxel-payment-gateways' ); ?></h3>
	</div>
	<div class="x-row">
		<div class="ts-form-group x-col-12">
			<p><?php _e( 'Configure these events in your Square Developer Dashboard → Webhooks:', 'voxel-payment-gateways' ); ?></p>

			<p style="margin-top: 15px;"><strong><?php _e( 'Payments:', 'voxel-payment-gateways' ); ?></strong></p>
			<ul style="list-style: disc; padding-left: 20px; margin-top: 5px;">
				<li><code>payment.completed</code></li>
				<li><code>payment.updated</code></li>
				<li><code>refund.created</code></li>
				<li><code>refund.updated</code></li>
			</ul>

			<p style="margin-top: 15px;"><strong><?php _e( 'Subscriptions:', 'voxel-payment-gateways' ); ?></strong></p>
			<ul style="list-style: disc; padding-left: 20px; margin-top: 5px;">
				<li><code>subscription.created</code></li>
				<li><code>subscription.updated</code></li>
				<li><code>invoice.payment_made</code></li>
				<li><code>invoice.canceled</code></li>
				<li><code>invoice.scheduled_charge_failed</code></li>
			</ul>

			<p style="margin-top: 15px;"><strong><?php _e( 'Orders:', 'voxel-payment-gateways' ); ?></strong></p>
			<ul style="list-style: disc; padding-left: 20px; margin-top: 5px;">
				<li><code>order.created</code></li>
				<li><code>order.updated</code></li>
			</ul>
		</div>
	</div>
</div>

<!-- Setup Guide -->
<div class="ts-group">
	<div class="ts-group-head">
		<h3><?php _e( 'Setup Guide', 'voxel-payment-gateways' ); ?></h3>
	</div>
	<div class="x-row">
		<div class="ts-form-group x-col-12">
			<p><strong>1. <?php _e( 'Create a Square Developer Account', 'voxel-payment-gateways' ); ?></strong></p>
			<ul style="list-style: disc; padding-left: 20px; margin: 5px 0 15px 0;">
				<li><?php _e( 'Visit:', 'voxel-payment-gateways' ); ?> <a href="https://developer.squareup.com/" target="_blank">https://developer.squareup.com/</a></li>
				<li><?php _e( 'Sign up or log in with your Square account', 'voxel-payment-gateways' ); ?></li>
			</ul>

			<p><strong>2. <?php _e( 'Create an Application', 'voxel-payment-gateways' ); ?></strong></p>
			<ul style="list-style: disc; padding-left: 20px; margin: 5px 0 15px 0;">
				<li><?php _e( 'Go to Applications in your Developer Dashboard', 'voxel-payment-gateways' ); ?></li>
				<li><?php _e( 'Click "Create Application"', 'voxel-payment-gateways' ); ?></li>
				<li><?php _e( 'Copy your Application ID and Access Token', 'voxel-payment-gateways' ); ?></li>
			</ul>

			<p><strong>3. <?php _e( 'Get Your Location ID', 'voxel-payment-gateways' ); ?></strong></p>
			<ul style="list-style: disc; padding-left: 20px; margin: 5px 0 15px 0;">
				<li><?php _e( 'Go to Locations in your Developer Dashboard', 'voxel-payment-gateways' ); ?></li>
				<li><?php _e( 'Copy the Location ID for your business', 'voxel-payment-gateways' ); ?></li>
			</ul>

			<p><strong>4. <?php _e( 'Configure Webhooks', 'voxel-payment-gateways' ); ?></strong></p>
			<ul style="list-style: disc; padding-left: 20px; margin: 5px 0 15px 0;">
				<li><?php _e( 'Go to Webhooks in your Developer Dashboard', 'voxel-payment-gateways' ); ?></li>
				<li><?php _e( 'Add the webhook URL shown above', 'voxel-payment-gateways' ); ?></li>
				<li><?php _e( 'Subscribe to the required events listed above', 'voxel-payment-gateways' ); ?></li>
				<li><?php _e( 'Copy the Signature Key and add it here', 'voxel-payment-gateways' ); ?></li>
			</ul>

			<p><strong>5. <?php _e( 'Test in Sandbox', 'voxel-payment-gateways' ); ?></strong></p>
			<ul style="list-style: disc; padding-left: 20px; margin: 5px 0 15px 0;">
				<li><?php _e( 'Use sandbox credentials for testing', 'voxel-payment-gateways' ); ?></li>
				<li><?php _e( 'Test card number:', 'voxel-payment-gateways' ); ?> <code>4532 0123 4567 8901</code></li>
				<li><?php _e( 'Any future expiration date and any CVV', 'voxel-payment-gateways' ); ?></li>
			</ul>
		</div>
	</div>
</div>
