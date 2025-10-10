<?php
/**
 * PayPal Marketplace Settings Template
 */
if ( ! defined('ABSPATH') ) {
	exit;
}
?>

<div class="paypal-marketplace-settings">
	<h2><?php _e( 'Marketplace Settings', 'voxel-paypal-gateway' ); ?></h2>
	<p class="description">
		<?php _e( 'Configure marketplace mode to automatically split payments between your platform and vendors.', 'voxel-paypal-gateway' ); ?>
	</p>

	<div class="ts-form-group">
		<label>
			<input
				type="checkbox"
				v-model="config.marketplace.enabled"
			/>
			<?php _e( 'Enable marketplace mode', 'voxel-paypal-gateway' ); ?>
		</label>
		<p class="description">
			<?php _e( 'When enabled, payments will be split between your platform and vendors.', 'voxel-paypal-gateway' ); ?>
		</p>
	</div>

	<template v-if="config.marketplace.enabled">
		<div class="ts-form-group">
			<label><?php _e( 'Platform fee type', 'voxel-paypal-gateway' ); ?></label>
			<select v-model="config.marketplace.fee_type" class="ts-input">
				<option value="percentage"><?php _e( 'Percentage', 'voxel-paypal-gateway' ); ?></option>
				<option value="fixed"><?php _e( 'Fixed amount', 'voxel-paypal-gateway' ); ?></option>
				<option value="conditional"><?php _e( 'Conditional (advanced)', 'voxel-paypal-gateway' ); ?></option>
			</select>
		</div>

		<div class="ts-form-group" v-if="config.marketplace.fee_type !== 'conditional'">
			<label>
				<?php _e( 'Fee value', 'voxel-paypal-gateway' ); ?>
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
					<?php _e( 'Percentage to deduct from vendor earnings (e.g., 10 for 10%)', 'voxel-paypal-gateway' ); ?>
				</span>
				<span v-else>
					<?php _e( 'Fixed amount to deduct from vendor earnings', 'voxel-paypal-gateway' ); ?>
				</span>
			</p>
		</div>

		<div class="ts-form-group" v-if="config.marketplace.fee_type === 'conditional'">
			<label><?php _e( 'Conditional fee rules', 'voxel-paypal-gateway' ); ?></label>
			<div class="fee-conditions">
				<div
					v-for="(condition, index) in config.marketplace.fee_conditions"
					:key="index"
					class="fee-condition"
				>
					<select v-model="condition.type" class="ts-input">
						<option value="percentage"><?php _e( 'Percentage', 'voxel-paypal-gateway' ); ?></option>
						<option value="fixed"><?php _e( 'Fixed', 'voxel-paypal-gateway' ); ?></option>
					</select>

					<input
						type="number"
						v-model="condition.value"
						placeholder="<?php esc_attr_e( 'Value', 'voxel-paypal-gateway' ); ?>"
						step="0.01"
						min="0"
						class="ts-input"
					/>

					<input
						type="number"
						v-model="condition.min_amount"
						placeholder="<?php esc_attr_e( 'Min amount', 'voxel-paypal-gateway' ); ?>"
						step="0.01"
						min="0"
						class="ts-input"
					/>

					<input
						type="number"
						v-model="condition.max_amount"
						placeholder="<?php esc_attr_e( 'Max amount', 'voxel-paypal-gateway' ); ?>"
						step="0.01"
						min="0"
						class="ts-input"
					/>

					<button
						@click="removeCondition(index)"
						type="button"
						class="ts-button ts-outline"
					>
						<?php _e( 'Remove', 'voxel-paypal-gateway' ); ?>
					</button>
				</div>

				<button
					@click="addCondition"
					type="button"
					class="ts-button ts-outline"
				>
					<?php _e( 'Add condition', 'voxel-paypal-gateway' ); ?>
				</button>
			</div>
			<p class="description">
				<?php _e( 'Define conditional fees based on order amount ranges.', 'voxel-paypal-gateway' ); ?>
			</p>
		</div>

		<div class="ts-form-group">
			<label>
				<input
					type="checkbox"
					v-model="config.marketplace.auto_payout"
				/>
				<?php _e( 'Automatic payouts', 'voxel-paypal-gateway' ); ?>
			</label>
			<p class="description">
				<?php _e( 'Automatically send payouts to vendors when orders are completed.', 'voxel-paypal-gateway' ); ?>
			</p>
		</div>

		<div class="ts-form-group" v-if="config.marketplace.auto_payout">
			<label><?php _e( 'Payout delay (days)', 'voxel-paypal-gateway' ); ?></label>
			<input
				type="number"
				v-model="config.marketplace.payout_delay_days"
				min="0"
				class="ts-input"
			/>
			<p class="description">
				<?php _e( 'Number of days to wait before sending payout to vendors (0 for immediate).', 'voxel-paypal-gateway' ); ?>
			</p>
		</div>

		<div class="ts-form-group">
			<label><?php _e( 'Shipping responsibility', 'voxel-paypal-gateway' ); ?></label>
			<select v-model="config.marketplace.shipping_responsibility" class="ts-input">
				<option value="vendor"><?php _e( 'Vendor handles shipping', 'voxel-paypal-gateway' ); ?></option>
				<option value="platform"><?php _e( 'Platform handles shipping', 'voxel-paypal-gateway' ); ?></option>
			</select>
		</div>

		<div class="payout-logs">
			<h3><?php _e( 'Recent Payouts', 'voxel-paypal-gateway' ); ?></h3>
			<button
				@click="loadPayoutLogs"
				type="button"
				class="ts-button ts-outline"
			>
				<?php _e( 'Load payout logs', 'voxel-paypal-gateway' ); ?>
			</button>

			<div v-if="payoutLogs.length" class="payout-log-list">
				<table class="wp-list-table widefat">
					<thead>
						<tr>
							<th><?php _e( 'Timestamp', 'voxel-paypal-gateway' ); ?></th>
							<th><?php _e( 'Batch ID', 'voxel-paypal-gateway' ); ?></th>
							<th><?php _e( 'Status', 'voxel-paypal-gateway' ); ?></th>
							<th><?php _e( 'Items', 'voxel-paypal-gateway' ); ?></th>
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
			<h3><?php _e( 'Manual Payout', 'voxel-paypal-gateway' ); ?></h3>
			<p class="description">
				<?php _e( 'Create a manual payout to a vendor.', 'voxel-paypal-gateway' ); ?>
			</p>

			<div class="ts-form-group">
				<label><?php _e( 'Vendor ID', 'voxel-paypal-gateway' ); ?></label>
				<input
					type="number"
					v-model="manualPayout.vendor_id"
					class="ts-input"
				/>
			</div>

			<div class="ts-form-group">
				<label><?php _e( 'Amount', 'voxel-paypal-gateway' ); ?></label>
				<input
					type="number"
					v-model="manualPayout.amount"
					step="0.01"
					min="1"
					class="ts-input"
				/>
			</div>

			<div class="ts-form-group">
				<label><?php _e( 'Currency', 'voxel-paypal-gateway' ); ?></label>
				<input
					type="text"
					v-model="manualPayout.currency"
					placeholder="USD"
					class="ts-input"
				/>
			</div>

			<div class="ts-form-group">
				<label><?php _e( 'Note', 'voxel-paypal-gateway' ); ?></label>
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
				<?php _e( 'Create payout', 'voxel-paypal-gateway' ); ?>
			</button>
		</div>
	</template>
</div>

<style>
.paypal-marketplace-settings {
	max-width: 800px;
}

.fee-conditions {
	display: flex;
	flex-direction: column;
	gap: 10px;
}

.fee-condition {
	display: flex;
	gap: 10px;
	align-items: center;
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
