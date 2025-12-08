<?php
/**
 * PayPal Marketplace Settings Template (Simplified)
 */
if ( ! defined('ABSPATH') ) {
	exit;
}
?>

<div class="paypal-marketplace-settings">
	<h2><?php _e( 'Marketplace Settings', 'voxel-payment-gateways' ); ?></h2>
	<p class="description">
		<?php _e( 'Configure marketplace mode to automatically split payments between your platform and vendors.', 'voxel-payment-gateways' ); ?>
	</p>

	<div class="ts-form-group">
		<label>
			<input
				type="checkbox"
				v-model="config.marketplace.enabled"
			/>
			<?php _e( 'Enable marketplace mode', 'voxel-payment-gateways' ); ?>
		</label>
		<p class="description">
			<?php _e( 'When enabled, payments will be split between your platform and vendors.', 'voxel-payment-gateways' ); ?>
		</p>
	</div>

	<template v-if="config.marketplace.enabled">
		<div class="ts-form-group">
			<label><?php _e( 'Platform fee type', 'voxel-payment-gateways' ); ?></label>
			<select v-model="config.marketplace.fee_type" class="ts-input">
				<option value="percentage"><?php _e( 'Percentage', 'voxel-payment-gateways' ); ?></option>
				<option value="fixed"><?php _e( 'Fixed amount', 'voxel-payment-gateways' ); ?></option>
			</select>
		</div>

		<div class="ts-form-group">
			<label>
				<?php _e( 'Fee value', 'voxel-payment-gateways' ); ?>
				<span v-if="config.marketplace.fee_type === 'percentage'"> (%)</span>
			</label>
			<input
				type="number"
				v-model="config.marketplace.fee_value"
				step="0.01"
				min="0"
				class="ts-input"
			/>
			<p class="description">
				<span v-if="config.marketplace.fee_type === 'percentage'">
					<?php _e( 'Percentage to deduct from vendor earnings (e.g., 10 for 10%)', 'voxel-payment-gateways' ); ?>
				</span>
				<span v-else>
					<?php _e( 'Fixed amount to deduct from vendor earnings', 'voxel-payment-gateways' ); ?>
				</span>
			</p>
		</div>

		<div class="ts-form-group">
			<label>
				<input
					type="checkbox"
					v-model="config.marketplace.auto_payout"
				/>
				<?php _e( 'Automatic payouts', 'voxel-payment-gateways' ); ?>
			</label>
			<p class="description">
				<?php _e( 'Automatically send payouts to vendors when orders are completed.', 'voxel-payment-gateways' ); ?>
			</p>
		</div>

		<div class="ts-form-group" v-if="config.marketplace.auto_payout">
			<label><?php _e( 'Payout delay (days)', 'voxel-payment-gateways' ); ?></label>
			<input
				type="number"
				v-model="config.marketplace.payout_delay_days"
				min="0"
				class="ts-input"
			/>
			<p class="description">
				<?php _e( 'Number of days to wait before sending payout to vendors (0 for immediate).', 'voxel-payment-gateways' ); ?>
			</p>
		</div>

		<div class="ts-form-group">
			<label><?php _e( 'Shipping responsibility', 'voxel-payment-gateways' ); ?></label>
			<select v-model="config.marketplace.shipping_responsibility" class="ts-input">
				<option value="vendor"><?php _e( 'Vendor handles shipping', 'voxel-payment-gateways' ); ?></option>
				<option value="platform"><?php _e( 'Platform handles shipping', 'voxel-payment-gateways' ); ?></option>
			</select>
		</div>

		<div class="payout-logs">
			<h3><?php _e( 'Recent Payouts', 'voxel-payment-gateways' ); ?></h3>
			<button
				@click="loadPayoutLogs"
				type="button"
				class="ts-button ts-outline"
			>
				<?php _e( 'Load payout logs', 'voxel-payment-gateways' ); ?>
			</button>

			<div v-if="payoutLogs.length" class="payout-log-list">
				<table class="wp-list-table widefat">
					<thead>
						<tr>
							<th><?php _e( 'Timestamp', 'voxel-payment-gateways' ); ?></th>
							<th><?php _e( 'Batch ID', 'voxel-payment-gateways' ); ?></th>
							<th><?php _e( 'Status', 'voxel-payment-gateways' ); ?></th>
							<th><?php _e( 'Items', 'voxel-payment-gateways' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr v-for="log in payoutLogs" :key="log.batch_id">
							<td>{{ log.timestamp }}</td>
							<td>{{ log.batch_id }}</td>
							<td>{{ log.batch_status }}</td>
							<td>{{ log.items_count }}</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>

		<div class="manual-payout">
			<h3><?php _e( 'Manual Payout', 'voxel-payment-gateways' ); ?></h3>
			<p class="description">
				<?php _e( 'Create a manual payout to a vendor.', 'voxel-payment-gateways' ); ?>
			</p>

			<div class="ts-form-group">
				<label><?php _e( 'Vendor ID', 'voxel-payment-gateways' ); ?></label>
				<input
					type="number"
					v-model="manualPayout.vendor_id"
					class="ts-input"
				/>
			</div>

			<div class="ts-form-group">
				<label><?php _e( 'Amount', 'voxel-payment-gateways' ); ?></label>
				<input
					type="number"
					v-model="manualPayout.amount"
					step="0.01"
					min="1"
					class="ts-input"
				/>
			</div>

			<div class="ts-form-group">
				<label><?php _e( 'Currency', 'voxel-payment-gateways' ); ?></label>
				<input
					type="text"
					v-model="manualPayout.currency"
					placeholder="USD"
					class="ts-input"
				/>
			</div>

			<div class="ts-form-group">
				<label><?php _e( 'Note', 'voxel-payment-gateways' ); ?></label>
				<textarea
					v-model="manualPayout.note"
					class="ts-input"
					rows="3"
				></textarea>
			</div>

			<button
				@click="createManualPayout"
				type="button"
				class="ts-button"
			>
				<?php _e( 'Create payout', 'voxel-payment-gateways' ); ?>
			</button>
		</div>
	</template>
</div>

<style>
.paypal-marketplace-settings {
	max-width: 800px;
}

.payout-log-list {
	margin-top: 15px;
}

.manual-payout {
	margin-top: 30px;
	padding-top: 30px;
	border-top: 1px solid #ddd;
}
</style>
