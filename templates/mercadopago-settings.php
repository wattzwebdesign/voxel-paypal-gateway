<?php
if ( ! defined('ABSPATH') ) {
	exit;
}

$webhook_url = home_url( '/?vx=1&action=mercadopago.webhooks' );
$oauth_redirect_uri = home_url( '/?vx=1&action=mercadopago.oauth.callback' );
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

		<?php \Voxel\Utils\Form_Models\Select_Model::render( [
			'v-model' => 'settings.currency',
			'label' => 'Currency',
			'classes' => 'x-col-6',
			'choices' => [
				'ARS' => 'ARS - Argentine Peso',
				'BRL' => 'BRL - Brazilian Real',
				'CLP' => 'CLP - Chilean Peso',
				'COP' => 'COP - Colombian Peso',
				'MXN' => 'MXN - Mexican Peso',
				'PEN' => 'PEN - Peruvian Sol',
				'UYU' => 'UYU - Uruguayan Peso',
			],
			'infobox' => 'Mercado Pago only supports Latin American currencies',
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
			<?php \Voxel\Utils\Form_Models\Password_Model::render( [
				'v-model' => 'settings.sandbox.access_token',
				'label' => 'Sandbox Access Token',
				'classes' => 'x-col-12',
				'infobox' => 'Get from Mercado Pago Developers → Your integrations → Credentials',
			] ) ?>

			<?php \Voxel\Utils\Form_Models\Text_Model::render( [
				'v-model' => 'settings.sandbox.public_key',
				'label' => 'Sandbox Public Key',
				'classes' => 'x-col-12',
				'infobox' => 'Public key for frontend integrations',
			] ) ?>

			<?php \Voxel\Utils\Form_Models\Text_Model::render( [
				'v-model' => 'settings.sandbox.application_id',
				'label' => 'Application ID',
				'classes' => 'x-col-6',
				'infobox' => 'Required for marketplace OAuth (optional for direct payments)',
			] ) ?>

			<?php \Voxel\Utils\Form_Models\Password_Model::render( [
				'v-model' => 'settings.sandbox.client_secret',
				'label' => 'Client Secret',
				'classes' => 'x-col-6',
				'infobox' => 'Required for marketplace OAuth (optional for direct payments)',
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
				<input type="text" readonly value="<?= esc_attr( $webhook_url ) ?>" class="autofocus" onclick="this.select();">
				<p class="ts-description">
					<?php _e( 'Add this URL in Mercado Pago Developers → Your integrations → Webhooks', 'voxel-payment-gateways' ); ?>
				</p>
			</div>

			<?php \Voxel\Utils\Form_Models\Password_Model::render( [
				'v-model' => 'settings.sandbox.webhook_secret',
				'label' => 'Webhook Secret Signature',
				'classes' => 'x-col-12',
				'placeholder' => 'Not configured',
				'infobox' => 'Secret signature for verifying webhook requests (found in webhook settings)',
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
			<?php \Voxel\Utils\Form_Models\Password_Model::render( [
				'v-model' => 'settings.live.access_token',
				'label' => 'Live Access Token',
				'classes' => 'x-col-12',
				'infobox' => 'Get from Mercado Pago Developers → Your integrations → Production credentials',
			] ) ?>

			<?php \Voxel\Utils\Form_Models\Text_Model::render( [
				'v-model' => 'settings.live.public_key',
				'label' => 'Live Public Key',
				'classes' => 'x-col-12',
				'infobox' => 'Public key for frontend integrations',
			] ) ?>

			<?php \Voxel\Utils\Form_Models\Text_Model::render( [
				'v-model' => 'settings.live.application_id',
				'label' => 'Application ID',
				'classes' => 'x-col-6',
				'infobox' => 'Required for marketplace OAuth (optional for direct payments)',
			] ) ?>

			<?php \Voxel\Utils\Form_Models\Password_Model::render( [
				'v-model' => 'settings.live.client_secret',
				'label' => 'Client Secret',
				'classes' => 'x-col-6',
				'infobox' => 'Required for marketplace OAuth (optional for direct payments)',
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
					<?php _e( 'Add this URL in Mercado Pago Developers → Your integrations → Webhooks', 'voxel-payment-gateways' ); ?>
				</p>
			</div>

			<?php \Voxel\Utils\Form_Models\Password_Model::render( [
				'v-model' => 'settings.live.webhook_secret',
				'label' => 'Webhook Secret Signature',
				'classes' => 'x-col-12',
				'placeholder' => 'Not configured',
				'infobox' => 'Secret signature for verifying webhook requests (found in webhook settings)',
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
			<p style="margin: 0 0 15px 0;"><?php _e( 'Mercado Pago Checkout Pro supports the following payment methods:', 'voxel-payment-gateways' ); ?></p>
			<ul style="margin: 0; padding-left: 20px; line-height: 1.8;">
				<li><strong><?php _e( 'Credit & Debit Cards', 'voxel-payment-gateways' ); ?></strong> - <?php _e( 'Visa, Mastercard, American Express, and local cards', 'voxel-payment-gateways' ); ?></li>
				<li><strong><?php _e( 'Bank Transfers', 'voxel-payment-gateways' ); ?></strong> - <?php _e( 'PSE (Colombia), PIX (Brazil), etc.', 'voxel-payment-gateways' ); ?></li>
				<li><strong><?php _e( 'Cash Payments', 'voxel-payment-gateways' ); ?></strong> - <?php _e( 'OXXO (Mexico), Boleto (Brazil), Pago Fácil/Rapipago (Argentina)', 'voxel-payment-gateways' ); ?></li>
				<li><strong><?php _e( 'Mercado Pago Wallet', 'voxel-payment-gateways' ); ?></strong> - <?php _e( 'One-click payment for MP account holders', 'voxel-payment-gateways' ); ?></li>
				<li><strong><?php _e( 'Installments', 'voxel-payment-gateways' ); ?></strong> - <?php _e( 'Split payments available in most countries', 'voxel-payment-gateways' ); ?></li>
			</ul>
			<p style="margin: 15px 0 0 0; padding: 10px; background: rgba(255,255,255,0.05); border-radius: 5px;">
				<strong><?php _e( 'Note:', 'voxel-payment-gateways' ); ?></strong>
				<?php _e( 'Available payment methods depend on the country of your Mercado Pago account.', 'voxel-payment-gateways' ); ?>
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
			'infobox' => 'Name displayed on Mercado Pago checkout (leave empty for site name)',
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
				'1' => 'Enabled - Allow vendors to connect their Mercado Pago accounts',
			],
		] ) ?>

		<template v-if="settings.marketplace.enabled === '1'">
			<div class="ts-form-group x-col-12" style="margin-bottom: 15px;">
				<label><?php _e( 'OAuth Redirect URI', 'voxel-payment-gateways' ); ?></label>
				<input type="text" readonly value="<?= esc_attr( $oauth_redirect_uri ) ?>" class="autofocus" onclick="this.select();">
				<p class="ts-description">
					<?php _e( 'Add this as an authorized redirect URI in your Mercado Pago application settings.', 'voxel-payment-gateways' ); ?>
				</p>
			</div>

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
				'infobox' => 'Percentage (e.g., 10 for 10%) or fixed amount in the selected currency',
			] ) ?>
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
			<p><?php _e( 'Subscribe to these events in Mercado Pago Developers → Your integrations → Webhooks:', 'voxel-payment-gateways' ); ?></p>

			<p style="margin-top: 15px;"><strong><?php _e( 'For Payments:', 'voxel-payment-gateways' ); ?></strong></p>
			<ul style="list-style: disc; padding-left: 20px; margin-top: 5px;">
				<li><code>payment</code> - <?php _e( 'Payment status updates', 'voxel-payment-gateways' ); ?></li>
			</ul>

			<p style="margin-top: 15px;"><strong><?php _e( 'For Subscriptions:', 'voxel-payment-gateways' ); ?></strong></p>
			<ul style="list-style: disc; padding-left: 20px; margin-top: 5px;">
				<li><code>subscription_preapproval</code> - <?php _e( 'Subscription status changes', 'voxel-payment-gateways' ); ?></li>
				<li><code>subscription_authorized_payment</code> - <?php _e( 'Recurring payment notifications', 'voxel-payment-gateways' ); ?></li>
			</ul>

			<p style="margin-top: 15px;"><strong><?php _e( 'For Marketplace:', 'voxel-payment-gateways' ); ?></strong></p>
			<ul style="list-style: disc; padding-left: 20px; margin-top: 5px;">
				<li><code>merchant_order</code> - <?php _e( 'Marketplace order updates', 'voxel-payment-gateways' ); ?></li>
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
			<p><strong>1. <?php _e( 'Create a Mercado Pago Developer Account', 'voxel-payment-gateways' ); ?></strong></p>
			<ul style="list-style: disc; padding-left: 20px; margin: 5px 0 15px 0;">
				<li><?php _e( 'Visit:', 'voxel-payment-gateways' ); ?> <a href="https://www.mercadopago.com/developers" target="_blank">https://www.mercadopago.com/developers</a></li>
				<li><?php _e( 'Sign up or log in with your Mercado Pago/Mercado Libre account', 'voxel-payment-gateways' ); ?></li>
			</ul>

			<p><strong>2. <?php _e( 'Create an Application', 'voxel-payment-gateways' ); ?></strong></p>
			<ul style="list-style: disc; padding-left: 20px; margin: 5px 0 15px 0;">
				<li><?php _e( 'Go to "Your integrations" and create a new application', 'voxel-payment-gateways' ); ?></li>
				<li><?php _e( 'Select "Checkout Pro" as the integration type', 'voxel-payment-gateways' ); ?></li>
				<li><?php _e( 'Copy your Access Token and Public Key from Credentials', 'voxel-payment-gateways' ); ?></li>
			</ul>

			<p><strong>3. <?php _e( 'Configure Webhooks', 'voxel-payment-gateways' ); ?></strong></p>
			<ul style="list-style: disc; padding-left: 20px; margin: 5px 0 15px 0;">
				<li><?php _e( 'Go to Webhooks in your application settings', 'voxel-payment-gateways' ); ?></li>
				<li><?php _e( 'Add the webhook URL shown above', 'voxel-payment-gateways' ); ?></li>
				<li><?php _e( 'Subscribe to the required events', 'voxel-payment-gateways' ); ?></li>
				<li><?php _e( 'Copy the Secret Signature and add it here', 'voxel-payment-gateways' ); ?></li>
			</ul>

			<p><strong>4. <?php _e( 'For Marketplace (Optional)', 'voxel-payment-gateways' ); ?></strong></p>
			<ul style="list-style: disc; padding-left: 20px; margin: 5px 0 15px 0;">
				<li><?php _e( 'Enable "Payments on behalf of third parties" in your application', 'voxel-payment-gateways' ); ?></li>
				<li><?php _e( 'Add the OAuth Redirect URI shown above', 'voxel-payment-gateways' ); ?></li>
				<li><?php _e( 'Get Application ID and Client Secret from your application settings', 'voxel-payment-gateways' ); ?></li>
			</ul>

			<p><strong>5. <?php _e( 'Test in Sandbox', 'voxel-payment-gateways' ); ?></strong></p>
			<ul style="list-style: disc; padding-left: 20px; margin: 5px 0 15px 0;">
				<li><?php _e( 'Use sandbox credentials for testing', 'voxel-payment-gateways' ); ?></li>
				<li><?php _e( 'Create test users in your Mercado Pago Developers dashboard', 'voxel-payment-gateways' ); ?></li>
				<li><?php _e( 'Test with the provided test credit card numbers', 'voxel-payment-gateways' ); ?></li>
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
				.mp-country-notice { background: #e3f2fd !important; border: 1px solid #2196f3 !important; padding: 15px !important; border-radius: 5px !important; }
				.mp-country-notice * { color: #1565c0 !important; }
			</style>
			<div class="mp-country-notice">
				<p style="margin: 0; line-height: 1.6;">
					<strong><?php _e( 'Mercado Pago is available in:', 'voxel-payment-gateways' ); ?></strong>
					<?php _e( 'Argentina, Brazil, Chile, Colombia, Mexico, Peru, and Uruguay.', 'voxel-payment-gateways' ); ?>
				</p>
				<p style="margin: 10px 0 0 0; line-height: 1.6;">
					<?php _e( 'Each country only supports its local currency. Your Mercado Pago account country determines which currency you can accept.', 'voxel-payment-gateways' ); ?>
				</p>
			</div>
		</div>
	</div>
</div>
