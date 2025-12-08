<?php
if ( ! defined('ABSPATH') ) {
	exit;
}
?>

<div class="ts-group">
	<div class="ts-group-head">
		<h3><?php _e( 'General Settings', 'voxel-payment-gateways' ); ?></h3>
	</div>
	<div class="x-row">
		<?php \Voxel\Utils\Form_Models\Text_Model::render( [
			'v-model' => 'settings.label',
			'label' => 'Checkout Button Label',
			'classes' => 'x-col-12',
			'placeholder' => 'Pay Offline',
			'infobox' => 'Text shown on the checkout button (e.g., "Cash on Delivery", "Pay at Pickup")',
		] ) ?>
	</div>
</div>

<div class="ts-group">
	<div class="ts-group-head">
		<h3><?php _e( 'Order Settings', 'voxel-payment-gateways' ); ?></h3>
	</div>
	<div class="x-row">
		<?php \Voxel\Utils\Form_Models\Select_Model::render( [
			'v-model' => 'settings.order_status',
			'label' => 'Default Order Status',
			'classes' => 'x-col-12',
			'choices' => [
				'pending_payment' => 'Pending Payment - Order awaits payment',
				'pending_approval' => 'Pending Approval - Vendor must approve order',
			],
			'infobox' => 'Status assigned to new offline orders. Vendors can mark orders as paid from their dashboard.',
		] ) ?>
	</div>
</div>

<div class="ts-group">
	<div class="ts-group-head">
		<h3><?php _e( 'Customer Instructions', 'voxel-payment-gateways' ); ?></h3>
	</div>
	<div class="x-row">
		<?php \Voxel\Utils\Form_Models\Textarea_Model::render( [
			'v-model' => 'settings.instructions',
			'label' => 'Payment Instructions',
			'classes' => 'x-col-12',
			'infobox' => 'Instructions shown to customers after placing an order. Example: "Please have exact amount ready for delivery."',
		] ) ?>
	</div>
</div>

<div class="ts-group">
	<div class="ts-group-head">
		<h3><?php _e( 'How It Works', 'voxel-payment-gateways' ); ?></h3>
	</div>
	<div class="x-row">
		<div class="ts-form-group x-col-12">
			<p style="margin: 0 0 10px 0; opacity: 0.7;"><strong><?php _e( 'Offline Payment Flow:', 'voxel-payment-gateways' ); ?></strong></p>
			<ol style="margin: 0 0 15px 0; padding-left: 20px; opacity: 0.7; line-height: 1.8;">
				<li><?php _e( 'Customer places order and selects offline payment', 'voxel-payment-gateways' ); ?></li>
				<li><?php _e( 'Order is created with "Pending Payment" status', 'voxel-payment-gateways' ); ?></li>
				<li><?php _e( 'Customer receives order confirmation with payment instructions', 'voxel-payment-gateways' ); ?></li>
				<li><?php _e( 'Payment is collected offline (cash, bank transfer, etc.)', 'voxel-payment-gateways' ); ?></li>
				<li><?php _e( 'Vendor marks order as "Paid" from their dashboard', 'voxel-payment-gateways' ); ?></li>
				<li><?php _e( 'Order status changes to "Completed"', 'voxel-payment-gateways' ); ?></li>
			</ol>

			<p style="margin: 0 0 10px 0; opacity: 0.7;"><strong><?php _e( 'Use Cases:', 'voxel-payment-gateways' ); ?></strong></p>
			<ul style="margin: 0; padding-left: 20px; opacity: 0.7; line-height: 1.8;">
				<li><?php _e( 'Cash on Delivery (COD)', 'voxel-payment-gateways' ); ?></li>
				<li><?php _e( 'Pay at Pickup / In-Store Payment', 'voxel-payment-gateways' ); ?></li>
				<li><?php _e( 'Bank Transfer / Wire Payment', 'voxel-payment-gateways' ); ?></li>
				<li><?php _e( 'Invoice Payment', 'voxel-payment-gateways' ); ?></li>
				<li><?php _e( 'Check Payment', 'voxel-payment-gateways' ); ?></li>
			</ul>
		</div>
	</div>
</div>
