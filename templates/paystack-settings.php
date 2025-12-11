<?php
if ( ! defined('ABSPATH') ) {
	exit;
}

$webhook_url = home_url( '/?vx=1&action=paystack.webhooks' );
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
				'sandbox' => 'Test mode',
				'live' => 'Live mode',
			],
		] ) ?>

		<?php \Voxel\Utils\Form_Models\Select_Model::render( [
			'v-model' => 'settings.currency',
			'label' => 'Currency',
			'classes' => 'x-col-6',
			'choices' => [
				'NGN' => 'NGN - Nigerian Naira',
				'GHS' => 'GHS - Ghanaian Cedi',
				'ZAR' => 'ZAR - South African Rand',
				'USD' => 'USD - US Dollar',
				'KES' => 'KES - Kenyan Shilling',
			],
			'infobox' => 'Currency must match your Paystack account country',
		] ) ?>
	</div>
</div>

<!-- Test Credentials -->
<template v-if="settings.mode === 'sandbox'">
	<div class="ts-group">
		<div class="ts-group-head">
			<h3><?php _e( 'Test API Keys', 'voxel-payment-gateways' ); ?></h3>
		</div>
		<div class="x-row">
			<?php \Voxel\Utils\Form_Models\Password_Model::render( [
				'v-model' => 'settings.sandbox.secret_key',
				'label' => 'Test Secret Key',
				'classes' => 'x-col-12',
				'placeholder' => 'sk_test_...',
				'infobox' => 'Get from Paystack Dashboard → Settings → API Keys & Webhooks',
			] ) ?>

			<?php \Voxel\Utils\Form_Models\Text_Model::render( [
				'v-model' => 'settings.sandbox.public_key',
				'label' => 'Test Public Key',
				'classes' => 'x-col-12',
				'placeholder' => 'pk_test_...',
				'infobox' => 'Used for frontend integrations',
			] ) ?>
		</div>
	</div>

	<!-- Test Webhook -->
	<div class="ts-group">
		<div class="ts-group-head">
			<h3><?php _e( 'Test Webhook', 'voxel-payment-gateways' ); ?></h3>
		</div>
		<div class="x-row">
			<div class="ts-form-group x-col-12">
				<label><?php _e( 'Webhook URL', 'voxel-payment-gateways' ); ?></label>
				<input type="text" readonly value="<?= esc_attr( $webhook_url ) ?>" class="autofocus" onclick="this.select();">
				<p class="ts-description">
					<?php _e( 'Add this URL in Paystack Dashboard → Settings → API Keys & Webhooks', 'voxel-payment-gateways' ); ?>
				</p>
			</div>

			<?php \Voxel\Utils\Form_Models\Password_Model::render( [
				'v-model' => 'settings.sandbox.webhook_secret',
				'label' => 'Test Webhook Secret',
				'classes' => 'x-col-12',
				'placeholder' => 'Not configured',
				'infobox' => 'Optional but recommended. Found in Paystack webhook settings.',
			] ) ?>
		</div>
	</div>
</template>

