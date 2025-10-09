<?php
if ( ! defined('ABSPATH') ) {
	exit;
}

$webhook_url = home_url( '/?vx=1&action=paypal.webhooks' );
?>

<div class="ts-group">
	<div class="ts-group-head">
		<h3><?php _e( 'General', 'voxel-paypal-gateway' ); ?></h3>
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
			<h3><?php _e( 'Sandbox Credentials', 'voxel-paypal-gateway' ); ?></h3>
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
			<h3><?php _e( 'Sandbox Webhook', 'voxel-paypal-gateway' ); ?></h3>
		</div>
		<div class="x-row">
			<div class="ts-form-group x-col-12">
				<label><?php _e( 'Webhook URL', 'voxel-paypal-gateway' ); ?></label>
				<input type="text" readonly value="<?= esc_attr( $webhook_url ) ?>" class="autofocus">
				<p class="ts-description">
					<?php _e( 'Add this URL in PayPal Developer Dashboard → Webhooks', 'voxel-paypal-gateway' ); ?>
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
			<h3><?php _e( 'Live Credentials', 'voxel-paypal-gateway' ); ?></h3>
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
			<h3><?php _e( 'Live Webhook', 'voxel-paypal-gateway' ); ?></h3>
		</div>
		<div class="x-row">
			<div class="ts-form-group x-col-12">
				<label><?php _e( 'Webhook URL', 'voxel-paypal-gateway' ); ?></label>
				<input type="text" readonly value="<?= esc_attr( $webhook_url ) ?>" class="autofocus">
				<p class="ts-description">
					<?php _e( 'Add this URL in PayPal Business Dashboard → Webhooks', 'voxel-paypal-gateway' ); ?>
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
		<h3><?php _e( 'Payment Settings', 'voxel-paypal-gateway' ); ?></h3>
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

<!-- Required Webhook Events -->
<div class="ts-group">
	<div class="ts-group-head">
		<h3><?php _e( 'Required Webhook Events', 'voxel-paypal-gateway' ); ?></h3>
	</div>
	<div class="x-row">
		<div class="ts-form-group x-col-12">
			<p><?php _e( 'Configure these events in your PayPal webhook:', 'voxel-paypal-gateway' ); ?></p>

			<p style="margin-top: 15px;"><strong><?php _e( 'One-Time Payments:', 'voxel-paypal-gateway' ); ?></strong></p>
			<ul style="list-style: disc; padding-left: 20px; margin-top: 5px;">
				<li><code>PAYMENT.CAPTURE.COMPLETED</code></li>
				<li><code>PAYMENT.CAPTURE.DENIED</code></li>
				<li><code>PAYMENT.CAPTURE.DECLINED</code></li>
				<li><code>PAYMENT.CAPTURE.REFUNDED</code></li>
				<li><code>PAYMENT.AUTHORIZATION.CREATED</code></li>
				<li><code>PAYMENT.AUTHORIZATION.VOIDED</code></li>
				<li><code>CHECKOUT.ORDER.APPROVED</code></li>
			</ul>

			<p style="margin-top: 15px;"><strong><?php _e( 'Subscriptions:', 'voxel-paypal-gateway' ); ?></strong></p>
			<ul style="list-style: disc; padding-left: 20px; margin-top: 5px;">
				<li><code>BILLING.SUBSCRIPTION.ACTIVATED</code></li>
				<li><code>BILLING.SUBSCRIPTION.CANCELLED</code></li>
				<li><code>BILLING.SUBSCRIPTION.EXPIRED</code></li>
				<li><code>BILLING.SUBSCRIPTION.SUSPENDED</code></li>
				<li><code>BILLING.SUBSCRIPTION.UPDATED</code></li>
				<li><code>BILLING.SUBSCRIPTION.PAYMENT.FAILED</code></li>
			</ul>
		</div>
	</div>
</div>