<!-- Live Credentials -->
<template v-else>
	<div class="ts-group">
		<div class="ts-group-head">
			<h3><?php _e( 'Live API Keys', 'voxel-payment-gateways' ); ?></h3>
		</div>
		<div class="x-row">
			<?php \Voxel\Utils\Form_Models\Password_Model::render( [
				'v-model' => 'settings.live.secret_key',
				'label' => 'Live Secret Key',
				'classes' => 'x-col-12',
				'placeholder' => 'sk_live_...',
				'infobox' => 'Get from Paystack Dashboard → Settings → API Keys & Webhooks',
			] ) ?>

			<?php \Voxel\Utils\Form_Models\Text_Model::render( [
				'v-model' => 'settings.live.public_key',
				'label' => 'Live Public Key',
				'classes' => 'x-col-12',
				'placeholder' => 'pk_live_...',
				'infobox' => 'Used for frontend integrations',
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
				<input type="text" readonly value="<?= esc_attr( $webhook_url ) ?>" class="autofocus" onclick="this.select();">
				<p class="ts-description">
					<?php _e( 'Add this URL in Paystack Dashboard → Settings → API Keys & Webhooks', 'voxel-payment-gateways' ); ?>
				</p>
			</div>

			<?php \Voxel\Utils\Form_Models\Password_Model::render( [
				'v-model' => 'settings.live.webhook_secret',
				'label' => 'Live Webhook Secret',
				'classes' => 'x-col-12',
				'placeholder' => 'Not configured',
				'infobox' => 'Optional but recommended. Found in Paystack webhook settings.',
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
			<p style="margin: 0 0 15px 0;"><?php _e( 'Paystack Checkout supports the following payment methods:', 'voxel-payment-gateways' ); ?></p>
			<ul style="margin: 0; padding-left: 20px; line-height: 1.8;">
				<li><strong><?php _e( 'Cards', 'voxel-payment-gateways' ); ?></strong> - <?php _e( 'Visa, Mastercard, Verve, and American Express', 'voxel-payment-gateways' ); ?></li>
				<li><strong><?php _e( 'Bank Transfer', 'voxel-payment-gateways' ); ?></strong> - <?php _e( 'Pay via bank transfer with automatic confirmation', 'voxel-payment-gateways' ); ?></li>
				<li><strong><?php _e( 'Mobile Money', 'voxel-payment-gateways' ); ?></strong> - <?php _e( 'MTN, Vodafone, AirtelTigo (Ghana)', 'voxel-payment-gateways' ); ?></li>
				<li><strong><?php _e( 'USSD', 'voxel-payment-gateways' ); ?></strong> - <?php _e( 'Pay using bank USSD codes (Nigeria)', 'voxel-payment-gateways' ); ?></li>
				<li><strong><?php _e( 'QR Code', 'voxel-payment-gateways' ); ?></strong> - <?php _e( 'Visa QR payments', 'voxel-payment-gateways' ); ?></li>
				<li><strong><?php _e( 'Bank Account', 'voxel-payment-gateways' ); ?></strong> - <?php _e( 'Direct bank debit (Nigeria)', 'voxel-payment-gateways' ); ?></li>
			</ul>
			<p style="margin: 15px 0 0 0; padding: 10px; background: rgba(255,255,255,0.05); border-radius: 5px;">
				<strong><?php _e( 'Note:', 'voxel-payment-gateways' ); ?></strong>
				<?php _e( 'Available payment methods depend on your Paystack account country and configuration.', 'voxel-payment-gateways' ); ?>
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
				'automatic' => 'Automatic - Complete order on successful payment',
				'manual' => 'Manual - Require vendor approval after payment',
			],
		] ) ?>

		<?php \Voxel\Utils\Form_Models\Text_Model::render( [
			'v-model' => 'settings.payments.brand_name',
			'label' => 'Brand Name',
			'classes' => 'x-col-12',
			'placeholder' => get_bloginfo( 'name' ),
			'infobox' => 'Name displayed on Paystack checkout (leave empty for site name)',
		] ) ?>
	</div>
</div>

<!-- Marketplace Settings -->
<div class="ts-group">
	<div class="ts-group-head">
		<h3><?php _e( 'Marketplace (Split Payments)', 'voxel-payment-gateways' ); ?></h3>
	</div>
	<div class="x-row">
		<?php \Voxel\Utils\Form_Models\Select_Model::render( [
			'v-model' => 'settings.marketplace.enabled',
			'label' => 'Enable Marketplace Mode',
			'classes' => 'x-col-12',
			'choices' => [
				'0' => 'Disabled',
				'1' => 'Enabled - Allow vendors to connect their bank accounts',
			],
		] ) ?>

		<template v-if="settings.marketplace.enabled === '1'">
			<?php \Voxel\Utils\Form_Models\Select_Model::render( [
				'v-model' => 'settings.marketplace.fee_type',
				'label' => 'Platform Fee Type',
				'classes' => 'x-col-6',
				'choices' => [
					'percentage' => 'Percentage of transaction',
					'fixed' => 'Fixed amount per transaction',
				],
			] ) ?>

			<?php \Voxel\Utils\Form_Models\Text_Model::render( [
				'v-model' => 'settings.marketplace.fee_value',
				'label' => 'Platform Fee Value',
				'classes' => 'x-col-6',
				'placeholder' => '10',
				'infobox' => 'Percentage (e.g., 10 for 10%) or fixed amount in the smallest currency unit (kobo/pesewas)',
			] ) ?>

			<?php \Voxel\Utils\Form_Models\Select_Model::render( [
				'v-model' => 'settings.marketplace.fee_bearer',
				'label' => 'Transaction Fee Bearer',
				'classes' => 'x-col-12',
				'choices' => [
					'account' => 'Platform bears all Paystack fees',
					'subaccount' => 'Vendor bears all Paystack fees',
					'all' => 'Fees split equally between platform and vendor',
					'all_proportional' => 'Fees split proportionally based on transaction share',
				],
				'infobox' => 'Who pays the Paystack transaction fees',
			] ) ?>

			<div class="ts-form-group x-col-12">
				<p style="margin: 0; padding: 12px; background: linear-gradient(135deg, rgba(88, 192, 242, 0.1), rgba(88, 192, 242, 0.05)); border: 1px solid rgba(88, 192, 242, 0.3); border-radius: 5px;">
					<strong style="color: #58c0f2;"><?php _e( 'How it works:', 'voxel-payment-gateways' ); ?></strong><br>
					<?php _e( 'Vendors connect their bank accounts through your site. When a customer makes a purchase, Paystack automatically splits the payment - the vendor receives their share directly, and your platform receives the fee.', 'voxel-payment-gateways' ); ?>
				</p>
			</div>
		</template>
	</div>
</div>

<!-- Required Webhook Events -->
<div class="ts-group">
	<div class="ts-group-head">
		<h3><?php _e( 'Required Webhook Events', 'voxel-payment-gateways' ); ?></h3>
	</div>
	<div class="x-row">
		<div class="ts-form-group x-col-12">
			<p><?php _e( 'Ensure these events are enabled in your Paystack webhook settings:', 'voxel-payment-gateways' ); ?></p>

			<p style="margin-top: 15px;"><strong><?php _e( 'For Payments:', 'voxel-payment-gateways' ); ?></strong></p>
			<ul style="list-style: disc; padding-left: 20px; margin-top: 5px;">
				<li><code>charge.success</code> - <?php _e( 'Successful payment', 'voxel-payment-gateways' ); ?></li>
				<li><code>charge.failed</code> - <?php _e( 'Failed payment attempt', 'voxel-payment-gateways' ); ?></li>
			</ul>

			<p style="margin-top: 15px;"><strong><?php _e( 'For Subscriptions:', 'voxel-payment-gateways' ); ?></strong></p>
			<ul style="list-style: disc; padding-left: 20px; margin-top: 5px;">
				<li><code>subscription.create</code> - <?php _e( 'New subscription created', 'voxel-payment-gateways' ); ?></li>
				<li><code>subscription.disable</code> - <?php _e( 'Subscription cancelled', 'voxel-payment-gateways' ); ?></li>
				<li><code>subscription.not_renew</code> - <?php _e( 'Subscription will not renew', 'voxel-payment-gateways' ); ?></li>
				<li><code>invoice.create</code> - <?php _e( 'Upcoming subscription charge', 'voxel-payment-gateways' ); ?></li>
				<li><code>invoice.payment_failed</code> - <?php _e( 'Subscription renewal failed', 'voxel-payment-gateways' ); ?></li>
			</ul>

			<p style="margin-top: 15px;"><strong><?php _e( 'For Marketplace (if enabled):', 'voxel-payment-gateways' ); ?></strong></p>
			<ul style="list-style: disc; padding-left: 20px; margin-top: 5px;">
				<li><code>transfer.success</code> - <?php _e( 'Transfer to vendor completed', 'voxel-payment-gateways' ); ?></li>
				<li><code>transfer.failed</code> - <?php _e( 'Transfer to vendor failed', 'voxel-payment-gateways' ); ?></li>
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
			<p><strong>1. <?php _e( 'Create a Paystack Account', 'voxel-payment-gateways' ); ?></strong></p>
			<ul style="list-style: disc; padding-left: 20px; margin: 5px 0 15px 0;">
				<li><?php _e( 'Visit:', 'voxel-payment-gateways' ); ?> <a href="https://dashboard.paystack.com/#/signup" target="_blank">https://dashboard.paystack.com</a></li>
				<li><?php _e( 'Complete the registration and verify your business', 'voxel-payment-gateways' ); ?></li>
			</ul>

			<p><strong>2. <?php _e( 'Get Your API Keys', 'voxel-payment-gateways' ); ?></strong></p>
			<ul style="list-style: disc; padding-left: 20px; margin: 5px 0 15px 0;">
				<li><?php _e( 'Go to Settings → API Keys & Webhooks', 'voxel-payment-gateways' ); ?></li>
				<li><?php _e( 'Copy your Secret Key and Public Key', 'voxel-payment-gateways' ); ?></li>
				<li><?php _e( 'Use Test keys for testing, Live keys for production', 'voxel-payment-gateways' ); ?></li>
			</ul>

			<p><strong>3. <?php _e( 'Configure Webhooks', 'voxel-payment-gateways' ); ?></strong></p>
			<ul style="list-style: disc; padding-left: 20px; margin: 5px 0 15px 0;">
				<li><?php _e( 'In Paystack Dashboard, go to Settings → API Keys & Webhooks', 'voxel-payment-gateways' ); ?></li>
				<li><?php _e( 'Add the webhook URL shown above', 'voxel-payment-gateways' ); ?></li>
				<li><?php _e( 'Copy the Webhook Secret and paste it here', 'voxel-payment-gateways' ); ?></li>
			</ul>

			<p><strong>4. <?php _e( 'For Marketplace (Optional)', 'voxel-payment-gateways' ); ?></strong></p>
			<ul style="list-style: disc; padding-left: 20px; margin: 5px 0 15px 0;">
				<li><?php _e( 'Enable split payments in your Paystack dashboard', 'voxel-payment-gateways' ); ?></li>
				<li><?php _e( 'Vendors will be able to connect their bank accounts through your site', 'voxel-payment-gateways' ); ?></li>
				<li><?php _e( 'Paystack will automatically split payments between you and vendors', 'voxel-payment-gateways' ); ?></li>
			</ul>

			<p><strong>5. <?php _e( 'Test Your Integration', 'voxel-payment-gateways' ); ?></strong></p>
			<ul style="list-style: disc; padding-left: 20px; margin: 5px 0 15px 0;">
				<li><?php _e( 'Use test mode with test API keys', 'voxel-payment-gateways' ); ?></li>
				<li><?php _e( 'Test card: 4084 0840 8408 4081 (any expiry, any CVV)', 'voxel-payment-gateways' ); ?></li>
				<li><?php _e( 'Test card requiring OTP: 4084 0840 8408 4081 (OTP: 123456)', 'voxel-payment-gateways' ); ?></li>
				<li><?php _e( 'Failed card: 4084 0840 8408 4084', 'voxel-payment-gateways' ); ?></li>
			</ul>
		</div>
	</div>
</div>

<!-- Country Info -->
<div class="ts-group">
	<div class="ts-group-head">
		<h3><?php _e( 'Supported Countries', 'voxel-payment-gateways' ); ?></h3>
	</div>
	<div class="x-row">
		<div class="ts-form-group x-col-12">
			<style>
				.paystack-country-notice { background: linear-gradient(135deg, rgba(88, 192, 242, 0.15), rgba(88, 192, 242, 0.05)) !important; border: 1px solid rgba(88, 192, 242, 0.4) !important; padding: 15px !important; border-radius: 5px !important; }
				.paystack-country-notice * { color: #58c0f2 !important; }
			</style>
			<div class="paystack-country-notice">
				<p style="margin: 0; line-height: 1.6;">
					<strong><?php _e( 'Paystack is available in:', 'voxel-payment-gateways' ); ?></strong>
					<?php _e( 'Nigeria, Ghana, South Africa, Kenya, and for international payments (USD).', 'voxel-payment-gateways' ); ?>
				</p>
				<p style="margin: 10px 0 0 0; line-height: 1.6;">
					<?php _e( 'Your Paystack account country determines which currencies and payment methods are available.', 'voxel-payment-gateways' ); ?>
				</p>
			</div>
		</div>
	</div>
</div>
